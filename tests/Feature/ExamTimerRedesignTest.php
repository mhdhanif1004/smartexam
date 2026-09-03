<?php

namespace Tests\Feature;

use App\Models\ExamPeriod;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\ExamToken;
use App\Models\Question;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExamTimerRedesignTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;

    private ExamPeriod $period;

    private ExamSchedule $scheduleA;

    private ExamSchedule $scheduleB;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-15 09:00:00'));

        $this->student = Student::factory()->create(['class_name' => 'XI RPL 1']);
        $room = Room::factory()->create();
        $this->student->update(['room_id' => $room->id]);

        $subjectA = Subject::factory()->create(['name' => 'Matematika']);
        $subjectB = Subject::factory()->create(['name' => 'Fisika']);

        $this->period = ExamPeriod::create([
            'name' => 'Sesi Pagi',
            'name_prefix' => 'SP',
            'grade_level' => null,
            'session_number' => 1,
            'exam_date' => now()->toDateString(),
            'start_time' => '08:00:00',
            'end_time' => '11:00:00',
        ]);

        $this->scheduleA = ExamSchedule::factory()->create([
            'room_id' => $room->id,
            'subject_id' => $subjectA->id,
            'class_name' => 'XI RPL 1',
            'exam_period_id' => $this->period->id,
            'exam_date' => now()->toDateString(),
            'start_time' => '08:00:00',
            'end_time' => '09:30:00',
            'duration_minutes' => 60,
            'status' => ExamSchedule::STATUS_ONGOING,
        ]);

        $this->scheduleB = ExamSchedule::factory()->create([
            'room_id' => $room->id,
            'subject_id' => $subjectB->id,
            'class_name' => 'XI RPL 1',
            'exam_period_id' => $this->period->id,
            'exam_date' => now()->toDateString(),
            'start_time' => '09:30:00',
            'end_time' => '11:00:00',
            'duration_minutes' => 60,
            'status' => ExamSchedule::STATUS_SCHEDULED,
        ]);

        DB::table('exam_room_assignments')->insert([
            'exam_period_id' => $this->period->id,
            'student_id' => $this->student->id,
            'room_id' => $room->id,
            'seat_number' => 1,
        ]);

        $question = Question::factory()->create(['subject_id' => $subjectA->id]);
        $question->classrooms()->sync($this->student->classroom_id);

        $questionB = Question::factory()->create(['subject_id' => $subjectB->id]);
        $questionB->classrooms()->sync($this->student->classroom_id);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function beginSession(ExamSchedule $schedule, ?Carbon $startedAt = null): ExamSession
    {
        $deadlineType = $schedule->computedStatus() === ExamSchedule::STATUS_FINISHED
            ? ExamSession::DEADLINE_TYPE_DURATION
            : ExamSession::DEADLINE_TYPE_SCHEDULE_END;

        return ExamSession::create([
            'student_id' => $this->student->id,
            'exam_schedule_id' => $schedule->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'started_at' => $startedAt ?? now(),
            'deadline_type' => $deadlineType,
            'attendance_confirmed' => true,
        ]);
    }

    private function enterToken(ExamSchedule $schedule): void
    {
        ExamToken::create([
            'exam_period_id' => $schedule->exam_period_id,
            'token_code' => 'ABC12345',
            'rotation_index' => 0,
            'valid_from' => now()->subHour(),
            'valid_until' => now()->addHour(),
        ]);

        ExamSession::create([
            'student_id' => $this->student->id,
            'exam_schedule_id' => $schedule->id,
            'status' => ExamSession::STATUS_NOT_STARTED,
            'attendance_confirmed' => true,
        ]);

        $this->actingAs($this->student->user)->post(route('peserta.exams.token.validate', $schedule->id), [
            'token_code' => 'ABC12345',
        ]);
    }

    // ---------------------------------------------------------------
    // TEST 1: remaining_seconds berkurang seiring waktu
    // ONGOING → deadline_type=schedule_end → deadline = examEnd
    // ---------------------------------------------------------------

    public function test_status_remaining_mapel_decreases_over_time(): void
    {
        $this->beginSession($this->scheduleA, Carbon::parse('2026-08-15 08:45:00'));

        // computedStatus=ONGOING → deadline = examEnd = 09:30
        // Now = 09:00:00 => remaining = 1800s (30 min to 09:30)
        Carbon::setTestNow(Carbon::parse('2026-08-15 09:00:00'));
        $response1 = $this->actingAs($this->student->user)
            ->getJson(route('peserta.exams.status', $this->scheduleA->id));
        $response1->assertOk();
        $this->assertEquals(1800, $response1->json('mapel.remaining_seconds'));

        // Now = 09:15:00 => remaining = 900s (15 min to 09:30)
        Carbon::setTestNow(Carbon::parse('2026-08-15 09:15:00'));
        $response2 = $this->actingAs($this->student->user)
            ->getJson(route('peserta.exams.status', $this->scheduleA->id));
        $response2->assertOk();
        $this->assertEquals(900, $response2->json('mapel.remaining_seconds'));

        // Now = 09:45:00 => remaining = 0 (expired)
        Carbon::setTestNow(Carbon::parse('2026-08-15 09:45:00'));
        $response3 = $this->actingAs($this->student->user)
            ->getJson(route('peserta.exams.status', $this->scheduleA->id));
        $response3->assertOk();
        $this->assertEquals(0, $response3->json('mapel.remaining_seconds'));
    }

    // ---------------------------------------------------------------
    // TEST 2: isFinalMapel hanya untuk schedule dengan start_time terakhir
    // ---------------------------------------------------------------

    public function test_is_final_mapel_true_only_for_last_start_time(): void
    {
        $this->beginSession($this->scheduleA);

        $response = $this->actingAs($this->student->user)
            ->getJson(route('peserta.exams.status', $this->scheduleA->id));
        $response->assertOk();
        $this->assertFalse($response->json('mapel.is_final'));
    }

    public function test_is_final_mapel_true_for_last_schedule(): void
    {
        $this->beginSession($this->scheduleB);

        $response = $this->actingAs($this->student->user)
            ->getJson(route('peserta.exams.status', $this->scheduleB->id));
        $response->assertOk();
        $this->assertTrue($response->json('mapel.is_final'));
    }

    public function test_is_final_mapel_independent_of_execution_order(): void
    {
        $this->beginSession($this->scheduleB);

        $responseB = $this->actingAs($this->student->user)
            ->getJson(route('peserta.exams.status', $this->scheduleB->id));
        $this->assertTrue($responseB->json('mapel.is_final'));

        $this->beginSession($this->scheduleA);

        $responseA = $this->actingAs($this->student->user)
            ->getJson(route('peserta.exams.status', $this->scheduleA->id));
        $this->assertFalse($responseA->json('mapel.is_final'));

        $responseB2 = $this->actingAs($this->student->user)
            ->getJson(route('peserta.exams.status', $this->scheduleB->id));
        $this->assertTrue($responseB2->json('mapel.is_final'));
    }

    // ---------------------------------------------------------------
    // TEST 3: remaining_seconds berbasis schedule_end untuk ONGOING
    // ---------------------------------------------------------------

    public function test_remaining_mapel_uses_schedule_end_for_ongoing_student(): void
    {
        // scheduleA start = 08:00, siswa mulai di 08:50
        // computedStatus=ONGOING → deadline = examEnd = 09:30
        $this->beginSession($this->scheduleA, Carbon::parse('2026-08-15 08:50:00'));

        // Now = 09:00 => remaining = 1800s (30 min to 09:30)
        Carbon::setTestNow(Carbon::parse('2026-08-15 09:00:00'));
        $response = $this->actingAs($this->student->user)
            ->getJson(route('peserta.exams.status', $this->scheduleA->id));
        $this->assertEquals(1800, $response->json('mapel.remaining_seconds'));
    }

    public function test_remaining_mapel_not_affected_by_late_start_of_another_schedule(): void
    {
        // scheduleA: started 08:50, ONGOING → deadline = examEndA = 09:30
        $this->beginSession($this->scheduleA, Carbon::parse('2026-08-15 08:50:00'));

        // scheduleB: started 09:00, SCHEDULED → deadline = examEndB = 11:00
        $this->beginSession($this->scheduleB, Carbon::parse('2026-08-15 09:00:00'));

        Carbon::setTestNow(Carbon::parse('2026-08-15 09:10:00'));

        // scheduleA remaining = 09:30 - 09:10 = 1200s
        $responseA = $this->actingAs($this->student->user)
            ->getJson(route('peserta.exams.status', $this->scheduleA->id));
        $this->assertEquals(1200, $responseA->json('mapel.remaining_seconds'));

        // scheduleB remaining = 11:00 - 09:10 = 6600s
        $responseB = $this->actingAs($this->student->user)
            ->getJson(route('peserta.exams.status', $this->scheduleB->id));
        $this->assertEquals(6600, $responseB->json('mapel.remaining_seconds'));
    }

    // ---------------------------------------------------------------
    // TEST 4: Sesi total_seconds konsisten
    // ---------------------------------------------------------------

    public function test_sesi_total_seconds_matches_period_window(): void
    {
        $this->beginSession($this->scheduleA);

        $response = $this->actingAs($this->student->user)
            ->getJson(route('peserta.exams.status', $this->scheduleA->id));

        $this->assertEquals(10800, $response->json('sesi.total_seconds'));
    }

    // ---------------------------------------------------------------
    // TEST 5: Status endpoint tidak return data timer saat inactive
    // ---------------------------------------------------------------

    public function test_status_returns_active_false_when_session_not_in_progress(): void
    {
        $response = $this->actingAs($this->student->user)
            ->getJson(route('peserta.exams.status', $this->scheduleA->id));
        $response->assertOk();
        $this->assertFalse($response->json('active'));
        $this->assertArrayNotHasKey('mapel', $response->json());
    }

    // ---------------------------------------------------------------
    // TEST 6: work() view receives timer data (incl. graceSeconds)
    // ---------------------------------------------------------------

    public function test_work_page_includes_dual_timer_data(): void
    {
        $this->beginSession($this->scheduleA);

        $this->actingAs($this->student->user)
            ->get(route('peserta.exams.work', $this->scheduleA->id))
            ->assertOk()
            ->assertSee('totalSessionSeconds')
            ->assertSee('remainingSession')
            ->assertSee('remainingGrace')
            ->assertSee('isFinalMapel');
    }

    // ---------------------------------------------------------------
    // TEST 7: started_at IMMUTABLE — tidak berubah walau request berulang
    // ONGOING → deadline = examEnd = 09:30
    // ---------------------------------------------------------------

    public function test_started_at_immutable_across_multiple_requests(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-15 08:18:00'));

        $this->enterToken($this->scheduleA);

        $session = ExamSession::where('student_id', $this->student->id)
            ->where('exam_schedule_id', $this->scheduleA->id)
            ->first();

        $originalStartedAt = $session->started_at->copy();
        $this->assertEquals('2026-08-15 08:18:00', $originalStartedAt->toDateTimeString());

        // Request 1: Now = 08:20, deadline = examEnd = 09:30 => remaining = 4200s
        Carbon::setTestNow(Carbon::parse('2026-08-15 08:20:00'));
        $r1 = $this->actingAs($this->student->user)
            ->getJson(route('peserta.exams.status', $this->scheduleA->id));
        $r1->assertOk();
        $this->assertEquals(4200, $r1->json('mapel.remaining_seconds'));

        $session->refresh();
        $this->assertEquals(
            $originalStartedAt->toDateTimeString(),
            $session->started_at->toDateTimeString(),
            'started_at should not change after status() call'
        );

        // Request 2: Now = 08:30 => remaining = 3600s
        Carbon::setTestNow(Carbon::parse('2026-08-15 08:30:00'));
        $r2 = $this->actingAs($this->student->user)
            ->getJson(route('peserta.exams.status', $this->scheduleA->id));
        $r2->assertOk();
        $this->assertEquals(3600, $r2->json('mapel.remaining_seconds'));

        $session->refresh();
        $this->assertEquals(
            $originalStartedAt->toDateTimeString(),
            $session->started_at->toDateTimeString(),
            'started_at should not change after multiple status() calls'
        );

        // Request 3: Now = 09:18 => remaining = 720s (NOT 0)
        Carbon::setTestNow(Carbon::parse('2026-08-15 09:18:00'));
        $r3 = $this->actingAs($this->student->user)
            ->getJson(route('peserta.exams.status', $this->scheduleA->id));
        $r3->assertOk();
        $this->assertEquals(720, $r3->json('mapel.remaining_seconds'));

        // Request reload work() page
        Carbon::setTestNow(Carbon::parse('2026-08-15 08:35:00'));
        $this->actingAs($this->student->user)
            ->get(route('peserta.exams.work', $this->scheduleA->id))
            ->assertOk();

        $session->refresh();
        $this->assertEquals(
            $originalStartedAt->toDateTimeString(),
            $session->started_at->toDateTimeString(),
            'started_at should not change after work() reload'
        );

        // First request remaining should never equal full duration (3600)
        $this->assertNotEquals(3600, $r1->json('mapel.remaining_seconds'),
            'remaining should never equal full duration on first request');
    }

    public function test_remaining_mapel_consistent_across_multiple_requests(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-15 08:18:00'));
        $this->enterToken($this->scheduleA);

        // Request 1: Now = 08:20, deadline = examEnd = 09:30 => remaining = 4200s
        Carbon::setTestNow(Carbon::parse('2026-08-15 08:20:00'));
        $r1 = $this->actingAs($this->student->user)
            ->getJson(route('peserta.exams.status', $this->scheduleA->id));
        $this->assertEquals(4200, $r1->json('mapel.remaining_seconds'));

        // Request 2: Now = 08:30 => remaining = 3600s
        Carbon::setTestNow(Carbon::parse('2026-08-15 08:30:00'));
        $r2 = $this->actingAs($this->student->user)
            ->getJson(route('peserta.exams.status', $this->scheduleA->id));
        $this->assertEquals(3600, $r2->json('mapel.remaining_seconds'));

        // Request 3: Now = 09:18 => remaining = 720s (NOT 0)
        Carbon::setTestNow(Carbon::parse('2026-08-15 09:18:00'));
        $r3 = $this->actingAs($this->student->user)
            ->getJson(route('peserta.exams.status', $this->scheduleA->id));
        $this->assertEquals(720, $r3->json('mapel.remaining_seconds'));

        // Pastikan remaining TIDAK PERNAH reset ke full duration (3600s)
        $this->assertNotEquals(3600, $r1->json('mapel.remaining_seconds'),
            'remaining should never reset to full duration after being less');
    }

    // ---------------------------------------------------------------
    // TEST 8: ONGOING late student → deadline = examEnd (window berkurang)
    // ---------------------------------------------------------------

    public function test_ongoing_late_student_gets_reduced_window(): void
    {
        // Siswa telat masuk token di 08:18
        // computedStatus=ONGOING → deadline_type=schedule_end → deadline = examEnd = 09:30
        Carbon::setTestNow(Carbon::parse('2026-08-15 08:18:00'));
        $this->enterToken($this->scheduleA);

        // Now 08:18:16 => remaining = 09:30 - 08:18:16 = 4304s
        Carbon::setTestNow(Carbon::parse('2026-08-15 08:18:16'));

        $response = $this->actingAs($this->student->user)
            ->getJson(route('peserta.exams.status', $this->scheduleA->id));
        $response->assertOk();
        $this->assertEquals(4304, $response->json('mapel.remaining_seconds'));
    }

    // ---------------------------------------------------------------
    // TEST 9: SUSULAN (FINISHED) → deadline = started_at + duration
    // ---------------------------------------------------------------

    public function test_susulan_student_gets_full_duration(): void
    {
        // Set now to 09:40 (AFTER examEnd=09:30) → computedStatus=FINISHED
        Carbon::setTestNow(Carbon::parse('2026-08-15 09:40:00'));
        $this->enterToken($this->scheduleA);

        // deadline_type=duration → deadline = started_at + duration = 09:40 + 60 = 10:40
        // Now 09:40:16 => remaining = 10:40 - 09:40:16 = 3584s
        Carbon::setTestNow(Carbon::parse('2026-08-15 09:40:16'));

        $response = $this->actingAs($this->student->user)
            ->getJson(route('peserta.exams.status', $this->scheduleA->id));
        $response->assertOk();
        $this->assertEquals(3584, $response->json('mapel.remaining_seconds'));
    }

    // ---------------------------------------------------------------
    // TEST 10: deadline_type di-snapshots saat entry, tidak berubah
    // ---------------------------------------------------------------

    public function test_deadline_type_snapshotted_at_entry(): void
    {
        // enterToken at 08:18 → computedStatus=ONGOING → deadline_type = schedule_end
        Carbon::setTestNow(Carbon::parse('2026-08-15 08:18:00'));
        $this->enterToken($this->scheduleA);

        $session = ExamSession::where('student_id', $this->student->id)
            ->where('exam_schedule_id', $this->scheduleA->id)
            ->first();

        $this->assertEquals('schedule_end', $session->deadline_type);

        // Advance to 09:35 (computedStatus = FINISHED)
        Carbon::setTestNow(Carbon::parse('2026-08-15 09:35:00'));
        $session->refresh();

        // deadline_type STILL = 'schedule_end' (snapshot unchanged)
        $this->assertEquals('schedule_end', $session->deadline_type);

        // deadline() = examEnd (09:30), NOT started_at + duration
        $this->assertEquals(
            Carbon::parse('2026-08-15 09:30:00')->timestamp,
            $session->deadline()->timestamp
        );
    }

    // ---------------------------------------------------------------
    // TEST 11: NULL deadline_type fallback → treated as 'duration'
    // ---------------------------------------------------------------

    public function test_legacy_null_deadline_type_falls_back_to_duration(): void
    {
        // beginSession at 08:45, then set deadline_type = null
        $session = $this->beginSession($this->scheduleA, Carbon::parse('2026-08-15 08:45:00'));
        $session->update(['deadline_type' => null]);

        // Now = 09:00, fallback deadline = started_at + duration = 08:45 + 60 = 09:45
        // remaining = 09:45 - 09:00 = 2700s
        Carbon::setTestNow(Carbon::parse('2026-08-15 09:00:00'));
        $response = $this->actingAs($this->student->user)
            ->getJson(route('peserta.exams.status', $this->scheduleA->id));
        $response->assertOk();
        $this->assertEquals(2700, $response->json('mapel.remaining_seconds'));
    }

    // ---------------------------------------------------------------
    // TEST 12: Timer Sesi dua tahap — periodEnd (tanpa grace) lalu masa toleransi
    // ---------------------------------------------------------------

    public function test_sesi_remaining_splits_period_and_grace(): void
    {
        $this->beginSession($this->scheduleA);

        // Period end = 11:00, grace = 10 min. Sebelum periodEnd → tahap 1.
        // Now = 10:50:00 => remaining_period = 600s, belum in_grace.
        Carbon::setTestNow(Carbon::parse('2026-08-15 10:50:00'));
        $response = $this->actingAs($this->student->user)
            ->getJson(route('peserta.exams.status', $this->scheduleA->id));
        $response->assertOk();
        $this->assertEquals(600, $response->json('sesi.remaining_seconds'));
        $this->assertFalse($response->json('sesi.in_grace'));
        $this->assertEquals(1200, $response->json('sesi.remaining_grace'));

        // Now = 11:05 (5 menit dalam grace) → tahap 2: periode berakhir (negatif/0),
        // sisa toleransi = 300s, in_grace = true.
        Carbon::setTestNow(Carbon::parse('2026-08-15 11:05:00'));
        $response2 = $this->actingAs($this->student->user)
            ->getJson(route('peserta.exams.status', $this->scheduleA->id));
        $response2->assertOk();
        $this->assertEquals(-300, $response2->json('sesi.remaining_seconds'));
        $this->assertEquals(300, $response2->json('sesi.remaining_grace'));
        $this->assertTrue($response2->json('sesi.in_grace'));
    }

    public function test_sesi_grace_not_reset_on_refresh_while_in_grace(): void
    {
        $this->beginSession($this->scheduleA);

        // Period end = 11:00, grace = 10 min. Siswa membuka status saat sudah 4 menit
        // dalam masa toleransi (11:04). Sisa toleransi harus 6 menit (360s), BUKAN 10 menit penuh.
        Carbon::setTestNow(Carbon::parse('2026-08-15 11:04:00'));
        $r1 = $this->actingAs($this->student->user)
            ->getJson(route('peserta.exams.status', $this->scheduleA->id));
        $r1->assertOk();
        $this->assertLessThanOrEqual(0, $r1->json('sesi.remaining_seconds'));
        $this->assertTrue($r1->json('sesi.in_grace'));

        // 2 menit kemudian refresh/status lagi → sisa toleransi harus berkurang menjadi 4 menit (240s).
        Carbon::setTestNow(Carbon::parse('2026-08-15 11:06:00'));
        $r2 = $this->actingAs($this->student->user)
            ->getJson(route('peserta.exams.status', $this->scheduleA->id));
        $r2->assertOk();
        $this->assertTrue($r2->json('sesi.in_grace'));
        $this->assertEquals(360, $r1->json('sesi.remaining_grace'));
        $this->assertEquals(240, $r2->json('sesi.remaining_grace'));
        $this->assertLessThan($r1->json('sesi.remaining_grace'), $r2->json('sesi.remaining_grace'),
            'remaining_grace harus menurun seiring waktu, tidak mereset');
    }

    public function test_sesi_zero_after_grace_expires(): void
    {
        $this->beginSession($this->scheduleA);

        // Period end = 11:00, grace = 10 min → deadline sebenarnya = 11:10.
        // Now = 11:15 => sudah lewat total; periode & grace sama-sama negatif, in_grace = false.
        Carbon::setTestNow(Carbon::parse('2026-08-15 11:15:00'));
        $response = $this->actingAs($this->student->user)
            ->getJson(route('peserta.exams.status', $this->scheduleA->id));
        $response->assertOk();
        $this->assertLessThanOrEqual(0, $response->json('sesi.remaining_seconds'));
        $this->assertLessThanOrEqual(0, $response->json('sesi.remaining_grace'));
        $this->assertFalse($response->json('sesi.in_grace'));
    }
}

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

class EarlyStartTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;

    private Room $room;

    private Subject $subject1;

    private Subject $subject2;

    private Subject $subject3;

    private ExamPeriod $period;

    private ExamSchedule $schedule1;

    private ExamSchedule $schedule2;

    private ExamSchedule $schedule3;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-20 10:00:00'));

        $this->student = Student::factory()->create(['class_name' => 'XI RPL 1']);
        $this->room = Room::factory()->create();
        $this->student->update(['room_id' => $this->room->id]);

        $this->subject1 = Subject::factory()->create(['name' => 'Bahasa Indonesia']);
        $this->subject2 = Subject::factory()->create(['name' => 'Bahasa Inggris']);
        $this->subject3 = Subject::factory()->create(['name' => 'Matematika']);

        $this->period = ExamPeriod::create([
            'name' => 'Sesi Pagi',
            'name_prefix' => 'SP',
            'grade_level' => null,
            'session_number' => 1,
            'exam_date' => now()->toDateString(),
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
        ]);

        // Mapel 1: sudah selesai (08:00-09:00)
        $this->schedule1 = ExamSchedule::factory()->create([
            'room_id' => $this->room->id,
            'subject_id' => $this->subject1->id,
            'class_name' => 'XI RPL 1',
            'exam_period_id' => $this->period->id,
            'exam_date' => now()->toDateString(),
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'duration_minutes' => 60,
            'status' => ExamSchedule::STATUS_FINISHED,
        ]);

        // Mapel 2: SCHEDULED secara waktu (mulai 10:30, sekarang 10:00)
        $this->schedule2 = ExamSchedule::factory()->create([
            'room_id' => $this->room->id,
            'subject_id' => $this->subject2->id,
            'class_name' => 'XI RPL 1',
            'exam_period_id' => $this->period->id,
            'exam_date' => now()->toDateString(),
            'start_time' => '10:30:00',
            'end_time' => '11:30:00',
            'duration_minutes' => 60,
            'status' => ExamSchedule::STATUS_SCHEDULED,
        ]);

        // Mapel 3: SCHEDULED secara waktu (mulai 11:30)
        $this->schedule3 = ExamSchedule::factory()->create([
            'room_id' => $this->room->id,
            'subject_id' => $this->subject3->id,
            'class_name' => 'XI RPL 1',
            'exam_period_id' => $this->period->id,
            'exam_date' => now()->toDateString(),
            'start_time' => '11:30:00',
            'end_time' => '12:00:00',
            'duration_minutes' => 30,
            'status' => ExamSchedule::STATUS_SCHEDULED,
        ]);

        DB::table('exam_room_assignments')->insert([
            'exam_period_id' => $this->period->id,
            'student_id' => $this->student->id,
            'room_id' => $this->room->id,
            'seat_number' => 1,
        ]);

        // Buat soal untuk semua subject
        foreach ([$this->subject1, $this->subject2, $this->subject3] as $subject) {
            $question = Question::factory()->create(['subject_id' => $subject->id]);
            $question->classrooms()->sync($this->student->classroom_id);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Mapel 2 SCHEDULED + mapel 1 COMPLETED + tidak ada session in_progress lain
     → bisa_dimulai, token/work bisa diakses.
     */
    public function test_completed_other_mapel_allows_early_start(): void
    {
        $this->assertSame(ExamSchedule::STATUS_SCHEDULED, $this->schedule2->computedStatus());
        $this->assertTrue($this->schedule2->isWithinPeriodWindow());

        // Tandai mapel 1 sebagai completed
        ExamSession::create([
            'student_id' => $this->student->id,
            'exam_schedule_id' => $this->schedule1->id,
            'status' => ExamSession::STATUS_COMPLETED,
            'attendance_confirmed' => true,
            'started_at' => Carbon::parse('2026-08-20 08:05:00'),
            'finished_at' => Carbon::parse('2026-08-20 08:50:00'),
        ]);

        // Dashboard harus tampilkan "Bisa Dimulai" untuk mapel 2
        $tokenUrl2 = route('peserta.exams.token', $this->schedule2);
        $this->actingAs($this->student->user)
            ->get(route('peserta.dashboard'))
            ->assertOk()
            ->assertSee('Bisa Dimulai')
            ->assertSee($tokenUrl2, false);

        // Token page untuk mapel 2 harus bisa diakses
        $this->actingAs($this->student->user)
            ->get($tokenUrl2)
            ->assertOk()
            ->assertViewIs('peserta.exams.token');

        // Token valid harus berhasil dan redirect ke work page
        $period = $this->period;
        ExamToken::create([
            'exam_period_id' => $period->id,
            'token_code' => 'EARLY123',
            'rotation_index' => 0,
            'valid_from' => now()->subMinute(),
            'valid_until' => now()->addMinutes(15),
        ]);

        // Buat session not_started dengan absensi terkonfirmasi (syarat akses token)
        ExamSession::create([
            'student_id' => $this->student->id,
            'exam_schedule_id' => $this->schedule2->id,
            'status' => ExamSession::STATUS_NOT_STARTED,
            'attendance_confirmed' => true,
        ]);

        $this->actingAs($this->student->user)
            ->post(route('peserta.exams.token.validate', $this->schedule2), [
                'token_code' => 'EARLY123',
            ])
            ->assertRedirect(route('peserta.exams.work', $this->schedule2));
    }

    /**
     * Mapel 2 SCHEDULED + mapel 1 COMPLETED + mapel 3 IN_PROGRESS
     → belum_mulai untuk mapel 2, token/work DITOLAK (guard blocks).
     */
    public function test_active_session_in_period_blocks_early_start(): void
    {
        // Tandai mapel 1 sebagai completed
        ExamSession::create([
            'student_id' => $this->student->id,
            'exam_schedule_id' => $this->schedule1->id,
            'status' => ExamSession::STATUS_COMPLETED,
            'attendance_confirmed' => true,
            'started_at' => Carbon::parse('2026-08-20 08:05:00'),
            'finished_at' => Carbon::parse('2026-08-20 08:50:00'),
        ]);

        // Mapel 3 sedang in_progress (siswa buka di tab lain)
        ExamSession::create([
            'student_id' => $this->student->id,
            'exam_schedule_id' => $this->schedule3->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'attendance_confirmed' => true,
            'started_at' => Carbon::parse('2026-08-20 09:55:00'),
        ]);

        // Dashboard harus tampilkan "Belum Mulai" untuk mapel 2
        $tokenUrl2 = route('peserta.exams.token', $this->schedule2);
        $this->actingAs($this->student->user)
            ->get(route('peserta.dashboard'))
            ->assertOk()
            ->assertSee('Belum Mulai')
            ->assertDontSee($tokenUrl2, false);

        // Token page untuk mapel 2 harus ditolak
        $this->actingAs($this->student->user)
            ->get($tokenUrl2)
            ->assertRedirect(route('peserta.dashboard'));
    }

    /**
     * Simulasi flow multi-tab (edge case #6):
     - Tab A submit mapel 1 → session completed
     - Tab B cek mapel 2 SEBELUM submit → belum_mulai
     - Tab B cek mapel 2 SESUDAH submit → bisa_dimulai
     */
    public function test_multitab_flow_transisi_bisa_dimulai_setelah_submit(): void
    {
        // Mapel 1 sedang in_progress (siswa sedang mengerjakan)
        $session1 = ExamSession::create([
            'student_id' => $this->student->id,
            'exam_schedule_id' => $this->schedule1->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'attendance_confirmed' => true,
            'started_at' => Carbon::parse('2026-08-20 08:05:00'),
        ]);

        $tokenUrl2 = route('peserta.exams.token', $this->schedule2);

        // SEBELUM mapel 1 di-submit: mapel 2 harus "Belum Mulai"
        // Karena hasCompletedOtherMapelInPeriod = false (mapel 1 masih in_progress)
        $this->actingAs($this->student->user)
            ->get(route('peserta.dashboard'))
            ->assertOk()
            ->assertSee('Belum Mulai')
            ->assertDontSee($tokenUrl2, false);

        // Tab A: submit mapel 1 (finalize)
        $session1->update([
            'status' => ExamSession::STATUS_COMPLETED,
            'started_at' => Carbon::parse('2026-08-20 08:05:00'),
            'finished_at' => now(),
        ]);

        // SESUDAH mapel 1 di-submit: mapel 2 harus "Bisa Dimulai"
        $this->actingAs($this->student->user)
            ->get(route('peserta.dashboard'))
            ->assertOk()
            ->assertSee('Bisa Dimulai')
            ->assertSee($tokenUrl2, false);
    }

    /**
     * Mapel 2 SCHEDULED + belum ada mapel completed sama sekali
     → TETAP "belum_mulai" (regression check).
     */
    public function test_no_completed_mapel_keeps_belum_mulai(): void
    {
        // Tidak ada session apapun — mapel 1 belum dikerjakan

        $tokenUrl2 = route('peserta.exams.token', $this->schedule2);

        $this->actingAs($this->student->user)
            ->get(route('peserta.dashboard'))
            ->assertOk()
            ->assertSee('Belum Mulai')
            ->assertDontSee($tokenUrl2, false);

        // Token page harus ditolak
        $this->actingAs($this->student->user)
            ->get($tokenUrl2)
            ->assertRedirect(route('peserta.dashboard'));
    }

    /**
     * Period sudah berakhir + mapel 1 completed + mapel 2 masih SCHEDULED secara waktu
     → TIDAK bisa early-start (isWithinPeriodWindow = false menang duluan).
     */
    public function test_expired_period_blocks_early_start_even_if_completed(): void
    {
        // Pindahkan waktu ke setelah period berakhir
        Carbon::setTestNow(Carbon::parse('2026-08-20 13:00:00'));

        $this->assertFalse($this->schedule2->isWithinPeriodWindow());

        // Tandai mapel 1 sebagai completed
        ExamSession::create([
            'student_id' => $this->student->id,
            'exam_schedule_id' => $this->schedule1->id,
            'status' => ExamSession::STATUS_COMPLETED,
            'attendance_confirmed' => true,
            'started_at' => Carbon::parse('2026-08-20 08:05:00'),
            'finished_at' => Carbon::parse('2026-08-20 08:50:00'),
        ]);

        // Dashboard harus tampilkan "Waktu Terlewat" (bukan "Bisa Dimulai")
        $tokenUrl2 = route('peserta.exams.token', $this->schedule2);
        $this->actingAs($this->student->user)
            ->get(route('peserta.dashboard'))
            ->assertOk()
            ->assertSee('Terlewat')
            ->assertDontSee($tokenUrl2, false);

        // Token page harus ditolak
        $this->actingAs($this->student->user)
            ->get($tokenUrl2)
            ->assertRedirect(route('peserta.dashboard'));
    }
}

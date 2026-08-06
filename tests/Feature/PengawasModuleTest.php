<?php

namespace Tests\Feature;

use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\ExamToken;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Supervisor;
use App\Models\User;
use App\Models\Violation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PengawasModuleTest extends TestCase
{
    use RefreshDatabase;

    private Room $roomA;

    private Room $roomB;

    private User $pengawasA;

    private User $pengawasB;

    private ExamSchedule $scheduleA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->roomA = Room::factory()->create(['name' => 'Ruang A']);
        $this->roomB = Room::factory()->create(['name' => 'Ruang B']);
        $this->pengawasA = Supervisor::factory()->create(['room_id' => $this->roomA->id])->user;
        $this->pengawasB = Supervisor::factory()->create(['room_id' => $this->roomB->id])->user;

        $subjectA = Subject::factory()->create(['name' => 'Matematika']);
        $subjectB = Subject::factory()->create(['name' => 'Fisika']);

        $this->scheduleA = ExamSchedule::factory()->create([
            'room_id' => $this->roomA->id,
            'subject_id' => $subjectA->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => now()->toDateString(),
            'start_time' => now()->subMinutes(30)->format('H:i:s'),
            'end_time' => now()->addMinutes(30)->format('H:i:s'),
            'status' => 'ongoing',
        ]);

        ExamSchedule::factory()->create([
            'room_id' => $this->roomB->id,
            'subject_id' => $subjectB->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => now()->toDateString(),
            'status' => 'ongoing',
        ]);
    }

    /**
     * Buat siswa kelas XI RPL 1 yang ditempatkan permanen di ruangan scheduleA.
     */
    private function participant(): Student
    {
        return Student::factory()->create([
            'class_name' => 'XI RPL 1',
            'room_id' => $this->roomA->id,
        ]);
    }

    public function test_pengawas_dashboard_shows_only_own_room_data(): void
    {
        $student = $this->participant();
        ExamSession::factory()->create([
            'student_id' => $student->id,
            'exam_schedule_id' => $this->scheduleA->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->pengawasA)->get(route('pengawas.dashboard'));

        $response->assertOk()
            ->assertSee('Matematika')
            ->assertSee($student->user->name)
            ->assertSee('Ruang A')
            ->assertDontSee('Fisika')
            ->assertDontSee('Ruang B');
    }

    public function test_pengawas_dashboard_lists_all_today_schedules_with_time_based_status(): void
    {
        $subjectFuture = Subject::factory()->create(['name' => 'Bahasa Inggris']);
        $subjectPast = Subject::factory()->create(['name' => 'Sejarah']);
        $subjectTomorrow = Subject::factory()->create(['name' => 'Kimia']);

        ExamSchedule::factory()->create([
            'room_id' => $this->roomA->id,
            'subject_id' => $subjectFuture->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => now()->toDateString(),
            'start_time' => now()->addHour()->format('H:i:s'),
            'end_time' => now()->addHours(2)->format('H:i:s'),
            'status' => ExamSchedule::STATUS_SCHEDULED,
        ]);

        ExamSchedule::factory()->create([
            'room_id' => $this->roomA->id,
            'subject_id' => $subjectPast->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => now()->toDateString(),
            'start_time' => now()->subHours(2)->format('H:i:s'),
            'end_time' => now()->subHour()->format('H:i:s'),
            'status' => ExamSchedule::STATUS_FINISHED,
        ]);

        ExamSchedule::factory()->create([
            'room_id' => $this->roomA->id,
            'subject_id' => $subjectTomorrow->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => now()->addDay()->toDateString(),
            'status' => ExamSchedule::STATUS_SCHEDULED,
        ]);

        $response = $this->actingAs($this->pengawasA)->get(route('pengawas.dashboard'));

        $response->assertOk()
            ->assertSee('Matematika')
            ->assertSee('Bahasa Inggris')
            ->assertSee('Sejarah')
            ->assertSee('Sedang Berlangsung')
            ->assertSee('Belum Dimulai')
            ->assertSee('Selesai')
            ->assertDontSee('Kimia');
    }

    public function test_pengawas_dashboard_shows_attendance_and_progress_stats_for_ongoing_schedule(): void
    {
        $present = $this->participant();
        $working = $this->participant();
        $done = $this->participant();

        ExamSession::factory()->create([
            'student_id' => $present->id,
            'exam_schedule_id' => $this->scheduleA->id,
            'attendance_confirmed' => true,
            'status' => ExamSession::STATUS_COMPLETED,
        ]);

        ExamSession::factory()->create([
            'student_id' => $working->id,
            'exam_schedule_id' => $this->scheduleA->id,
            'attendance_confirmed' => true,
            'status' => ExamSession::STATUS_IN_PROGRESS,
        ]);

        ExamSession::factory()->create([
            'student_id' => $done->id,
            'exam_schedule_id' => $this->scheduleA->id,
            'status' => ExamSession::STATUS_COMPLETED,
        ]);

        $response = $this->actingAs($this->pengawasA)->get(route('pengawas.dashboard'));

        $response->assertOk()
            ->assertSee('Absen Hadir')
            ->assertSee('dari 3 peserta')
            ->assertSee('Sedang Mengerjakan')
            ->assertSee('Sudah Selesai');
    }

    public function test_pengawas_dashboard_shows_empty_message_when_no_schedule_today(): void
    {
        ExamSchedule::where('room_id', $this->roomA->id)->delete();

        $response = $this->actingAs($this->pengawasA)->get(route('pengawas.dashboard'));

        $response->assertOk()
            ->assertSee('Tidak ada jadwal ujian di ruangan Anda hari ini.')
            ->assertDontSee('Matematika');
    }

    public function test_attendance_checkbox_uses_attendance_confirmed_and_creates_session(): void
    {
        $student = $this->participant();

        $this->actingAs($this->pengawasA)->get(route('pengawas.attendance.index'))
            ->assertOk()
            ->assertSee($student->user->name);

        $this->assertDatabaseHas('exam_sessions', [
            'student_id' => $student->id,
            'exam_schedule_id' => $this->scheduleA->id,
            'status' => ExamSession::STATUS_NOT_STARTED,
            'attendance_confirmed' => false,
        ]);
    }

    public function test_attendance_confirm_patch_updates_attendance_confirmed(): void
    {
        $student = $this->participant();

        $this->actingAs($this->pengawasA)
            ->patch(route('pengawas.attendance.confirm', $this->scheduleA->id), [
                'student_id' => $student->id,
                'confirmed' => true,
            ])
            ->assertOk();

        $this->assertDatabaseHas('exam_sessions', [
            'student_id' => $student->id,
            'exam_schedule_id' => $this->scheduleA->id,
            'attendance_confirmed' => true,
            'attendance_status' => ExamSession::ATTENDANCE_PRESENT,
            'attendance_confirmed_by' => $this->pengawasA->id,
        ]);

        $this->actingAs($this->pengawasA)
            ->patch(route('pengawas.attendance.confirm', $this->scheduleA->id), [
                'student_id' => $student->id,
                'confirmed' => false,
            ])
            ->assertOk();

        $this->assertDatabaseHas('exam_sessions', [
            'student_id' => $student->id,
            'exam_schedule_id' => $this->scheduleA->id,
            'attendance_confirmed' => false,
            'attendance_status' => ExamSession::ATTENDANCE_ABSENT,
            'attendance_confirmed_by' => $this->pengawasA->id,
        ]);
    }

    public function test_attendance_confirm_rejects_student_not_in_class(): void
    {
        $outsider = Student::factory()->create(['class_name' => 'XII TKJ 1']);

        $this->actingAs($this->pengawasA)
            ->patch(route('pengawas.attendance.confirm', $this->scheduleA->id), [
                'student_id' => $outsider->id,
                'confirmed' => true,
            ])
            ->assertStatus(422);
    }

    public function test_attendance_locked_by_admin_blocks_checkbox_and_patch(): void
    {
        $student = $this->participant();
        ExamSession::factory()->create([
            'student_id' => $student->id,
            'exam_schedule_id' => $this->scheduleA->id,
            'locked_by_admin' => true,
        ]);

        $this->actingAs($this->pengawasA)->get(route('pengawas.attendance.index'))
            ->assertOk()
            ->assertSee('Dikunci oleh Admin');

        $this->actingAs($this->pengawasA)
            ->patch(route('pengawas.attendance.confirm', $this->scheduleA->id), [
                'student_id' => $student->id,
                'confirmed' => true,
            ])
            ->assertStatus(423);
    }

    public function test_attendance_highlights_auto_disabled_student_with_violation(): void
    {
        $student = $this->participant();
        $session = ExamSession::factory()->create([
            'student_id' => $student->id,
            'exam_schedule_id' => $this->scheduleA->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'attendance_confirmed' => false,
        ]);
        Violation::factory()->create(['exam_session_id' => $session->id]);

        $this->actingAs($this->pengawasA)->get(route('pengawas.attendance.index'))
            ->assertOk()
            ->assertSee('Nonaktif otomatis - ada pelanggaran');
    }

    public function test_token_page_lists_who_entered_token_by_status(): void
    {
        $entered = $this->participant();
        $confirmedNotEntered = $this->participant();

        ExamSession::factory()->create([
            'student_id' => $entered->id,
            'exam_schedule_id' => $this->scheduleA->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);

        ExamSession::factory()->create([
            'student_id' => $confirmedNotEntered->id,
            'exam_schedule_id' => $this->scheduleA->id,
            'status' => ExamSession::STATUS_NOT_STARTED,
            'attendance_confirmed' => true,
        ]);

        $this->actingAs($this->pengawasA)->get(route('pengawas.tokens.index'))
            ->assertOk()
            ->assertSee('Sudah memasukkan token')
            ->assertSee('Belum memasukkan token')
            ->assertSee('sudah diabsen hadir');
    }

    public function test_pengawas_latest_violations_only_from_own_room(): void
    {
        $studentA = Student::factory()->create(['class_name' => 'XI RPL 1']);
        $studentB = Student::factory()->create(['class_name' => 'XI RPL 1']);

        $sessionA = ExamSession::factory()->create(['student_id' => $studentA->id, 'exam_schedule_id' => $this->scheduleA->id]);
        $scheduleB = ExamSchedule::where('room_id', $this->roomB->id)->first();
        $sessionB = ExamSession::factory()->create(['student_id' => $studentB->id, 'exam_schedule_id' => $scheduleB->id]);

        Violation::factory()->create(['exam_session_id' => $sessionA->id, 'violation_type' => 'mencontek']);
        Violation::factory()->create(['exam_session_id' => $sessionB->id, 'violation_type' => 'membawa_handphone']);

        $response = $this->actingAs($this->pengawasA)->getJson(route('pengawas.violations.latest'));

        $response->assertOk()
            ->assertJsonFragment(['violation_type' => 'mencontek'])
            ->assertJsonMissing(['violation_type' => 'membawa_handphone']);
    }

    public function test_pengawas_dashboard_blocks_other_room_schedule_via_url(): void
    {
        $scheduleB = ExamSchedule::where('room_id', $this->roomB->id)->first();

        $this->actingAs($this->pengawasA)->get(route('pengawas.attendance.index', ['schedule' => $scheduleB->id]))
            ->assertOk()
            ->assertDontSee('Fisika');

        $this->actingAs($this->pengawasA)->post(route('pengawas.tokens.generate', ['schedule' => $scheduleB->id]))
            ->assertNotFound();
    }

    public function test_attendance_page_lists_class_participants_and_saves(): void
    {
        $studentA = $this->participant();
        $studentB = $this->participant();

        $this->actingAs($this->pengawasA)->get(route('pengawas.attendance.index'))
            ->assertOk()
            ->assertSee($studentA->user->name)
            ->assertSee($studentB->user->name);

        $this->actingAs($this->pengawasA)->post(route('pengawas.attendance.update', ['schedule' => $this->scheduleA->id]), [
            'attendance' => [
                $studentA->id => 'hadir',
                $studentB->id => 'tidak_hadir',
            ],
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('exam_sessions', [
            'student_id' => $studentA->id,
            'exam_schedule_id' => $this->scheduleA->id,
            'attendance_status' => 'hadir',
        ]);
        $this->assertDatabaseHas('exam_sessions', [
            'student_id' => $studentB->id,
            'exam_schedule_id' => $this->scheduleA->id,
            'attendance_status' => 'tidak_hadir',
        ]);
    }

    public function test_attendance_rejects_student_not_in_class(): void
    {
        $outsider = Student::factory()->create(['class_name' => 'XII TKJ 1']);

        $this->actingAs($this->pengawasA)->post(route('pengawas.attendance.update', ['schedule' => $this->scheduleA->id]), [
            'attendance' => [$outsider->id => 'hadir'],
        ])->assertSessionHasErrors('attendance');
    }

    public function test_token_generation_and_active_token_display(): void
    {
        $student = $this->participant();
        ExamSession::factory()->create([
            'student_id' => $student->id,
            'exam_schedule_id' => $this->scheduleA->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $this->actingAs($this->pengawasA)->post(route('pengawas.tokens.generate', ['schedule' => $this->scheduleA->id]))
            ->assertRedirect()->assertSessionHas('success');

        $token = ExamToken::where('exam_schedule_id', $this->scheduleA->id)->first();
        $this->assertNotNull($token);
        $this->assertEquals(8, strlen($token->token_code));

        $this->actingAs($this->pengawasA)->get(route('pengawas.tokens.index'))
            ->assertOk()
            ->assertSee($token->token_code)
            ->assertSee('Sudah memasukkan token');
    }

    public function test_pengawas_without_room_assignment_is_forbidden(): void
    {
        $unassigned = User::factory()->pengawas()->create();

        $this->actingAs($unassigned)->get(route('pengawas.dashboard'))->assertForbidden();
        $this->actingAs($unassigned)->get(route('pengawas.attendance.index'))->assertForbidden();
        $this->actingAs($unassigned)->get(route('pengawas.tokens.index'))->assertForbidden();
    }

    public function test_non_pengawas_cannot_access_pengawas_modules(): void
    {
        $admin = User::factory()->admin()->create();
        $peserta = User::factory()->peserta()->create();

        $this->actingAs($admin)->get(route('pengawas.dashboard'))->assertForbidden();
        $this->actingAs($peserta)->get(route('pengawas.tokens.index'))->assertForbidden();
    }
}

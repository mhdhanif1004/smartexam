<?php

namespace Tests\Feature;

use App\Models\ExamPeriod;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\ExamToken;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Supervisor;
use App\Models\SupervisorRoomAssignment;
use App\Models\User;
use App\Models\Violation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

        $this->roomA = Room::factory()->create(['room_number' => 1]);
        $this->roomB = Room::factory()->create(['room_number' => 2]);
        $this->pengawasA = Supervisor::factory()->create(['room_id' => $this->roomA->id])->user;
        $this->pengawasB = Supervisor::factory()->create(['room_id' => $this->roomB->id])->user;

        $subjectA = Subject::factory()->create(['name' => 'Matematika']);
        $subjectB = Subject::factory()->create(['name' => 'Fisika']);

        $this->periodA = ExamPeriod::create([
            'name' => 'Sesi Ruang A',
            'name_prefix' => 'S1',
            'grade_level' => null,
            'session_number' => 1,
            'exam_date' => now()->toDateString(),
            'start_time' => now()->subMinutes(30)->format('H:i:s'),
            'end_time' => now()->addMinutes(30)->format('H:i:s'),
        ]);

        $this->scheduleA = ExamSchedule::factory()->create([
            'room_id' => $this->roomA->id,
            'subject_id' => $subjectA->id,
            'class_name' => 'XI RPL 1',
            'exam_period_id' => $this->periodA->id,
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

        SupervisorRoomAssignment::factory()->create([
            'supervisor_id' => $this->pengawasA->supervisor->id,
            'room_id' => $this->roomA->id,
            'exam_period_id' => $this->periodA->id,
            'exam_date' => now()->toDateString(),
        ]);
    }

    /**
     * Buat siswa kelas XI RPL 1 yang ditempatkan permanen di ruangan scheduleA.
     */
    private function participant(): Student
    {
        $student = Student::factory()->create([
            'class_name' => 'XI RPL 1',
            'room_id' => $this->roomA->id,
        ]);

        DB::table('exam_room_assignments')->insert([
            'exam_period_id' => $this->periodA->id,
            'student_id' => $student->id,
            'room_id' => $this->roomA->id,
            'seat_number' => $student->id,
        ]);

        return $student;
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
            ->assertSee('Ruang 1')
            ->assertDontSee('Fisika')
            ->assertDontSee('Ruang 2');
    }

    public function test_pengawas_dashboard_lists_all_today_schedules_with_time_based_status(): void
    {
        $subjectFuture = Subject::factory()->create(['name' => 'Bahasa Inggris']);
        $subjectPast = Subject::factory()->create(['name' => 'Sejarah']);
        $subjectTomorrow = Subject::factory()->create(['name' => 'Kimia']);

        $periodFuture = ExamPeriod::create([
            'name' => 'Sesi Future',
            'name_prefix' => 'S2',
            'grade_level' => null,
            'session_number' => 2,
            'exam_date' => now()->toDateString(),
            'start_time' => now()->addHour()->format('H:i:s'),
            'end_time' => now()->addHours(2)->format('H:i:s'),
        ]);

        $periodPast = ExamPeriod::create([
            'name' => 'Sesi Past',
            'name_prefix' => 'S3',
            'grade_level' => null,
            'session_number' => 3,
            'exam_date' => now()->toDateString(),
            'start_time' => now()->subHours(2)->format('H:i:s'),
            'end_time' => now()->subHour()->format('H:i:s'),
        ]);

        ExamSchedule::factory()->create([
            'room_id' => $this->roomA->id,
            'subject_id' => $subjectFuture->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => now()->toDateString(),
            'start_time' => now()->addHour()->format('H:i:s'),
            'end_time' => now()->addHours(2)->format('H:i:s'),
            'exam_period_id' => $periodFuture->id,
            'status' => ExamSchedule::STATUS_SCHEDULED,
        ]);

        ExamSchedule::factory()->create([
            'room_id' => $this->roomA->id,
            'subject_id' => $subjectPast->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => now()->toDateString(),
            'start_time' => now()->subHours(2)->format('H:i:s'),
            'end_time' => now()->subHour()->format('H:i:s'),
            'exam_period_id' => $periodPast->id,
            'status' => ExamSchedule::STATUS_FINISHED,
        ]);

        ExamSchedule::factory()->create([
            'room_id' => $this->roomA->id,
            'subject_id' => $subjectTomorrow->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => now()->addDay()->toDateString(),
            'status' => ExamSchedule::STATUS_SCHEDULED,
        ]);

        SupervisorRoomAssignment::factory()->create([
            'supervisor_id' => $this->pengawasA->supervisor->id,
            'room_id' => $this->roomA->id,
            'exam_period_id' => $periodFuture->id,
            'exam_date' => now()->toDateString(),
        ]);

        SupervisorRoomAssignment::factory()->create([
            'supervisor_id' => $this->pengawasA->supervisor->id,
            'room_id' => $this->roomA->id,
            'exam_period_id' => $periodPast->id,
            'exam_date' => now()->toDateString(),
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

        $this->assertDatabaseMissing('exam_sessions', [
            'student_id' => $student->id,
            'exam_schedule_id' => $this->scheduleA->id,
        ]);

        $this->actingAs($this->pengawasA)
            ->patch(route('pengawas.attendance.confirm', $this->scheduleA->id), [
                'student_id' => $student->id,
                'confirmed' => true,
            ])
            ->assertOk();

        $this->assertDatabaseHas('exam_sessions', [
            'student_id' => $student->id,
            'exam_schedule_id' => $this->scheduleA->id,
            'status' => ExamSession::STATUS_NOT_STARTED,
            'attendance_confirmed' => true,
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

        $studentB = Student::factory()->create([
            'class_name' => 'XI RPL 1',
            'room_id' => $this->roomB->id,
        ]);

        $this->actingAs($this->pengawasA)->patch(route('pengawas.attendance.confirm', $scheduleB->id), [
            'student_id' => $studentB->id,
            'confirmed' => true,
        ])->assertNotFound();
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

        ExamToken::create([
            'exam_period_id' => $this->periodA->id,
            'token_code' => 'TEST9999',
            'rotation_index' => 0,
            'valid_from' => now()->subMinute(),
            'valid_until' => now()->addMinutes(15),
        ]);

        $this->actingAs($this->pengawasA)->get(route('pengawas.tokens.index'))
            ->assertOk()
            ->assertSee('TEST9999')
            ->assertSee('Sudah memasukkan token');
    }

    public function test_pengawas_without_room_assignment_gets_empty_state_not_forbidden(): void
    {
        // Pengawas SAH (punya record Supervisor) tapi tidak ditugaskan ke
        // ruangan mana pun — tanpa rotasi hari ini DAN tanpa ruangan statis.
        $supervisor = Supervisor::factory()->create(['room_id' => null]);
        $user = $supervisor->user;

        $this->actingAs($user)->get(route('pengawas.dashboard'))
            ->assertOk()
            ->assertSee('Belum ada jadwal ujian untuk Anda saat ini.');
        $this->actingAs($user)->get(route('pengawas.attendance.index'))
            ->assertOk()
            ->assertSee('Belum ada jadwal ujian untuk Anda saat ini.');
        $this->actingAs($user)->get(route('pengawas.tokens.index'))
            ->assertOk()
            ->assertSee('Belum ada jadwal ujian untuk Anda saat ini.');

        // JSON endpoints tidak crash, kembalikan data kosong
        $this->actingAs($user)->getJson(route('pengawas.violations.recent'))
            ->assertOk()
            ->assertJson(['violations' => []]);
        $this->actingAs($user)->getJson(route('pengawas.tokens.current'))
            ->assertOk()
            ->assertJson(['active' => false]);
    }

    public function test_non_pengawas_cannot_access_pengawas_modules(): void
    {
        $admin = User::factory()->admin()->create();
        $peserta = User::factory()->peserta()->create();

        $this->actingAs($admin)->get(route('pengawas.dashboard'))->assertForbidden();
        $this->actingAs($peserta)->get(route('pengawas.tokens.index'))->assertForbidden();
    }

    public function test_token_endpoints_only_show_own_room_period_tokens(): void
    {
        // Pengawas A -> roomA, has periodA with token "ROOMA001"
        // Pengawas B -> roomB, has periodB with token "ROOMB002"
        ExamToken::create([
            'exam_period_id' => $this->periodA->id,
            'token_code' => 'ROOMA001',
            'rotation_index' => 0,
            'valid_from' => now()->subMinute(),
            'valid_until' => now()->addMinutes(15),
        ]);

        $subjectB = Subject::factory()->create(['name' => 'Biologi']);
        $periodB = ExamPeriod::create([
            'name' => 'Sesi Ruang B',
            'name_prefix' => 'S2',
            'grade_level' => null,
            'session_number' => 1,
            'exam_date' => now()->toDateString(),
            'start_time' => now()->subMinutes(30)->format('H:i:s'),
            'end_time' => now()->addMinutes(30)->format('H:i:s'),
        ]);

        $scheduleForRoomB = ExamSchedule::where('room_id', $this->roomB->id)->first();
        $scheduleForRoomB->update(['exam_period_id' => $periodB->id]);

        ExamToken::create([
            'exam_period_id' => $periodB->id,
            'token_code' => 'ROOMB002',
            'rotation_index' => 0,
            'valid_from' => now()->subMinute(),
            'valid_until' => now()->addMinutes(15),
        ]);

        // Pengawas A sees only their room's token
        $this->actingAs($this->pengawasA)->get(route('pengawas.tokens.index'))
            ->assertOk()
            ->assertSee('ROOMA001')
            ->assertDontSee('ROOMB002')
            ->assertSee('Sesi Ruang A')
            ->assertDontSee('Sesi Ruang B');

        // Pengawas B sees only their room's token
        $this->actingAs($this->pengawasB)->get(route('pengawas.tokens.index'))
            ->assertOk()
            ->assertSee('ROOMB002')
            ->assertDontSee('ROOMA001')
            ->assertSee('Sesi Ruang B')
            ->assertDontSee('Sesi Ruang A');

        // AJAX endpoint: pengawas A gets only their token
        $this->actingAs($this->pengawasA)->getJson(route('pengawas.tokens.current'))
            ->assertOk()
            ->assertJson(['active' => true, 'token_code' => 'ROOMA001'])
            ->assertJsonMissing(['token_code' => 'ROOMB002']);

        // AJAX endpoint: pengawas B gets only their token
        $this->actingAs($this->pengawasB)->getJson(route('pengawas.tokens.current'))
            ->assertOk()
            ->assertJson(['active' => true, 'token_code' => 'ROOMB002'])
            ->assertJsonMissing(['token_code' => 'ROOMA001']);

        // Both routes are parameterless GET — no way to inject period_id via URL
        $routeCollection = app('router')->getRoutes();
        $indexRoute = $routeCollection->getByName('pengawas.tokens.index');
        $currentRoute = $routeCollection->getByName('pengawas.tokens.current');
        $this->assertNotNull($indexRoute);
        $this->assertNotNull($currentRoute);
        $this->assertContains('GET', $indexRoute->methods());
        $this->assertContains('GET', $currentRoute->methods());
    }
}

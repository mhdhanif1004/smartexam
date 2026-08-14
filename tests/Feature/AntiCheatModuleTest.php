<?php

namespace Tests\Feature;

use App\Http\Controllers\Peserta\ExamController;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Supervisor;
use App\Models\User;
use App\Models\Violation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AntiCheatModuleTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;

    private User $user;

    private User $admin;

    private User $pengawas;

    private Room $room;

    private ExamSchedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-31 09:00:00'));

        $this->room = Room::factory()->create(['room_number' => 1]);
        $this->student = Student::factory()->create([
            'class_name' => 'XI RPL 1',
            'room_id' => $this->room->id,
        ]);
        $this->user = $this->student->user;
        $this->admin = User::factory()->admin()->create();
        $this->pengawas = Supervisor::factory()->create(['room_id' => $this->room->id])->user;

        $subject = Subject::factory()->create(['name' => 'Matematika']);
        $this->schedule = ExamSchedule::factory()->create([
            'room_id' => $this->room->id,
            'subject_id' => $subject->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => now()->toDateString(),
            'start_time' => '08:30:00',
            'end_time' => '10:30:00',
            'duration_minutes' => 60,
            'status' => 'ongoing',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function workingSession(): ExamSession
    {
        return ExamSession::create([
            'student_id' => $this->student->id,
            'exam_schedule_id' => $this->schedule->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'started_at' => now(),
            'attendance_confirmed' => true,
        ]);
    }

    public function test_tab_switch_full_loop_auto_disables_then_reconfirm_allows_reentry(): void
    {
        $session = $this->workingSession();

        // Peserta terdeteksi pindah tab -> violation tercatat + auto-disable.
        $this->actingAs($this->user)->postJson(route('peserta.exams.violation', $this->schedule->id), [
            'violation_type' => Violation::TYPE_TAB_SWITCH,
        ])->assertOk()
            ->assertJson([
                'redirect' => true,
                'url' => route('peserta.dashboard'),
            ]);

        $this->assertDatabaseHas('violations', [
            'exam_session_id' => $session->id,
            'violation_type' => Violation::TYPE_TAB_SWITCH,
        ]);

        $session->refresh();
        $this->assertTrue($session->violation_flag_1);
        $this->assertFalse($session->violation_flag_2);
        $this->assertFalse($session->attendance_confirmed);

        // Coba masuk token lagi -> ditolak dengan pesan yang benar.
        $this->actingAs($this->user)->post(route('peserta.exams.token.validate', $this->schedule->id), [
            'token_code' => 'ABC12345',
        ])->assertRedirect()
            ->assertSessionHas('error', ExamController::ACCESS_ERROR_DISABLED_BY_VIOLATION);

        // Halaman token menampilkan pesan menonjol.
        $this->actingAs($this->user)->get(route('peserta.exams.token', $this->schedule->id))
            ->assertOk()
            ->assertSee('Absensi Anda dinonaktifkan sistem karena terdeteksi pelanggaran');

        // Tidak bisa lanjut mengerjakan soal.
        $this->actingAs($this->user)->get(route('peserta.exams.work', $this->schedule->id))
            ->assertRedirect(route('peserta.dashboard'))
            ->assertSessionHas('error', ExamController::ACCESS_ERROR_DISABLED_BY_VIOLATION);

        $this->actingAs($this->user)->postJson(route('peserta.exams.save-answer', $this->schedule->id), [
            'answers' => [],
        ])->assertStatus(403);

        // Pengawas absen ulang secara manual.
        $this->actingAs($this->pengawas)
            ->patch(route('pengawas.attendance.confirm', $this->schedule->id), [
                'student_id' => $this->student->id,
                'confirmed' => true,
            ])->assertOk();

        $this->assertDatabaseHas('exam_sessions', [
            'id' => $session->id,
            'attendance_confirmed' => true,
        ]);

        // Peserta bisa masuk lagi dan lanjut mengerjakan.
        $this->actingAs($this->user)->post(route('peserta.exams.token.validate', $this->schedule->id), [
            'token_code' => 'ABC12345',
        ])->assertRedirect(route('peserta.exams.work', $this->schedule->id));

        $this->actingAs($this->user)->get(route('peserta.exams.work', $this->schedule->id))
            ->assertOk();
    }

    public function test_three_violations_fill_three_flags_and_fullscreen_exit_adds_no_flag(): void
    {
        $session = $this->workingSession();

        foreach ([Violation::TYPE_TAB_SWITCH, Violation::TYPE_BLUR, Violation::TYPE_RESIZE] as $type) {
            $this->actingAs($this->user)->postJson(route('peserta.exams.violation', $this->schedule->id), [
                'violation_type' => $type,
            ])->assertOk()->assertJson(['redirect' => true]);
        }

        $session->refresh();
        $this->assertTrue($session->violation_flag_1);
        $this->assertTrue($session->violation_flag_2);
        $this->assertTrue($session->violation_flag_3);
        $this->assertFalse($session->attendance_confirmed);
        $this->assertSame(3, $session->violations()->count());

        // Keluar fullscreen hanya dicatat, tidak mengaktifkan flag tambahan.
        $this->actingAs($this->user)->postJson(route('peserta.exams.violation', $this->schedule->id), [
            'violation_type' => Violation::TYPE_FULLSCREEN_EXIT,
        ])->assertOk()->assertJson(['recorded' => true]);

        $session->refresh();
        $this->assertTrue($session->violation_flag_1);
        $this->assertTrue($session->violation_flag_2);
        $this->assertTrue($session->violation_flag_3);
        $this->assertFalse($session->attendance_confirmed);
        $this->assertSame(4, $session->violations()->count());
    }

    public function test_fullscreen_exit_is_recorded_without_disabling_session(): void
    {
        $session = $this->workingSession();

        $this->actingAs($this->user)->postJson(route('peserta.exams.violation', $this->schedule->id), [
            'violation_type' => Violation::TYPE_FULLSCREEN_EXIT,
        ])->assertOk()
            ->assertJson(['recorded' => true])
            ->assertJsonMissing(['redirect' => true]);

        $this->assertDatabaseHas('violations', [
            'exam_session_id' => $session->id,
            'violation_type' => Violation::TYPE_FULLSCREEN_EXIT,
        ]);

        $session->refresh();
        $this->assertFalse($session->violation_flag_1);
        $this->assertTrue($session->attendance_confirmed);

        // Sesi tetap aktif: peserta masih bisa mengerjakan tanpa absensi ulang.
        $this->actingAs($this->user)->get(route('peserta.exams.work', $this->schedule->id))
            ->assertOk();
    }

    public function test_admin_lock_blocks_student_until_unlock(): void
    {
        $session = $this->workingSession();

        // Admin mengunci sesi peserta.
        $this->actingAs($this->admin)
            ->patch(route('admin.violations.lock', $session->id), ['locked' => true])
            ->assertOk()
            ->assertJson(['ok' => true, 'locked' => true]);

        $this->assertDatabaseHas('exam_sessions', [
            'id' => $session->id,
            'locked_by_admin' => true,
            'locked_by_admin_by' => $this->admin->id,
        ]);

        // Peserta ditolak dengan pesan "hubungi admin".
        $this->actingAs($this->user)->get(route('peserta.exams.work', $this->schedule->id))
            ->assertRedirect(route('peserta.dashboard'))
            ->assertSessionHas('error', ExamController::ACCESS_ERROR_LOCKED_ADMIN);

        $this->actingAs($this->user)->post(route('peserta.exams.token.validate', $this->schedule->id), [
            'token_code' => 'ABC12345',
        ])->assertRedirect()
            ->assertSessionHas('error', ExamController::ACCESS_ERROR_LOCKED_ADMIN);

        // Polling status peserta menandakan dikunci.
        $this->actingAs($this->user)->getJson(route('peserta.exams.status', $this->schedule->id))
            ->assertOk()
            ->assertJson(['locked' => true]);

        // Pengawas tidak berwenang membuka kunci (lewat absensi pun ditolak).
        $this->actingAs($this->pengawas)
            ->patch(route('pengawas.attendance.confirm', $this->schedule->id), [
                'student_id' => $this->student->id,
                'confirmed' => true,
            ])->assertStatus(423);

        // Admin membuka kunci.
        $this->actingAs($this->admin)
            ->patch(route('admin.violations.lock', $session->id), ['locked' => false])
            ->assertOk()
            ->assertJson(['ok' => true, 'locked' => false]);

        $this->assertDatabaseHas('exam_sessions', [
            'id' => $session->id,
            'locked_by_admin' => false,
        ]);

        // Peserta bisa mengerjakan lagi.
        $this->actingAs($this->user)->getJson(route('peserta.exams.status', $this->schedule->id))
            ->assertOk()
            ->assertJson(['locked' => false]);

        $this->actingAs($this->user)->get(route('peserta.exams.work', $this->schedule->id))
            ->assertOk();
    }

    public function test_only_admin_can_lock_unlock(): void
    {
        $session = $this->workingSession();

        $this->actingAs($this->pengawas)
            ->patch(route('admin.violations.lock', $session->id), ['locked' => true])
            ->assertForbidden();

        $this->actingAs($this->user)
            ->patch(route('admin.violations.lock', $session->id), ['locked' => true])
            ->assertForbidden();

        $this->assertDatabaseHas('exam_sessions', [
            'id' => $session->id,
            'locked_by_admin' => false,
        ]);
    }

    public function test_admin_violations_page_shows_flag_count_and_lock_toggle(): void
    {
        $session = $this->workingSession();
        $session->update([
            'violation_flag_1' => true,
            'violation_flag_2' => true,
            'locked_by_admin' => true,
        ]);
        Violation::factory()->create(['exam_session_id' => $session->id, 'violation_type' => Violation::TYPE_TAB_SWITCH]);

        $this->actingAs($this->admin)->get(route('admin.violations.index'))
            ->assertOk()
            ->assertSee('Checklist Aktif')
            ->assertSee('Hentikan Paksa')
            ->assertSee('2 dari 3')
            ->assertSee('Dikunci');
    }

    public function test_pengawas_recent_payload_includes_flags_and_handled_marker(): void
    {
        $session = $this->workingSession();
        $session->update(['violation_flag_1' => true, 'violation_flag_2' => true]);
        $violation = Violation::factory()->create([
            'exam_session_id' => $session->id,
            'violation_type' => Violation::TYPE_TAB_SWITCH,
        ]);

        $this->actingAs($this->pengawas)->getJson(route('pengawas.violations.recent'))
            ->assertOk()
            ->assertJsonFragment([
                'id' => $violation->id,
                'flags' => [1, 1, 0],
                'flag_count' => 2,
                'handled' => false,
            ]);

        // Tandai ditangani -> hanya penanda UI; flag dan data asli tidak berubah.
        $this->actingAs($this->pengawas)
            ->patch(route('pengawas.violations.handle', $violation->id))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('violations', [
            'id' => $violation->id,
            'handled_by_supervisor' => true,
            'handled_by' => $this->pengawas->id,
        ]);
        $this->assertNotNull($violation->refresh()->handled_at);

        $session->refresh();
        $this->assertTrue($session->violation_flag_1);
        $this->assertTrue($session->violation_flag_2);
        $this->assertFalse($session->violation_flag_3);
        $this->assertSame(ExamSession::STATUS_IN_PROGRESS, $session->status);
    }

    public function test_pengawas_cannot_handle_violation_from_other_room(): void
    {
        $otherRoom = Room::factory()->create();
        $otherSchedule = ExamSchedule::factory()->create(['room_id' => $otherRoom->id]);
        $otherStudent = Student::factory()->create(['class_name' => 'XI RPL 1']);
        $otherSession = ExamSession::factory()->create([
            'student_id' => $otherStudent->id,
            'exam_schedule_id' => $otherSchedule->id,
        ]);
        $violation = Violation::factory()->create(['exam_session_id' => $otherSession->id]);

        $this->actingAs($this->pengawas)
            ->patch(route('pengawas.violations.handle', $violation->id))
            ->assertForbidden();
    }
}

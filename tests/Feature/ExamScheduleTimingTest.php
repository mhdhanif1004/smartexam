<?php

namespace Tests\Feature;

use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Supervisor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ExamScheduleTimingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeSchedule(string $date, string $start, string $end, int $duration = 90): ExamSchedule
    {
        return ExamSchedule::factory()->create([
            'exam_date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'duration_minutes' => $duration,
        ]);
    }

    /**
     * @return array{Room, User}
     */
    private function supervisorRoom(): array
    {
        $room = Room::factory()->create(['name' => 'Ruang A']);
        $pengawas = Supervisor::factory()->create(['room_id' => $room->id])->user;

        return [$room, $pengawas];
    }

    public function test_computed_status_before_start_is_scheduled(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 08:00:00'));
        $schedule = $this->makeSchedule('2026-08-10', '09:00:00', '10:00:00');

        $this->assertSame(ExamSchedule::STATUS_SCHEDULED, $schedule->computedStatus());
        $this->assertSame(ExamSchedule::STATUS_SCHEDULED, $schedule->current_status);
    }

    public function test_computed_status_at_exactly_start_is_ongoing(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 09:00:00'));
        $schedule = $this->makeSchedule('2026-08-10', '09:00:00', '10:00:00');

        $this->assertSame(ExamSchedule::STATUS_ONGOING, $schedule->computedStatus());
    }

    public function test_computed_status_during_exam_is_ongoing(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 09:30:00'));
        $schedule = $this->makeSchedule('2026-08-10', '09:00:00', '10:00:00');

        $this->assertSame(ExamSchedule::STATUS_ONGOING, $schedule->computedStatus());
    }

    public function test_computed_status_at_exactly_end_is_finished(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 10:00:00'));
        $schedule = $this->makeSchedule('2026-08-10', '09:00:00', '10:00:00');

        $this->assertSame(ExamSchedule::STATUS_FINISHED, $schedule->computedStatus());
    }

    public function test_computed_status_uses_end_time_column_not_duration(): void
    {
        // duration_minutes = 90 (berakhir 10:30) tapi end_time = 10:00 -> tetap finished
        Carbon::setTestNow(Carbon::parse('2026-08-10 10:15:00'));
        $schedule = $this->makeSchedule('2026-08-10', '09:00:00', '10:00:00', 90);

        $this->assertSame(ExamSchedule::STATUS_FINISHED, $schedule->computedStatus());
    }

    public function test_attendance_and_token_window_boundaries(): void
    {
        $schedule = $this->makeSchedule('2026-08-10', '09:00:00', '10:00:00');

        // 11 menit sebelum mulai -> belum masuk jendela absensi maupun token
        Carbon::setTestNow(Carbon::parse('2026-08-10 08:49:00'));
        $this->assertFalse($schedule->isAttendanceWindowOpen());
        $this->assertFalse($schedule->isTokenWindowOpen());

        // tepat 10 menit sebelum mulai -> jendela absensi terbuka, token belum
        Carbon::setTestNow(Carbon::parse('2026-08-10 08:50:00'));
        $this->assertTrue($schedule->isAttendanceWindowOpen());
        $this->assertFalse($schedule->isTokenWindowOpen());

        // dalam jendela absensi tapi belum jendela token
        Carbon::setTestNow(Carbon::parse('2026-08-10 08:53:00'));
        $this->assertTrue($schedule->isAttendanceWindowOpen());
        $this->assertFalse($schedule->isTokenWindowOpen());

        // tepat 5 menit sebelum mulai -> token tersedia
        Carbon::setTestNow(Carbon::parse('2026-08-10 08:55:00'));
        $this->assertTrue($schedule->isAttendanceWindowOpen());
        $this->assertTrue($schedule->isTokenWindowOpen());

        // saat ujian berlangsung -> kedua jendela terbuka
        Carbon::setTestNow(Carbon::parse('2026-08-10 09:15:00'));
        $this->assertTrue($schedule->isAttendanceWindowOpen());
        $this->assertTrue($schedule->isTokenWindowOpen());

        // sesaat sebelum waktu selesai -> masih terbuka
        Carbon::setTestNow(Carbon::parse('2026-08-10 09:59:59'));
        $this->assertTrue($schedule->isAttendanceWindowOpen());
        $this->assertTrue($schedule->isTokenWindowOpen());

        // tepat waktu selesai -> status finished, jendela masih inklusif
        Carbon::setTestNow(Carbon::parse('2026-08-10 10:00:00'));
        $this->assertSame(ExamSchedule::STATUS_FINISHED, $schedule->computedStatus());

        // dalam toleransi absensi ulang (10 menit setelah selesai) -> absensi
        // tetap terbuka, token sudah tertutup
        Carbon::setTestNow(Carbon::parse('2026-08-10 10:05:00'));
        $this->assertTrue($schedule->isAttendanceWindowOpen());
        $this->assertFalse($schedule->isTokenWindowOpen());

        // tepat 10 menit setelah selesai -> jendela absensi masih terbuka (inklusif)
        Carbon::setTestNow(Carbon::parse('2026-08-10 10:10:00'));
        $this->assertTrue($schedule->isAttendanceWindowOpen());
        $this->assertFalse($schedule->isTokenWindowOpen());

        // lewat toleransi -> jendela absensi tertutup total
        Carbon::setTestNow(Carbon::parse('2026-08-10 10:10:01'));
        $this->assertFalse($schedule->isAttendanceWindowOpen());
        $this->assertFalse($schedule->isTokenWindowOpen());
    }

    public function test_attendance_page_available_ten_minutes_before_start(): void
    {
        [$room, $pengawas] = $this->supervisorRoom();
        $subject = Subject::factory()->create(['name' => 'Matematika']);
        $schedule = ExamSchedule::factory()->create([
            'room_id' => $room->id,
            'subject_id' => $subject->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => '2026-08-10',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'status' => ExamSchedule::STATUS_SCHEDULED,
        ]);

        // 10 menit sebelum mulai -> halaman absensi sudah menampilkan jadwal + badge jendela absensi
        Carbon::setTestNow(Carbon::parse('2026-08-10 08:50:00'));
        $this->actingAs($pengawas)->get(route('pengawas.attendance.index'))
            ->assertOk()
            ->assertSee('Matematika')
            ->assertSee('Jendela Absensi');

        // 15 menit sebelum mulai -> belum masuk jendela, tampilkan info jadwal akan aktif
        Carbon::setTestNow(Carbon::parse('2026-08-10 08:45:00'));
        $this->actingAs($pengawas)->get(route('pengawas.attendance.index'))
            ->assertOk()
            ->assertSee('akan aktif mulai pukul', false)
            ->assertSee('08:50', false);
    }

    public function test_attendance_reconfirm_allowed_until_tolerance_after_exam_end(): void
    {
        [$room, $pengawas] = $this->supervisorRoom();
        $subject = Subject::factory()->create(['name' => 'Matematika']);
        $schedule = ExamSchedule::factory()->create([
            'room_id' => $room->id,
            'subject_id' => $subject->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => '2026-08-10',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'status' => ExamSchedule::STATUS_FINISHED,
        ]);
        $student = Student::factory()->create(['class_name' => 'XI RPL 1', 'room_id' => $room->id]);
        $session = ExamSession::create([
            'student_id' => $student->id,
            'exam_schedule_id' => $schedule->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'started_at' => now(),
            'attendance_confirmed' => false,
            'violation_flag_1' => true,
        ]);

        // 9 menit setelah ujian selesai (masih dalam toleransi) -> absensi ulang boleh
        Carbon::setTestNow(Carbon::parse('2026-08-10 10:09:00'));
        $this->actingAs($pengawas)
            ->patchJson(route('pengawas.attendance.confirm', $schedule->id), [
                'student_id' => $student->id,
                'confirmed' => true,
            ])->assertOk();

        $this->assertDatabaseHas('exam_sessions', [
            'id' => $session->id,
            'attendance_confirmed' => true,
        ]);

        // 11 menit setelah selesai (lewat toleransi) -> ditolak 404
        Carbon::setTestNow(Carbon::parse('2026-08-10 10:11:00'));
        $this->actingAs($pengawas)
            ->patchJson(route('pengawas.attendance.confirm', $schedule->id), [
                'student_id' => $student->id,
                'confirmed' => true,
            ])->assertStatus(404);
    }

    public function test_attendance_index_still_lists_schedule_within_tolerance_after_end(): void
    {
        [$room, $pengawas] = $this->supervisorRoom();
        $subject = Subject::factory()->create(['name' => 'Fisika']);
        $schedule = ExamSchedule::factory()->create([
            'room_id' => $room->id,
            'subject_id' => $subject->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => '2026-08-10',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'status' => ExamSchedule::STATUS_FINISHED,
        ]);

        // 5 menit setelah selesai -> jadwal masih muncul di halaman absensi
        Carbon::setTestNow(Carbon::parse('2026-08-10 10:05:00'));
        $this->actingAs($pengawas)->get(route('pengawas.attendance.index'))
            ->assertOk()
            ->assertSee('Fisika')
            ->assertSee('absensi ulang peserta yang dinonaktifkan', false);

        // 11 menit setelah selesai -> jadwal tidak lagi muncul
        Carbon::setTestNow(Carbon::parse('2026-08-10 10:11:00'));
        $this->actingAs($pengawas)->get(route('pengawas.attendance.index'))
            ->assertOk()
            ->assertDontSee('Fisika');
    }

    public function test_token_page_available_five_minutes_before_start(): void
    {
        [$room, $pengawas] = $this->supervisorRoom();
        $subject = Subject::factory()->create(['name' => 'Fisika']);
        $schedule = ExamSchedule::factory()->create([
            'room_id' => $room->id,
            'subject_id' => $subject->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => '2026-08-10',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'status' => ExamSchedule::STATUS_SCHEDULED,
        ]);

        // 5 menit sebelum mulai -> halaman token menampilkan jadwal + info token tersedia
        Carbon::setTestNow(Carbon::parse('2026-08-10 08:55:00'));
        $this->actingAs($pengawas)->get(route('pengawas.tokens.index'))
            ->assertOk()
            ->assertSee('Fisika')
            ->assertSee('Token tersedia');

        // 6 menit sebelum mulai -> belum masuk jendela token, tampilkan info
        Carbon::setTestNow(Carbon::parse('2026-08-10 08:54:00'));
        $this->actingAs($pengawas)->get(route('pengawas.tokens.index'))
            ->assertOk()
            ->assertSee('5 menit sebelum ujian dimulai', false)
            ->assertSee('08:55', false);
    }

    public function test_token_generation_blocked_before_window_and_allowed_in_window(): void
    {
        [$room, $pengawas] = $this->supervisorRoom();
        $schedule = ExamSchedule::factory()->create([
            'room_id' => $room->id,
            'exam_date' => '2026-08-10',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'status' => ExamSchedule::STATUS_SCHEDULED,
        ]);

        // 6 menit sebelum mulai -> generate token ditolak
        Carbon::setTestNow(Carbon::parse('2026-08-10 08:54:00'));
        $this->actingAs($pengawas)
            ->post(route('pengawas.tokens.generate', ['schedule' => $schedule->id]))
            ->assertNotFound();

        // 5 menit sebelum mulai -> generate token berhasil
        Carbon::setTestNow(Carbon::parse('2026-08-10 08:55:00'));
        $this->actingAs($pengawas)
            ->post(route('pengawas.tokens.generate', ['schedule' => $schedule->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('exam_tokens', ['exam_schedule_id' => $schedule->id]);
    }

    public function test_admin_exam_schedule_status_filter_uses_computed_status(): void
    {
        $admin = User::factory()->admin()->create();

        // status DB sengaja 'scheduled', padahal sudah lewat end_time -> dihitung finished
        $finished = ExamSchedule::factory()->create([
            'exam_date' => '2026-08-10',
            'start_time' => '07:00:00',
            'end_time' => '08:00:00',
            'status' => ExamSchedule::STATUS_SCHEDULED,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-08-10 09:00:00'));

        $this->actingAs($admin)->get(route('admin.exam-schedules.index', ['status' => ExamSchedule::STATUS_FINISHED]))
            ->assertOk()
            ->assertSee('10 Agustus 2026');

        $this->actingAs($admin)->get(route('admin.exam-schedules.index', ['status' => ExamSchedule::STATUS_SCHEDULED]))
            ->assertOk()
            ->assertDontSee('10 Agustus 2026');
    }

    public function test_peserta_dashboard_badge_uses_computed_status_not_static_column(): void
    {
        $room = Room::factory()->create();
        $student = Student::factory()->create(['class_name' => 'XI RPL 1', 'room_id' => $room->id]);
        $peserta = $student->user;
        $subject = Subject::factory()->create(['name' => 'Biologi']);

        // status DB sengaja 'finished', padahal ujian sedang berlangsung
        ExamSchedule::factory()->create([
            'room_id' => $room->id,
            'subject_id' => $subject->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => '2026-08-10',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'status' => ExamSchedule::STATUS_FINISHED,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-08-10 09:30:00'));

        $this->actingAs($peserta)->get(route('peserta.dashboard'))
            ->assertOk()
            ->assertSee('Biologi')
            ->assertSee('Bisa Dimulai')
            ->assertSee('Masuk Ujian');
    }

    public function test_window_and_computed_status_consistent_using_explicit_wib_timezone(): void
    {
        // Jadwal nyata dari laporan bug: "Pemrograman Web" mulai 11:09, jendela buka 10:59.
        $schedule = $this->makeSchedule('2026-08-10', '11:09:00', '12:09:00');

        // 11 menit sebelum mulai (10:58) -> semua jendela tertutup, masih scheduled
        Carbon::setTestNow(Carbon::parse('2026-08-10 10:58:00', 'Asia/Jakarta'));
        $this->assertFalse($schedule->isAttendanceWindowOpen());
        $this->assertFalse($schedule->isTokenWindowOpen());
        $this->assertSame(ExamSchedule::STATUS_SCHEDULED, $schedule->computedStatus());

        // tepat 10:59 -> jendela absensi terbuka, token belum, masih scheduled
        Carbon::setTestNow(Carbon::parse('2026-08-10 10:59:00', 'Asia/Jakarta'));
        $this->assertTrue($schedule->isAttendanceWindowOpen());
        $this->assertFalse($schedule->isTokenWindowOpen());
        $this->assertSame(ExamSchedule::STATUS_SCHEDULED, $schedule->computedStatus());

        // 11:04 -> jendela absensi & token terbuka (token buka 5 menit = 11:04)
        Carbon::setTestNow(Carbon::parse('2026-08-10 11:04:00', 'Asia/Jakarta'));
        $this->assertTrue($schedule->isAttendanceWindowOpen());
        $this->assertTrue($schedule->isTokenWindowOpen());
        $this->assertSame(ExamSchedule::STATUS_SCHEDULED, $schedule->computedStatus());

        // 11:11 (kasus bug: sudah lewat 10:59 DAN 11:09) -> semua harus aktif
        Carbon::setTestNow(Carbon::parse('2026-08-10 11:11:00', 'Asia/Jakarta'));
        $this->assertTrue($schedule->isAttendanceWindowOpen());
        $this->assertTrue($schedule->isTokenWindowOpen());
        $this->assertSame(ExamSchedule::STATUS_ONGOING, $schedule->computedStatus());
    }

    public function test_attendance_page_no_longer_shows_upcoming_message_when_window_already_open_wib(): void
    {
        [$room, $pengawas] = $this->supervisorRoom();
        $subject = Subject::factory()->create(['name' => 'Pemrograman Web']);
        ExamSchedule::factory()->create([
            'room_id' => $room->id,
            'subject_id' => $subject->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => '2026-08-10',
            'start_time' => '11:09:00',
            'end_time' => '12:09:00',
            'status' => ExamSchedule::STATUS_SCHEDULED,
        ]);

        // now = 11:11 WIB, sudah lewat jendela buka (10:59) dan waktu mulai (11:09)
        Carbon::setTestNow(Carbon::parse('2026-08-10 11:11:00', 'Asia/Jakarta'));

        $this->actingAs($pengawas)->get(route('pengawas.attendance.index'))
            ->assertOk()
            ->assertSee('Pemrograman Web')
            ->assertSee('Sedang Berlangsung')
            ->assertDontSee('akan aktif mulai pukul', false);
    }
}

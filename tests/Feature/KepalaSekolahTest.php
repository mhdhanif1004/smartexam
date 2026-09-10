<?php

namespace Tests\Feature;

use App\Models\ExamResult;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\GuruMapel;
use App\Models\KepalaSekolah;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Supervisor;
use App\Models\SupervisorAttendance;
use App\Models\User;
use App\Models\Violation;
use App\Services\ExamSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KepalaSekolahTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $kepalaSekolahUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->kepalaSekolahUser = $this->createKepalaSekolah('Kepala Sekolah Utama');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);

        parent::tearDown();
    }

    private function createKepalaSekolah(string $name = 'Kepala Sekolah', array $overrides = []): User
    {
        $user = User::factory()->kepalaSekolah()->create(array_merge(['name' => $name], $overrides));

        // Pastikan baris kepala_sekolahs ada (UserFactory hanya buat users)
        if (! KepalaSekolah::where('user_id', $user->id)->exists()) {
            KepalaSekolah::create([
                'user_id' => $user->id,
                'nip' => fake()->numerify('################'),
            ]);
        }

        return $user->fresh();
    }

    // -----------------------------------------------------------------
    // MIDDLEWARE / ROLE ACCESS
    // -----------------------------------------------------------------

    public function test_guest_redirected_to_login_for_kepala_sekolah_dashboard(): void
    {
        $this->get(route('kepala_sekolah.dashboard'))->assertRedirect(route('login'));
        $this->get(route('admin.kepala-sekolahs.index'))->assertRedirect(route('login'));
    }

    public function test_kepala_sekolah_tidak_bisa_akses_route_admin(): void
    {
        $this->actingAs($this->kepalaSekolahUser)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($this->kepalaSekolahUser)->get(route('admin.kepala-sekolahs.index'))->assertForbidden();
        $this->actingAs($this->kepalaSekolahUser)->get(route('admin.kepala-sekolahs.create'))->assertForbidden();
    }

    public function test_admin_tidak_bisa_akses_dashboard_kepala_sekolah(): void
    {
        $this->actingAs($this->admin)->get(route('kepala_sekolah.dashboard'))->assertForbidden();
    }

    public function test_pengawas_tidak_bisa_akses_dashboard_kepala_sekolah(): void
    {
        $pengawas = Supervisor::factory()->create()->user;

        $this->actingAs($pengawas)->get(route('kepala_sekolah.dashboard'))->assertForbidden();
    }

    public function test_guru_mapel_tidak_bisa_akses_dashboard_kepala_sekolah(): void
    {
        $guru = User::factory()->guruMapel()->create();
        // GuruMapel companion tidak wajib untuk gate role, tapi buat agar konsisten
        GuruMapel::factory()->create(['user_id' => $guru->id]);

        $this->actingAs($guru)->get(route('kepala_sekolah.dashboard'))->assertForbidden();
    }

    public function test_peserta_tidak_bisa_akses_dashboard_kepala_sekolah(): void
    {
        $peserta = User::factory()->peserta()->create();

        $this->actingAs($peserta)->get(route('kepala_sekolah.dashboard'))->assertForbidden();
    }

    public function test_kepala_sekolah_bisa_akses_dashboard_sendiri(): void
    {
        $this->actingAs($this->kepalaSekolahUser)
            ->get(route('kepala_sekolah.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard Kepala Sekolah');
    }

    public function test_non_kepala_sekolah_tidak_bisa_crud_kepala_sekolah(): void
    {
        $peserta = User::factory()->peserta()->create();

        $this->actingAs($peserta)->get(route('admin.kepala-sekolahs.index'))->assertForbidden();
        $this->actingAs($this->kepalaSekolahUser)->get(route('admin.kepala-sekolahs.index'))->assertForbidden();
        $this->actingAs($peserta)->post(route('admin.kepala-sekolahs.store'), [])->assertForbidden();
    }

    // -----------------------------------------------------------------
    // CRUD ADMIN
    // -----------------------------------------------------------------

    public function test_admin_bisa_list_kepala_sekolahs(): void
    {
        $ks2 = $this->createKepalaSekolah('Kepala Dua');

        $this->actingAs($this->admin)
            ->get(route('admin.kepala-sekolahs.index'))
            ->assertOk()
            ->assertSee('Data Kepala Sekolah')
            ->assertSee($this->kepalaSekolahUser->name)
            ->assertSee($ks2->name);
    }

    public function test_admin_bisa_view_create_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.kepala-sekolahs.create'))
            ->assertOk()
            ->assertSee('Tambah Kepala Sekolah');
    }

    public function test_admin_bisa_store_kepala_sekolah_dan_role_server_side(): void
    {
        $payload = [
            'name' => 'Dr. Kepala Baru',
            'email' => 'kepala.baru@smartexam.test',
            'nip' => '1234567890123456',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_active' => '1',
            // percobaan injeksi role — harus diabaikan server-side
            'role' => User::ROLE_ADMIN,
        ];

        $this->actingAs($this->admin)
            ->post(route('admin.kepala-sekolahs.store'), $payload)
            ->assertRedirect(route('admin.kepala-sekolahs.index'))
            ->assertSessionHas('success');

        $user = User::where('email', 'kepala.baru@smartexam.test')->firstOrFail();

        $this->assertSame(User::ROLE_KEPALA_SEKOLAH, $user->role);
        $this->assertTrue($user->isKepalaSekolah());
        $this->assertDatabaseHas('kepala_sekolahs', ['user_id' => $user->id, 'nip' => '1234567890123456']);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => User::ROLE_KEPALA_SEKOLAH]);
    }

    public function test_plain_password_terenkripsi_di_database(): void
    {
        $this->actingAs($this->admin)->post(route('admin.kepala-sekolahs.store'), [
            'name' => 'Kepala Enkripsi',
            'email' => 'enkripsi@smartexam.test',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'is_active' => '1',
        ])->assertRedirect(route('admin.kepala-sekolahs.index'));

        $user = User::where('email', 'enkripsi@smartexam.test')->firstOrFail();

        // Lewat model: ter-decrypt otomatis dan sama dengan input
        $this->assertSame('rahasia123', $user->plain_password);
        $this->assertTrue(password_verify('rahasia123', $user->password));

        // Raw di DB harus terenkripsi (tidak sama dengan plaintext)
        $raw = DB::table('users')->where('id', $user->id)->value('plain_password');
        $this->assertNotSame('rahasia123', $raw);
        $this->assertNotEmpty($raw);
    }

    public function test_store_generate_password_dan_email_jika_kosong(): void
    {
        $this->actingAs($this->admin)->post(route('admin.kepala-sekolahs.store'), [
            'name' => 'Kepala Auto Generate',
            'is_active' => '1',
        ])->assertRedirect(route('admin.kepala-sekolahs.index'));

        $user = User::where('name', 'Kepala Auto Generate')->firstOrFail();

        $this->assertNotNull($user->email);
        $this->assertNotNull($user->plain_password);
        $this->assertTrue(strlen($user->plain_password) >= 8);
        $this->assertTrue(password_verify($user->plain_password, $user->password));
        $this->assertSame(User::ROLE_KEPALA_SEKOLAH, $user->role);
    }

    public function test_admin_bisa_edit_kepala_sekolah(): void
    {
        $kepala = KepalaSekolah::where('user_id', $this->kepalaSekolahUser->id)->firstOrFail();

        $this->actingAs($this->admin)
            ->get(route('admin.kepala-sekolahs.edit', $kepala))
            ->assertOk()
            ->assertSee($this->kepalaSekolahUser->name)
            ->assertSee('Edit Kepala Sekolah');
    }

    public function test_admin_bisa_update_tanpa_ganti_password(): void
    {
        $kepala = KepalaSekolah::where('user_id', $this->kepalaSekolahUser->id)->firstOrFail();
        $beforePassword = $this->kepalaSekolahUser->password;
        $beforePlain = $this->kepalaSekolahUser->plain_password;

        $this->actingAs($this->admin)->put(route('admin.kepala-sekolahs.update', $kepala), [
            'name' => 'Nama Kepala Update',
            'email' => $this->kepalaSekolahUser->email,
            'nip' => '99999999',
            'is_active' => '1',
        ])->assertRedirect(route('admin.kepala-sekolahs.index'))
            ->assertSessionHas('success');

        $freshUser = $kepala->fresh()->user->fresh();

        $this->assertSame('Nama Kepala Update', $freshUser->name);
        $this->assertSame('99999999', $kepala->fresh()->nip);
        $this->assertSame($beforePassword, $freshUser->password);
        $this->assertSame($beforePlain, $freshUser->plain_password);
    }

    public function test_admin_bisa_update_dengan_password_baru(): void
    {
        $kepala = KepalaSekolah::where('user_id', $this->kepalaSekolahUser->id)->firstOrFail();

        $this->actingAs($this->admin)->put(route('admin.kepala-sekolahs.update', $kepala), [
            'name' => $this->kepalaSekolahUser->name,
            'email' => $this->kepalaSekolahUser->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
            'is_active' => '1',
        ])->assertRedirect(route('admin.kepala-sekolahs.index'));

        $freshUser = $kepala->fresh()->user->fresh();

        $this->assertSame('newpassword123', $freshUser->plain_password);
        $this->assertTrue(password_verify('newpassword123', $freshUser->password));
    }

    public function test_admin_bisa_delete_kepala_sekolah_dan_user(): void
    {
        $kepala = KepalaSekolah::where('user_id', $this->kepalaSekolahUser->id)->firstOrFail();
        $userId = $this->kepalaSekolahUser->id;

        $this->actingAs($this->admin)
            ->delete(route('admin.kepala-sekolahs.destroy', $kepala))
            ->assertRedirect(route('admin.kepala-sekolahs.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('kepala_sekolahs', ['id' => $kepala->id]);
        $this->assertDatabaseMissing('users', ['id' => $userId]);
    }

    public function test_admin_bisa_bulk_delete_kepala_sekolahs(): void
    {
        $ks2 = $this->createKepalaSekolah('Kepala Dua');
        $ks3 = $this->createKepalaSekolah('Kepala Tiga');

        $ids = KepalaSekolah::whereIn('user_id', [$this->kepalaSekolahUser->id, $ks2->id, $ks3->id])->pluck('id')->all();

        $this->actingAs($this->admin)
            ->post(route('admin.kepala-sekolahs.bulk-delete'), ['ids' => [$ids[0], $ids[1]]])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('kepala_sekolahs', ['id' => $ids[0]]);
        $this->assertDatabaseMissing('kepala_sekolahs', ['id' => $ids[1]]);
        $this->assertDatabaseHas('kepala_sekolahs', ['id' => $ids[2]]);
        // user ikut terhapus
        $this->assertDatabaseMissing('users', ['id' => $this->kepalaSekolahUser->id]);
        $this->assertDatabaseMissing('users', ['id' => $ks2->id]);
    }

    public function test_bulk_delete_wajib_pilih_minimal_satu(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.kepala-sekolahs.bulk-delete'), ['ids' => []])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_plain_password_endpoint_bisa_untuk_kepala_sekolah(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.users.plain-password', $this->kepalaSekolahUser))
            ->assertOk()
            ->assertJson(['plain_password' => $this->kepalaSekolahUser->plain_password]);
    }

    // -----------------------------------------------------------------
    // DASHBOARD KEPALA SEKOLAH
    // -----------------------------------------------------------------

    public function test_dashboard_menampilkan_empat_card_kehadiran(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-26 09:00:00', 'Asia/Jakarta'));
        $today = Carbon::today('Asia/Jakarta')->toDateString();

        $room = Room::factory()->create();
        $subject = Subject::factory()->create();

        $schedule = ExamSchedule::factory()->create([
            'room_id' => $room->id,
            'subject_id' => $subject->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => $today,
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'duration_minutes' => 90,
            'status' => ExamSchedule::STATUS_ONGOING,
        ]);

        // 2 siswa: 1 hadir (confirmed=1), 1 tidak hadir (confirmed=0)
        $siswaHadir = Student::factory()->create(['class_name' => 'XI RPL 1', 'room_id' => $room->id]);
        $siswaAbsen = Student::factory()->create(['class_name' => 'XI RPL 1', 'room_id' => $room->id]);

        ExamSession::factory()->create([
            'student_id' => $siswaHadir->id,
            'exam_schedule_id' => $schedule->id,
            'attendance_confirmed' => true,
            'status' => ExamSession::STATUS_NOT_STARTED,
        ]);
        ExamSession::factory()->create([
            'student_id' => $siswaAbsen->id,
            'exam_schedule_id' => $schedule->id,
            'attendance_confirmed' => false,
            'status' => ExamSession::STATUS_NOT_STARTED,
        ]);

        // Pengawas hadir/tidak hadir hari ini
        $supHadir = Supervisor::factory()->create();
        $supAbsen = Supervisor::factory()->create();

        SupervisorAttendance::factory()->create([
            'supervisor_id' => $supHadir->id,
            'exam_schedule_id' => $schedule->id,
            'room_id' => $room->id,
            'status' => SupervisorAttendance::STATUS_PRESENT,
            'checked_in_at' => Carbon::parse($today.' 08:10:00', 'Asia/Jakarta'),
        ]);
        SupervisorAttendance::factory()->create([
            'supervisor_id' => $supAbsen->id,
            'exam_schedule_id' => $schedule->id,
            'room_id' => $room->id,
            'status' => SupervisorAttendance::STATUS_ABSENT,
            'checked_in_at' => Carbon::parse($today.' 08:10:00', 'Asia/Jakarta'),
        ]);

        $response = $this->actingAs($this->kepalaSekolahUser)->get(route('kepala_sekolah.dashboard'));

        $response->assertOk();
        $response->assertViewHas('studentPresentCount', 1);
        $response->assertViewHas('studentAbsentCount', 1);
        $response->assertViewHas('attendancePresentCount', 1);
        $response->assertViewHas('attendanceAbsentCount', 1);
        $response->assertViewHas('supervisorPresentCount', 1);
        $response->assertViewHas('supervisorAbsentCount', 1);
        $response->assertSee('Hadir Siswa');
        $response->assertSee('Tidak Hadir Siswa');
        $response->assertSee('Hadir Pengawas');
        $response->assertSee('Tidak Hadir Pengawas');
    }

    public function test_dashboard_donut_has_data_false_saat_belum_ada_nilai(): void
    {
        // Pastikan tidak ada ExamResult
        $this->assertDatabaseCount('exam_results', 0);

        $response = $this->actingAs($this->kepalaSekolahUser)->get(route('kepala_sekolah.dashboard'));

        $response->assertOk();
        $response->assertViewHas('hasData', false);
        $response->assertViewHas('average', 0);
        $response->assertViewHas('donutData', [0, 0]);
        $response->assertSee('Belum ada data nilai');
    }

    public function test_dashboard_donut_has_data_true_dengan_average_dan_lulus_tidak_lulus(): void
    {
        // Buat 3 hasil: 2 lulus (80, 90), 1 tidak lulus (50) => avg 73.33
        $scores = [
            ['score' => 80, 'passed' => true],
            ['score' => 90, 'passed' => true],
            ['score' => 50, 'passed' => false],
        ];

        foreach ($scores as $item) {
            $student = Student::factory()->create();
            $schedule = ExamSchedule::factory()->create();
            $session = ExamSession::factory()->create([
                'student_id' => $student->id,
                'exam_schedule_id' => $schedule->id,
                'status' => ExamSession::STATUS_COMPLETED,
            ]);
            ExamResult::factory()->create([
                'exam_session_id' => $session->id,
                'total_score' => $item['score'],
                'is_passed' => $item['passed'],
            ]);
        }

        $response = $this->actingAs($this->kepalaSekolahUser)->get(route('kepala_sekolah.dashboard'));

        $response->assertOk();
        $response->assertViewHas('hasData', true);
        $response->assertViewHas('summary', function (array $summary) {
            return $summary['passed'] === 2 && $summary['failed'] === 1 && $summary['total'] === 3;
        });
        $response->assertViewHas('donutData', [2, 1]);
        $response->assertViewHas('average', 73.33);
        $response->assertViewHas('donutLabels', ['Lulus', 'Tidak Lulus']);
        $response->assertSee('Distribusi Kelulusan');
        $response->assertSee('Rata-rata:');
        // Persentase di view: 66.7% lulus, 33.3% tidak lulus — cek average tampil
        $response->assertSee('73.33');
    }

    public function test_dashboard_violation_pasif_tanpa_polling(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-26 10:00:00'));

        $room = Room::factory()->create();
        $subject = Subject::factory()->create(['name' => 'Matematika']);
        $schedule = ExamSchedule::factory()->create([
            'room_id' => $room->id,
            'subject_id' => $subject->id,
            'exam_date' => now()->toDateString(),
            'status' => ExamSchedule::STATUS_ONGOING,
        ]);
        $student = Student::factory()->create(['class_name' => $schedule->class_name, 'room_id' => $room->id]);
        $session = ExamSession::factory()->create([
            'student_id' => $student->id,
            'exam_schedule_id' => $schedule->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);
        $violation = Violation::factory()->create([
            'exam_session_id' => $session->id,
            'violation_type' => Violation::TYPE_TAB_SWITCH,
            'occurred_at' => now(),
        ]);

        $response = $this->actingAs($this->kepalaSekolahUser)->get(route('kepala_sekolah.dashboard'));

        $response->assertOk();
        $response->assertViewHas('recentViolations', function ($recent) {
            return is_array($recent) && count($recent) === 1;
        });
        $response->assertSee($student->user->name);
        $response->assertSee('Berpindah Tab/Aplikasi Lain');
        // Pastikan tidak ada polling endpoint / badge mencolok di dashboard kepala sekolah
        $response->assertDontSee('violations/polling');
        $response->assertDontSee('unhandled_count');
    }

    public function test_dashboard_violation_kosong_menampilkan_placeholder(): void
    {
        $response = $this->actingAs($this->kepalaSekolahUser)->get(route('kepala_sekolah.dashboard'));

        $response->assertOk();
        $response->assertViewHas('recentViolations', []);
        $response->assertSee('Belum ada pelanggaran');
    }

    public function test_exam_summary_service_reuse_tidak_duplikasi(): void
    {
        // Service harus ada dan dipakai DashboardController (cek file contains)
        $this->assertTrue(class_exists(ExamSummaryService::class));

        $controllerPath = app_path('Http/Controllers/KepalaSekolah/DashboardController.php');
        $content = file_get_contents($controllerPath);
        $this->assertStringContainsString('ExamSummaryService', $content);
        $this->assertStringContainsString('summary(', $content);

        // Verifikasi service menghitung agregat SQL dengan benar (1 query, tanpa N+1)
        $student = Student::factory()->create();
        $schedule = ExamSchedule::factory()->create();
        $session = ExamSession::factory()->create([
            'student_id' => $student->id,
            'exam_schedule_id' => $schedule->id,
        ]);
        ExamResult::factory()->create([
            'exam_session_id' => $session->id,
            'total_score' => 85,
            'is_passed' => true,
        ]);
        ExamResult::factory()->create([
            'exam_session_id' => ExamSession::factory()->create(['student_id' => Student::factory()->create()->id, 'exam_schedule_id' => ExamSchedule::factory()->create()->id])->id,
            'total_score' => 45,
            'is_passed' => false,
        ]);

        $service = new ExamSummaryService;
        $summary = $service->summary(ExamResult::query()->whereNotNull('total_score'));

        $this->assertSame(2, $summary['total']);
        $this->assertSame(2, $summary['scored']);
        $this->assertSame(65.0, $summary['average']);
        $this->assertSame(1, $summary['passed']);
        $this->assertSame(1, $summary['failed']);

        // Dashboard juga harus konsisten dengan service (reuse, bukan duplikasi logika)
        $response = $this->actingAs($this->kepalaSekolahUser)->get(route('kepala_sekolah.dashboard'));
        $response->assertViewHas('summary', $summary);
    }

    public function test_role_constant_kepala_sekolah_tersedia(): void
    {
        $this->assertSame('kepala_sekolah', User::ROLE_KEPALA_SEKOLAH);
        $user = User::factory()->kepalaSekolah()->create();
        $this->assertSame(User::ROLE_KEPALA_SEKOLAH, $user->role);
        $this->assertTrue($user->isKepalaSekolah());
    }
}

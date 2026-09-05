<?php

namespace Tests\Feature;

use App\Models\KepalaSekolah;
use App\Models\Supervisor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KepalaSekolahReadOnlyPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $kepalaSekolahUser;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->kepalaSekolahUser = $this->createKepalaSekolah('Kepala Sekolah Utama');
    }

    private function createKepalaSekolah(string $name = 'Kepala Sekolah', array $overrides = []): User
    {
        $user = User::factory()->kepalaSekolah()->create(array_merge(['name' => $name], $overrides));

        if (! KepalaSekolah::where('user_id', $user->id)->exists()) {
            KepalaSekolah::create([
                'user_id' => $user->id,
                'nip' => fake()->numerify('################'),
            ]);
        }

        return $user->fresh();
    }

    // -----------------------------------------------------------------
    // kepala_sekolah boleh akses 200 + lihat judul + tanpa tombol mutasi
    // -----------------------------------------------------------------

    public function test_kepala_sekolah_bisa_akses_data_siswa(): void
    {
        $this->actingAs($this->kepalaSekolahUser)
            ->get(route('kepala_sekolah.students.index'))
            ->assertOk()
            ->assertSee('Data Siswa')
            ->assertDontSee('Tambah Siswa')
            ->assertDontSee('Impor')
            ->assertDontSee('Hapus Terpilih')
            ->assertDontSee('Aksi');
    }

    public function test_kepala_sekolah_bisa_akses_data_pengawas(): void
    {
        $this->actingAs($this->kepalaSekolahUser)
            ->get(route('kepala_sekolah.supervisors.index'))
            ->assertOk()
            ->assertSee('Data Pengawas')
            ->assertDontSee('Tambah Pengawas')
            ->assertDontSee('Impor')
            ->assertDontSee('Hapus Terpilih')
            ->assertDontSee('Aksi');
    }

    public function test_kepala_sekolah_bisa_akses_data_guru_mapel(): void
    {
        $this->actingAs($this->kepalaSekolahUser)
            ->get(route('kepala_sekolah.guru-mapels.index'))
            ->assertOk()
            ->assertSee('Data Guru Mapel')
            ->assertDontSee('Tambah Guru Mapel')
            ->assertDontSee('Impor')
            ->assertDontSee('Hapus Terpilih')
            ->assertDontSee('Aksi');
    }

    public function test_kepala_sekolah_bisa_akses_absensi(): void
    {
        $this->actingAs($this->kepalaSekolahUser)
            ->get(route('kepala_sekolah.attendance.index'))
            ->assertOk()
            ->assertSee('Absensi Ujian')
            ->assertSee('Ringkasan kehadiran')
            ->assertSee('Tanggal Ujian')
            ->assertDontSee('Tambah')
            ->assertDontSee('Impor')
            ->assertDontSee('bulk-delete');
    }

    public function test_kepala_sekolah_bisa_akses_absensi_summary_json(): void
    {
        $this->actingAs($this->kepalaSekolahUser)
            ->getJson(route('kepala_sekolah.attendance.summary'))
            ->assertOk()
            ->assertJsonStructure(['totals' => ['present', 'absent', 'supervisorPresent', 'supervisorAbsent'], 'periods']);
    }

    // -----------------------------------------------------------------
    // Guest diarahkan ke login
    // -----------------------------------------------------------------

    public function test_guest_diarahkan_ke_login_untuk_halaman_readonly(): void
    {
        $this->get(route('kepala_sekolah.students.index'))->assertRedirect(route('login'));
        $this->get(route('kepala_sekolah.supervisors.index'))->assertRedirect(route('login'));
        $this->get(route('kepala_sekolah.guru-mapels.index'))->assertRedirect(route('login'));
        $this->get(route('kepala_sekolah.attendance.index'))->assertRedirect(route('login'));
        $this->get(route('kepala_sekolah.attendance.summary'))->assertRedirect(route('login'));
    }

    // -----------------------------------------------------------------
    // Role lain 403 (admin, pengawas, guru_mapel, peserta)
    // -----------------------------------------------------------------

    public function test_admin_tidak_bisa_akses_halaman_readonly_kepala_sekolah(): void
    {
        $this->actingAs($this->admin)->get(route('kepala_sekolah.students.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('kepala_sekolah.supervisors.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('kepala_sekolah.guru-mapels.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('kepala_sekolah.attendance.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('kepala_sekolah.attendance.summary'))->assertForbidden();
    }

    public function test_pengawas_tidak_bisa_akses_halaman_readonly(): void
    {
        $pengawas = Supervisor::factory()->create()->user;

        $this->actingAs($pengawas)->get(route('kepala_sekolah.students.index'))->assertForbidden();
        $this->actingAs($pengawas)->get(route('kepala_sekolah.supervisors.index'))->assertForbidden();
        $this->actingAs($pengawas)->get(route('kepala_sekolah.guru-mapels.index'))->assertForbidden();
        $this->actingAs($pengawas)->get(route('kepala_sekolah.attendance.index'))->assertForbidden();
    }

    public function test_guru_mapel_tidak_bisa_akses_halaman_readonly(): void
    {
        $guru = User::factory()->guruMapel()->create();
        \App\Models\GuruMapel::factory()->create(['user_id' => $guru->id]);

        $this->actingAs($guru)->get(route('kepala_sekolah.students.index'))->assertForbidden();
        $this->actingAs($guru)->get(route('kepala_sekolah.supervisors.index'))->assertForbidden();
        $this->actingAs($guru)->get(route('kepala_sekolah.guru-mapels.index'))->assertForbidden();
        $this->actingAs($guru)->get(route('kepala_sekolah.attendance.index'))->assertForbidden();
    }

    public function test_peserta_tidak_bisa_akses_halaman_readonly(): void
    {
        $peserta = User::factory()->peserta()->create();

        $this->actingAs($peserta)->get(route('kepala_sekolah.students.index'))->assertForbidden();
        $this->actingAs($peserta)->get(route('kepala_sekolah.supervisors.index'))->assertForbidden();
        $this->actingAs($peserta)->get(route('kepala_sekolah.guru-mapels.index'))->assertForbidden();
        $this->actingAs($peserta)->get(route('kepala_sekolah.attendance.index'))->assertForbidden();
    }

    // -----------------------------------------------------------------
    // Hanya GET yang diizinkan
    // -----------------------------------------------------------------

    public function test_halaman_readonly_hanya_get(): void
    {
        $this->actingAs($this->kepalaSekolahUser)->post(route('kepala_sekolah.students.index'))->assertStatus(405);
        $this->actingAs($this->kepalaSekolahUser)->post(route('kepala_sekolah.supervisors.index'))->assertStatus(405);
        $this->actingAs($this->kepalaSekolahUser)->post(route('kepala_sekolah.guru-mapels.index'))->assertStatus(405);
        $this->actingAs($this->kepalaSekolahUser)->post(route('kepala_sekolah.attendance.index'))->assertStatus(405);
        $this->actingAs($this->kepalaSekolahUser)->post(route('kepala_sekolah.attendance.summary'))->assertStatus(405);
    }

    // -----------------------------------------------------------------
    // Dashboard card sudah ditautkan
    // -----------------------------------------------------------------

    public function test_dashboard_card_tertaut_ke_halaman_readonly(): void
    {
        $response = $this->actingAs($this->kepalaSekolahUser)->get(route('kepala_sekolah.dashboard'));

        $response->assertOk();
        $response->assertSee(route('kepala_sekolah.students.index'), false);
        $response->assertSee(route('kepala_sekolah.supervisors.index'), false);
        $response->assertSee(route('kepala_sekolah.guru-mapels.index'), false);
    }
}

<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Semester;
use App\Models\User;
use App\Models\WaliKelas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaliKelasSessionSemesterTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroom;

    private WaliKelas $wali;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classroom = Classroom::factory()->create(['name' => 'XI RPL 1']);
        $this->wali = WaliKelas::factory()->create(['classroom_id' => $this->classroom->id]);
    }

    private function makeSemester(string $jenis, bool $isActive = false): Semester
    {
        return Semester::factory()->create([
            'academic_year_id' => AcademicYear::factory()->create()->id,
            'jenis' => $jenis,
            'is_active' => $isActive,
        ]);
    }

    // ── POST set-semester ─────────────────────────────────────────────

    public function test_set_semester_persists_to_session_and_redirects_back(): void
    {
        $semester = $this->makeSemester('ganjil', true);
        $pageUrl = route('wali_kelas.dashboard');

        $response = $this->actingAs($this->wali->user)
            ->from($pageUrl)
            ->post(route('wali_kelas.set-semester'), ['semester_id' => $semester->id]);

        $response->assertRedirect($pageUrl);

        $this->assertSame($semester->id, session('wali_kelas_semester_id'));
    }

    public function test_set_semester_rejects_invalid_id(): void
    {
        $response = $this->actingAs($this->wali->user)
            ->from(route('wali_kelas.dashboard'))
            ->post(route('wali_kelas.set-semester'), ['semester_id' => 9999]);

        $response->assertSessionHasErrors('semester_id');
    }

    public function test_set_semester_redirects_back_to_non_dashboard_page(): void
    {
        $semester = $this->makeSemester('ganjil', true);
        $pageUrl = route('wali_kelas.catatan');

        $response = $this->actingAs($this->wali->user)
            ->from($pageUrl)
            ->post(route('wali_kelas.set-semester'), ['semester_id' => $semester->id]);

        $response->assertRedirect($pageUrl);
    }

    // ── Konsistensi lintas halaman ────────────────────────────────────

    public function test_semester_change_on_one_page_affects_another_page(): void
    {
        $s1 = $this->makeSemester('ganjil', true);
        $s2 = $this->makeSemester('genap', false);

        // Ganti ke s2 dari halaman catatan (redirect back ke catatan).
        $this->actingAs($this->wali->user)
            ->from(route('wali_kelas.catatan'))
            ->post(route('wali_kelas.set-semester'), ['semester_id' => $s2->id])
            ->assertRedirect(route('wali_kelas.catatan'));

        // Halaman lain sekarang harus memakai s2 (session tidak reset).
        $this->actingAs($this->wali->user)
            ->get(route('wali_kelas.nilai-sikap'))
            ->assertOk();

        $this->assertSame($s2->id, session('wali_kelas_semester_id'));
    }

    public function test_session_semester_persists_across_all_pages(): void
    {
        $s1 = $this->makeSemester('ganjil', true);

        foreach (['wali_kelas.dashboard', 'wali_kelas.nilai-akademik', 'wali_kelas.nilai-sikap', 'wali_kelas.pelanggaran', 'wali_kelas.catatan'] as $route) {
            $this->actingAs($this->wali->user)
                ->withSession(['wali_kelas_semester_id' => $s1->id])
                ->get(route($route))
                ->assertOk();

            $this->assertSame($s1->id, session('wali_kelas_semester_id'));
        }
    }

    // ── Fallback session kosong / tidak valid ─────────────────────────

    public function test_fallback_to_active_semester_when_session_empty(): void
    {
        $active = $this->makeSemester('genap', true);

        $this->actingAs($this->wali->user)
            ->get(route('wali_kelas.catatan'))
            ->assertOk();

        $this->assertSame($active->id, session('wali_kelas_semester_id'));
    }

    public function test_fallback_replaces_deleted_semester_in_session(): void
    {
        $deleted = $this->makeSemester('ganjil', false);
        $active = $this->makeSemester('genap', true);
        $deleted->delete();

        $this->actingAs($this->wali->user)
            ->withSession(['wali_kelas_semester_id' => $deleted->id])
            ->get(route('wali_kelas.catatan'))
            ->assertOk();

        $this->assertSame($active->id, session('wali_kelas_semester_id'));
    }

    // ── Otorisasi route baru ──────────────────────────────────────────

    public function test_non_wali_kelas_cannot_access_new_routes(): void
    {
        $user = User::factory()->create(['role' => 'peserta']);

        $this->actingAs($user)
            ->get(route('wali_kelas.nilai-akademik'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('wali_kelas.nilai-sikap'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('wali_kelas.pelanggaran'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('wali_kelas.catatan'))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('wali_kelas.set-semester'), ['semester_id' => 1])
            ->assertForbidden();
    }

    public function test_wali_kelas_can_access_all_new_pages(): void
    {
        $active = $this->makeSemester('genap', true);

        $this->actingAs($this->wali->user)
            ->get(route('wali_kelas.nilai-akademik'))
            ->assertOk();

        $this->actingAs($this->wali->user)
            ->get(route('wali_kelas.nilai-sikap'))
            ->assertOk();

        $this->actingAs($this->wali->user)
            ->get(route('wali_kelas.pelanggaran'))
            ->assertOk();

        $this->actingAs($this->wali->user)
            ->get(route('wali_kelas.catatan'))
            ->assertOk();

        $this->assertSame($active->id, session('wali_kelas_semester_id'));
    }
}

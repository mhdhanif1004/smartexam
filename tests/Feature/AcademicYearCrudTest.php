<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicYearCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    // ── Akses & Otorisasi ─────────────────────────────────────────────

    public function test_non_admin_cannot_access_academic_year_index(): void
    {
        $user = User::factory()->create(['role' => 'peserta']);

        $this->actingAs($user)
            ->get(route('admin.academic-years.index'))
            ->assertForbidden();
    }

    public function test_admin_can_access_academic_year_index(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.academic-years.index'))
            ->assertOk();
    }

    // ── CRUD Operations ───────────────────────────────────────────────

    public function test_admin_can_create_academic_year(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.academic-years.store'), [
                'nama' => '2025/2026',
                'tanggal_mulai' => '2025-07-01',
                'tanggal_selesai' => '2026-06-30',
            ]);

        $response->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('academic_years', [
            'nama' => '2025/2026',
            'tanggal_mulai' => '2025-07-01',
            'tanggal_selesai' => '2026-06-30',
            'is_active' => false,
        ]);
    }

    public function test_admin_can_update_academic_year(): void
    {
        $academicYear = AcademicYear::factory()->create(['nama' => '2024/2025']);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.academic-years.update', $academicYear), [
                'nama' => '2025/2026',
                'tanggal_mulai' => null,
                'tanggal_selesai' => null,
            ]);

        $response->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('academic_years', [
            'id' => $academicYear->id,
            'nama' => '2025/2026',
        ]);
    }

    public function test_admin_can_delete_academic_year_without_semesters(): void
    {
        $academicYear = AcademicYear::factory()->create(['nama' => '2024/2025']);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.academic-years.destroy', $academicYear));

        $response->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseMissing('academic_years', ['id' => $academicYear->id]);
    }

    public function test_destroy_rejected_when_academic_year_has_semesters(): void
    {
        $academicYear = AcademicYear::factory()->create(['nama' => '2024/2025']);
        Semester::factory()->ganjil($academicYear)->create();

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.academic-years.destroy', $academicYear));

        $response->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseHas('academic_years', ['id' => $academicYear->id]);
    }

    // ── Validasi ──────────────────────────────────────────────────────

    public function test_store_requires_unique_nama(): void
    {
        AcademicYear::factory()->create(['nama' => '2025/2026']);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.academic-years.store'), [
                'nama' => '2025/2026',
            ]);

        $response->assertSessionHasErrors('nama');
    }

    public function test_update_requires_unique_nama_ignoring_self(): void
    {
        $academicYear = AcademicYear::factory()->create(['nama' => '2025/2026']);

        // Update nama tetap sama (ignore self) — harus sukses
        $response = $this->actingAs($this->admin)
            ->put(route('admin.academic-years.update', $academicYear), [
                'nama' => '2025/2026',
            ]);

        $response->assertRedirect()->assertSessionHas('success');
    }

    public function test_store_rejects_tanggal_selesai_before_mulai(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.academic-years.store'), [
                'nama' => '2025/2026',
                'tanggal_mulai' => '2026-06-30',
                'tanggal_selesai' => '2025-07-01',
            ]);

        $response->assertSessionHasErrors('tanggal_selesai');
    }
}

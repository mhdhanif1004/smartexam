<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SemesterCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    // ── Akses & Otorisasi ─────────────────────────────────────────────

    public function test_non_admin_cannot_access_semester_index(): void
    {
        $user = User::factory()->create(['role' => 'peserta']);

        $this->actingAs($user)
            ->get(route('admin.semesters.index'))
            ->assertForbidden();
    }

    public function test_admin_can_access_semester_index(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.semesters.index'))
            ->assertOk();
    }

    // ── CRUD Operations ───────────────────────────────────────────────

    public function test_admin_can_create_semester(): void
    {
        $tahunAjaran = AcademicYear::factory()->create(['nama' => '2025/2026']);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.semesters.store'), [
                'academic_year_id' => $tahunAjaran->id,
                'jenis' => 'ganjil',
            ]);

        $response->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('semesters', [
            'academic_year_id' => $tahunAjaran->id,
            'jenis' => 'ganjil',
            'is_active' => false,
        ]);
    }

    public function test_admin_can_update_semester(): void
    {
        $tahunAjaran = AcademicYear::factory()->create(['nama' => '2024/2025']);
        $semester = Semester::create(['academic_year_id' => $tahunAjaran->id, 'jenis' => 'ganjil', 'is_active' => false]);

        $tahunAjaranBaru = AcademicYear::factory()->create(['nama' => '2025/2026']);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.semesters.update', $semester), [
                'academic_year_id' => $tahunAjaranBaru->id,
                'jenis' => 'genap',
            ]);

        $response->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('semesters', [
            'id' => $semester->id,
            'academic_year_id' => $tahunAjaranBaru->id,
            'jenis' => 'genap',
        ]);
    }

    public function test_admin_can_delete_semester(): void
    {
        $tahunAjaran = AcademicYear::factory()->create(['nama' => '2024/2025']);
        $semester = Semester::create(['academic_year_id' => $tahunAjaran->id, 'jenis' => 'ganjil', 'is_active' => false]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.semesters.destroy', $semester));

        $response->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseMissing('semesters', ['id' => $semester->id]);
    }

    // ── Duplikat (academic_year_id, jenis) ────────────────────────────

    public function test_store_rejects_duplicate_jenis_in_same_academic_year(): void
    {
        $tahunAjaran = AcademicYear::factory()->create(['nama' => '2024/2025']);
        Semester::create(['academic_year_id' => $tahunAjaran->id, 'jenis' => 'ganjil', 'is_active' => false]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.semesters.store'), [
                'academic_year_id' => $tahunAjaran->id,
                'jenis' => 'ganjil',
            ]);

        $response->assertSessionHasErrors('jenis');
        $this->assertDatabaseCount('semesters', 1);
    }

    public function test_store_allows_same_jenis_in_different_academic_year(): void
    {
        $tahunAjaranA = AcademicYear::factory()->create(['nama' => '2024/2025']);
        $tahunAjaranB = AcademicYear::factory()->create(['nama' => '2025/2026']);
        Semester::create(['academic_year_id' => $tahunAjaranA->id, 'jenis' => 'ganjil', 'is_active' => false]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.semesters.store'), [
                'academic_year_id' => $tahunAjaranB->id,
                'jenis' => 'ganjil',
            ]);

        $response->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseCount('semesters', 2);
    }

    // ── Make Active ───────────────────────────────────────────────────

    public function test_admin_can_make_semester_active(): void
    {
        $tahunAjaran = AcademicYear::factory()->create(['nama' => '2024/2025']);
        $old = Semester::create(['academic_year_id' => $tahunAjaran->id, 'jenis' => 'ganjil', 'is_active' => true]);
        $new = Semester::create(['academic_year_id' => $tahunAjaran->id, 'jenis' => 'genap', 'is_active' => false]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.semesters.make-active', $new));

        $response->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('semesters', ['id' => $new->id, 'is_active' => true]);
        $this->assertDatabaseHas('semesters', ['id' => $old->id, 'is_active' => false]);
    }

    public function test_only_one_semester_can_be_active(): void
    {
        $tahunAjaranA = AcademicYear::factory()->create(['nama' => '2024/2025']);
        $tahunAjaranB = AcademicYear::factory()->create(['nama' => '2025/2026']);

        $s1 = Semester::create(['academic_year_id' => $tahunAjaranA->id, 'jenis' => 'ganjil', 'is_active' => true]);
        $s2 = Semester::create(['academic_year_id' => $tahunAjaranA->id, 'jenis' => 'genap', 'is_active' => false]);
        $s3 = Semester::create(['academic_year_id' => $tahunAjaranB->id, 'jenis' => 'ganjil', 'is_active' => false]);

        // Aktifkan s3
        $this->actingAs($this->admin)
            ->post(route('admin.semesters.make-active', $s3));

        $this->assertDatabaseHas('semesters', ['id' => $s3->id, 'is_active' => true]);
        $this->assertDatabaseHas('semesters', ['id' => $s1->id, 'is_active' => false]);
        $this->assertDatabaseHas('semesters', ['id' => $s2->id, 'is_active' => false]);
    }

    // ── Validasi ──────────────────────────────────────────────────────

    public function test_store_requires_academic_year_id_and_jenis(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.semesters.store'), []);

        $response->assertSessionHasErrors(['academic_year_id', 'jenis']);
    }

    public function test_store_requires_valid_jenis_value(): void
    {
        $tahunAjaran = AcademicYear::factory()->create(['nama' => '2024/2025']);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.semesters.store'), [
                'academic_year_id' => $tahunAjaran->id,
                'jenis' => 'tengah',
            ]);

        $response->assertSessionHasErrors('jenis');
    }

    public function test_store_rejects_invalid_academic_year(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.semesters.store'), [
                'academic_year_id' => 999,
                'jenis' => 'ganjil',
            ]);

        $response->assertSessionHasErrors('academic_year_id');
    }
}

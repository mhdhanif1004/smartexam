<?php

namespace Tests\Feature;

use App\Models\ExamPeriod;
use App\Models\ExamType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamTypeToggleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    // ── Migration: default value ─────────────────────────────────────

    public function test_migration_sets_harian_and_uts_boleh_dijadwalkan_guru(): void
    {
        $harian = ExamType::where('code', 'harian')->first();
        $uts = ExamType::where('code', 'uts')->first();
        $uas = ExamType::where('code', 'uas')->first();

        $this->assertTrue((bool) $harian->boleh_dijadwalkan_guru);
        $this->assertTrue((bool) $uts->boleh_dijadwalkan_guru);
        $this->assertFalse((bool) $uas->boleh_dijadwalkan_guru);
    }

    public function test_new_exam_type_defaults_to_false(): void
    {
        $type = ExamType::create(['name' => 'Susulan', 'code' => 'susulan', 'sort_order' => 5]);

        $this->assertFalse((bool) $type->boleh_dijadwalkan_guru);
    }

    // ── Admin CRUD ───────────────────────────────────────────────────

    public function test_admin_can_view_exam_types_index(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.exam-types.index'))
            ->assertOk()
            ->assertSee('Jenis Ujian');
    }

    public function test_admin_can_create_exam_type_with_toggle(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.exam-types.store'), [
                'name' => 'UTS Susulan',
                'code' => 'uts-susulan',
                'sort_order' => 5,
                'boleh_dijadwalkan_guru' => true,
            ]);

        $response->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('exam_types', [
            'code' => 'uts-susulan',
            'boleh_dijadwalkan_guru' => true,
        ]);
    }

    public function test_admin_can_update_toggle(): void
    {
        $type = ExamType::create(['name' => 'UAS Susulan', 'code' => 'uas-susulan', 'sort_order' => 6, 'boleh_dijadwalkan_guru' => false]);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.exam-types.update', $type), [
                'name' => 'Ujian Akhir',
                'code' => 'uas-susulan',
                'sort_order' => 6,
                'boleh_dijadwalkan_guru' => true,
            ]);

        $response->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('exam_types', [
            'id' => $type->id,
            'boleh_dijadwalkan_guru' => true,
        ]);
    }

    public function test_destroy_rejected_when_exam_type_has_periods(): void
    {
        $type = ExamType::create(['name' => 'Harian Khusus', 'code' => 'harian-khusus', 'sort_order' => 9]);
        // Simulate existing usage by creating a period with exam_type_id
        ExamPeriod::factory()->create(['exam_type_id' => $type->id, 'exam_date' => '2026-12-01']);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.exam-types.destroy', $type));

        $response->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseHas('exam_types', ['id' => $type->id]);
    }

    public function test_store_rejects_duplicate_code(): void
    {
        // 'baru' tidak bentrok dengan kode existing (harian/uts/uas/kehadiran).
        ExamType::create(['name' => 'Baru', 'code' => 'baru', 'sort_order' => 10]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.exam-types.store'), [
                'name' => 'Baru Lain',
                'code' => 'baru',
                'boleh_dijadwalkan_guru' => false,
            ]);

        $response->assertSessionHasErrors('code');
    }

    // ── Akses ────────────────────────────────────────────────────────

    public function test_non_admin_cannot_access(): void
    {
        $user = User::factory()->create(['role' => 'peserta']);

        $this->actingAs($user)
            ->get(route('admin.exam-types.index'))
            ->assertForbidden();
    }
}

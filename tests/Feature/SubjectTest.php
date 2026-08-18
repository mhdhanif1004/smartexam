<?php

namespace Tests\Feature;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_store_requires_code_name_and_duration(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.subjects.store'), [
                'name' => 'Matematika',
                'default_duration_minutes' => 90,
            ])
            ->assertSessionHasErrors('code');

        $this->actingAs($this->admin)
            ->post(route('admin.subjects.store'), [
                'code' => 'MTK',
                'default_duration_minutes' => 90,
            ])
            ->assertSessionHasErrors('name');

        $this->actingAs($this->admin)
            ->post(route('admin.subjects.store'), [
                'code' => 'MTK',
                'name' => 'Matematika',
            ])
            ->assertSessionHasErrors('default_duration_minutes');

        $this->assertDatabaseCount('subjects', 0);
    }

    public function test_duplicate_code_is_rejected(): void
    {
        Subject::factory()->create(['code' => 'MTK']);

        $this->actingAs($this->admin)
            ->post(route('admin.subjects.store'), [
                'code' => 'MTK',
                'name' => 'Matematika Lanjutan',
                'default_duration_minutes' => 90,
            ])
            ->assertSessionHasErrors('code');

        $this->assertSame(1, Subject::query()->where('code', 'MTK')->count());
    }

    public function test_update_ignores_its_own_row_for_unique_code(): void
    {
        $subject = Subject::factory()->create(['code' => 'MTK']);

        $this->actingAs($this->admin)
            ->put(route('admin.subjects.update', $subject), [
                'code' => 'MTK',
                'name' => 'Matematika Lanjutan',
                'default_duration_minutes' => 90,
            ])
            ->assertRedirect(route('admin.subjects.index'));

        $this->assertDatabaseHas('subjects', ['code' => 'MTK', 'name' => 'Matematika Lanjutan']);
    }

    public function test_store_ignores_legacy_class_label_field(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.subjects.store'), [
                'code' => 'MTK',
                'name' => 'Matematika',
                'class_label' => 'XI RPL 1',
                'default_duration_minutes' => 90,
            ])
            ->assertRedirect(route('admin.subjects.index'));

        $this->assertDatabaseHas('subjects', ['code' => 'MTK', 'name' => 'Matematika']);
        $this->assertDatabaseCount('subjects', 1);
    }

    public function test_index_lists_subjects_and_supports_search(): void
    {
        $subject = Subject::factory()->create(['name' => 'Matematika', 'code' => 'MTK']);

        $this->actingAs($this->admin)
            ->get(route('admin.subjects.index'))
            ->assertOk()
            ->assertSee('Matematika');

        $this->actingAs($this->admin)
            ->get(route('admin.subjects.index', ['search' => 'MTK']))
            ->assertOk()
            ->assertSee($subject->name);
    }
}

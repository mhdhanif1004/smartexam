<?php

namespace Tests\Feature;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectClassLabelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_store_requires_class_label(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.subjects.store'), [
                'code' => 'MTK',
                'name' => 'Matematika',
                'default_duration_minutes' => 90,
            ])
            ->assertSessionHasErrors('class_label');

        $this->assertDatabaseMissing('subjects', ['code' => 'MTK']);
    }

    public function test_same_code_allowed_for_different_class_labels(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.subjects.store'), [
                'code' => 'MTK',
                'name' => 'Matematika',
                'class_label' => 'X',
                'default_duration_minutes' => 90,
            ])
            ->assertRedirect(route('admin.subjects.index'));

        $this->actingAs($this->admin)
            ->post(route('admin.subjects.store'), [
                'code' => 'MTK',
                'name' => 'Matematika',
                'class_label' => 'XI',
                'default_duration_minutes' => 90,
            ])
            ->assertRedirect(route('admin.subjects.index'));

        $this->assertDatabaseHas('subjects', ['code' => 'MTK', 'class_label' => 'X']);
        $this->assertDatabaseHas('subjects', ['code' => 'MTK', 'class_label' => 'XI']);
        $this->assertSame(2, Subject::query()->where('code', 'MTK')->count());
    }

    public function test_same_code_with_same_class_label_is_rejected(): void
    {
        Subject::factory()->create(['code' => 'MTK', 'class_label' => 'X']);

        $this->actingAs($this->admin)
            ->post(route('admin.subjects.store'), [
                'code' => 'MTK',
                'name' => 'Matematika',
                'class_label' => 'X',
                'default_duration_minutes' => 90,
            ])
            ->assertSessionHasErrors('code');

        $this->assertSame(1, Subject::query()->where('code', 'MTK')->count());
    }

    public function test_update_ignores_its_own_row_for_composite_unique(): void
    {
        $subject = Subject::factory()->create(['code' => 'MTK', 'class_label' => 'X']);

        $this->actingAs($this->admin)
            ->put(route('admin.subjects.update', $subject), [
                'code' => 'MTK',
                'name' => 'Matematika Lanjutan',
                'class_label' => 'X',
                'default_duration_minutes' => 90,
            ])
            ->assertRedirect(route('admin.subjects.index'));

        $this->assertDatabaseHas('subjects', ['code' => 'MTK', 'class_label' => 'X', 'name' => 'Matematika Lanjutan']);
    }

    public function test_index_lists_class_label_column_and_supports_search(): void
    {
        $subject = Subject::factory()->create(['name' => 'Matematika', 'class_label' => 'XI RPL 1']);

        $this->actingAs($this->admin)
            ->get(route('admin.subjects.index'))
            ->assertOk()
            ->assertSee('Kelas')
            ->assertSee($subject->class_label);

        $this->actingAs($this->admin)
            ->get(route('admin.subjects.index', ['search' => 'XI RPL']))
            ->assertOk()
            ->assertSee($subject->name);
    }
}

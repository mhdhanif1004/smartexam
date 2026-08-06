<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassroomCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_can_view_classroom_index_with_student_count(): void
    {
        Classroom::create(['name' => 'XI RPL 1']);
        Student::factory()->count(2)->create(['class_name' => 'XI RPL 1']);

        $this->actingAs($this->admin)
            ->get(route('admin.classrooms.index'))
            ->assertOk()
            ->assertSee('Master Data Kelas')
            ->assertSee('XI RPL 1')
            ->assertSee('2 siswa');
    }

    public function test_admin_can_search_classroom_by_name(): void
    {
        Classroom::create(['name' => 'XI RPL 1']);
        Classroom::create(['name' => 'XII TKJ 1']);

        $this->actingAs($this->admin)
            ->get(route('admin.classrooms.index', ['search' => 'RPL']))
            ->assertOk()
            ->assertSee('XI RPL 1')
            ->assertDontSee('XII TKJ 1');
    }

    public function test_admin_can_view_create_and_edit_page(): void
    {
        $classroom = Classroom::create(['name' => 'XI RPL 1']);

        $this->actingAs($this->admin)->get(route('admin.classrooms.create'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.classrooms.edit', $classroom))->assertOk()->assertSee('XI RPL 1');
    }

    public function test_admin_can_create_classroom(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.classrooms.store'), ['name' => 'XI RPL 1'])
            ->assertRedirect(route('admin.classrooms.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('classes', ['name' => 'XI RPL 1']);
    }

    public function test_classroom_name_is_required_and_unique(): void
    {
        Classroom::create(['name' => 'XI RPL 1']);

        $this->actingAs($this->admin)
            ->from(route('admin.classrooms.create'))
            ->post(route('admin.classrooms.store'), ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->actingAs($this->admin)
            ->from(route('admin.classrooms.create'))
            ->post(route('admin.classrooms.store'), ['name' => 'XI RPL 1'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Classroom::count());
    }

    public function test_admin_can_update_classroom_name(): void
    {
        $classroom = Classroom::create(['name' => 'XI RPL 1']);

        $this->actingAs($this->admin)
            ->put(route('admin.classrooms.update', $classroom), ['name' => 'XI RPL 1A'])
            ->assertRedirect(route('admin.classrooms.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('classes', ['name' => 'XI RPL 1A']);
        $this->assertDatabaseMissing('classes', ['name' => 'XI RPL 1']);
    }

    public function test_updating_classroom_renames_connected_students(): void
    {
        $classroom = Classroom::create(['name' => 'XI RPL 1']);
        Student::factory()->create(['class_name' => 'XI RPL 1']);

        $this->actingAs($this->admin)
            ->put(route('admin.classrooms.update', $classroom), ['name' => 'XI RPL 1A'])
            ->assertRedirect(route('admin.classrooms.index'));

        $this->assertSame(0, Student::query()->where('class_name', 'XI RPL 1')->count());
        $this->assertSame(1, Student::query()->where('class_name', 'XI RPL 1A')->count());
    }

    public function test_updating_classroom_keeps_unique_rule_ignoring_self(): void
    {
        $classroom = Classroom::create(['name' => 'XI RPL 1']);

        $this->actingAs($this->admin)
            ->put(route('admin.classrooms.update', $classroom), ['name' => 'XI RPL 1'])
            ->assertRedirect(route('admin.classrooms.index'));

        $this->assertDatabaseHas('classes', ['name' => 'XI RPL 1']);
    }

    public function test_admin_can_delete_classroom_without_students(): void
    {
        $classroom = Classroom::create(['name' => 'XI MM 1']);

        $this->actingAs($this->admin)
            ->delete(route('admin.classrooms.destroy', $classroom))
            ->assertRedirect(route('admin.classrooms.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('classes', ['id' => $classroom->id]);
    }

    public function test_classroom_with_students_cannot_be_deleted(): void
    {
        $classroom = Classroom::create(['name' => 'XI RPL 1']);
        Student::factory()->count(3)->create(['class_name' => 'XI RPL 1']);

        $this->actingAs($this->admin)
            ->from(route('admin.classrooms.index'))
            ->delete(route('admin.classrooms.destroy', $classroom))
            ->assertRedirect(route('admin.classrooms.index'))
            ->assertSessionHas('error', 'Tidak bisa dihapus, masih ada 3 siswa di kelas ini. Pindahkan siswa itu ke kelas lain dulu.');

        $this->assertDatabaseHas('classes', ['id' => $classroom->id]);
    }

    public function test_non_admin_cannot_access_classroom_pages(): void
    {
        $peserta = User::factory()->peserta()->create();

        $this->actingAs($peserta)->get(route('admin.classrooms.index'))->assertForbidden();
    }
}

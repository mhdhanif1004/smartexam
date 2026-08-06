<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomPlacementModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_can_view_rooms_index_with_student_count(): void
    {
        $room = Room::factory()->create(['name' => 'Ruang A']);
        Student::factory()->count(3)->create(['room_id' => $room->id]);

        $this->actingAs($this->admin)
            ->get(route('admin.rooms.index'))
            ->assertOk()
            ->assertSee('Ruang A')
            ->assertSee('3 siswa');
    }

    public function test_admin_can_create_room_with_students_and_supervisor(): void
    {
        $students = Student::factory()->count(3)->create();
        $supervisor = Supervisor::factory()->create(['room_id' => null]);

        $this->actingAs($this->admin)
            ->post(route('admin.rooms.store'), [
                'name' => 'Ruang A',
                'supervisor_id' => $supervisor->id,
                'student_ids' => $students->pluck('id')->all(),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $room = Room::where('name', 'Ruang A')->first();
        $this->assertNotNull($room);
        $this->assertSame(3, $room->capacity);

        foreach ($students as $student) {
            $this->assertSame($room->id, $student->refresh()->room_id);
        }

        $this->assertSame($room->id, $supervisor->refresh()->room_id);
    }

    public function test_create_rejects_student_already_in_another_room(): void
    {
        $other = Room::factory()->create();
        $student = Student::factory()->create(['room_id' => $other->id]);

        $this->actingAs($this->admin)
            ->post(route('admin.rooms.store'), [
                'name' => 'Ruang B',
                'student_ids' => [$student->id],
            ])
            ->assertSessionHasErrors('student_ids');

        $this->assertSame($other->id, $student->refresh()->room_id);
        $this->assertDatabaseMissing('rooms', ['name' => 'Ruang B']);
    }

    public function test_update_moves_free_students_and_clears_unchecked(): void
    {
        $room = Room::factory()->create(['name' => 'Ruang A']);
        $stay = Student::factory()->create(['room_id' => $room->id]);
        $leave = Student::factory()->create(['room_id' => $room->id]);
        $join = Student::factory()->create(['room_id' => null]);

        $this->actingAs($this->admin)
            ->put(route('admin.rooms.update', $room), [
                'name' => 'Ruang A',
                'student_ids' => [$stay->id, $join->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame($room->id, $stay->refresh()->room_id);
        $this->assertSame($room->id, $join->refresh()->room_id);
        $this->assertNull($leave->refresh()->room_id);
        $this->assertSame(2, $room->refresh()->capacity);
    }

    public function test_update_cannot_steal_student_from_another_room(): void
    {
        $roomA = Room::factory()->create();
        $roomB = Room::factory()->create();
        $student = Student::factory()->create(['room_id' => $roomA->id]);

        $this->actingAs($this->admin)
            ->put(route('admin.rooms.update', $roomB), [
                'name' => $roomB->name,
                'student_ids' => [$student->id],
            ])
            ->assertSessionHasErrors('student_ids');

        $this->assertSame($roomA->id, $student->refresh()->room_id);
    }

    public function test_update_moves_supervisor_and_releases_previous(): void
    {
        $roomA = Room::factory()->create();
        $roomB = Room::factory()->create();
        $supervisorA = Supervisor::factory()->create(['room_id' => $roomA->id]);
        $supervisorB = Supervisor::factory()->create(['room_id' => $roomB->id]);

        $this->actingAs($this->admin)
            ->put(route('admin.rooms.update', $roomB), [
                'name' => $roomB->name,
                'supervisor_id' => $supervisorA->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame($roomB->id, $supervisorA->refresh()->room_id);
        $this->assertNull($supervisorB->refresh()->room_id);
    }

    public function test_clearing_supervisor_releases_the_room_supervisor(): void
    {
        $room = Room::factory()->create();
        $supervisor = Supervisor::factory()->create(['room_id' => $room->id]);

        $this->actingAs($this->admin)
            ->put(route('admin.rooms.update', $room), [
                'name' => $room->name,
                'supervisor_id' => '',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNull($supervisor->refresh()->room_id);
    }

    public function test_destroy_releases_students_and_supervisor(): void
    {
        $room = Room::factory()->create(['name' => 'Ruang A']);
        $student = Student::factory()->create(['room_id' => $room->id]);
        $supervisor = Supervisor::factory()->create(['room_id' => $room->id]);

        $this->actingAs($this->admin)
            ->delete(route('admin.rooms.destroy', $room))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('rooms', ['id' => $room->id]);
        $this->assertNull($student->refresh()->room_id);
        $this->assertNull($supervisor->refresh()->room_id);
    }

    public function test_room_capacity_always_matches_assigned_student_count(): void
    {
        $room = Room::factory()->create(['name' => 'Ruang A', 'capacity' => 40]);
        $students = Student::factory()->count(2)->create(['room_id' => $room->id]);

        $this->actingAs($this->admin)
            ->put(route('admin.rooms.update', $room), [
                'name' => 'Ruang A',
                'capacity' => 99,
                'student_ids' => $students->pluck('id')->all(),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(2, $room->refresh()->capacity);
    }

    public function test_non_admin_cannot_manage_rooms(): void
    {
        $peserta = User::factory()->peserta()->create();

        $this->actingAs($peserta)
            ->get(route('admin.rooms.index'))
            ->assertForbidden();

        $this->actingAs($peserta)
            ->post(route('admin.rooms.store'), ['name' => 'Ruang X'])
            ->assertForbidden();
    }

    public function test_one_class_can_be_split_across_multiple_rooms(): void
    {
        $students = Student::factory()->count(4)->create(['class_name' => 'XI RPL 1']);

        $this->actingAs($this->admin)
            ->post(route('admin.rooms.store'), [
                'name' => 'Ruang A',
                'student_ids' => $students->take(2)->pluck('id')->all(),
            ])
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->post(route('admin.rooms.store'), [
                'name' => 'Ruang B',
                'student_ids' => $students->skip(2)->pluck('id')->all(),
            ])
            ->assertRedirect();

        $roomA = Room::where('name', 'Ruang A')->first();
        $roomB = Room::where('name', 'Ruang B')->first();

        $this->assertSame(2, $roomA->students()->count());
        $this->assertSame(2, $roomB->students()->count());
        $this->assertSame(0, Student::query()->whereNull('room_id')->count());
    }
}

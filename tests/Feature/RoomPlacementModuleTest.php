<?php

namespace Tests\Feature;

use App\Models\ExamSchedule;
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

    public function test_admin_can_create_room_with_capacity_and_supervisors(): void
    {
        $supervisorA = Supervisor::factory()->create(['room_id' => null]);
        $supervisorB = Supervisor::factory()->create(['room_id' => null]);

        $this->actingAs($this->admin)
            ->post(route('admin.rooms.store'), [
                'name' => 'Ruang A',
                'capacity' => 30,
                'supervisor_ids' => [$supervisorA->id, $supervisorB->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $room = Room::where('name', 'Ruang A')->first();
        $this->assertNotNull($room);
        $this->assertSame(30, $room->capacity);

        $this->assertSame($room->id, $supervisorA->refresh()->room_id);
        $this->assertSame($room->id, $supervisorB->refresh()->room_id);
    }

    public function test_update_saves_capacity_from_form(): void
    {
        $room = Room::factory()->create(['name' => 'Ruang A', 'capacity' => 40]);

        $this->actingAs($this->admin)
            ->put(route('admin.rooms.update', $room), [
                'name' => 'Ruang A',
                'capacity' => 25,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(25, $room->refresh()->capacity);
    }

    public function test_room_update_does_not_touch_student_placements(): void
    {
        $roomA = Room::factory()->create(['name' => 'Ruang A']);
        $roomB = Room::factory()->create(['name' => 'Ruang B']);
        $student = Student::factory()->create(['room_id' => $roomA->id]);

        $this->actingAs($this->admin)
            ->put(route('admin.rooms.update', $roomB), [
                'name' => $roomB->name,
                'capacity' => $roomB->capacity,
                'student_ids' => [$student->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

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
                'supervisor_ids' => [$supervisorA->id],
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
                'supervisor_ids' => [],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNull($supervisor->refresh()->room_id);
    }

    public function test_update_assigns_multiple_supervisors_to_one_room(): void
    {
        $room = Room::factory()->create(['name' => 'Ruang A']);
        $supervisors = Supervisor::factory()->count(3)->create(['room_id' => null]);

        $this->actingAs($this->admin)
            ->put(route('admin.rooms.update', $room), [
                'name' => $room->name,
                'supervisor_ids' => $supervisors->pluck('id')->all(),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        foreach ($supervisors as $supervisor) {
            $this->assertSame($room->id, $supervisor->refresh()->room_id);
        }

        $this->assertSame(3, $room->refresh()->supervisors()->count());
    }

    public function test_index_shows_supervisor_count_for_multiple_supervisors(): void
    {
        $room = Room::factory()->create(['name' => 'Ruang A']);
        Supervisor::factory()->count(3)->create(['room_id' => $room->id]);

        $this->actingAs($this->admin)
            ->get(route('admin.rooms.index'))
            ->assertOk()
            ->assertSee('3 pengawas');
    }

    public function test_update_releases_supervisors_removed_from_selection(): void
    {
        $room = Room::factory()->create();
        $supervisors = Supervisor::factory()->count(3)->create(['room_id' => $room->id]);
        $stay = $supervisors->take(2);
        $released = $supervisors->last();

        $this->actingAs($this->admin)
            ->put(route('admin.rooms.update', $room), [
                'name' => $room->name,
                'supervisor_ids' => $stay->pluck('id')->all(),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        foreach ($stay as $supervisor) {
            $this->assertSame($room->id, $supervisor->refresh()->room_id);
        }

        $this->assertNull($released->refresh()->room_id);
        $this->assertSame(2, $room->refresh()->supervisors()->count());
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

    public function test_bulk_delete_removes_rooms_without_schedules(): void
    {
        $rooms = Room::factory()->count(3)->create();

        $this->actingAs($this->admin)
            ->post(route('admin.rooms.bulk-delete'), [
                'ids' => [$rooms[0]->id, $rooms[1]->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', '2 ruangan berhasil dihapus.');

        $this->assertDatabaseMissing('rooms', ['id' => $rooms[0]->id]);
        $this->assertDatabaseMissing('rooms', ['id' => $rooms[1]->id]);
        $this->assertDatabaseHas('rooms', ['id' => $rooms[2]->id]);
    }

    public function test_bulk_delete_skips_rooms_with_exam_schedules_and_reports_error(): void
    {
        $scheduledRoom = Room::factory()->create(['name' => 'Ruang A']);
        $cleanRoom = Room::factory()->create(['name' => 'Ruang B']);

        ExamSchedule::factory()->create(['room_id' => $scheduledRoom->id]);

        $this->actingAs($this->admin)
            ->post(route('admin.rooms.bulk-delete'), [
                'ids' => [$scheduledRoom->id, $cleanRoom->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', '1 ruangan berhasil dihapus.')
            ->assertSessionHas('error');

        $this->assertStringContainsString('Ruang A', session('error'));
        $this->assertStringContainsString('jadwal ujian', session('error'));

        $this->assertDatabaseMissing('rooms', ['id' => $cleanRoom->id]);
        $this->assertDatabaseHas('rooms', ['id' => $scheduledRoom->id]);
    }

    public function test_bulk_delete_requires_selection(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.rooms.bulk-delete'), ['ids' => []])
            ->assertRedirect()
            ->assertSessionHas('error', 'Pilih minimal satu ruangan untuk dihapus.');
    }

    public function test_rooms_index_renders_bulk_delete_ui(): void
    {
        Room::factory()->count(2)->create();

        $this->actingAs($this->admin)
            ->get(route('admin.rooms.index'))
            ->assertOk()
            ->assertSee('Hapus Terpilih')
            ->assertSee('smartexam_selected_rooms')
            ->assertSee('confirm-bulk-delete');
    }
}

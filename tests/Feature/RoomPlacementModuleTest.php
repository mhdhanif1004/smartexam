<?php

namespace Tests\Feature;

use App\Models\ExamPeriod;
use App\Models\ExamSchedule;
use App\Models\Room;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\SupervisorRoomAssignment;
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
        $room = Room::factory()->create(['room_number' => 1]);
        Student::factory()->count(3)->create(['room_id' => $room->id]);

        $this->actingAs($this->admin)
            ->get(route('admin.rooms.index'))
            ->assertOk()
            ->assertSee('Ruang 1')
            ->assertSee('3 siswa tetap');
    }

    public function test_admin_can_create_room_with_capacity(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.rooms.store'), [
                'room_number' => 2,
                'capacity' => 30,
                'supervisor_count' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $room = Room::where('room_number', 2)->first();
        $this->assertNotNull($room);
        $this->assertSame(30, $room->capacity);
    }

    public function test_update_saves_capacity_from_form(): void
    {
        $room = Room::factory()->create(['room_number' => 1, 'capacity' => 40]);

        $this->actingAs($this->admin)
            ->put(route('admin.rooms.update', $room), [
                'room_number' => 1,
                'capacity' => 25,
                'supervisor_count' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(25, $room->refresh()->capacity);
    }

    public function test_room_update_does_not_touch_student_placements(): void
    {
        $roomA = Room::factory()->create(['room_number' => 1]);
        $roomB = Room::factory()->create(['room_number' => 2]);
        $student = Student::factory()->create(['room_id' => $roomA->id]);

        $this->actingAs($this->admin)
            ->put(route('admin.rooms.update', $roomB), [
                'room_number' => $roomB->room_number,
                'capacity' => $roomB->capacity,
                'supervisor_count' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame($roomA->id, $student->refresh()->room_id);
    }

    public function test_index_shows_rotation_based_supervisor_count(): void
    {
        $room = Room::factory()->create(['room_number' => 1]);
        $supervisor = Supervisor::factory()->create();

        $period = ExamPeriod::factory()->create([
            'exam_date' => now()->toDateString(),
        ]);

        SupervisorRoomAssignment::factory()->create([
            'exam_period_id' => $period->id,
            'exam_date' => now()->toDateString(),
            'supervisor_id' => $supervisor->id,
            'room_id' => $room->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.rooms.index'))
            ->assertOk()
            ->assertSee('1 pengawas');
    }

    public function test_index_shows_zero_when_no_rotation_today(): void
    {
        $room = Room::factory()->create(['room_number' => 1]);

        $this->actingAs($this->admin)
            ->get(route('admin.rooms.index'))
            ->assertOk()
            ->assertSee('0 pengawas');
    }

    public function test_update_warns_when_supervisor_count_changed_with_future_rotation(): void
    {
        $room = Room::factory()->create(['room_number' => 1, 'supervisor_count' => 1]);
        $period = ExamPeriod::factory()->create([
            'exam_date' => now()->addDays(3)->toDateString(),
        ]);

        SupervisorRoomAssignment::factory()->create([
            'exam_period_id' => $period->id,
            'exam_date' => now()->addDays(3)->toDateString(),
            'room_id' => $room->id,
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.rooms.update', $room), [
                'room_number' => $room->room_number,
                'capacity' => $room->capacity,
                'supervisor_count' => 2,
            ])
            ->assertRedirect()
            ->assertSessionHas('warning');

        $this->assertSame(2, $room->refresh()->supervisor_count);
    }

    public function test_destroy_releases_students(): void
    {
        $room = Room::factory()->create(['room_number' => 1]);
        $student = Student::factory()->create(['room_id' => $room->id]);

        $this->actingAs($this->admin)
            ->delete(route('admin.rooms.destroy', $room))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('rooms', ['id' => $room->id]);
        $this->assertNull($student->refresh()->room_id);
    }

    public function test_non_admin_cannot_manage_rooms(): void
    {
        $peserta = User::factory()->peserta()->create();

        $this->actingAs($peserta)
            ->get(route('admin.rooms.index'))
            ->assertForbidden();

        $this->actingAs($peserta)
            ->post(route('admin.rooms.store'), ['room_number' => 99])
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
        $scheduledRoom = Room::factory()->create(['room_number' => 1]);
        $cleanRoom = Room::factory()->create(['room_number' => 2]);

        ExamSchedule::factory()->create(['room_id' => $scheduledRoom->id]);

        $this->actingAs($this->admin)
            ->post(route('admin.rooms.bulk-delete'), [
                'ids' => [$scheduledRoom->id, $cleanRoom->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', '1 ruangan berhasil dihapus.')
            ->assertSessionHas('error');

        $this->assertStringContainsString('Ruang 1', session('error'));
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

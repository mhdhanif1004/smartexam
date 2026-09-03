<?php

namespace Tests\Feature;

use App\Models\ExamPeriod;
use App\Models\ExamSchedule;
use App\Models\Room;
use App\Models\Subject;
use App\Models\Supervisor;
use App\Models\SupervisorRoomAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupervisorManualAssignTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Room $roomA;

    private Room $roomB;

    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();

        $this->roomA = Room::factory()->create(['room_number' => 1, 'capacity' => 25, 'supervisor_count' => 1]);
        $this->roomB = Room::factory()->create(['room_number' => 2, 'capacity' => 25, 'supervisor_count' => 1]);

        $this->subject = Subject::factory()->create(['name' => 'Matematika']);
    }

    private function supervisor(string $name, bool $active = true): Supervisor
    {
        return Supervisor::factory()->create([
            'room_id' => null,
            'user_id' => User::factory()->pengawas()->create([
                'name' => $name,
                'is_active' => $active,
            ])->id,
        ]);
    }

    private function periodWithRoom(Room $room, string $date = '2026-08-12'): ExamPeriod
    {
        $period = ExamPeriod::factory()->create([
            'name' => 'Sesi 1',
            'exam_date' => $date,
        ]);

        ExamSchedule::factory()->create([
            'exam_period_id' => $period->id,
            'room_id' => $room->id,
            'subject_id' => $this->subject->id,
            'exam_date' => $date,
        ]);

        return $period;
    }

    public function test_admin_can_fill_empty_slot_manually(): void
    {
        $supervisor = $this->supervisor('Andi');
        $period = $this->periodWithRoom($this->roomA);

        $this->actingAs($this->admin)
            ->postJson(route('admin.exam-periods.supervisor-assignments.store', $period), [
                'room_id' => $this->roomA->id,
                'supervisor_id' => $supervisor->id,
            ])
            ->assertOk()
            ->assertJson(['message' => 'Pengawas berhasil ditugaskan.']);

        $this->assertDatabaseHas('supervisor_room_assignments', [
            'exam_period_id' => $period->id,
            'exam_date' => '2026-08-12',
            'supervisor_id' => $supervisor->id,
            'room_id' => $this->roomA->id,
            'rotation_index' => 1,
        ]);
    }

    public function test_assign_is_rejected_when_room_slot_is_full(): void
    {
        $assigned = $this->supervisor('Andi');
        $incoming = $this->supervisor('Budi');
        $period = $this->periodWithRoom($this->roomA);

        SupervisorRoomAssignment::factory()->create([
            'exam_period_id' => $period->id,
            'exam_date' => '2026-08-12',
            'supervisor_id' => $assigned->id,
            'room_id' => $this->roomA->id,
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('admin.exam-periods.supervisor-assignments.store', $period), [
                'room_id' => $this->roomA->id,
                'supervisor_id' => $incoming->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('supervisor_id');

        $this->assertDatabaseCount('supervisor_room_assignments', 1);
    }

    public function test_assign_is_rejected_when_supervisor_already_assigned_in_same_period(): void
    {
        $supervisor = $this->supervisor('Andi');
        $period = $this->periodWithRoom($this->roomA);
        $this->periodWithRoom($this->roomB);

        SupervisorRoomAssignment::factory()->create([
            'exam_period_id' => $period->id,
            'exam_date' => '2026-08-12',
            'supervisor_id' => $supervisor->id,
            'room_id' => $this->roomB->id,
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('admin.exam-periods.supervisor-assignments.store', $period), [
                'room_id' => $this->roomA->id,
                'supervisor_id' => $supervisor->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('supervisor_id');

        $this->assertDatabaseCount('supervisor_room_assignments', 1);
    }

    public function test_assign_is_rejected_for_inactive_supervisor(): void
    {
        $supervisor = $this->supervisor('Andi', active: false);
        $period = $this->periodWithRoom($this->roomA);

        $this->actingAs($this->admin)
            ->postJson(route('admin.exam-periods.supervisor-assignments.store', $period), [
                'room_id' => $this->roomA->id,
                'supervisor_id' => $supervisor->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('supervisor_id');

        $this->assertDatabaseCount('supervisor_room_assignments', 0);
    }

    public function test_assign_is_rejected_when_room_not_in_period(): void
    {
        $supervisor = $this->supervisor('Andi');
        $period = $this->periodWithRoom($this->roomA);

        $this->actingAs($this->admin)
            ->postJson(route('admin.exam-periods.supervisor-assignments.store', $period), [
                'room_id' => $this->roomB->id,
                'supervisor_id' => $supervisor->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('room_id');

        $this->assertDatabaseCount('supervisor_room_assignments', 0);
    }

    public function test_assign_is_rejected_when_same_room_same_date_in_other_period(): void
    {
        $supervisor = $this->supervisor('Andi');

        $periodA = ExamPeriod::factory()->create(['name' => 'Gelombang 1', 'exam_date' => '2026-08-12']);
        ExamSchedule::factory()->create([
            'exam_period_id' => $periodA->id,
            'room_id' => $this->roomA->id,
            'subject_id' => $this->subject->id,
            'exam_date' => '2026-08-12',
        ]);

        $periodB = ExamPeriod::factory()->create(['name' => 'Gelombang 2', 'exam_date' => '2026-08-12']);
        ExamSchedule::factory()->create([
            'exam_period_id' => $periodB->id,
            'room_id' => $this->roomA->id,
            'subject_id' => $this->subject->id,
            'exam_date' => '2026-08-12',
        ]);

        SupervisorRoomAssignment::factory()->create([
            'exam_period_id' => $periodA->id,
            'exam_date' => '2026-08-12',
            'supervisor_id' => $supervisor->id,
            'room_id' => $this->roomA->id,
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('admin.exam-periods.supervisor-assignments.store', $periodB), [
                'room_id' => $this->roomA->id,
                'supervisor_id' => $supervisor->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('supervisor_id');

        $this->assertDatabaseCount('supervisor_room_assignments', 1);
    }

    public function test_non_admin_cannot_assign_supervisor(): void
    {
        $supervisor = $this->supervisor('Andi');
        $period = $this->periodWithRoom($this->roomA);
        $guru = User::factory()->guruMapel()->create();

        $this->actingAs($guru)
            ->postJson(route('admin.exam-periods.supervisor-assignments.store', $period), [
                'room_id' => $this->roomA->id,
                'supervisor_id' => $supervisor->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('supervisor_room_assignments', 0);
    }
}

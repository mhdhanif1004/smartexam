<?php

namespace Tests\Feature;

use App\Models\ExamPeriod;
use App\Models\Question;
use App\Models\Room;
use App\Models\Subject;
use App\Models\Supervisor;
use App\Models\SupervisorRoomAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupervisorAssignmentEditResetTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Room $roomA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->roomA = Room::factory()->create(['room_number' => 1]);
        $subject = Subject::factory()->create(['name' => 'Mat']);
        Question::factory()->create(['subject_id' => $subject->id]);
    }

    private function supervisor(string $name): User
    {
        return Supervisor::factory()->create([
            'user_id' => User::factory()->pengawas()->create(['name' => $name])->id,
        ])->user;
    }

    public function test_patch_with_json_body_works(): void
    {
        $supA = $this->supervisor('Andi');
        $supB = $this->supervisor('Budi');

        $period = ExamPeriod::factory()->create(['exam_date' => '2026-08-12']);

        $sra = SupervisorRoomAssignment::factory()->create([
            'exam_period_id' => $period->id,
            'exam_date' => '2026-08-12',
            'supervisor_id' => $supA->supervisor->id,
            'room_id' => $this->roomA->id,
        ]);

        // Simulate browser fetch: JSON body + JSON Accept
        $this->actingAs($this->admin)
            ->patchJson(
                route('admin.exam-periods.supervisor-assignments.update', [$period, $sra]),
                ['supervisor_id' => $supB->supervisor->id]
            )
            ->assertStatus(200)
            ->assertJsonPath('message', 'Pengawas berhasil diperbarui.')
            ->assertJsonPath('supervisor_room_assignment.supervisor_id', $supB->supervisor->id);

        $this->assertDatabaseHas('supervisor_room_assignments', [
            'id' => $sra->id,
            'supervisor_id' => $supB->supervisor->id,
        ]);
    }

    public function test_patch_with_json_body_rejects_conflict(): void
    {
        $supA = $this->supervisor('Andi');
        $supB = $this->supervisor('Budi');

        $period = ExamPeriod::factory()->create(['exam_date' => '2026-08-12']);

        // A sudah ditugaskan di Ruang 1
        SupervisorRoomAssignment::factory()->create([
            'exam_period_id' => $period->id,
            'exam_date' => '2026-08-12',
            'supervisor_id' => $supA->supervisor->id,
            'room_id' => $this->roomA->id,
        ]);

        // B ditugaskan di Risung 2 (buat ruang lain)
        $roomB = Room::factory()->create(['room_number' => 2]);
        $sraB = SupervisorRoomAssignment::factory()->create([
            'exam_period_id' => $period->id,
            'exam_date' => '2026-08-12',
            'supervisor_id' => $supB->supervisor->id,
            'room_id' => $roomB->id,
        ]);

        // Coba ganti B (Ruang 2) --> A yang sudah dipakai di Ruang 1 --> harus error
        $this->actingAs($this->admin)
            ->patchJson(
                route('admin.exam-periods.supervisor-assignments.update', [$period, $sraB]),
                ['supervisor_id' => $supA->supervisor->id]
            )
            ->assertStatus(422)
            ->assertJsonValidationErrors('supervisor_id');
    }

    public function test_delete_reset_via_json_accept_works(): void
    {
        $supA = $this->supervisor('Andi');

        $period = ExamPeriod::factory()->create(['exam_date' => '2026-08-12']);

        SupervisorRoomAssignment::factory()->create([
            'exam_period_id' => $period->id,
            'exam_date' => '2026-08-12',
            'supervisor_id' => $supA->supervisor->id,
            'room_id' => $this->roomA->id,
        ]);

        $this->actingAs($this->admin)
            ->deleteJson(route('admin.exam-periods.supervisor-assignments.reset-all', $period))
            ->assertStatus(200)
            ->assertJsonPath('deleted', 1)
            ->assertJsonPath('message', '1 penugasan pengawas berhasil dihapus. Anda dapat men generate ulang rotasi pengawas.');

        $this->assertDatabaseCount('supervisor_room_assignments', 0);
    }

    public function test_delete_reset_with_no_assignments_returns_json_info(): void
    {
        $period = ExamPeriod::factory()->create(['exam_date' => '2026-08-12']);

        $this->actingAs($this->admin)
            ->deleteJson(route('admin.exam-periods.supervisor-assignments.reset-all', $period))
            ->assertStatus(200)
            ->assertJsonPath('message', 'Tidak ada penugasan pengawas yang perlu dihapus.');
    }
}

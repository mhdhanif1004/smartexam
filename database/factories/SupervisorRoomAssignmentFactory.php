<?php

namespace Database\Factories;

use App\Models\ExamPeriod;
use App\Models\Room;
use App\Models\Supervisor;
use App\Models\SupervisorRoomAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupervisorRoomAssignment>
 */
class SupervisorRoomAssignmentFactory extends Factory
{
    protected $model = SupervisorRoomAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_period_id' => ExamPeriod::factory(),
            'exam_date' => fn () => now()->toDateString(),
            'supervisor_id' => Supervisor::factory(),
            'room_id' => Room::factory(),
            'rotation_index' => 1,
        ];
    }
}

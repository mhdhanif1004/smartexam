<?php

namespace Database\Factories;

use App\Models\ExamSchedule;
use App\Models\Room;
use App\Models\Supervisor;
use App\Models\SupervisorAttendance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupervisorAttendance>
 */
class SupervisorAttendanceFactory extends Factory
{
    protected $model = SupervisorAttendance::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supervisor_id' => Supervisor::factory(),
            'exam_schedule_id' => ExamSchedule::factory(),
            'room_id' => Room::factory(),
            'status' => SupervisorAttendance::STATUS_PRESENT,
            'checked_in_at' => now(),
        ];
    }
}

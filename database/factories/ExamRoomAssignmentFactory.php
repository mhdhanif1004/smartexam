<?php

namespace Database\Factories;

use App\Models\ExamPeriod;
use App\Models\ExamRoomAssignment;
use App\Models\Room;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamRoomAssignment>
 */
class ExamRoomAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_period_id' => ExamPeriod::factory(),
            'student_id' => Student::factory(),
            'room_id' => Room::factory(),
            'seat_number' => fake()->numberBetween(1, 40),
        ];
    }
}

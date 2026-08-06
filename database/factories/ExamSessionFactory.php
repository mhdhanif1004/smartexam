<?php

namespace Database\Factories;

use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamSession>
 */
class ExamSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'exam_schedule_id' => ExamSchedule::factory(),
            'started_at' => fake()->dateTime(),
            'finished_at' => fake()->dateTime(),
            'status' => 'completed',
        ];
    }
}

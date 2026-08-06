<?php

namespace Database\Factories;

use App\Models\ExamResult;
use App\Models\ExamSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamResult>
 */
class ExamResultFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_session_id' => ExamSession::factory(),
            'total_score' => fake()->randomFloat(2, 20, 100),
            'is_passed' => fake()->boolean(),
        ];
    }
}

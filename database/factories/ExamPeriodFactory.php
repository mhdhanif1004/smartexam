<?php

namespace Database\Factories;

use App\Models\ExamPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamPeriod>
 */
class ExamPeriodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Sesi '.fake()->numberBetween(1, 4),
            'exam_date' => fake()->dateTimeBetween('-1 week', '+2 weeks')->format('Y-m-d'),
            'start_time' => '07:30:00',
            'end_time' => '11:00:00',
        ];
    }
}

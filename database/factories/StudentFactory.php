<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->peserta(),
            'nisn' => fake()->unique()->numerify('##########'),
            'class_name' => fake()->randomElement([
                'X RPL 1',
                'X RPL 2',
                'XI RPL 1',
                'XI RPL 2',
                'XII RPL 1',
                'XII RPL 2',
                'XI TKJ 1',
                'XII TKJ 1',
            ]),
        ];
    }
}

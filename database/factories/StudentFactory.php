<?php

namespace Database\Factories;

use App\Models\Classroom;
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
     */
    public function definition(): array
    {
        $classNames = [
            'X RPL 1',
            'X RPL 2',
            'X RPL 3',
            'XI RPL 1',
            'XI RPL 2',
            'XI RPL 3',
            'XII RPL 1',
            'XII RPL 2',
            'XII RPL 3',
        ];
        $className = fake()->randomElement($classNames);

        return [
            'user_id' => User::factory()->peserta(),
            'nisn' => fake()->unique()->numerify('##########'),
            'class_name' => $className,
            'classroom_id' => Classroom::query()->firstOrCreate(['name' => $className])->id,
        ];
    }
}

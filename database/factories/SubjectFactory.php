<?php

namespace Database\Factories;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->regexify('[A-Z]{2,4}[0-9]{3}'),
            'name' => fake()->unique()->randomElement([
                'Matematika',
                'Bahasa Indonesia',
                'Bahasa Inggris',
                'Pemrograman Web',
                'Basis Data',
                'Jaringan Komputer',
                'Desain Grafis',
                'Informatika',
                'Fisika',
                'Pendidikan Pancasila',
            ]),
            'class_label' => fake()->randomElement(['X', 'XI', 'XII', 'X RPL 1', 'XI RPL 1', 'XII TKJ 1']),
            'default_duration_minutes' => fake()->randomElement([60, 90, 120]),
        ];
    }
}

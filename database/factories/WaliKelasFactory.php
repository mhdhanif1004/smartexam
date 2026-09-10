<?php

namespace Database\Factories;

use App\Models\Classroom;
use App\Models\User;
use App\Models\WaliKelas;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaliKelas>
 */
class WaliKelasFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->waliKelas(),
            'classroom_id' => Classroom::factory(),
        ];
    }
}

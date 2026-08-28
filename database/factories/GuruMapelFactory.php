<?php

namespace Database\Factories;

use App\Models\GuruMapel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuruMapel>
 */
class GuruMapelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->guruMapel(),
            'nip' => fake()->optional()->numerify('################'),
        ];
    }
}

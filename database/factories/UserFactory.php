<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->regexify('[a-z0-9]{12}'),
            'email' => null,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'plain_password' => 'password',
            'remember_token' => Str::random(10),
            'role' => 'peserta',
            'is_active' => true,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
            'email' => fake()->unique()->safeEmail(),
            'username' => null,
        ]);
    }

    public function pengawas(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'pengawas',
            'email' => fake()->unique()->safeEmail(),
            'username' => null,
        ]);
    }

    public function peserta(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'peserta',
            'email' => null,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}

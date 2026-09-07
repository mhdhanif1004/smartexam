<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserFcmToken;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserFcmToken>
 */
class UserFcmTokenFactory extends Factory
{
    protected $model = UserFcmToken::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'token' => 'fcm_'.fake()->unique()->lexify('????????????????????????????????????????'),
            'device_type' => fake()->randomElement(['web', 'android', 'ios', null]),
        ];
    }
}

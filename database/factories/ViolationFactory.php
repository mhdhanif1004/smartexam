<?php

namespace Database\Factories;

use App\Models\ExamSession;
use App\Models\User;
use App\Models\Violation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Violation>
 */
class ViolationFactory extends Factory
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
            'violation_type' => fake()->randomElement([
                'membawa_handphone',
                'mencontek',
                'bicara_dengan_teman',
                'membuka_buku',
                'keluar_ruangan',
            ]),
            'occurred_at' => fake()->dateTime(),
            'reported_by' => User::factory()->pengawas(),
        ];
    }
}

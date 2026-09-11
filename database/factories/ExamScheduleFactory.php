<?php

namespace Database\Factories;

use App\Models\Classroom;
use App\Models\ExamSchedule;
use App\Models\Room;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamSchedule>
 */
class ExamScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory(),
            'room_id' => Room::factory(),
            'classroom_id' => null,
            'class_name' => fake()->randomElement([
                'X RPL 1',
                'XI RPL 1',
                'XI TKJ 1',
                'XII RPL 1',
            ]),
            'exam_date' => fake()->dateTimeBetween('-1 week', '+1 week')->format('Y-m-d'),
            'start_time' => '08:00:00',
            'end_time' => '09:30:00',
            'duration_minutes' => 90,
            'status' => ExamSchedule::STATUS_SCHEDULED,
        ];
    }

    /**
     * Jadwal berbasis kelas (tanpa ruang) — dipakai flow Guru Mapel.
     */
    public function classroomBased(?Classroom $classroom = null): static
    {
        return $this->state(fn () => [
            'room_id' => null,
            'classroom_id' => $classroom?->id ?? Classroom::factory(),
        ]);
    }
}

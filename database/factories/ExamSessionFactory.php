<?php

namespace Database\Factories;

use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamSession>
 */
class ExamSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Distribusi realistis: 60% hadir, 25% tidak_hadir, 15% null (belum dicek)
        $roll = fake()->numberBetween(1, 100);

        $attendanceStatus = match (true) {
            $roll <= 60 => ExamSession::ATTENDANCE_PRESENT,
            $roll <= 85 => ExamSession::ATTENDANCE_ABSENT,
            default => null,
        };

        // Derivasi konsisten: hanya hadir yang confirmed true
        $confirmed = $attendanceStatus === ExamSession::ATTENDANCE_PRESENT;

        return [
            'student_id' => Student::factory(),
            'exam_schedule_id' => ExamSchedule::factory(),
            'started_at' => fake()->dateTime(),
            'finished_at' => fake()->dateTime(),
            'status' => 'completed',
            'attendance_status' => $attendanceStatus,
            'attendance_confirmed' => $confirmed,
            'attendance_confirmed_at' => $confirmed ? fake()->dateTimeBetween('-1 day', 'now') : null,
            // null agar tidak FK error di test RefreshDatabase tanpa user
            'attendance_confirmed_by' => null,
        ];
    }

    /**
     * State hadir — peserta dikonfirmasi hadir.
     */
    public function hadir(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'attendance_status' => ExamSession::ATTENDANCE_PRESENT,
            'attendance_confirmed' => true,
            'attendance_confirmed_at' => fake()->dateTimeBetween('-1 day', 'now'),
            'attendance_confirmed_by' => null,
        ]);
    }

    /**
     * State tidak hadir — peserta tidak hadir, belum dikonfirmasi.
     */
    public function tidakHadir(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'attendance_status' => ExamSession::ATTENDANCE_ABSENT,
            'attendance_confirmed' => false,
            'attendance_confirmed_at' => null,
            'attendance_confirmed_by' => null,
        ]);
    }

    /**
     * State belum dicek — absensi belum diverifikasi pengawas.
     */
    public function belumDicek(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'attendance_status' => null,
            'attendance_confirmed' => false,
            'attendance_confirmed_at' => null,
            'attendance_confirmed_by' => null,
        ]);
    }

    /**
     * State terkunci oleh admin — sesi dikunci agar tidak bisa diubah.
     */
    public function terkunci(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'locked_by_admin' => true,
            'locked_by_admin_at' => now(),
            'locked_by_admin_by' => null,
        ]);
    }
}

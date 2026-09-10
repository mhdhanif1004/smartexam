<?php

namespace Database\Factories;

use App\Models\Classroom;
use App\Models\Semester;
use App\Models\Student;
use App\Models\WaliKelas;
use App\Models\WaliKelasNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaliKelasNote>
 */
class WaliKelasNoteFactory extends Factory
{
    protected $model = WaliKelasNote::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'classroom_id' => Classroom::factory(),
            'wali_kelas_id' => WaliKelas::factory(),
            'semester_id' => fn () => Semester::create([
                'year' => '2025/2026',
                'semester' => 1,
                'is_active' => false,
            ])->id,
            'tipe' => fake()->optional(0.7)->randomElement(['observasi', 'pelanggaran', 'prestasi', 'lainnya']),
            'catatan' => fake()->sentence(8),
        ];
    }
}

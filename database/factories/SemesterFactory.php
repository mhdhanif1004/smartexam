<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Semester>
 */
class SemesterFactory extends Factory
{
    protected $model = Semester::class;

    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(),
            'jenis' => fake()->randomElement(['ganjil', 'genap']),
            'is_active' => false,
        ];
    }

    /**
     * Semester ganjil dalam tahun ajaran tertentu.
     */
    public function ganjil(?AcademicYear $academicYear = null): static
    {
        return $this->state(fn () => [
            'academic_year_id' => $academicYear?->id ?? AcademicYear::factory(),
            'jenis' => 'ganjil',
        ]);
    }

    /**
     * Semester genap dalam tahun ajaran tertentu.
     */
    public function genap(?AcademicYear $academicYear = null): static
    {
        return $this->state(fn () => [
            'academic_year_id' => $academicYear?->id ?? AcademicYear::factory(),
            'jenis' => 'genap',
        ]);
    }

    /**
     * Semester aktif.
     */
    public function aktif(): static
    {
        return $this->state(['is_active' => true]);
    }
}

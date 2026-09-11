<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicYear>
 */
class AcademicYearFactory extends Factory
{
    protected $model = AcademicYear::class;

    public function definition(): array
    {
        $tahun = fake()->numberBetween(2024, 2030);

        return [
            'nama' => "{$tahun}/".($tahun + 1),
            'tanggal_mulai' => "{$tahun}-07-01",
            'tanggal_selesai' => ($tahun + 1).'-06-30',
            'is_active' => false,
        ];
    }

    /**
     * Tahun ajaran aktif.
     */
    public function aktif(): static
    {
        return $this->state(['is_active' => true]);
    }
}

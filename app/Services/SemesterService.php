<?php

namespace App\Services;

use App\Models\Semester;
use Illuminate\Support\Facades\DB;

class SemesterService
{
    /**
     * Jadikan satu semester sebagai aktif.
     *
     * Dalam satu transaction: set target jadi `is_active = true`,
     * set SEMUA semester lain jadi `is_active = false`. Hanya boleh
     * ADA SATU semester aktif dalam satu waktu.
     */
    public function makeActive(Semester $semester): Semester
    {
        return DB::transaction(function () use ($semester) {
            // Nonaktifkan semua semester
            Semester::query()->where('is_active', true)->update(['is_active' => false]);

            // Aktifkan target
            $semester->update(['is_active' => true]);

            return $semester->fresh();
        });
    }
}

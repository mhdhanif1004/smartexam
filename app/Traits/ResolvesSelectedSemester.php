<?php

namespace App\Traits;

use App\Models\AcademicYear;
use App\Models\Semester;
use Illuminate\Support\Collection;

/**
 * Resolusi semester terpilih untuk modul Wali Kelas.
 *
 * Semua controller Wali Kelas menggunakan trait ini supaya semester
 * yang dipilih bersifat GLOBAL via session — berlaku untuk SEMUA halaman
 * sampai user ganti lagi via dropdown. Bukan per-request query param.
 */
trait ResolvesSelectedSemester
{
    protected const SEMESTER_SESSION_KEY = 'wali_kelas_semester_id';

    /**
     * Ambil ID semester yang sedang dipilih (dari session).
     *
     * Fallback:
     * 1. Session kosong → Semester::getActive()
     * 2. Semester di session sudah tidak ada → lupa + fallback
     * 3. Tidak ada semester di DB sama sekali → return 0 (halaman render empty state)
     */
    protected function selectedSemesterId(): int
    {
        $id = (int) session(self::SEMESTER_SESSION_KEY);

        if ($id > 0) {
            $exists = Semester::query()->whereKey($id)->exists();

            if ($exists) {
                return $id;
            }

            session()->forget(self::SEMESTER_SESSION_KEY);
        }

        $fallback = Semester::getActive();

        if ($fallback !== null) {
            session([self::SEMESTER_SESSION_KEY => $fallback->id]);

            return $fallback->id;
        }

        return 0;
    }

    /**
     * Data lengkap untuk komponen semester selector di view.
     *
     * @return array{semesters: Collection, selectedSemesterId: int}
     */
    protected function semesterSelectorData(): array
    {
        return [
            'semesters' => Semester::query()
                ->with('academicYear')
                ->orderByDesc(
                    AcademicYear::query()
                        ->select('nama')
                        ->whereColumn('academic_years.id', 'semesters.academic_year_id')
                )
                ->orderByDesc('jenis')
                ->get(),
            'selectedSemesterId' => $this->selectedSemesterId(),
        ];
    }
}

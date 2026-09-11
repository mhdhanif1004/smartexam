<?php

namespace App\Exports\WaliKelas;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Export rekap 4 kategori Wali Kelas dalam satu file Excel:
 * Nilai Akademik, Nilai Sikap, Rekap Pelanggaran, Catatan Wali Kelas.
 *
 * Semua sheet memakai data untuk semester yang sedang dipilih di session.
 */
class WaliKelasRecapExport implements WithMultipleSheets
{
    /**
     * @param  array<string, Collection>  $data  keyed: academicGrades|attitudeGrades|violations|catatan
     * @param  array<int, string>  $attitudeAspects  nama aspek (untuk header sheet Nilai Sikap)
     */
    public function __construct(
        private readonly array $data,
        private readonly array $attitudeAspects = [],
    ) {}

    public function sheets(): array
    {
        return [
            new AcademicGradesSheet($this->data['academicGrades'] ?? collect()),
            new AttitudeGradesSheet($this->data['attitudeGrades'] ?? collect(), $this->attitudeAspects),
            new ViolationsSheet($this->data['violations'] ?? collect()),
            new CatatanSheet($this->data['catatan'] ?? collect()),
        ];
    }
}

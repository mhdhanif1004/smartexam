<?php

namespace App\Exports\WaliKelas;

use App\Exports\Concerns\WithSheetStyling;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class AcademicGradesSheet implements FromCollection, WithColumnWidths, WithEvents, WithHeadings, WithTitle
{
    use WithSheetStyling;

    /**
     * @param  Collection<int, array<string, int|string|null>>  $rows  [NISN, Nama, Mapel, Nilai, Sumber]
     */
    public function __construct(private readonly Collection $rows) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return ['NISN', 'Nama Siswa', 'Mata Pelajaran', 'Nilai Akhir', 'Sumber'];
    }

    public function title(): string
    {
        return 'Nilai Akademik';
    }

    public function columnWidths(): array
    {
        return ['A' => 16, 'B' => 28, 'C' => 24, 'D' => 12, 'E' => 22];
    }
}

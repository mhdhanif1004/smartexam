<?php

namespace App\Exports\WaliKelas;

use App\Exports\Concerns\WithSheetStyling;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class AttitudeGradesSheet implements FromCollection, WithColumnWidths, WithEvents, WithHeadings, WithTitle
{
    use WithSheetStyling;

    /**
     * @param  Collection<int, array<int, mixed>>  $rows  [NISN, Nama, skor per aspek...]
     * @param  array<int, string>  $aspects  nama aspek → header kolom
     */
    public function __construct(
        private readonly Collection $rows,
        private readonly array $aspects = [],
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return ['NISN', 'Nama Siswa', ...$this->aspects];
    }

    public function title(): string
    {
        return 'Nilai Sikap';
    }

    public function columnWidths(): array
    {
        $widths = ['A' => 16, 'B' => 28];
        $column = 'C';
        foreach ($this->aspects as $aspect) {
            $widths[$column] = 14;
            $column++;
        }

        return $widths;
    }
}

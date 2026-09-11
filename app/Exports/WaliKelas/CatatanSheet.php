<?php

namespace App\Exports\WaliKelas;

use App\Exports\Concerns\WithSheetStyling;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class CatatanSheet implements FromCollection, WithColumnWidths, WithEvents, WithHeadings, WithTitle
{
    use WithSheetStyling;

    /**
     * @param  Collection<int, array<string, int|string|null>>  $rows  [Tanggal, NISN, Nama, Tipe, Isi]
     */
    public function __construct(private readonly Collection $rows) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return ['Tanggal', 'NISN', 'Nama Siswa', 'Tipe', 'Isi Catatan'];
    }

    public function title(): string
    {
        return 'Catatan Wali Kelas';
    }

    public function columnWidths(): array
    {
        return ['A' => 18, 'B' => 16, 'C' => 28, 'D' => 14, 'E' => 60];
    }
}

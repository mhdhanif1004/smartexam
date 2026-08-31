<?php

namespace App\Exports;

use App\Exports\Concerns\WithSheetStyling;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GuruMapelsTemplateExport implements FromCollection, WithColumnWidths, WithEvents, WithHeadings, WithMapping
{
    use WithSheetStyling;

    public function collection(): Collection
    {
        return new Collection([
            [
                'nama' => 'Nama Contoh Guru',
                'mapel' => 'Matematika',
                'tingkat' => 'XI',
                'kelas' => 'SEMUA',
            ],
            [
                'nama' => 'Nama Contoh Guru',
                'mapel' => 'Bahasa Indonesia',
                'tingkat' => 'X',
                'kelas' => 'X AKL 1',
            ],
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Nama', 'Mapel', 'Tingkat', 'Kelas'];
    }

    /**
     * @param  array{nama: string, mapel: string, tingkat: string, kelas: string}  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        return [$row['nama'], $row['mapel'], $row['tingkat'], $row['kelas']];
    }

    /**
     * @return array<string, int>
     */
    public function columnWidths(): array
    {
        return [
            'A' => 30,
            'B' => 25,
            'C' => 12,
            'D' => 30,
        ];
    }

    protected function afterSheetStyling(Worksheet $sheet): void
    {
        // Baris contoh (bukan data valid) diberi pengingat agar tidak ikut terproses.
        $sheet->getComment('A2')->getText()->createTextRun('Baris contoh. Ganti/isi sesuai data guru mapel Anda.');
    }
}

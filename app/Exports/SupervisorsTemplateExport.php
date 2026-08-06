<?php

namespace App\Exports;

use App\Exports\Concerns\WithSheetStyling;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SupervisorsTemplateExport implements FromCollection, WithColumnWidths, WithEvents, WithHeadings, WithMapping
{
    use WithSheetStyling;

    public function collection(): Collection
    {
        return new Collection([
            [
                'name' => 'Nama Contoh Pengawas',
                'email' => 'pengawas@example.com',
            ],
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Nama', 'Email'];
    }

    /**
     * @param  array{name: string, email: string}  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        return [$row['name'], $row['email']];
    }

    /**
     * @return array<string, int>
     */
    public function columnWidths(): array
    {
        return [
            'A' => 30,
            'B' => 30,
        ];
    }
}

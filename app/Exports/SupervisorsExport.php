<?php

namespace App\Exports;

use App\Exports\Concerns\WithSheetStyling;
use App\Models\Supervisor;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SupervisorsExport implements FromCollection, WithColumnWidths, WithEvents, WithHeadings, WithMapping
{
    use WithSheetStyling;

    /**
     * @param  Collection<int, Supervisor>  $rows
     */
    public function __construct(private readonly Collection $rows) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Nama', 'Email', 'Password', 'Ruangan'];
    }

    /**
     * @param  Supervisor  $supervisor
     * @return array<int, mixed>
     */
    public function map($supervisor): array
    {
        return [
            $supervisor->user?->name ?? '-',
            $supervisor->user?->email ?? '-',
            $supervisor->user?->plain_password ?? '-',
            $supervisor->room?->name ?? '-',
        ];
    }

    /**
     * @return array<string, int>
     */
    public function columnWidths(): array
    {
        return [
            'A' => 30,
            'B' => 30,
            'C' => 16,
            'D' => 16,
        ];
    }
}

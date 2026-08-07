<?php

namespace App\Exports;

use App\Exports\Concerns\WithSheetStyling;
use App\Models\Student;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StudentsExport implements FromCollection, WithColumnFormatting, WithColumnWidths, WithEvents, WithHeadings, WithMapping
{
    use WithSheetStyling;

    /**
     * @param  Collection<int, Student>  $rows
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
        return ['NISN', 'Nama', 'Kelas'];
    }

    /**
     * @param  Student  $student
     * @return array<int, mixed>
     */
    public function map($student): array
    {
        return [
            $student->nisn,
            $student->user?->name ?? '-',
            $student->class_name,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return [
            'A' => '@',
        ];
    }

    /**
     * @return array<string, int>
     */
    public function columnWidths(): array
    {
        return [
            'A' => 16,
            'B' => 30,
            'C' => 16,
        ];
    }
}

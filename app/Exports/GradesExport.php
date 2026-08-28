<?php

namespace App\Exports;

use App\Exports\Concerns\WithSheetStyling;
use App\Models\Grade;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class GradesExport implements FromCollection, WithColumnWidths, WithEvents, WithHeadings, WithMapping
{
    use WithSheetStyling;

    /**
     * @param  Collection<int, Grade>  $rows
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
        return ['NISN', 'Nama Siswa', 'Kelas', 'Jenis Nilai', 'Judul', 'Skor', 'Tanggal'];
    }

    /**
     * @param  Grade  $grade
     * @return array<int, mixed>
     */
    public function map($grade): array
    {
        return [
            $grade->student?->nisn ?? '-',
            $grade->student?->user?->name ?? '-',
            $grade->classroom?->name ?? '-',
            Grade::TYPES[$grade->grade_type] ?? $grade->grade_type,
            $grade->title ?: '-',
            (float) $grade->score,
            $grade->created_at?->format('d/m/Y') ?? '-',
        ];
    }

    /**
     * @return array<string, int>
     */
    public function columnWidths(): array
    {
        return [
            'A' => 16,
            'B' => 28,
            'C' => 16,
            'D' => 16,
            'E' => 22,
            'F' => 10,
            'G' => 14,
        ];
    }
}

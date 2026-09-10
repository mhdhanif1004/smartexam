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
     * @param  Collection<int, array<string, mixed>>  $breakdownMap  keyed by student_id
     */
    public function __construct(
        private readonly Collection $rows,
        private readonly Collection $breakdownMap = new Collection,
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['NISN', 'Nama Siswa', 'Kelas', 'Harian', 'UTS', 'UAS', 'Kehadiran', 'Nilai Akhir'];
    }

    /**
     * @param  Grade  $grade
     * @return array<int, mixed>
     */
    public function map($grade): array
    {
        $breakdown = $this->breakdownMap->get($grade->student_id, []);

        $value = fn (string $key) => isset($breakdown[$key])
            ? (float) $breakdown[$key]['average']
            : null;

        return [
            $grade->student?->nisn ?? '-',
            $grade->student?->user?->name ?? '-',
            $grade->classroom?->name ?? '-',
            $value('harian'),
            $value('uts'),
            $value('uas'),
            $value('kehadiran'),
            (float) $grade->score,
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
            'D' => 10,
            'E' => 10,
            'F' => 10,
            'G' => 12,
            'H' => 12,
        ];
    }
}

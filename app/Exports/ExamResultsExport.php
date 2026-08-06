<?php

namespace App\Exports;

use App\Models\ExamResult;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ExamResultsExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @param  Collection<int, ExamResult>  $rows
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
        return [
            'NISN',
            'Nama Siswa',
            'Kelas',
            'Mata Pelajaran',
            'Tanggal Ujian',
            'Ruangan',
            'Nilai',
            'Status',
        ];
    }

    /**
     * @param  ExamResult  $result
     * @return array<int, mixed>
     */
    public function map($result): array
    {
        $student = $result->examSession?->student;
        $schedule = $result->examSession?->examSchedule;

        return [
            $student?->nisn ?? '-',
            $student?->user?->name ?? '-',
            $student?->class_name ?? '-',
            $schedule?->subject?->name ?? '-',
            $schedule?->exam_date?->format('d/m/Y') ?? '-',
            $schedule?->room?->name ?? '-',
            $result->total_score,
            $result->is_passed ? 'Lulus' : 'Tidak Lulus',
        ];
    }
}

<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ExamResultsExport implements FromCollection, WithHeadings, WithMapping
{
    /** @param Collection|Builder $rows */
    public function __construct(private readonly Collection|Builder $rows) {}

    public function collection(): Collection
    {
        if ($this->rows instanceof Builder) {
            // cursor streaming — hydrasi per baris, tetap collect untuk mapping
            // tapi tidak load semua sekaligus via get() eager
            return $this->rows->cursor()->collect();
        }
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
            $schedule?->room?->display_name ?? '-',
            $result->total_score,
            $result->is_passed ? 'Lulus' : 'Tidak Lulus',
        ];
    }
}

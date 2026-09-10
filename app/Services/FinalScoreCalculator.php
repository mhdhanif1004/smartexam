<?php

namespace App\Services;

use App\Models\SubjectGrade;

class FinalScoreCalculator
{
    /**
     * Hitung Nilai Akhir per siswa-mapel-semester menggunakan Opsi A:
     * rata-rata tiap kategori (jenis ujian) dulu, lalu rata-rata antar
     * kategori yang ADA datanya. Kategori kosong di-skip, BUKAN dihitung 0.
     *
     * @return array{
     *   categories: array<int, array{exam_type_id:int, title:string, scores:array<int,float>, average:float}>
     *   , final_score: float|null
     * }
     */
    public function calculate(int $studentId, int $subjectId, int $semesterId): array
    {
        $grades = SubjectGrade::query()
            ->where('student_id', $studentId)
            ->where('subject_id', $subjectId)
            ->where('semester_id', $semesterId)
            ->whereNotNull('score')
            ->with('examType')
            ->get();

        $byType = $grades->groupBy('exam_type_id');

        $categories = $byType->map(function ($group) {
            $scores = $group->map(fn (SubjectGrade $g) => (float) $g->score)->values();

            return [
                'exam_type_id' => (int) $group->first()->exam_type_id,
                'title' => $group->first()->examType?->name ?? 'Kategori',
                'scores' => $scores->all(),
                'average' => round($scores->avg(), 2),
            ];
        })->values()->all();

        $finalScore = $categories === []
            ? null
            : round(collect($categories)->avg('average'), 2);

        return [
            'categories' => $categories,
            'final_score' => $finalScore,
        ];
    }
}

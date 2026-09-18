<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class ExamSummaryService
{
    /**
     * Ringkasan agregat dari hasil ujian yang cocok dengan filter,
     * dihitung di level SQL (1 query) tanpa memuat semua baris ke memori.
     *
     * @return array{total: int, scored: int, average: float, highest: float, lowest: float, passed: int, failed: int}
     */
    /**
     * Distribusi nilai peserta ke 5 bucket standar (0-39, 40-59, 60-74,
     * 75-89, 90-100), dihitung 1 query agregat CASE WHEN tanpa memuat
     * seluruh total_score ke memori. Dipakai bersama oleh Dashboard Admin
     * dan Dashboard Kepala Sekolah agar angkanya selalu konsisten.
     *
     * @return array{labels: array<int, string>, data: array<int, int>}
     */
    public function scoreDistribution(Builder $query): array
    {
        $labels = ['0 - 39', '40 - 59', '60 - 74', '75 - 89', '90 - 100'];

        $rows = (clone $query)
            ->setEagerLoads([])
            ->reorder()
            ->toBase()
            ->selectRaw("CASE
                WHEN total_score < 40 THEN '0 - 39'
                WHEN total_score < 60 THEN '40 - 59'
                WHEN total_score < 75 THEN '60 - 74'
                WHEN total_score < 90 THEN '75 - 89'
                ELSE '90 - 100'
            END as bucket")
            ->selectRaw('COUNT(*) as cnt')
            ->groupBy('bucket')
            ->pluck('cnt', 'bucket');

        return [
            'labels' => $labels,
            'data' => array_map(
                fn (string $label) => (int) ($rows[$label] ?? 0),
                $labels
            ),
        ];
    }

    public function summary(Builder $query): array
    {
        $row = (clone $query)
            ->setEagerLoads([])
            ->reorder()
            ->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COUNT(total_score) as scored')
            ->selectRaw('AVG(total_score) as average')
            ->selectRaw('MAX(total_score) as highest')
            ->selectRaw('MIN(total_score) as lowest')
            ->selectRaw('SUM(CASE WHEN is_passed = 1 THEN 1 ELSE 0 END) as passed')
            ->selectRaw('SUM(CASE WHEN is_passed = 0 THEN 1 ELSE 0 END) as failed')
            ->first();

        $scored = (int) ($row->scored ?? 0);

        return [
            'total' => (int) ($row->total ?? 0),
            'scored' => $scored,
            'average' => $scored === 0 ? 0 : round((float) ($row->average ?? 0), 2),
            'highest' => $scored === 0 ? 0 : (float) ($row->highest ?? 0),
            'lowest' => $scored === 0 ? 0 : (float) ($row->lowest ?? 0),
            'passed' => (int) ($row->passed ?? 0),
            'failed' => (int) ($row->failed ?? 0),
        ];
    }
}

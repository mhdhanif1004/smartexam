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

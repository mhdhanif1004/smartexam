<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ExamResultsExport;
use App\Http\Controllers\Controller;
use App\Models\ExamResult;
use App\Models\Student;
use App\Models\Subject;
use App\Services\ExamSummaryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        [$query, $filters] = $this->buildQuery($request);

        $summary = $this->summaryQuery($query);

        $results = $query->paginate(15)->withQueryString();

        return view('admin.reports.index', [
            'results' => $results,
            'summary' => $summary,
            'subjects' => Subject::query()->orderBy('name')->get(),
            'classes' => Student::query()->distinct()->orderBy('class_name')->pluck('class_name'),
            'filters' => $filters,
        ]);
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        [$query] = $this->buildQuery($request);

        // Streaming berbasis query (cursor) agar tidak load semua baris ke memori sekaligus
        return Excel::download(new ExamResultsExport($query), 'laporan-hasil-ujian.xlsx');
    }

    public function exportPdf(Request $request): Response
    {
        [$query, $filters] = $this->buildQuery($request);

        // Cursor untuk hindari OOM — PDF tetap butuh semua baris tapi tidak via get() eager sekaligus
        $rows = $query->cursor()->collect();
        $summary = $this->summary($rows->whereNotNull('total_score'), $rows);

        $pdf = Pdf::loadView('admin.reports.print', compact('rows', 'summary', 'filters'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('laporan-hasil-ujian.pdf');
    }

    /**
     * Ringkasan agregat dari seluruh data yang cocok dengan filter, dihitung
     * di level SQL (1 query) tanpa memuat semua baris ke memori — berbeda dari
     * tabel hasil yang hanya paginate(15) per halaman.
     *
     * Didelegasikan ke ExamSummaryService agar dapat dipakai lintas controller.
     *
     * @return array{total: int, scored: int, average: float, highest: float, lowest: float, passed: int, failed: int}
     */
    private function summaryQuery(Builder $query): array
    {
        return (new ExamSummaryService)->summary($query);
    }

    /**
     * @return array{total: int, average: float, highest: float, lowest: float, passed: int, failed: int}
     */
    private function summary(Collection $scored, Collection $all): array
    {
        return [
            'total' => $all->count(),
            'average' => $scored->isEmpty() ? 0 : round((float) $scored->avg('total_score'), 2),
            'highest' => $scored->isEmpty() ? 0 : (float) $scored->max('total_score'),
            'lowest' => $scored->isEmpty() ? 0 : (float) $scored->min('total_score'),
            'passed' => $all->where('is_passed', true)->count(),
            'failed' => $all->where('is_passed', false)->count(),
        ];
    }

    /**
     * @return array{Builder, array<string, mixed>}
     */
    private function buildQuery(Request $request): array
    {
        $filters = [
            'subject_id' => $request->filled('subject_id') ? (int) $request->input('subject_id') : null,
            'class_name' => $request->string('class_name')->trim()->toString() ?: null,
            'date_from' => $request->string('date_from')->trim()->toString() ?: null,
            'date_to' => $request->string('date_to')->trim()->toString() ?: null,
        ];

        $query = ExamResult::query()
            ->with([
                'examSession.student.user',
                'examSession.examSchedule.subject',
                'examSession.examSchedule.room',
            ])
            ->when($filters['subject_id'], fn ($q) => $q->whereHas(
                'examSession.examSchedule',
                fn ($schedule) => $schedule->where('subject_id', $filters['subject_id'])
            ))
            ->when($filters['class_name'], fn ($q) => $q->whereHas(
                'examSession.student',
                fn ($student) => $student->where('class_name', $filters['class_name'])
            ))
            ->when($filters['date_from'], fn ($q) => $q->whereHas(
                'examSession.examSchedule',
                fn ($schedule) => $schedule->whereDate('exam_date', '>=', $filters['date_from'])
            ))
            ->when($filters['date_to'], fn ($q) => $q->whereHas(
                'examSession.examSchedule',
                fn ($schedule) => $schedule->whereDate('exam_date', '<=', $filters['date_to'])
            ))
            ->orderByDesc('id');

        return [$query, $filters];
    }
}

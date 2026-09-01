<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamSession;
use App\Models\Room;
use App\Models\Subject;
use App\Models\Violation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ViolationController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'date_from' => $request->string('date_from')->trim()->toString() ?: null,
            'date_to' => $request->string('date_to')->trim()->toString() ?: null,
            'room_id' => $request->filled('room_id') ? (int) $request->input('room_id') : null,
            'violation_type' => $request->string('violation_type')->trim()->toString() ?: null,
            'student_search' => $request->string('student_search')->trim()->toString() ?: null,
            'handled_status' => $request->string('handled_status')->trim()->toString() ?: null,
            'subject_id' => $request->filled('subject_id') ? (int) $request->input('subject_id') : null,
        ];

        // Shared filter scope for violations query and stats
        $applyFilters = fn ($query) => $query
            ->when($filters['date_from'], fn ($q) => $q->whereDate('occurred_at', '>=', $filters['date_from']))
            ->when($filters['date_to'], fn ($q) => $q->whereDate('occurred_at', '<=', $filters['date_to']))
            ->when($filters['room_id'], fn ($q) => $q->whereHas(
                'examSession.examSchedule',
                fn ($schedule) => $schedule->where('room_id', $filters['room_id'])
            ))
            ->when($filters['violation_type'], fn ($q) => $q->where('violation_type', $filters['violation_type']))
            ->when($filters['subject_id'], fn ($q) => $q->whereHas(
                'examSession.examSchedule',
                fn ($schedule) => $schedule->where('subject_id', $filters['subject_id'])
            ))
            ->when($filters['student_search'], function ($q) use ($filters) {
                $search = $filters['student_search'];
                $q->whereHas('examSession.student', function ($studentQ) use ($search) {
                    $studentQ->whereHas('user', function ($userQ) use ($search) {
                        $userQ->where('name', 'like', "%{$search}%");
                    })->orWhere('nisn', 'like', "%{$search}%");
                });
            })
            ->when($filters['handled_status'] === 'handled', fn ($q) => $q->where('handled_by_supervisor', true))
            ->when($filters['handled_status'] === 'unhandled', fn ($q) => $q->where('handled_by_supervisor', false));

        // Group violations by exam_session (1 row per student per exam)
        $sessionIds = $applyFilters(Violation::query())
            ->select('exam_session_id')
            ->groupBy('exam_session_id')
            ->orderByDesc(DB::raw('MAX(occurred_at)'))
            ->pluck('exam_session_id');

        $paginatedIds = new \Illuminate\Pagination\LengthAwarePaginator(
            $sessionIds->slice(($request->input('page', 1) - 1) * 15, 15),
            $sessionIds->count(),
            15,
            $request->input('page', 1),
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $violations = ExamSession::query()
            ->with([
                'student.user',
                'examSchedule.subject',
                'examSchedule.room',
                'violations.reportedBy',
            ])
            ->withCount('violations')
            ->whereIn('id', $paginatedIds->items())
            ->get();

        // Sort by latest violation time (descending)
        $violations = $violations->sortByDesc(fn ($s) => $s->violations->max('occurred_at'));

        // Wrap in paginator for Blade links()
        $violations = $paginatedIds->setCollection($violations);

        $stats = [
            'total' => $applyFilters(Violation::query())->count(),
            'unhandled' => $applyFilters(Violation::query())->where('handled_by_supervisor', false)->count(),
            'unique_students' => $applyFilters(Violation::query())
                ->join('exam_sessions', 'violations.exam_session_id', '=', 'exam_sessions.id')
                ->distinct('exam_sessions.student_id')
                ->count('exam_sessions.student_id'),
            'by_type' => $applyFilters(Violation::query())
                ->selectRaw('violation_type, count(*) as total')
                ->groupBy('violation_type')
                ->pluck('total', 'violation_type'),
        ];

        return view('admin.violations.index', [
            'violations' => $violations,
            'rooms' => Room::query()->orderBy('room_number')->get(),
            'subjects' => Subject::query()->orderBy('name')->get(),
            'violationTypes' => Violation::query()->distinct()->orderBy('violation_type')->pluck('violation_type'),
            'filters' => $filters,
            'stats' => $stats,
        ]);
    }

    /**
     * Hentikan paksa / buka kembali sesi ujian peserta. Satu-satunya cara
     * mengubah locked_by_admin; pengawas tidak berwenang sama sekali.
     */
    public function toggleLock(Request $request, ExamSession $examSession): JsonResponse
    {
        $validated = $request->validate([
            'locked' => ['required', 'boolean'],
        ]);

        $locked = filter_var($validated['locked'], FILTER_VALIDATE_BOOLEAN);

        $examSession->update([
            'locked_by_admin' => $locked,
            'locked_by_admin_at' => $locked ? now() : null,
            'locked_by_admin_by' => $locked ? auth()->id() : null,
        ]);

        return response()->json(['ok' => true, 'locked' => $locked]);
    }

    /**
     * Polling endpoint: kembalikan pelanggaran BARU (seluruh sekolah)
     * yang belum dilihat client. Client mengirim `since` (ID terakhir diketahui).
     */
    public function polling(Request $request): JsonResponse
    {
        $since = (int) $request->query('since', 0);

        $violations = Violation::query()
            ->with(['examSession.student.user', 'examSession.examSchedule.subject', 'examSession.examSchedule.room'])
            ->latest('occurred_at')
            ->limit(50)
            ->get()
            ->map(fn (Violation $v) => Violation::panelPayload($v, $v->id > $since))
            ->values();

        $unhandledCount = $violations->where('handled', false)->count();

        return response()->json([
            'violations' => $violations,
            'unhandled_count' => $unhandledCount,
        ]);
    }
}

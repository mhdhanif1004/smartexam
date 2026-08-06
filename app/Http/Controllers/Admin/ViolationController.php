<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamSession;
use App\Models\Room;
use App\Models\Violation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        ];

        $violations = Violation::query()
            ->with([
                'examSession.student.user',
                'examSession.examSchedule.subject',
                'examSession.examSchedule.room',
                'reportedBy',
            ])
            ->when($filters['date_from'], fn ($q) => $q->whereDate('occurred_at', '>=', $filters['date_from']))
            ->when($filters['date_to'], fn ($q) => $q->whereDate('occurred_at', '<=', $filters['date_to']))
            ->when($filters['room_id'], fn ($q) => $q->whereHas(
                'examSession.examSchedule',
                fn ($schedule) => $schedule->where('room_id', $filters['room_id'])
            ))
            ->when($filters['violation_type'], fn ($q) => $q->where('violation_type', $filters['violation_type']))
            ->orderByDesc('occurred_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.violations.index', [
            'violations' => $violations,
            'rooms' => Room::query()->orderBy('name')->get(),
            'violationTypes' => Violation::query()->distinct()->orderBy('violation_type')->pluck('violation_type'),
            'filters' => $filters,
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
}

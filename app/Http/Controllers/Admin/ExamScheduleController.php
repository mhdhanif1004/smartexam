<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreExamScheduleRequest;
use App\Http\Requests\Admin\UpdateExamScheduleRequest;
use App\Models\ExamPeriod;
use App\Models\ExamRoomAssignment;
use App\Models\ExamSchedule;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ExamScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $dates = $this->applySearchFilters(ExamSchedule::query(), $request)
            ->selectRaw('exam_date, count(*) as total')
            ->groupBy('exam_date')
            ->orderBy('exam_date', 'desc')
            ->paginate(10)
            ->withQueryString();

        return view('admin.exam-schedules.index', [
            'dates' => $dates,
            'statuses' => ExamSchedule::STATUSES,
        ]);
    }

    public function byDate(Request $request): View
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
        ]);

        $date = $validated['date'];

        // Grouped query: exactly 1 row per (subject + date)
        $query = ExamSchedule::query()
            ->whereDate('exam_date', $date)
            ->select(
                'subject_id',
                'exam_date',
                DB::raw('MIN(start_time) as earliest_start'),
                DB::raw('MAX(end_time) as latest_end'),
                DB::raw('MIN(duration_minutes) as duration_minutes'),
                DB::raw('COUNT(DISTINCT room_id) as room_count'),
                DB::raw('GROUP_CONCAT(DISTINCT id ORDER BY id SEPARATOR ",") as schedule_ids'),
                DB::raw('MIN(id) as representative_id'),
                DB::raw("CASE
                    WHEN SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) > 0 THEN 'scheduled'
                    WHEN SUM(CASE WHEN status = 'ongoing' THEN 1 ELSE 0 END) > 0 THEN 'ongoing'
                    ELSE 'finished'
                END as dominant_status"),
            )
            ->groupBy('subject_id', 'exam_date');

        if ($request->filled('search')) {
            $search = $request->string('search')->trim();
            $query->whereHas('subject', fn (Builder $s) => $s->where('name', 'like', "%{$search}%"));
        }

        if ($request->filled('status')) {
            $query->having('dominant_status', '=', $request->string('status'));
        }

        $groups = $query->orderBy('earliest_start')
            ->paginate(10)
            ->withQueryString();

        $subjectIds = $groups->pluck('subject_id')->unique()->values()->all();
        $subjects = Subject::query()->whereIn('id', $subjectIds)->get()->keyBy('id');

        return view('admin.exam-schedules.by-date', [
            'groups' => $groups,
            'subjects' => $subjects,
            'statuses' => ExamSchedule::STATUSES,
            'examDate' => $date,
        ]);
    }

    public function detail(ExamSchedule $examSchedule): JsonResponse
    {
        $subjectId = $examSchedule->subject_id;
        $examDate = $examSchedule->exam_date->toDateString();

        // All schedules for this subject on this day
        $allSchedules = ExamSchedule::query()
            ->where('subject_id', $subjectId)
            ->whereDate('exam_date', $examDate)
            ->get();

        // Group by (exam_period_id, start_time, end_time) = 1 session
        $sessionGroups = $allSchedules->groupBy(
            fn ($s) => ($s->exam_period_id ?? 'manual').'|'.$s->start_time.'|'.$s->end_time
        );

        $periodIds = $sessionGroups->keys()
            ->filter(fn ($k) => ! str_starts_with($k, 'manual'))
            ->map(fn ($k) => (int) explode('|', $k)[0])
            ->values()
            ->all();
        $periods = ExamPeriod::query()->whereIn('id', $periodIds)->get()->keyBy('id');

        $sessions = [];
        $totalStudents = 0;

        foreach ($sessionGroups as $key => $group) {
            [$periodKey, $startTime, $endTime] = explode('|', $key);
            $periodId = $periodKey === 'manual' ? null : (int) $periodKey;
            $period = $periodId !== null ? ($periods[$periodId] ?? null) : null;

            $roomIds = $group->pluck('room_id')->unique()->values()->all();
            $scheduleIds = $group->pluck('id')->values()->all();

            $roomDetails = [];

            if ($period !== null) {
                $assignments = ExamRoomAssignment::query()
                    ->with(['student', 'room'])
                    ->where('exam_period_id', $period->id)
                    ->whereIn('room_id', $roomIds)
                    ->get();

                $byRoom = $assignments->groupBy('room_id');

                foreach ($roomIds as $roomId) {
                    $roomAssignments = $byRoom->get($roomId, collect());
                    $room = $roomAssignments->first()?->room ?? Room::find($roomId);

                    $classes = $roomAssignments
                        ->groupBy(fn ($a) => $a->student?->class_name ?? 'Tanpa Kelas')
                        ->map(fn ($students, $className) => [
                            'name' => $className,
                            'count' => $students->count(),
                        ])
                        ->values()
                        ->sortBy('name')
                        ->all();

                    $roomGradeLevel = $classes !== []
                        ? ExamPeriod::extractGradeLevel($classes[0]['name'])
                        : null;

                    $roomDetails[] = [
                        'room_id' => $roomId,
                        'room_name' => $room?->display_name ?? "Ruang #{$roomId}",
                        'student_count' => $roomAssignments->count(),
                        'classes' => $classes,
                        'grade_level' => $roomGradeLevel,
                    ];
                }
            } else {
                $rooms = Room::query()->whereIn('id', $roomIds)->get()->keyBy('id');

                foreach ($roomIds as $roomId) {
                    $roomSchedule = $group->firstWhere('room_id', $roomId);
                    $roomDetails[] = [
                        'room_id' => $roomId,
                        'room_name' => $rooms->get($roomId)?->display_name ?? "Ruang #{$roomId}",
                        'student_count' => null,
                        'classes' => [
                            ['name' => $roomSchedule?->class_name ?? '-', 'count' => 0],
                        ],
                        'grade_level' => ExamPeriod::extractGradeLevel($roomSchedule?->class_name ?? ''),
                    ];
                }
            }

            usort($roomDetails, fn ($a, $b) => $a['room_id'] <=> $b['room_id']);

            $sessionStudents = collect($roomDetails)->sum('student_count');
            $isManual = $period === null;
            if (! $isManual) {
                $totalStudents += $sessionStudents;
            }

            $label = match (true) {
                $period !== null => 'Sesi '.$period->session_number
                    .' · '.Carbon::parse($startTime)->format('H:i').'-'.Carbon::parse($endTime)->format('H:i'),
                default => 'Jadwal Manual',
            };

            $sessions[] = [
                'label' => $label,
                'period_name' => $period?->name ?? null,
                'name_prefix' => $period?->name_prefix ?? null,
                'grade_level' => $period?->grade_level ?? null,
                'start_time' => Carbon::parse($startTime)->format('H:i'),
                'end_time' => Carbon::parse($endTime)->format('H:i'),
                'rooms' => $roomDetails,
                'room_count' => count($roomDetails),
                'student_count' => $sessionStudents,
                'is_manual' => $isManual,
            ];
        }

        // Sort sessions by start_time
        usort($sessions, fn ($a, $b) => $a['start_time'] <=> $b['start_time']);

        $subject = $allSchedules->first()?->subject;

        $namePrefixes = collect($sessions)
            ->pluck('name_prefix')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return response()->json([
            'subject_name' => $subject?->name ?? '-',
            'date' => $examDate,
            'total_rooms' => $allSchedules->pluck('room_id')->unique()->count(),
            'total_students' => $totalStudents,
            'name_prefixes' => $namePrefixes,
            'sessions' => $sessions,
        ]);
    }

    public function create(): View
    {
        return view('admin.exam-schedules.create', $this->formOptions());
    }

    public function store(StoreExamScheduleRequest $request): RedirectResponse
    {
        $start = Carbon::createFromFormat('H:i', $request->start_time);

        ExamSchedule::create([
            'subject_id' => $request->subject_id,
            'room_id' => $request->room_id,
            'class_name' => $request->class_name,
            'exam_date' => $request->exam_date,
            'start_time' => $start->format('H:i:s'),
            'end_time' => $start->copy()->addMinutes((int) $request->duration_minutes)->format('H:i:s'),
            'duration_minutes' => $request->duration_minutes,
            'status' => $request->status,
        ]);

        return redirect()->route('admin.exam-schedules.index')->with('success', 'Jadwal ujian berhasil ditambahkan.');
    }

    public function edit(ExamSchedule $examSchedule): View
    {
        $examSchedule->syncStatusIfNeeded();

        $data = $this->formOptions();
        $data['examSchedule'] = $examSchedule;

        return view('admin.exam-schedules.edit', $data);
    }

    public function update(UpdateExamScheduleRequest $request, ExamSchedule $examSchedule): RedirectResponse
    {
        $start = Carbon::createFromFormat('H:i', $request->start_time);

        $examSchedule->update([
            'subject_id' => $request->subject_id,
            'room_id' => $request->room_id,
            'class_name' => $request->class_name,
            'exam_date' => $request->exam_date,
            'start_time' => $start->format('H:i:s'),
            'end_time' => $start->copy()->addMinutes((int) $request->duration_minutes)->format('H:i:s'),
            'duration_minutes' => $request->duration_minutes,
            'status' => $request->status,
        ]);

        return redirect()->route('admin.exam-schedules.index')->with('success', 'Jadwal ujian berhasil diperbarui.');
    }

    public function destroy(ExamSchedule $examSchedule): RedirectResponse
    {
        $deleted = $this->deleteScheduleGroup($examSchedule->subject_id, $examSchedule->exam_date);

        return redirect()->route('admin.exam-schedules.index')->with('success', "{$deleted} jadwal ujian berhasil dihapus.");
    }

    public function bulkDelete(Request $request): RedirectResponse
    {
        $ids = collect($request->input('ids', []))
            ->filter(fn ($id) => filter_var($id, FILTER_VALIDATE_INT))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return back()->with('error', 'Pilih minimal satu jadwal untuk dihapus.');
        }

        $totalDeleted = 0;
        $seenGroups = [];
        foreach ($ids as $id) {
            $schedule = ExamSchedule::find($id);
            if (! $schedule) {
                continue;
            }
            $groupKey = $schedule->subject_id.'|'.$schedule->exam_date;
            if (in_array($groupKey, $seenGroups, true)) {
                continue;
            }
            $seenGroups[] = $groupKey;
            $totalDeleted += $this->deleteScheduleGroup($schedule->subject_id, $schedule->exam_date);
        }

        return back()->with('success', "{$totalDeleted} jadwal ujian berhasil dihapus.");
    }

    /**
     * Delete ALL exam schedules for a given subject + date (all rooms, all sessions).
     *
     * @return int Number of deleted rows
     */
    private function deleteScheduleGroup(string|int $subjectId, string $examDate): int
    {
        return ExamSchedule::query()
            ->where('subject_id', $subjectId)
            ->whereDate('exam_date', $examDate)
            ->delete();
    }

    /**
     * Apply search/status filters for the date-index (ungrouped) query.
     */
    private function applySearchFilters(Builder $query, Request $request): Builder
    {
        return $query
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = $request->string('search')->trim();
                $query->where(function (Builder $builder) use ($search) {
                    $builder->where('class_name', 'like', "%{$search}%")
                        ->orWhere('exam_date', 'like', "%{$search}%")
                        ->orWhereHas('subject', fn ($subject) => $subject->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('room', fn ($room) => $room->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn (Builder $query) => $query->whereComputedStatus($request->string('status')));
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'subjects' => Subject::query()->orderBy('name')->get(),
            'rooms' => Room::query()->orderBy('room_number')->get(),
            'classes' => Student::query()->distinct()->orderBy('class_name')->pluck('class_name'),
            'statuses' => ExamSchedule::STATUSES,
        ];
    }
}

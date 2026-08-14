<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreExamScheduleRequest;
use App\Http\Requests\Admin\UpdateExamScheduleRequest;
use App\Models\ExamSchedule;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

        $schedules = $this->schedulesForDate($request, $validated['date'])
            ->with(['subject', 'room'])
            ->orderBy('start_time')
            ->paginate(10)
            ->withQueryString();

        return view('admin.exam-schedules.by-date', [
            'schedules' => $schedules,
            'statuses' => ExamSchedule::STATUSES,
            'examDate' => $validated['date'],
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
        $examSchedule->delete();

        return redirect()->route('admin.exam-schedules.index')->with('success', 'Jadwal ujian berhasil dihapus.');
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

        $deleted = ExamSchedule::query()->whereIn('id', $ids)->delete();

        return back()->with('success', "{$deleted} jadwal ujian berhasil dihapus.");
    }

    private function schedulesForDate(Request $request, string $date): Builder
    {
        return $this->applySearchFilters(
            ExamSchedule::query()->whereDate('exam_date', $date),
            $request,
        );
    }

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

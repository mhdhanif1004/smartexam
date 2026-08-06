<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreExamScheduleRequest;
use App\Http\Requests\Admin\UpdateExamScheduleRequest;
use App\Models\ExamSchedule;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ExamScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $schedules = ExamSchedule::query()
            ->with(['subject', 'room'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->trim();
                $query->where(function ($builder) use ($search) {
                    $builder->where('class_name', 'like', "%{$search}%")
                        ->orWhere('exam_date', 'like', "%{$search}%")
                        ->orWhereHas('subject', function ($subject) use ($search) {
                            $subject->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('room', function ($room) use ($search) {
                            $room->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderBy('exam_date', 'desc')
            ->orderBy('start_time')
            ->paginate(10)
            ->withQueryString();

        return view('admin.exam-schedules.index', [
            'schedules' => $schedules,
            'statuses' => ExamSchedule::STATUSES,
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

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'subjects' => Subject::query()->orderBy('name')->get(),
            'rooms' => Room::query()->orderBy('name')->get(),
            'classes' => Student::query()->distinct()->orderBy('class_name')->pluck('class_name'),
            'statuses' => ExamSchedule::STATUSES,
        ];
    }
}

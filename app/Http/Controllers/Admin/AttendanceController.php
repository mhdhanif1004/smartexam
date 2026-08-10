<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamSchedule;
use App\Models\Room;
use App\Models\Student;
use App\Models\SupervisorAttendance;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(): View
    {
        $rooms = Room::query()->orderBy('name')->get();

        $selectedRoomId = request()->integer('room_id');
        $room = $selectedRoomId ? Room::find($selectedRoomId) : $rooms->first();

        $students = collect();
        $supervisorAttendance = null;
        $schedule = null;

        if ($room) {
            $today = Carbon::today();
            $tomorrow = $today->copy()->addDay();

            $schedule = ExamSchedule::query()
                ->with(['subject', 'room'])
                ->where('room_id', $room->id)
                ->whereIn('status', [ExamSchedule::STATUS_SCHEDULED, ExamSchedule::STATUS_ONGOING, ExamSchedule::STATUS_FINISHED])
                ->where('exam_date', '>=', $today)
                ->where('exam_date', '<', $tomorrow)
                ->first();

            if ($schedule) {
                $schedule->syncStatusIfNeeded();
                $students = Student::query()
                    ->with([
                        'user',
                        'examSessions' => fn ($q) => $q->where('exam_schedule_id', $schedule->id),
                    ])
                    ->where('room_id', $room->id)
                    ->orderBy('nisn')
                    ->get()
                    ->map(function ($student) {
                        $session = $student->examSessions->first();
                        $student->setRelation('examSession', $session);

                        return $student;
                    });

                $supervisorAttendance = SupervisorAttendance::query()
                    ->where('exam_schedule_id', $schedule->id)
                    ->with('supervisor.user')
                    ->get()
                    ->keyBy('supervisor_id');
            }
        }

        return view('admin.attendance.index', compact(
            'rooms',
            'room',
            'schedule',
            'students',
            'supervisorAttendance'
        ));
    }
}

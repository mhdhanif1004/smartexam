<?php

namespace App\Http\Controllers\GuruMapel;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Traits\ScopesGuruMapel;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    use ScopesGuruMapel;

    public function index(Request $request): View
    {
        $guru = $this->currentGuru();

        $subjects = $this->ampuSubjects($guru);

        $subjectId = $request->filled('subject_id') ? (int) $request->integer('subject_id') : null;
        $classroomId = $request->filled('classroom_id') ? (int) $request->integer('classroom_id') : null;

        $classrooms = collect();
        if ($subjectId !== null) {
            $classrooms = $this->ampuClassrooms($guru, $subjectId);
        }

        $schedules = collect();
        $validSelection = $subjectId !== null
            && $classroomId !== null
            && $guru->isAmpu(subjectId: $subjectId, classroomId: $classroomId);

        if ($validSelection) {
            $className = Classroom::query()->whereKey($classroomId)->value('name');

            $schedules = ExamSchedule::query()
                ->with(['subject', 'examPeriod'])
                ->where('subject_id', $subjectId)
                ->when($className !== null, fn ($q) => $q->where('class_name', $className))
                ->whereHas('examSessions')
                ->orderByDesc('exam_date')
                ->orderBy('start_time')
                ->get();

            $schedules = $schedules->map(function (ExamSchedule $schedule) {
                $schedule->attendance_summary = ExamSession::query()
                    ->where('exam_schedule_id', $schedule->id)
                    ->get()
                    ->pipe(function ($sessions) {
                        $total = $sessions->count();
                        $present = $sessions->where('attendance_status', ExamSession::ATTENDANCE_PRESENT)->count();
                        $absent = $sessions->where('attendance_status', ExamSession::ATTENDANCE_ABSENT)->count();

                        return ['total' => $total, 'present' => $present, 'absent' => $absent];
                    });

                return $schedule;
            });
        }

        return view('guru_mapel.attendances.index', compact(
            'guru', 'subjects', 'classrooms', 'subjectId', 'classroomId', 'schedules', 'validSelection',
        ));
    }

    public function schedule(int $schedule): View
    {
        $guru = $this->currentGuru();
        $scheduleModel = $this->resolveAmpuSchedule($guru, $schedule);

        $students = $scheduleModel->participantStudents();

        $sessions = ExamSession::query()
            ->where('exam_schedule_id', $scheduleModel->id)
            ->get()
            ->keyBy('student_id');

        $present = $sessions->where('attendance_status', ExamSession::ATTENDANCE_PRESENT)->count();
        $absent = $sessions->where('attendance_status', ExamSession::ATTENDANCE_ABSENT)->count();
        $confirmed = $sessions->where('attendance_confirmed', true)->count();

        return view('guru_mapel.attendances.schedule', compact(
            'scheduleModel', 'students', 'sessions', 'present', 'absent', 'confirmed',
        ));
    }
}

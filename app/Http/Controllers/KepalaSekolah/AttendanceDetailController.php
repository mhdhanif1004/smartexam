<?php

namespace App\Http\Controllers\KepalaSekolah;

use App\Http\Controllers\Controller;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\SupervisorAttendance;
use Carbon\Carbon;
use Illuminate\View\View;

class AttendanceDetailController extends Controller
{
    public function studentsPresent(): View
    {
        $today = Carbon::today('Asia/Jakarta');

        $scheduleIds = ExamSchedule::query()
            ->whereDate('exam_date', $today)
            ->pluck('id');

        $sessions = ExamSession::query()
            ->with(['student.user', 'student.classroom', 'examSchedule.room'])
            ->join('students', 'students.id', '=', 'exam_sessions.student_id')
            ->whereIn('exam_sessions.exam_schedule_id', $scheduleIds)
            ->where('exam_sessions.attendance_status', ExamSession::ATTENDANCE_PRESENT)
            ->orderBy('students.nisn')
            ->select('exam_sessions.*')
            ->get();

        return view('kepala_sekolah.attendance.students-present', [
            'sessions' => $sessions,
            'today' => $today,
        ]);
    }

    public function studentsAbsent(): View
    {
        $today = Carbon::today('Asia/Jakarta');

        $scheduleIds = ExamSchedule::query()
            ->whereDate('exam_date', $today)
            ->pluck('id');

        $sessions = ExamSession::query()
            ->with(['student.user', 'student.classroom', 'examSchedule.room'])
            ->join('students', 'students.id', '=', 'exam_sessions.student_id')
            ->whereIn('exam_sessions.exam_schedule_id', $scheduleIds)
            ->where('exam_sessions.attendance_status', ExamSession::ATTENDANCE_ABSENT)
            ->whereNotIn('exam_sessions.student_id', function ($q) use ($scheduleIds) {
                $q->select('student_id')->from('exam_sessions')->whereIn('exam_schedule_id', $scheduleIds)->where('attendance_status', ExamSession::ATTENDANCE_PRESENT);
            })
            ->orderBy('students.nisn')
            ->select('exam_sessions.*')
            ->get();

        return view('kepala_sekolah.attendance.students-absent', [
            'sessions' => $sessions,
            'today' => $today,
        ]);
    }

    public function supervisorsPresent(): View
    {
        $today = Carbon::today('Asia/Jakarta');

        $attendances = SupervisorAttendance::query()
            ->with(['supervisor.user', 'supervisor.room', 'room', 'examSchedule.room'])
            ->join('supervisors', 'supervisors.id', '=', 'supervisor_attendances.supervisor_id')
            ->join('users', 'users.id', '=', 'supervisors.user_id')
            ->whereDate('supervisor_attendances.checked_in_at', $today)
            ->where('supervisor_attendances.status', SupervisorAttendance::STATUS_PRESENT)
            ->orderBy('users.name')
            ->select('supervisor_attendances.*')
            ->get();

        return view('kepala_sekolah.attendance.supervisors-present', [
            'attendances' => $attendances,
            'today' => $today,
        ]);
    }

    public function supervisorsAbsent(): View
    {
        $today = Carbon::today('Asia/Jakarta');

        $attendances = SupervisorAttendance::query()
            ->with(['supervisor.user', 'supervisor.room', 'room', 'examSchedule.room'])
            ->join('supervisors', 'supervisors.id', '=', 'supervisor_attendances.supervisor_id')
            ->join('users', 'users.id', '=', 'supervisors.user_id')
            ->whereDate('supervisor_attendances.checked_in_at', $today)
            ->where('supervisor_attendances.status', SupervisorAttendance::STATUS_ABSENT)
            ->whereNotIn('supervisor_attendances.supervisor_id', function ($q) use ($today) {
                $q->select('supervisor_id')->from('supervisor_attendances')->whereDate('checked_in_at', $today)->where('status', SupervisorAttendance::STATUS_PRESENT);
            })
            ->orderBy('users.name')
            ->select('supervisor_attendances.*')
            ->get();

        return view('kepala_sekolah.attendance.supervisors-absent', [
            'attendances' => $attendances,
            'today' => $today,
        ]);
    }
}

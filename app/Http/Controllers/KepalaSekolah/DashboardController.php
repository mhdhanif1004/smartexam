<?php

namespace App\Http\Controllers\KepalaSekolah;

use App\Http\Controllers\Controller;
use App\Models\ExamResult;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\GuruMapel;
use App\Models\Question;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Supervisor;
use App\Models\SupervisorAttendance;
use App\Models\Violation;
use App\Services\ExamSummaryService;
use Carbon\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $today = Carbon::today('Asia/Jakarta');
        $tomorrow = $today->copy()->addDay();

        // a) Card ringkasan
        $totalStudents = Student::count();
        $totalSupervisors = Supervisor::count();
        $totalGuruMapels = GuruMapel::count();
        $totalSubjects = Subject::count();
        $todaySchedules = ExamSchedule::whereDate('exam_date', $today)->count();
        $totalRooms = class_exists(Room::class) ? Room::count() : 0;
        $totalQuestions = class_exists(Question::class) ? Question::count() : 0;

        // b) Hadir/Tidak Hadir Siswa — 1 query agregat SUM CASE seperti Admin Dashboard
        $todayScheduleIds = ExamSchedule::query()
            ->whereDate('exam_date', '>=', $today)
            ->whereDate('exam_date', '<', $tomorrow)
            ->pluck('id');

        $attendanceAggregates = ExamSession::query()
            ->whereIn('exam_schedule_id', $todayScheduleIds)
            ->selectRaw('SUM(CASE WHEN attendance_confirmed = 1 THEN 1 ELSE 0 END) as present')
            ->selectRaw('SUM(CASE WHEN attendance_confirmed = 0 OR attendance_confirmed IS NULL THEN 1 ELSE 0 END) as absent')
            ->first();

        $studentPresentCount = (int) ($attendanceAggregates->present ?? 0);
        $studentAbsentCount = (int) ($attendanceAggregates->absent ?? 0);
        // alias kompatibel dengan view admin
        $attendancePresentCount = $studentPresentCount;
        $attendanceAbsentCount = $studentAbsentCount;

        // c) Hadir/Tidak Hadir Pengawas — hitung berdasarkan status hadir/tidak_hadir hari ini
        $supervisorPresentCount = SupervisorAttendance::query()
            ->whereDate('checked_in_at', $today)
            ->where('status', SupervisorAttendance::STATUS_PRESENT)
            ->count();

        $supervisorAbsentCount = SupervisorAttendance::query()
            ->whereDate('checked_in_at', $today)
            ->where('status', SupervisorAttendance::STATUS_ABSENT)
            ->count();

        // d) Chart donut Lulus/Tidak Lulus + rata-rata
        $query = ExamResult::query()->whereNotNull('total_score');
        $summary = (new ExamSummaryService)->summary($query);
        $donutLabels = ['Lulus', 'Tidak Lulus'];
        $donutData = [$summary['passed'], $summary['failed']];
        $average = $summary['average'];
        $hasData = $summary['total'] > 0 && $summary['scored'] > 0;

        // e) Pelanggaran terbaru — pasif, tanpa polling, tanpa badge mencolok
        $recentViolations = Violation::with(['examSession.student.user', 'examSession.examSchedule.subject', 'examSession.examSchedule.room'])
            ->latest('occurred_at')
            ->take(5)
            ->get()
            ->map(fn (Violation $v) => Violation::panelPayload($v))
            ->all();

        // f) Mata Pelajaran Hari Ini — DISTINCT di SQL (Opsi B: Subject JOIN exam_schedules)
        $subjectsToday = Subject::query()
            ->select('subjects.id', 'subjects.name', 'subjects.code')
            ->join('exam_schedules', 'exam_schedules.subject_id', '=', 'subjects.id')
            ->whereDate('exam_schedules.exam_date', $today)
            ->distinct()
            ->orderBy('subjects.name')
            ->get();

        return view('kepala_sekolah.dashboard', compact(
            'totalStudents',
            'totalSupervisors',
            'totalGuruMapels',
            'totalSubjects',
            'todaySchedules',
            'totalRooms',
            'totalQuestions',
            'studentPresentCount',
            'studentAbsentCount',
            'attendancePresentCount',
            'attendanceAbsentCount',
            'supervisorPresentCount',
            'supervisorAbsentCount',
            'summary',
            'donutLabels',
            'donutData',
            'average',
            'hasData',
            'recentViolations',
            'subjectsToday'
        ));
    }
}

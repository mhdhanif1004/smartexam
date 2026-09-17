<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamResult;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Supervisor;
use App\Models\SupervisorAttendance;
use App\Models\Violation;
use App\Services\ExamSummaryService;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $trendStart = Carbon::today()->subDays(6);
        $trendDays = collect(range(0, 6))->map(fn (int $offset) => $trendStart->copy()->addDays($offset));

        $schedulesByDate = ExamSchedule::query()
            ->selectRaw('exam_date, count(*) as total')
            ->whereDate('exam_date', '>=', $trendStart)
            ->whereDate('exam_date', '<=', Carbon::today())
            ->groupBy('exam_date')
            ->pluck('total', 'exam_date');

        $chartTrendLabels = $trendDays->map(fn (Carbon $day) => $day->format('d M'))->all();
        $chartTrendData = $trendDays
            ->map(fn (Carbon $day) => $schedulesByDate->get($day->format('Y-m-d'), 0))
            ->all();

        $distributionBuckets = (new ExamSummaryService)
            ->scoreDistribution(ExamResult::query()->whereNotNull('total_score'));
        $distributionLabels = $distributionBuckets['labels'];
        $distributionData = $distributionBuckets['data'];

        $passFailSummary = (new ExamSummaryService)
            ->summary(ExamResult::query()->whereNotNull('total_score'));
        $donutLabels = ['Lulus', 'Tidak Lulus'];
        $donutData = [$passFailSummary['passed'], $passFailSummary['failed']];
        $average = $passFailSummary['average'];
        $hasData = $passFailSummary['total'] > 0 && $passFailSummary['scored'] > 0;

        $today = Carbon::today();
        $tomorrow = $today->copy()->addDay();

        // Kehadiran hari ini dihitung 1 query agregat (whereIn + SUM CASE),
        // bukan 1 query per jadwal.
        $todayScheduleIds = ExamSchedule::query()
            ->whereDate('exam_date', '>=', $today)
            ->whereDate('exam_date', '<', $tomorrow)
            ->pluck('id');

        $attendanceAggregates = ExamSession::query()
            ->whereIn('exam_schedule_id', $todayScheduleIds)
            ->selectRaw('SUM(CASE WHEN attendance_confirmed = 1 THEN 1 ELSE 0 END) as present')
            ->selectRaw('SUM(CASE WHEN attendance_confirmed = 0 OR attendance_confirmed IS NULL THEN 1 ELSE 0 END) as absent')
            ->first();

        $presentCount = (int) ($attendanceAggregates->present ?? 0);
        $absentCount = (int) ($attendanceAggregates->absent ?? 0);

        $recentSupervisorAttendances = SupervisorAttendance::query()
            ->with(['supervisor.user', 'examSchedule.subject', 'room'])
            ->whereHas('examSchedule', fn ($q) => $q->whereDate('exam_date', $today))
            ->latest('checked_in_at')
            ->get();

        return view('admin.dashboard', [
            'totalStudents' => Student::count(),
            'totalSupervisors' => Supervisor::count(),
            'totalSubjects' => Subject::count(),
            'totalQuestions' => Question::count(),
            'examsToday' => ExamSchedule::query()->whereDate('exam_date', Carbon::today())->count(),
            'totalRooms' => Room::count(),
            'chartTrendLabels' => $chartTrendLabels,
            'chartTrendData' => $chartTrendData,
            'distributionLabels' => $distributionLabels,
            'distributionData' => $distributionData,
            'donutLabels' => $donutLabels,
            'donutData' => $donutData,
            'average' => $average,
            'hasData' => $hasData,
            'upcomingSchedules' => ExamSchedule::query()
                ->with(['subject', 'room'])
                ->whereDate('exam_date', $today)
                ->orderBy('start_time')
                ->get(),
            'recentViolations' => Violation::query()
                ->with(['examSession.student.user', 'examSession.examSchedule.subject', 'examSession.examSchedule.room'])
                ->whereDate('occurred_at', $today)
                ->latest('occurred_at')
                ->get()
                ->map(fn (Violation $violation) => Violation::panelPayload($violation))
                ->values()->all(),
            'attendancePresentCount' => $presentCount,
            'attendanceAbsentCount' => $absentCount,
            'recentSupervisorAttendances' => $recentSupervisorAttendances,
        ]);
    }
}

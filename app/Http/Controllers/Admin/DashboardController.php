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

        $distributionBuckets = [
            '0 - 39' => 0,
            '40 - 59' => 0,
            '60 - 74' => 0,
            '75 - 89' => 0,
            '90 - 100' => 0,
        ];

        // Distribusi nilai dihitung 1 query agregat (bucket CASE WHEN),
        // bukan memuat seluruh total_score ke PHP.
        $bucketTotals = ExamResult::query()
            ->whereNotNull('total_score')
            ->selectRaw("CASE
                WHEN total_score < 40 THEN '0 - 39'
                WHEN total_score < 60 THEN '40 - 59'
                WHEN total_score < 75 THEN '60 - 74'
                WHEN total_score < 90 THEN '75 - 89'
                ELSE '90 - 100'
            END as bucket")
            ->selectRaw('COUNT(*) as cnt')
            ->groupBy('bucket')
            ->pluck('cnt', 'bucket');

        foreach ($distributionBuckets as $key => $value) {
            $distributionBuckets[$key] = (int) ($bucketTotals[$key] ?? 0);
        }

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
            ->latest('checked_in_at')
            ->take(5)
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
            'distributionLabels' => array_keys($distributionBuckets),
            'distributionData' => array_values($distributionBuckets),
            'upcomingSchedules' => ExamSchedule::query()
                ->with(['subject', 'room'])
                ->whereDate('exam_date', '>=', Carbon::today())
                ->orderBy('exam_date')
                ->orderBy('start_time')
                ->take(5)
                ->get(),
            'recentViolations' => Violation::query()
                ->with(['examSession.student.user', 'examSession.examSchedule.subject', 'examSession.examSchedule.room'])
                ->latest('occurred_at')
                ->take(5)
                ->get()
                ->map(fn (Violation $violation) => Violation::panelPayload($violation))
                ->values()->all(),
            'attendancePresentCount' => $presentCount,
            'attendanceAbsentCount' => $absentCount,
            'recentSupervisorAttendances' => $recentSupervisorAttendances,
        ]);
    }
}

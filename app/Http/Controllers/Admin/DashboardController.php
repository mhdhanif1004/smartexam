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

        foreach (ExamResult::query()->whereNotNull('total_score')->pluck('total_score') as $score) {
            $score = (float) $score;

            if ($score < 40) {
                $distributionBuckets['0 - 39']++;
            } elseif ($score < 60) {
                $distributionBuckets['40 - 59']++;
            } elseif ($score < 75) {
                $distributionBuckets['60 - 74']++;
            } elseif ($score < 90) {
                $distributionBuckets['75 - 89']++;
            } else {
                $distributionBuckets['90 - 100']++;
            }
        }

        $today = Carbon::today();
        $tomorrow = $today->copy()->addDay();

        $todaySchedules = ExamSchedule::query()
            ->whereDate('exam_date', '>=', $today)
            ->whereDate('exam_date', '<', $tomorrow)
            ->get();

        $presentCount = 0;
        $absentCount = 0;

        foreach ($todaySchedules as $schedule) {
            $sessions = ExamSession::query()
                ->where('exam_schedule_id', $schedule->id)
                ->get();

            foreach ($sessions as $session) {
                if ($session->attendance_confirmed) {
                    $presentCount++;
                } else {
                    $absentCount++;
                }
            }
        }

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
                ->with(['examSession.student.user', 'examSession.examSchedule.subject'])
                ->latest('occurred_at')
                ->take(5)
                ->get(),
            'attendancePresentCount' => $presentCount,
            'attendanceAbsentCount' => $absentCount,
            'recentSupervisorAttendances' => $recentSupervisorAttendances,
        ]);
    }
}

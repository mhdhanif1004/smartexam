<?php

namespace App\Http\Controllers\Pengawas;

use App\Http\Controllers\Controller;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Traits\ScopesSupervisorRoom;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use ScopesSupervisorRoom;

    public function __invoke(): View
    {
        $room = $this->supervisorRoom();

        $schedules = ExamSchedule::query()
            ->with(['subject', 'room'])
            ->where('room_id', $room->id)
            ->whereDate('exam_date', Carbon::today())
            ->orderBy('start_time')
            ->get();

        $scheduleStats = [];
        $activeSchedule = null;

        foreach ($schedules as $schedule) {
            $schedule->setAttribute('live_status', $schedule->computedStatus());

            if ($schedule->live_status === ExamSchedule::STATUS_ONGOING) {
                $scheduleStats[$schedule->id] = $this->scheduleStats($schedule);
                $activeSchedule ??= $schedule;
            }
        }

        return view('pengawas.dashboard', [
            'room' => $room,
            'schedules' => $schedules,
            'scheduleStats' => $scheduleStats,
            'activeSchedule' => $activeSchedule,
            'students' => $activeSchedule !== null ? $this->participants($activeSchedule) : collect(),
            'recentViolations' => $this->roomViolations($room, 5),
        ]);
    }

    /**
     * Ringkasan absensi dan progres peserta dari exam_sessions jadwal tersebut.
     *
     * @return array{total: int, hadir: int, sedang_mengerjakan: int, selesai: int}
     */
    protected function scheduleStats(ExamSchedule $schedule): array
    {
        $total = count($schedule->participantStudentIds());

        $sessions = ExamSession::query()
            ->where('exam_schedule_id', $schedule->id)
            ->get();

        return [
            'total' => $total,
            'hadir' => $sessions->where('attendance_confirmed', true)->count(),
            'sedang_mengerjakan' => $sessions->where('status', ExamSession::STATUS_IN_PROGRESS)->count(),
            'selesai' => $sessions->where('status', ExamSession::STATUS_COMPLETED)->count(),
        ];
    }
}

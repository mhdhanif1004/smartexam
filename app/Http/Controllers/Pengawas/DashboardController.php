<?php

namespace App\Http\Controllers\Pengawas;

use App\Http\Controllers\Controller;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\Supervisor;
use App\Traits\ScopesSupervisorRoom;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use ScopesSupervisorRoom;

    public function __invoke(): View
    {
        $supervisor = auth()->user()?->supervisor;
        abort_unless($supervisor instanceof Supervisor, 403);

        $room = $this->supervisorRoom();

        // Pengawas sah tapi belum ditugaskan ke ruangan mana pun hari ini —
        // bukan pelanggaran akses, render empty state.
        if ($room === null) {
            return view('pengawas.dashboard', [
                'room' => null,
                'schedules' => collect(),
                'scheduleStats' => [],
                'activeSchedule' => null,
                'students' => collect(),
                'recentViolations' => collect(),
            ]);
        }

        $assignedPeriodIds = $supervisor->roomAssignments()
            ->where('exam_date', Carbon::today())
            ->where('room_id', $room->id)
            ->pluck('exam_period_id');

        $schedules = ExamSchedule::query()
            ->with(['subject', 'room'])
            ->where('room_id', $room->id)
            ->whereDate('exam_date', Carbon::today())
            ->whereIn('exam_period_id', $assignedPeriodIds)
            ->orderBy('start_time')
            ->get();

        $scheduleStats = [];
        $activeSchedule = null;
        $ongoingSchedules = collect();

        foreach ($schedules as $schedule) {
            $schedule->setAttribute('live_status', $schedule->computedStatus());

            if ($schedule->live_status === ExamSchedule::STATUS_ONGOING) {
                $ongoingSchedules->push($schedule);
                $activeSchedule ??= $schedule;
            }
        }

        $participantIdsBySchedule = ExamSchedule::participantStudentIdsBySchedules($ongoingSchedules);

        foreach ($ongoingSchedules as $schedule) {
            $scheduleStats[$schedule->id] = $this->scheduleStats(
                $schedule,
                $participantIdsBySchedule->get($schedule->id, []),
            );
        }

        $activeParticipantIds = $activeSchedule !== null
            ? $participantIdsBySchedule->get($activeSchedule->id, [])
            : [];
        $students = $activeSchedule !== null
            ? $this->participants($activeSchedule, $activeParticipantIds)
            : collect();

        if ($activeSchedule !== null) {
            $total = $scheduleStats[$activeSchedule->id]['total'] ?? 0;
            if ($total > 0 && $students->isEmpty()) {
                Log::warning('Dashboard pengawas mismatch: total '.$total.' tapi students kosong', [
                    'schedule_id' => $activeSchedule->id,
                    'room_id' => $room->id,
                    'exam_period_id' => $activeSchedule->exam_period_id,
                    'participant_ids_count' => count($activeParticipantIds),
                ]);
            } elseif ($total !== $students->count()) {
                Log::warning('Dashboard pengawas count mismatch: total '.$total.' vs students '.$students->count(), [
                    'schedule_id' => $activeSchedule->id,
                    'room_id' => $room->id,
                    'exam_period_id' => $activeSchedule->exam_period_id,
                ]);
            }
        }

        return view('pengawas.dashboard', [
            'room' => $room,
            'schedules' => $schedules,
            'scheduleStats' => $scheduleStats,
            'activeSchedule' => $activeSchedule,
            'students' => $students,
            'recentViolations' => $this->roomViolations($room, 5),
        ]);
    }

    /**
     * Ringkasan absensi dan progres peserta dari exam_sessions jadwal tersebut.
     *
     * @param  array<int, int>  $participantIds
     * @return array{total: int, hadir: int, sedang_mengerjakan: int, selesai: int}
     */
    protected function scheduleStats(ExamSchedule $schedule, array $participantIds = []): array
    {
        $total = count($participantIds !== [] ? $participantIds : $schedule->participantStudentIds());

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

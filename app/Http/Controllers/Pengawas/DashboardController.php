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

        $rooms = $this->supervisorRooms();

        // Pengawas sah tapi belum ditugaskan ke ruangan mana pun hari ini —
        // bukan pelanggaran akses, render empty state.
        if ($rooms->isEmpty()) {
            return view('pengawas.dashboard', [
                'room' => null,
                'rooms' => collect(),
                'schedules' => collect(),
                'scheduleStats' => [],
                'activeSchedule' => null,
                'students' => collect(),
                'recentViolations' => collect(),
            ]);
        }

        // Backward-compat: $room = ruangan pertama untuk kode/view lama yang
        // masih pakai variabel tunggal. $rooms dipakai untuk multi-room.
        $room = $rooms->first();
        $roomIds = $rooms->pluck('id')->all();

        // Ambil assignment hari ini untuk semua ruangan pengawas, lalu bangun
        // query jadwal yang mencocokkan pasangan (room_id, exam_period_id)
        // secara tepat — bukan sekadar whereIn terpisah yang bisa over-fetch
        // bila pengawas pegang Room A periode 1 dan Room B periode 2.
        $todayAssignments = $supervisor->roomAssignments()
            ->where('exam_date', Carbon::today())
            ->whereIn('room_id', $roomIds)
            ->get(['room_id', 'exam_period_id'])
            ->unique(fn ($a) => $a->room_id.'|'.$a->exam_period_id)
            ->values();

        if ($todayAssignments->isNotEmpty()) {
            $schedules = ExamSchedule::query()
                ->with(['subject', 'room'])
                ->whereDate('exam_date', Carbon::today())
                ->where(function ($query) use ($todayAssignments) {
                    foreach ($todayAssignments as $assignment) {
                        $query->orWhere(function ($q) use ($assignment) {
                            $q->where('room_id', $assignment->room_id)
                              ->where('exam_period_id', $assignment->exam_period_id);
                        });
                    }
                })
                ->orderBy('start_time')
                ->get();
        } else {
            // Fallback legacy: tidak ada baris rotasi hari ini tapi ada
            // ruangan statis — perilakunya dipertahankan identik dengan
            // sebelumnya (whereIn exam_period_id kosong → tidak ada jadwal).
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
        }

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

        // Multi-room: pelanggaran dari semua ruangan; untuk 1 ruangan identik dengan sebelumnya.
        $recentViolations = $rooms->count() > 1
            ? $this->violationsForRooms($rooms, 5)
            : $this->roomViolations($room, 5);

        return view('pengawas.dashboard', [
            'room' => $room, // alias single untuk view lama
            'rooms' => $rooms,
            'schedules' => $schedules,
            'scheduleStats' => $scheduleStats,
            'activeSchedule' => $activeSchedule,
            'students' => $students,
            'recentViolations' => $recentViolations,
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

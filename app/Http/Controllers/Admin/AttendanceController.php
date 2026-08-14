<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamPeriod;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\SupervisorAttendance;
use App\Models\SupervisorRoomAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $date = $this->resolveDate($request->query('date'));

        [$periods, $totals] = $this->attendanceData($date);

        return view('admin.attendance.index', [
            'date' => $date,
            'periods' => $periods,
            'totals' => $totals,
            'summary' => $this->summaries($periods),
        ]);
    }

    /**
     * Ringkasan Hadir/Tidak Hadir per sesi & per ruangan dalam JSON, dipakai
     * auto-refresh berkala di frontend supaya angka terbaru tanpa reload halaman.
     */
    public function summary(Request $request): JsonResponse
    {
        $date = $this->resolveDate($request->query('date'));

        [$periods] = $this->attendanceData($date);

        return response()->json($this->summaries($periods))
            ->header('Cache-Control', 'no-store');
    }

    /**
     * @return array{0: Collection<int, array<string, mixed>>, 1: array<string, int>}
     */
    private function attendanceData(string $date): array
    {
        $schedules = ExamSchedule::query()
            ->with(['subject', 'room', 'examPeriod'])
            ->whereDate('exam_date', $date)
            ->orderBy('start_time')
            ->get();

        $scheduleIds = $schedules->pluck('id')->all();

        $sessionsBySchedule = ExamSession::query()
            ->with('student.user')
            ->whereIn('exam_schedule_id', $scheduleIds)
            ->get()
            ->groupBy('exam_schedule_id');

        $attendancesBySchedule = SupervisorAttendance::query()
            ->with('supervisor.user')
            ->whereIn('exam_schedule_id', $scheduleIds)
            ->get()
            ->groupBy('exam_schedule_id');

        // Supervisor per ruangan dibaca dari penugasan rotasi untuk tanggal ini.
        // Untuk ruangan yang belum memiliki penugasan rotasi (jadwal legacy),
        // fallback ke relasi statis supervisors.room_id.
        $roomIds = $schedules->pluck('room_id')->filter()->unique()->values();

        $supervisorsByRoom = SupervisorRoomAssignment::query()
            ->with('supervisor.user')
            ->whereDate('exam_date', $date)
            ->whereIn('room_id', $roomIds)
            ->get()
            ->groupBy('room_id')
            ->map(fn ($assignments) => $assignments->map->supervisor->values());

        Supervisor::query()
            ->with('user')
            ->whereIn('room_id', $roomIds->diff($supervisorsByRoom->keys())->values())
            ->get()
            ->groupBy('room_id')
            ->each(fn ($group, int $roomId) => $supervisorsByRoom->put($roomId, $group));

        $students = Student::query()
            ->with('user')
            ->whereIn('id', $schedules->flatMap(fn (ExamSchedule $schedule) => $schedule->participantStudentIds())->unique())
            ->get()
            ->keyBy('id');

        $periods = $schedules
            ->groupBy(fn (ExamSchedule $schedule) => $schedule->exam_period_id ?? 'own-'.$schedule->id)
            ->map(function (Collection $group) use ($sessionsBySchedule, $attendancesBySchedule, $supervisorsByRoom, $students) {
                $first = $group->first();
                $period = $first->examPeriod;

                $rooms = $group
                    ->groupBy(fn (ExamSchedule $schedule) => $schedule->room_id)
                    ->map(fn (Collection $roomSchedules) => $this->buildRoom(
                        $roomSchedules,
                        $sessionsBySchedule,
                        $attendancesBySchedule,
                        $supervisorsByRoom,
                        $students,
                    ))
                    ->values();

                return [
                    'name' => $period?->name ?? $first->subject?->name ?? 'Tanpa Sesi',
                    'dateLabel' => $first->exam_date->format('d M Y'),
                    'start' => $this->timeLabel($period?->start_time ?? $first->start_time),
                    'end' => $this->timeLabel($period?->end_time ?? $first->end_time),
                    'status' => $period !== null ? $this->periodStatus($period) : $first->computedStatus(),
                    'rooms' => $rooms,
                ];
            })
            ->values();

        $totals = $this->totals($periods);

        return [$periods, $totals];
    }

    /**
     * Ringkasan ringan (tanpa daftar peserta) untuk auto-refresh di frontend.
     *
     * @return array{totals: array<string, int>, periods: Collection<int, array<string, mixed>>}
     */
    private function summaries(Collection $periods): array
    {
        return [
            'totals' => $this->totals($periods),
            'periods' => $periods->map(function (array $period) {
                $status = $period['status'];

                return [
                    'present' => $period['rooms']->sum('present'),
                    'absent' => $period['rooms']->sum('absent'),
                    'supervisorPresent' => $period['rooms']->sum('supervisorPresent'),
                    'supervisorAbsent' => $period['rooms']->sum('supervisorAbsent'),
                    'rooms' => $period['rooms']->map(fn (array $room) => [
                        'present' => $room['present'],
                        'absent' => $room['absent'],
                        'unchecked' => $room['unchecked'],
                        'total' => $room['present'] + $room['absent'] + $room['unchecked'],
                        'supervisorPresent' => $room['supervisorPresent'],
                        'supervisorAbsent' => $room['supervisorAbsent'],
                        'supervisorUnchecked' => max(0, $room['supervisorTotal'] - $room['supervisorPresent'] - $room['supervisorAbsent']),
                        'needsAttention' => $room['supervisorAbsent'] > 0 && in_array($status, [ExamSchedule::STATUS_ONGOING, ExamSchedule::STATUS_FINISHED], true),
                    ])->values(),
                ];
            })->values(),
        ];
    }

    private function resolveDate(mixed $value): string
    {
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return Carbon::today()->format('Y-m-d');
        }
    }

    private function timeLabel(?string $time): string
    {
        return $time !== null && $time !== '' ? substr($time, 0, 5) : '-';
    }

    private function periodStatus(ExamPeriod $period): string
    {
        $now = Carbon::now();
        $start = Carbon::parse($period->exam_date->format('Y-m-d').' '.$period->start_time);
        $end = Carbon::parse($period->exam_date->format('Y-m-d').' '.$period->end_time);

        if ($now->lt($start)) {
            return ExamSchedule::STATUS_SCHEDULED;
        }

        if ($now->lt($end)) {
            return ExamSchedule::STATUS_ONGOING;
        }

        return ExamSchedule::STATUS_FINISHED;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRoom(
        Collection $roomSchedules,
        Collection $sessionsBySchedule,
        Collection $attendancesBySchedule,
        Collection $supervisorsByRoom,
        Collection $students,
    ): array {
        $room = $roomSchedules->first()->room;

        $schedules = $roomSchedules
            ->sortBy(fn (ExamSchedule $schedule) => $schedule->start_time)
            ->map(fn (ExamSchedule $schedule) => $this->buildSchedule(
                $schedule,
                $sessionsBySchedule,
                $attendancesBySchedule,
                $supervisorsByRoom,
                $students,
            ))
            ->values();

        $presentIds = collect();
        $absentIds = collect();

        foreach ($schedules as $payload) {
            foreach ($payload['participants'] as $item) {
                $session = $item['session'];

                if ($session === null) {
                    continue;
                }

                $studentId = $item['student']->id;

                if ($session->attendance_status === ExamSession::ATTENDANCE_PRESENT) {
                    $presentIds->push($studentId);
                } elseif ($session->attendance_status === ExamSession::ATTENDANCE_ABSENT) {
                    $absentIds->push($studentId);
                }
            }
        }

        $presentIds = $presentIds->unique();
        $absentIds = $absentIds->unique()->diff($presentIds);

        $roomSupervisors = $room !== null ? $supervisorsByRoom->get($room->id, collect()) : collect();

        $supervisorPresentIds = collect();
        $supervisorAbsentIds = collect();

        foreach ($schedules as $payload) {
            foreach ($payload['supervisors'] as $item) {
                $attendance = $item['attendance'];

                if ($attendance === null) {
                    continue;
                }

                $supervisorId = $item['supervisor']->id;

                if ($attendance->status === SupervisorAttendance::STATUS_PRESENT) {
                    $supervisorPresentIds->push($supervisorId);
                } elseif ($attendance->status === SupervisorAttendance::STATUS_ABSENT) {
                    $supervisorAbsentIds->push($supervisorId);
                }
            }
        }

        $supervisorPresentIds = $supervisorPresentIds->unique();
        $supervisorAbsentIds = $supervisorAbsentIds->unique()->diff($supervisorPresentIds);

        $participantCount = $schedules
            ->flatMap(fn (array $payload) => $payload['participants']->pluck('student.id'))
            ->unique()
            ->count();

        return [
            'room' => $room,
            'schedules' => $schedules,
            'present' => $presentIds->count(),
            'absent' => $absentIds->count(),
            'unchecked' => max(0, $participantCount - $presentIds->count() - $absentIds->count()),
            'supervisorPresent' => $supervisorPresentIds->count(),
            'supervisorAbsent' => $supervisorAbsentIds->count(),
            'supervisorTotal' => $roomSupervisors->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSchedule(
        ExamSchedule $schedule,
        Collection $sessionsBySchedule,
        Collection $attendancesBySchedule,
        Collection $supervisorsByRoom,
        Collection $students,
    ): array {
        $scheduleSessions = ($sessionsBySchedule[$schedule->id] ?? collect())->keyBy('student_id');
        $scheduleAttendances = ($attendancesBySchedule[$schedule->id] ?? collect())->keyBy('supervisor_id');
        $roomSupervisors = $supervisorsByRoom->get($schedule->room_id, collect());

        $participants = collect($schedule->participantStudentIds())
            ->map(fn (int $studentId) => [
                'student' => $students->get($studentId),
                'session' => $scheduleSessions->get($studentId),
            ])
            ->filter(fn (array $item) => $item['student'] !== null)
            ->sortBy(fn (array $item) => $item['student']->nisn)
            ->values();

        $supervisors = $roomSupervisors
            ->map(fn (Supervisor $supervisor) => [
                'supervisor' => $supervisor,
                'attendance' => $scheduleAttendances->get($supervisor->id),
            ])
            ->values();

        return [
            'schedule' => $schedule,
            'status' => $schedule->computedStatus(),
            'participants' => $participants,
            'supervisors' => $supervisors,
        ];
    }

    /**
     * @return array{present: int, absent: int, supervisorPresent: int, supervisorAbsent: int}
     */
    private function totals(Collection $periods): array
    {
        $present = 0;
        $absent = 0;
        $supervisorPresent = 0;
        $supervisorAbsent = 0;

        foreach ($periods as $period) {
            foreach ($period['rooms'] as $room) {
                $present += $room['present'];
                $absent += $room['absent'];
                $supervisorPresent += $room['supervisorPresent'];
                $supervisorAbsent += $room['supervisorAbsent'];
            }
        }

        return compact('present', 'absent', 'supervisorPresent', 'supervisorAbsent');
    }
}

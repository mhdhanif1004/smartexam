<?php

namespace App\Http\Controllers\Peserta;

use App\Http\Controllers\Controller;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $student = auth()->user()?->student;
        abort_unless($student instanceof Student, 403, 'Akun ini tidak terdaftar sebagai peserta.');

        $today = Carbon::today();
        $schedules = ExamSchedule::query()
            ->with(['subject', 'room'])
            ->accessibleToStudent($student)
            ->where('exam_date', '>=', $today)
            ->where('exam_date', '<', $today->copy()->addDay())
            ->orderBy('start_time')
            ->get();

        $sessions = ExamSession::query()
            ->where('student_id', $student->id)
            ->whereIn('exam_schedule_id', $schedules->pluck('id'))
            ->get()
            ->keyBy('exam_schedule_id');

        $schedules->each(function (ExamSchedule $schedule) use ($sessions, $student) {
            $schedule->exam_session = $sessions->get($schedule->id);
            $schedule->display = $this->displayFor($schedule, $student);
        });

        $stats = [
            'today' => $schedules->count(),
            'done' => $schedules->where('display.key', 'selesai')->count(),
            'upcoming' => $schedules->where('display.key', 'belum_mulai')->count(),
        ];

        return view('peserta.dashboard', compact('schedules', 'stats'));
    }

    /**
     * @return array{key: string, label: string, can_start: bool, url: ?string}
     */
    private function displayFor(ExamSchedule $schedule, Student $student): array
    {
        $session = $schedule->exam_session;

        if ($session !== null && $session->isTerminal()) {
            return [
                'key' => 'selesai',
                'label' => $session->status === ExamSession::STATUS_TIMED_OUT
                    ? 'Waktu Habis'
                    : 'Selesai',
                'can_start' => false,
                'url' => route('peserta.exams.finished', $schedule),
            ];
        }

        if ($session !== null && $session->status === ExamSession::STATUS_IN_PROGRESS) {
            if (($blocked = $this->attendanceBlockedDisplay($schedule, $session)) !== null) {
                return $blocked;
            }

            return [
                'key' => 'sedang_mengerjakan',
                'label' => 'Sedang Mengerjakan',
                'can_start' => true,
                'url' => route('peserta.exams.work', $schedule),
            ];
        }

        // Early-start: sudah selesai mapel lain dalam sesi yang sama,
        // boleh langsung lanjut ke mapel berikutnya (selama tidak ada
        // sesi paralel lain yang masih in_progress).
        if ($schedule->computedStatus() === ExamSchedule::STATUS_SCHEDULED
            && $schedule->exam_period_id !== null
            && $schedule->isWithinPeriodWindow()
            && $this->hasCompletedOtherMapelInPeriod($schedule, $student)
            && ! $this->hasActiveSessionInPeriod($schedule, $student)) {
            if (($blocked = $this->attendanceBlockedDisplay($schedule, $session)) !== null) {
                return $blocked;
            }

            return [
                'key' => 'bisa_dimulai',
                'label' => 'Bisa Dimulai',
                'can_start' => true,
                'url' => route('peserta.exams.token', $schedule),
            ];
        }

        return match ($schedule->computedStatus()) {
            ExamSchedule::STATUS_SCHEDULED => [
                'key' => 'belum_mulai',
                'label' => 'Belum Mulai',
                'can_start' => false,
                'url' => null,
            ],
            ExamSchedule::STATUS_ONGOING => $this->attendanceBlockedDisplay($schedule, $session) ?? [
                'key' => 'bisa_dimulai',
                'label' => 'Bisa Dimulai',
                'can_start' => true,
                'url' => route('peserta.exams.token', $schedule),
            ],
            default => $schedule->isWithinPeriodWindow()
                ? ($this->attendanceBlockedDisplay($schedule, $session) ?? [
                    'key' => 'susulan',
                    'label' => 'Bisa Dikerjakan',
                    'can_start' => true,
                    'url' => route('peserta.exams.token', $schedule),
                ])
                : [
                    'key' => 'terlewat',
                    'label' => 'Waktu Terlewat',
                    'can_start' => false,
                    'url' => null,
                ],
        };
    }

    /**
     * Guard absensi untuk dashboard agar konsisten dengan ExamController::accessBlock().
     * - session null         → belum diabsen (NOT_CONFIRMED di token)
     * - !confirmed + violation && window tutup → absensi_tertutup (Sesi Berakhir)
     * - !confirmed + violation && window buka  → tidak_hadir (Dinonaktifkan)
     * - !confirmed tanpa violation               → tidak_hadir (Belum Diabsen)
     * Mengembalikan null jika boleh lanjut (attendance_confirmed = true).
     *
     * @return array{key: string, label: string, can_start: bool, url: null}|null
     */
    private function attendanceBlockedDisplay(ExamSchedule $schedule, ?ExamSession $session): ?array
    {
        if ($session === null) {
            return [
                'key' => 'tidak_hadir',
                'label' => 'Belum Diabsen',
                'can_start' => false,
                'url' => null,
            ];
        }

        if ($session->attendance_confirmed) {
            return null;
        }

        if ($session->activeViolationFlags() > 0) {
            if (! $schedule->isAttendanceWindowOpen()) {
                return [
                    'key' => 'absensi_tertutup',
                    'label' => 'Sesi Berakhir',
                    'can_start' => false,
                    'url' => null,
                ];
            }

            return [
                'key' => 'tidak_hadir',
                'label' => 'Dinonaktifkan',
                'can_start' => false,
                'url' => null,
            ];
        }

        return [
            'key' => 'tidak_hadir',
            'label' => 'Belum Diabsen',
            'can_start' => false,
            'url' => null,
        ];
    }

    private function hasCompletedOtherMapelInPeriod(ExamSchedule $schedule, Student $student): bool
    {
        if ($schedule->exam_period_id === null) {
            return false;
        }

        return ExamSession::query()
            ->where('student_id', $student->id)
            ->whereHas('examSchedule', fn ($q) => $q
                ->where('exam_period_id', $schedule->exam_period_id)
                ->where('id', '!=', $schedule->id))
            ->whereIn('status', [ExamSession::STATUS_COMPLETED, ExamSession::STATUS_TIMED_OUT])
            ->exists();
    }

    private function hasActiveSessionInPeriod(ExamSchedule $schedule, Student $student): bool
    {
        if ($schedule->exam_period_id === null) {
            return false;
        }

        return ExamSession::query()
            ->where('student_id', $student->id)
            ->whereHas('examSchedule', fn ($q) => $q
                ->where('exam_period_id', $schedule->exam_period_id)
                ->where('id', '!=', $schedule->id))
            ->where('status', ExamSession::STATUS_IN_PROGRESS)
            ->exists();
    }
}

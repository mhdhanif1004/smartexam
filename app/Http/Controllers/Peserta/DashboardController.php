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

        $schedules->each(function (ExamSchedule $schedule) use ($sessions) {
            $schedule->exam_session = $sessions->get($schedule->id);
            $schedule->display = $this->displayFor($schedule);
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
    private function displayFor(ExamSchedule $schedule): array
    {
        $session = $schedule->exam_session;

        if ($session !== null && $session->status === ExamSession::STATUS_COMPLETED) {
            return [
                'key' => 'selesai',
                'label' => 'Selesai',
                'can_start' => false,
                'url' => route('peserta.exams.finished', $schedule),
            ];
        }

        if ($session !== null && $session->status === ExamSession::STATUS_IN_PROGRESS) {
            return [
                'key' => 'sedang_mengerjakan',
                'label' => 'Sedang Mengerjakan',
                'can_start' => true,
                'url' => route('peserta.exams.work', $schedule),
            ];
        }

        return match ($schedule->computedStatus()) {
            ExamSchedule::STATUS_SCHEDULED => [
                'key' => 'belum_mulai',
                'label' => 'Belum Mulai',
                'can_start' => false,
                'url' => null,
            ],
            ExamSchedule::STATUS_ONGOING => [
                'key' => 'bisa_dimulai',
                'label' => 'Bisa Dimulai',
                'can_start' => true,
                'url' => route('peserta.exams.token', $schedule),
            ],
            default => [
                'key' => 'terlewat',
                'label' => 'Waktu Terlewat',
                'can_start' => false,
                'url' => null,
            ],
        };
    }
}

<?php

namespace App\Console\Commands;

use App\Models\ExamSession;
use App\Services\ExamGradingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupStuckSessions extends Command
{
    protected $signature = 'sessions:cleanup-stuck';

    protected $description = 'Finalisasi otomatis sesi ujian yang macet (tidak ada aktivitas) menjadi status timed_out';

    public function handle(ExamGradingService $grading): int
    {
        $deadlineGrace = (int) config('exam.grace_period_minutes', 10);

        $candidates = ExamSession::query()
            ->where('status', ExamSession::STATUS_IN_PROGRESS)
            ->whereNotNull('started_at')
            ->with(['examSchedule.subject', 'examSchedule.examPeriod'])
            ->get();

        $cleaned = 0;

        foreach ($candidates as $session) {
            $schedule = $session->examSchedule;

            if ($schedule === null || $session->started_at === null) {
                continue;
            }

            $activity = $session->last_activity_at ?? $session->started_at;

            // 1) Sinyal utama: tidak ada aktivitas (nadi terakhir) lebih dari
            //    grace cleanup → hampir pasti macet/terbengkalai.
            $stuckAt = $activity->copy()->addMinutes($this->cleanupGraceMinutes());

            if (! now()->gt($stuckAt)) {
                continue;
            }

            // 2) Fail-safe: jangan pernah finalisasi sebelum deadline mapel
            //    individual + grace period berakhir. Melindungi sesi susulan
            //    (deadline_type=duration) serta siswa yang baru saja mulai.
            $deadline = $session->deadline($schedule);

            if (! now()->gt($deadline->copy()->addMinutes($deadlineGrace))) {
                continue;
            }

            $result = $grading->finalize($session, $schedule);

            $session->update([
                'status' => ExamSession::STATUS_TIMED_OUT,
                'timed_out_at' => now(),
            ]);

            Log::warning('Sesi ujian macet di-cleanup', [
                'exam_session_id' => $session->id,
                'student_id' => $session->student_id,
                'exam_schedule_id' => $session->exam_schedule_id,
                'started_at' => $session->started_at?->toDateTimeString(),
                'last_activity_at' => $session->last_activity_at?->toDateTimeString(),
                'stuck_at' => $stuckAt->toDateTimeString(),
                'total_score' => $result->total_score,
                'timed_out_at' => now()->toDateTimeString(),
            ]);

            $cleaned++;
        }

        $this->info("Sesi macet di-cleanup: {$cleaned}.");

        return Command::SUCCESS;
    }

    private function cleanupGraceMinutes(): int
    {
        return (int) config('exam.cleanup_grace_minutes', 30);
    }
}

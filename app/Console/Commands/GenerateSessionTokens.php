<?php

namespace App\Console\Commands;

use App\Models\ExamPeriod;
use App\Models\ExamToken;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GenerateSessionTokens extends Command
{
    protected $signature = 'tokens:rotate';

    protected $description = 'Generate new rotation tokens for active exam periods every minute';

    public function handle(): int
    {
        $lock = Cache::lock('tokens-rotate-lock', 120);

        if (! $lock->get()) {
            $this->warn('tokens:rotate SKIP — previous run still active at '.now()->toDateTimeString());
            Log::warning('tokens:rotate skipped — previous execution still running');

            return self::FAILURE;
        }

        try {
            $this->rotate();
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }

    private function rotate(): void
    {
        $now = now();
        $today = $now->toDateString();
        $currentTime = $now->format('H:i:s');
        $windowEdge = $now->copy()->addMinutes(5)->format('H:i:s');

        $activePeriods = ExamPeriod::query()
            ->where('exam_date', $today)
            ->where('start_time', '<=', $windowEdge)
            ->where('end_time', '>', $currentTime)
            ->get();

        foreach ($activePeriods as $period) {
            $this->rotateForPeriod($period, $now);
        }

        if ($activePeriods->isEmpty()) {
            $this->line('No active periods. Nothing to rotate.');
        }
    }

    private function rotateForPeriod(ExamPeriod $period, Carbon $now): void
    {
        $periodStart = Carbon::parse($period->exam_date->format('Y-m-d').' '.$period->start_time);
        $periodEnd = Carbon::parse($period->exam_date->format('Y-m-d').' '.$period->end_time);

        $tokenWindowStart = $periodStart->copy()->subMinutes(5);

        if ($now->lt($tokenWindowStart) || $now->gte($periodEnd)) {
            return;
        }

        $minutesSinceTokenStart = (int) (($now->getTimestamp() - $tokenWindowStart->getTimestamp()) / 60);
        $windowIndex = intdiv(max(0, $minutesSinceTokenStart), 15);

        $existing = ExamToken::where('exam_period_id', $period->id)
            ->where('rotation_index', $windowIndex)
            ->exists();

        if ($existing) {
            return;
        }

        $validFrom = $tokenWindowStart->copy()->addMinutes($windowIndex * 15);
        $validUntil = $validFrom->copy()->addMinutes(15);

        ExamToken::create([
            'exam_period_id' => $period->id,
            'token_code' => strtoupper(Str::random(8)),
            'rotation_index' => $windowIndex,
            'valid_from' => $validFrom,
            'valid_until' => $validUntil,
        ]);

        $this->info("Period [{$period->name}] window {$windowIndex}: token generated ({$validFrom->format('H:i')} - {$validUntil->format('H:i')})");
    }
}

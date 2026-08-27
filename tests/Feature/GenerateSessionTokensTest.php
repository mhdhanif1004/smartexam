<?php

namespace Tests\Feature;

use App\Console\Commands\GenerateSessionTokens;
use App\Models\ExamPeriod;
use App\Models\ExamToken;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateSessionTokensTest extends TestCase
{
    use RefreshDatabase;

    private ExamPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->period = ExamPeriod::create([
            'name' => 'UAS Sesi 1',
            'name_prefix' => 'UAS-S1',
            'grade_level' => null,
            'session_number' => 1,
            'exam_date' => '2026-08-20',
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
        ]);
    }

    public function test_tokens_rotate_increments_window_index_over_time(): void
    {
        $examDate = '2026-08-20';

        $checkpoints = [
            ['08:50:00', -1, '5 min before window start — no token yet'],
            ['08:55:00', 0, 'window starts (5 min before session)'],
            ['09:00:00', 0, 'session starts — still window 0 (5 min elapsed from 08:55)'],
            ['09:05:00', 0, '10 min elapsed — still window 0'],
            ['09:12:00', 1, '17 min elapsed — rotated to window 1'],
            ['09:25:00', 2, '30 min elapsed — rotated to window 2'],
            ['09:40:00', 3, '45 min elapsed — rotated to window 3'],
        ];

        foreach ($checkpoints as [$time, $expectedWindow, $description]) {
            $now = Carbon::parse("{$examDate} {$time}");
            Carbon::setTestNow($now);
            $this->artisan(GenerateSessionTokens::class);

            if ($expectedWindow < 0) {
                $this->assertDatabaseCount('exam_tokens', 0, null,
                    "At {$time} ({$description}): expected no token");

                $activeToken = ExamToken::where('exam_period_id', $this->period->id)
                    ->where('valid_from', '<=', $now)
                    ->where('valid_until', '>', $now)
                    ->first();
                $this->assertNull($activeToken,
                    "At {$time} ({$description}): no active token should exist");
            } else {
                $this->assertDatabaseHas('exam_tokens', [
                    'exam_period_id' => $this->period->id,
                    'rotation_index' => $expectedWindow,
                ], null, "At {$time} ({$description}): expected token with rotation_index={$expectedWindow}");

                $activeToken = ExamToken::where('exam_period_id', $this->period->id)
                    ->where('valid_from', '<=', $now)
                    ->where('valid_until', '>', $now)
                    ->first();
                $this->assertNotNull($activeToken,
                    "At {$time} ({$description}): expected an active token");
                $this->assertEquals($expectedWindow, $activeToken->rotation_index,
                    "At {$time} ({$description}): active token should be window {$expectedWindow}");
            }
        }

        $this->assertDatabaseCount('exam_tokens', 4);

        $tokens = ExamToken::where('exam_period_id', $this->period->id)
            ->orderBy('rotation_index')
            ->pluck('rotation_index')
            ->toArray();
        $this->assertEquals([0, 1, 2, 3], $tokens);

        Carbon::setTestNow();
    }

    public function test_duplicate_window_index_not_created(): void
    {
        $examDate = '2026-08-20';

        Carbon::setTestNow(Carbon::parse("{$examDate} 09:05:00"));
        $this->artisan(GenerateSessionTokens::class);
        $this->artisan(GenerateSessionTokens::class);

        $this->assertDatabaseCount('exam_tokens', 1);
        $this->assertDatabaseHas('exam_tokens', [
            'exam_period_id' => $this->period->id,
            'rotation_index' => 0,
        ]);

        Carbon::setTestNow();
    }

    public function test_no_tokens_outside_exam_period(): void
    {
        $examDate = '2026-08-20';

        Carbon::setTestNow(Carbon::parse("{$examDate} 07:00:00"));
        $this->artisan(GenerateSessionTokens::class);
        $this->assertDatabaseCount('exam_tokens', 0);

        Carbon::setTestNow(Carbon::parse("{$examDate} 12:30:00"));
        $this->artisan(GenerateSessionTokens::class);
        $this->assertDatabaseCount('exam_tokens', 0);

        Carbon::setTestNow();
    }
}

<?php

namespace Tests\Feature;

use App\Models\ExamPeriod;
use App\Models\ExamSchedule;
use App\Models\ExamToken;
use App\Models\Room;
use App\Models\Subject;
use App\Models\Supervisor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TokenDelayedWarningTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    private Supervisor $supervisor;

    private ExamPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-26 13:00:00'));

        $this->room = Room::factory()->create();
        $this->supervisor = Supervisor::factory()->create(['room_id' => $this->room->id]);

        $this->period = ExamPeriod::create([
            'name' => 'Sesi 1',
            'name_prefix' => 'S1',
            'grade_level' => null,
            'session_number' => 1,
            'exam_date' => now()->toDateString(),
            'start_time' => '14:00:00',
            'end_time' => '16:00:00',
        ]);

        ExamSchedule::factory()->create([
            'room_id' => $this->room->id,
            'subject_id' => Subject::factory()->create(['name' => 'Matematika'])->id,
            'class_name' => 'XI RPL 1',
            'exam_period_id' => $this->period->id,
            'exam_date' => now()->toDateString(),
            'start_time' => '14:00:00',
            'end_time' => '16:00:00',
            'duration_minutes' => 90,
            'status' => ExamSchedule::STATUS_SCHEDULED,
        ]);

        DB::table('supervisor_room_assignments')->insert([
            'exam_period_id' => $this->period->id,
            'exam_date' => now()->toDateString(),
            'supervisor_id' => $this->supervisor->id,
            'room_id' => $this->room->id,
            'rotation_index' => 1,
        ]);

        Carbon::setTestNow(null);
    }

    public function test_warning_shows_when_token_window_open_but_no_token(): void
    {
        // 14:00 = exactly period start → token window opened at 13:55
        // No token in DB → should show warning
        Carbon::setTestNow(Carbon::parse('2026-08-26 14:00:00'));

        $this->actingAs($this->supervisor->user)
            ->get(route('pengawas.tokens.index'))
            ->assertOk()
            ->assertSee('Token belum ter-generate')
            ->assertSee('scheduler sistem');
    }

    public function test_warning_shows_during_session_without_token(): void
    {
        // 14:30 = well into the session, still no token
        Carbon::setTestNow(Carbon::parse('2026-08-26 14:30:00'));

        $this->actingAs($this->supervisor->user)
            ->get(route('pengawas.tokens.index'))
            ->assertOk()
            ->assertSee('Token belum ter-generate');
    }

    public function test_no_warning_when_token_exists(): void
    {
        // Token window is open (14:00), AND a token exists → no warning
        Carbon::setTestNow(Carbon::parse('2026-08-26 14:00:00'));

        ExamToken::create([
            'exam_period_id' => $this->period->id,
            'token_code' => 'ABC12345',
            'rotation_index' => 0,
            'valid_from' => now()->subMinute(),
            'valid_until' => now()->addMinutes(14),
        ]);

        $this->actingAs($this->supervisor->user)
            ->get(route('pengawas.tokens.index'))
            ->assertOk()
            ->assertDontSee('Token belum ter-generate')
            ->assertSee('ABC12345');
    }

    public function test_no_warning_before_token_window_opens(): void
    {
        // 13:50 = 10 min before start, token window opens at 13:55
        // Not yet in window → no warning (period not active yet → $period is null)
        Carbon::setTestNow(Carbon::parse('2026-08-26 13:50:00'));

        $this->actingAs($this->supervisor->user)
            ->get(route('pengawas.tokens.index'))
            ->assertOk()
            ->assertDontSee('Token belum ter-generate')
            ->assertSee('Tidak ada sesi ujian');
    }

    public function test_warning_shows_exact_window_open_time(): void
    {
        // 13:55 = exactly 5 min before start = token window opens
        // No token → warning should show
        Carbon::setTestNow(Carbon::parse('2026-08-26 13:55:00'));

        $this->actingAs($this->supervisor->user)
            ->get(route('pengawas.tokens.index'))
            ->assertOk()
            ->assertSee('Token belum ter-generate')
            ->assertSee('13:55');
    }
}

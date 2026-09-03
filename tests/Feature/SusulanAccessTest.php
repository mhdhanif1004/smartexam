<?php

namespace Tests\Feature;

use App\Models\ExamPeriod;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SusulanAccessTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;

    private Room $room;

    private Subject $subject;

    private ExamPeriod $period;

    private ExamSchedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-15 10:00:00'));

        $this->student = Student::factory()->create(['class_name' => 'XI RPL 1']);
        $this->room = Room::factory()->create();
        $this->student->update(['room_id' => $this->room->id]);

        $this->subject = Subject::factory()->create();

        $this->period = ExamPeriod::create([
            'name' => 'Sesi Pagi',
            'name_prefix' => 'SP',
            'grade_level' => null,
            'session_number' => 1,
            'exam_date' => now()->toDateString(),
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
        ]);

        $this->schedule = ExamSchedule::factory()->create([
            'room_id' => $this->room->id,
            'subject_id' => $this->subject->id,
            'class_name' => 'XI RPL 1',
            'exam_period_id' => $this->period->id,
            'exam_date' => now()->toDateString(),
            'start_time' => '08:00:00',
            'end_time' => '09:30:00',
            'duration_minutes' => 60,
            'status' => ExamSchedule::STATUS_FINISHED,
        ]);

        DB::table('exam_room_assignments')->insert([
            'exam_period_id' => $this->period->id,
            'student_id' => $this->student->id,
            'room_id' => $this->room->id,
            'seat_number' => 1,
        ]);

        $question = Question::factory()->create(['subject_id' => $this->subject->id]);
        $question->classrooms()->sync($this->student->classroom_id);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function confirmedSession(string $status, array $overrides = []): ExamSession
    {
        return ExamSession::create(array_merge([
            'student_id' => $this->student->id,
            'exam_schedule_id' => $this->schedule->id,
            'status' => $status,
            'deadline_type' => ExamSession::DEADLINE_TYPE_DURATION,
            'attendance_confirmed' => true,
        ], $overrides));
    }

    public function test_within_period_window_shows_susulan_and_allows_access(): void
    {
        $this->assertSame(ExamSchedule::STATUS_FINISHED, $this->schedule->computedStatus());
        $this->assertTrue($this->schedule->isWithinPeriodWindow());

        $session = $this->confirmedSession(ExamSession::STATUS_NOT_STARTED);

        $tokenUrl = route('peserta.exams.token', $this->schedule);

        $this->actingAs($this->student->user)
            ->get(route('peserta.dashboard'))
            ->assertOk()
            ->assertSee('Susulan')
            ->assertSee($tokenUrl, false);

        $response = $this->actingAs($this->student->user)->get($tokenUrl);

        $response->assertOk();
        $response->assertViewIs('peserta.exams.token');
        $response->assertViewHas('schedule', fn (ExamSchedule $viewSchedule) => $viewSchedule->is($this->schedule));
        $response->assertViewHas('accessError', null);

        $this->assertDatabaseHas('exam_sessions', [
            'id' => $session->id,
            'status' => ExamSession::STATUS_NOT_STARTED,
        ]);
    }

    public function test_completed_session_stays_selesai_not_susulan(): void
    {
        $this->confirmedSession(ExamSession::STATUS_COMPLETED, [
            'started_at' => Carbon::parse('2026-08-15 08:05:00'),
            'finished_at' => Carbon::parse('2026-08-15 09:00:00'),
        ]);

        $tokenUrl = route('peserta.exams.token', $this->schedule);
        $finishedUrl = route('peserta.exams.finished', $this->schedule);

        $this->actingAs($this->student->user)
            ->get(route('peserta.dashboard'))
            ->assertOk()
            ->assertDontSee('Susulan')
            ->assertDontSee($tokenUrl, false)
            ->assertSee($finishedUrl, false);

        $this->actingAs($this->student->user)
            ->get($tokenUrl)
            ->assertRedirect($finishedUrl);
    }

    public function test_past_period_shows_terlewat_and_denies_access(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-15 13:00:00'));

        $this->assertSame(ExamSchedule::STATUS_FINISHED, $this->schedule->computedStatus());
        $this->assertFalse($this->schedule->isWithinPeriodWindow());

        $this->confirmedSession(ExamSession::STATUS_NOT_STARTED);

        $tokenUrl = route('peserta.exams.token', $this->schedule);

        $this->actingAs($this->student->user)
            ->get(route('peserta.dashboard'))
            ->assertOk()
            ->assertSee('Terlewat')
            ->assertDontSee($tokenUrl, false);

        $response = $this->actingAs($this->student->user)->get($tokenUrl);

        $response->assertRedirect(route('peserta.dashboard'));
        $response->assertSessionHas('error', fn (string $message) => str_contains($message, 'berakhir'));
    }

    public function test_susulan_timer_uses_session_remaining(): void
    {
        $this->schedule->update(['duration_minutes' => 90]);

        $this->assertTrue($this->schedule->isWithinPeriodWindow());

        $session = $this->confirmedSession(ExamSession::STATUS_IN_PROGRESS, [
            'started_at' => Carbon::parse('2026-08-15 09:50:00'),
        ]);

        $response = $this->actingAs($this->student->user)
            ->get(route('peserta.exams.work', $this->schedule));

        $response->assertOk();
        $response->assertViewIs('peserta.exams.work');

        $periodEnd = Carbon::parse('2026-08-15 12:00:00');
        $periodStart = Carbon::parse('2026-08-15 08:00:00');
        $mapelDeadline = Carbon::parse('2026-08-15 09:50:00')->addMinutes(90);

        $graceMinutes = config('exam.grace_period_minutes', 10);
        $graceEnd = $periodEnd->copy()->addMinutes($graceMinutes);
        // Tahap 1: sisa waktu resmi sesi (periodEnd), tanpa grace.
        $response->assertViewHas('remainingSession', max(0, $periodEnd->getTimestamp() - now()->getTimestamp()));
        $this->assertSame(7200, $periodEnd->getTimestamp() - now()->getTimestamp());
        // Tahap 2: sisa masa toleransi (periodEnd + grace).
        $response->assertViewHas('remainingGrace', max(0, $graceEnd->getTimestamp() - now()->getTimestamp()));

        $response->assertViewHas('totalSessionSeconds', $periodEnd->getTimestamp() - $periodStart->getTimestamp());
        $response->assertViewHas('deadline', $mapelDeadline->getTimestamp());
        $response->assertViewHas(
            'session',
            fn (ExamSession $viewSession) => $viewSession->is($session) && $viewSession->started_at->equalTo($mapelDeadline->subMinutes(90))
        );

        $this->assertDatabaseHas('exam_sessions', [
            'id' => $session->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
        ]);
    }
}

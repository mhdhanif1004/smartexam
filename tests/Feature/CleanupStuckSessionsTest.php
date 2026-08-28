<?php

namespace Tests\Feature;

use App\Models\ExamAnswer;
use App\Models\ExamPeriod;
use App\Models\ExamResult;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CleanupStuckSessionsTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;

    private Room $room;

    private Subject $subject1;

    private Subject $subject2;

    private ExamPeriod $period;

    private ExamSchedule $schedule1;

    private ExamSchedule $schedule2;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-20 10:00:00'));

        $this->student = Student::factory()->create(['class_name' => 'XI RPL 1']);
        $this->room = Room::factory()->create();
        $this->student->update(['room_id' => $this->room->id]);

        $this->subject1 = Subject::factory()->create(['name' => 'Bahasa Indonesia']);
        $this->subject2 = Subject::factory()->create(['name' => 'Bahasa Inggris']);

        $this->period = ExamPeriod::create([
            'name' => 'Sesi Pagi',
            'name_prefix' => 'SP',
            'grade_level' => null,
            'session_number' => 1,
            'exam_date' => now()->toDateString(),
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
        ]);

        // Mapel 1: sedang dikerjakan (08:00-09:00), dijadikan "stuck"
        $this->schedule1 = ExamSchedule::factory()->create([
            'room_id' => $this->room->id,
            'subject_id' => $this->subject1->id,
            'class_name' => 'XI RPL 1',
            'exam_period_id' => $this->period->id,
            'exam_date' => now()->toDateString(),
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'duration_minutes' => 60,
            'status' => ExamSchedule::STATUS_ONGOING,
        ]);

        // Mapel 2: SCHEDULED secara waktu (mulai 10:30), target early-start
        $this->schedule2 = ExamSchedule::factory()->create([
            'room_id' => $this->room->id,
            'subject_id' => $this->subject2->id,
            'class_name' => 'XI RPL 1',
            'exam_period_id' => $this->period->id,
            'exam_date' => now()->toDateString(),
            'start_time' => '10:30:00',
            'end_time' => '11:30:00',
            'duration_minutes' => 60,
            'status' => ExamSchedule::STATUS_SCHEDULED,
        ]);

        DB::table('exam_room_assignments')->insert([
            'exam_period_id' => $this->period->id,
            'student_id' => $this->student->id,
            'room_id' => $this->room->id,
            'seat_number' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function questionFor(Subject $subject, int $scoreWeight = 10): Question
    {
        $question = Question::factory()->create([
            'subject_id' => $subject->id,
            'type' => 'single_choice',
            'answer_key' => 'A',
            'score_weight' => $scoreWeight,
        ]);

        $question->classrooms()->sync($this->student->classroom_id);

        return $question;
    }

    /**
     * Sesi in_progress dengan last_activity_at jauh (35 menit) dan deadline
     * mapel sudah lewat + grace → di-cleanup menjadi timed_out, finished_at
     * terisi, dan ExamResult dihitung dari jawaban yang tersimpan.
     */
    public function test_stuck_session_is_cleaned_up_to_timed_out_and_graded(): void
    {
        $question = $this->questionFor($this->subject1);

        $session = ExamSession::create([
            'student_id' => $this->student->id,
            'exam_schedule_id' => $this->schedule1->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'attendance_confirmed' => true,
            'started_at' => Carbon::parse('2026-08-20 08:05:00'),
            'last_activity_at' => now()->subMinutes(35),
            // deadline_type default NULL → duration = started_at + 60m = 09:05
        ]);

        // Jawaban yang sudah tersimpan di DB (siswa crash setelah mengetik)
        ExamAnswer::create([
            'exam_session_id' => $session->id,
            'question_id' => $question->id,
            'student_answer' => 'A',
        ]);

        Log::spy();

        $this->artisan('sessions:cleanup-stuck')->assertExitCode(0);

        $session->refresh();

        $this->assertSame(ExamSession::STATUS_TIMED_OUT, $session->status);
        $this->assertNotNull($session->finished_at);
        $this->assertNotNull($session->timed_out_at);
        $this->assertTrue($session->isTerminal());

        $result = ExamResult::where('exam_session_id', $session->id)->first();
        $this->assertNotNull($result);
        $this->assertSame(10.0, (float) $result->total_score);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context) use ($session) {
                return $message === 'Sesi ujian macet di-cleanup'
                    && isset($context['exam_session_id'], $context['student_id'], $context['total_score'])
                    && $context['exam_session_id'] === $session->id;
            });
    }

    /**
     * Sesi yang baru saja aktif (last_activity_at 5 menit lalu) TIDAK
     * di-cleanup, meski deadline mapel sudah lewat duluan. Nadi terakhir
     * (heartbeat) dominan.
     */
    public function test_active_session_is_not_cleaned_even_when_deadline_passed(): void
    {
        // started_at sangat lama (3 jam lalu) sehingga deadline (started_at+60m)
        // sudah lewat berjam-jam, tapi last_activity_at masih segar (5 menit).
        $session = ExamSession::create([
            'student_id' => $this->student->id,
            'exam_schedule_id' => $this->schedule1->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'attendance_confirmed' => true,
            'started_at' => now()->subHours(3),
            'last_activity_at' => now()->subMinutes(5),
        ]);

        $this->artisan('sessions:cleanup-stuck')->assertExitCode(0);

        $this->assertSame(ExamSession::STATUS_IN_PROGRESS, $session->fresh()->status);
        $this->assertNull($session->fresh()->timed_out_at);
    }

    /**
     * Sesi NOT_STARTED (tanpa started_at) tidak tersentuh sama sekali oleh
     * command cleanup.
     */
    public function test_not_started_session_is_untouched(): void
    {
        $session = ExamSession::create([
            'student_id' => $this->student->id,
            'exam_schedule_id' => $this->schedule2->id,
            'status' => ExamSession::STATUS_NOT_STARTED,
            'attendance_confirmed' => true,
        ]);

        $this->artisan('sessions:cleanup-stuck')->assertExitCode(0);

        $this->assertSame(ExamSession::STATUS_NOT_STARTED, $session->fresh()->status);
        $this->assertNull($session->fresh()->timed_out_at);
    }

    /**
     * Setelah sesi macet di-cleanup menjadi timed_out, early-start ke mapel
     * lain terbuka: hasActiveSessionInPeriod() jadi false dan dashboard
     * menampilkan "Bisa Dimulai" untuk mapel berikutnya.
     */
    public function test_cleanup_frees_early_start_to_next_mapel(): void
    {
        // Mapel 1 stuck (in_progress, tidak ada aktivitas lama)
        ExamSession::create([
            'student_id' => $this->student->id,
            'exam_schedule_id' => $this->schedule1->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'attendance_confirmed' => true,
            'started_at' => Carbon::parse('2026-08-20 08:05:00'),
            'last_activity_at' => now()->subMinutes(40),
        ]);

        // Sebelum cleanup: mapel 2 masih "Belum Mulai" (ada in_progress lain)
        $tokenUrl2 = route('peserta.exams.token', $this->schedule2);
        $this->actingAs($this->student->user)
            ->get(route('peserta.dashboard'))
            ->assertOk()
            ->assertSee('Belum Mulai')
            ->assertDontSee($tokenUrl2, false);

        $this->artisan('sessions:cleanup-stuck')->assertExitCode(0);

        // Sesudah cleanup: mapel 1 jadi timed_out, early-start mapel 2 terbuka
        $this->actingAs($this->student->user)
            ->get(route('peserta.dashboard'))
            ->assertOk()
            ->assertSee('Bisa Dimulai')
            ->assertSee($tokenUrl2, false);

        $this->assertTrue($this->schedule2->isWithinPeriodWindow());
        $this->assertFalse($this->schedule2->computedStatus() === ExamSchedule::STATUS_FINISHED);
    }

    /**
     * Log::warning tercatat untuk setiap sesi yang di-cleanup.
     */
    public function test_log_warning_recorded_per_cleanup(): void
    {
        ExamSession::create([
            'student_id' => $this->student->id,
            'exam_schedule_id' => $this->schedule1->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'attendance_confirmed' => true,
            'started_at' => Carbon::parse('2026-08-20 08:05:00'),
            'last_activity_at' => now()->subMinutes(35),
        ]);

        Log::spy();

        $this->artisan('sessions:cleanup-stuck')->assertExitCode(0);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return $message === 'Sesi ujian macet di-cleanup'
                    && isset($context['exam_session_id'], $context['student_id'], $context['total_score']);
            });
    }
}

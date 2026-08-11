<?php

namespace Tests\Feature;

use App\Models\ExamAnswer;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DoubtfulQuestionTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;

    private User $user;

    private Subject $subject;

    private ExamSchedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-10 09:00:00'));

        $this->student = Student::factory()->create(['class_name' => 'XI RPL 1']);
        $room = Room::factory()->create();
        $this->student->update(['room_id' => $room->id]);
        $this->user = $this->student->user;
        $this->subject = Subject::factory()->create(['name' => 'Matematika']);
        $this->schedule = ExamSchedule::factory()->create([
            'subject_id' => $this->subject->id,
            'room_id' => $this->student->room_id,
            'class_name' => 'XI RPL 1',
            'exam_date' => now()->toDateString(),
            'start_time' => '08:30:00',
            'end_time' => '10:30:00',
            'duration_minutes' => 60,
            'status' => 'ongoing',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function beginSession(): ExamSession
    {
        return ExamSession::create([
            'student_id' => $this->student->id,
            'exam_schedule_id' => $this->schedule->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);
    }

    private function question(): Question
    {
        return Question::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'options' => ['A' => 'Apa', 'B' => 'Bapa'],
            'answer_key' => 'A',
        ]);
    }

    private function toggle(int $questionId): TestResponse
    {
        return $this->actingAs($this->user)->postJson(
            route('peserta.exams.questions.toggle-doubtful', [$this->schedule->id, $questionId])
        );
    }

    public function test_toggle_doubtful_marks_question_in_database(): void
    {
        $session = $this->beginSession();
        $question = $this->question();

        $this->toggle($question->id)->assertOk()
            ->assertJson(['ok' => true, 'question_id' => $question->id, 'is_doubtful' => true]);

        $this->assertDatabaseHas('exam_answers', [
            'exam_session_id' => $session->id,
            'question_id' => $question->id,
            'student_answer' => null,
            'is_doubtful' => true,
        ]);
    }

    public function test_toggle_doubtful_again_unmarks_question(): void
    {
        $session = $this->beginSession();
        $question = $this->question();

        $this->toggle($question->id)->assertOk();
        $this->toggle($question->id)->assertOk()
            ->assertJson(['ok' => true, 'question_id' => $question->id, 'is_doubtful' => false]);

        $this->assertDatabaseMissing('exam_answers', [
            'exam_session_id' => $session->id,
            'question_id' => $question->id,
        ]);
    }

    public function test_doubtful_state_persists_across_work_page_reload(): void
    {
        $this->beginSession();
        $question = $this->question();

        $this->toggle($question->id)->assertOk();

        $this->actingAs($this->user)
            ->get(route('peserta.exams.work', $this->schedule->id))
            ->assertOk()
            ->assertViewHas('doubtfulQuestions', function ($doubtful) use ($question) {
                return $doubtful->has($question->id) && $doubtful[$question->id] === true;
            });
    }

    public function test_doubtful_on_answered_question_keeps_answer(): void
    {
        $session = $this->beginSession();
        $question = $this->question();

        $this->actingAs($this->user)->postJson(route('peserta.exams.save-answer', $this->schedule->id), [
            'answers' => [$question->id => 'A'],
        ])->assertOk();

        $this->toggle($question->id)->assertOk()->assertJson(['is_doubtful' => true]);

        $saved = ExamAnswer::where('exam_session_id', $session->id)->where('question_id', $question->id)->first();
        $this->assertSame('A', $saved->student_answer);
        $this->assertTrue($saved->is_doubtful);

        $this->toggle($question->id)->assertOk()->assertJson(['is_doubtful' => false]);

        $saved->refresh();
        $this->assertSame('A', $saved->student_answer);
        $this->assertFalse($saved->is_doubtful);
    }

    public function test_toggle_doubtful_rejects_question_outside_exam(): void
    {
        $this->beginSession();
        $foreign = Subject::factory()->create();
        $foreignQuestion = Question::factory()->create([
            'subject_id' => $foreign->id,
            'type' => Question::TYPE_SINGLE_CHOICE,
        ]);

        $this->toggle($foreignQuestion->id)->assertStatus(422);
        $this->assertDatabaseMissing('exam_answers', ['question_id' => $foreignQuestion->id]);
    }
}

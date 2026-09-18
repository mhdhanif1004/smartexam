<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ExamPeriod;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\ExamSessionQuestionOrder;
use App\Models\ExamType;
use App\Models\GuruMapel;
use App\Models\Question;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeacherSubjectClassAssignment;
use App\Services\QuestionOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionRandomizationTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroom;

    private Subject $subject;

    private ExamPeriod $period;

    private ExamSchedule $schedule;

    private QuestionOrderService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classroom = Classroom::factory()->create(['name' => 'XI RPL 1']);
        $this->subject = Subject::factory()->create(['name' => 'Matematika']);

        $guru = GuruMapel::factory()->create();
        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => $this->subject->id,
            'classroom_id' => $this->classroom->id,
        ]);

        $harian = ExamType::where('code', 'harian')->first();

        $start = now()->subMinutes(30);
        $this->period = ExamPeriod::create([
            'name' => 'Harian Matematika',
            'name_prefix' => 'Harian Matematika',
            'exam_type_id' => $harian?->id,
            'grade_level' => 'XI',
            'exam_date' => now()->toDateString(),
            'start_time' => $start->format('H:i:s'),
            'end_time' => $start->copy()->addHours(4)->format('H:i:s'),
            'session_number' => 1,
        ]);

        $this->schedule = ExamSchedule::create([
            'subject_id' => $this->subject->id,
            'room_id' => null,
            'classroom_id' => $this->classroom->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => now()->toDateString(),
            'start_time' => now()->subHour()->format('H:i:s'),
            'end_time' => now()->addHour()->format('H:i:s'),
            'duration_minutes' => 60,
            'status' => ExamSchedule::STATUS_ONGOING,
            'exam_period_id' => $this->period->id,
            'is_random_question_order' => true,
        ]);

        $this->service = app(QuestionOrderService::class);
    }

    private function createQuestions(int $count): array
    {
        $questions = [];
        for ($i = 1; $i <= $count; $i++) {
            $q = Question::factory()->create([
                'subject_id' => $this->subject->id,
                'type' => 'single_choice',
                'question_text' => "Soal {$i}",
                'is_active' => true,
            ]);
            $q->classrooms()->attach($this->classroom->id);
            $questions[] = $q;
        }

        return $questions;
    }

    public function test_order_generated_once_and_consistent_across_requests(): void
    {
        $questions = $this->createQuestions(10);

        $student = Student::factory()->create(['classroom_id' => $this->classroom->id]);
        $session = ExamSession::factory()->create([
            'student_id' => $student->id,
            'exam_schedule_id' => $this->schedule->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'started_at' => now(),
            'attendance_confirmed' => true,
            'attendance_status' => ExamSession::ATTENDANCE_PRESENT,
        ]);

        $firstOrder = $this->service->orderedQuestionsFor($session, $this->schedule, $this->classroom->id);
        $secondOrder = $this->service->orderedQuestionsFor($session, $this->schedule, $this->classroom->id);

        $this->assertEquals(
            $firstOrder->pluck('id')->toArray(),
            $secondOrder->pluck('id')->toArray(),
            'Order should remain consistent across multiple calls'
        );

        $this->assertDatabaseCount('exam_session_question_orders', 10);
    }

    public function test_different_students_get_different_orders(): void
    {
        $questions = $this->createQuestions(20);

        $student1 = Student::factory()->create(['classroom_id' => $this->classroom->id]);
        $student2 = Student::factory()->create(['classroom_id' => $this->classroom->id]);

        $session1 = ExamSession::factory()->create([
            'student_id' => $student1->id,
            'exam_schedule_id' => $this->schedule->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'started_at' => now(),
            'attendance_confirmed' => true,
            'attendance_status' => ExamSession::ATTENDANCE_PRESENT,
        ]);

        $session2 = ExamSession::factory()->create([
            'student_id' => $student2->id,
            'exam_schedule_id' => $this->schedule->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'started_at' => now(),
            'attendance_confirmed' => true,
            'attendance_status' => ExamSession::ATTENDANCE_PRESENT,
        ]);

        $order1 = $this->service->orderedQuestionsFor($session1, $this->schedule, $this->classroom->id);
        $order2 = $this->service->orderedQuestionsFor($session2, $this->schedule, $this->classroom->id);

        $this->assertNotEquals(
            $order1->pluck('id')->toArray(),
            $order2->pluck('id')->toArray(),
            'Different students should have different question orders'
        );
    }

    public function test_unanswered_question_numbers_match_displayed_order(): void
    {
        $questions = $this->createQuestions(5);

        $student = Student::factory()->create(['classroom_id' => $this->classroom->id]);
        $session = ExamSession::factory()->create([
            'student_id' => $student->id,
            'exam_schedule_id' => $this->schedule->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'started_at' => now(),
            'attendance_confirmed' => true,
            'attendance_status' => ExamSession::ATTENDANCE_PRESENT,
        ]);

        $orderedQuestions = $this->service->orderedQuestionsFor($session, $this->schedule, $this->classroom->id);

        $this->actingAs($student->user);

        $response = $this->get(route('peserta.exams.work', $this->schedule->id));
        $response->assertOk();

        $questionsData = $response->viewData('questionsData');
        $this->assertEquals(
            $orderedQuestions->pluck('id')->toArray(),
            $questionsData->pluck('id')->toArray()
        );
    }

    public function test_new_question_added_after_session_started_appears_at_end(): void
    {
        $questions = $this->createQuestions(5);

        $student = Student::factory()->create(['classroom_id' => $this->classroom->id]);
        $session = ExamSession::factory()->create([
            'student_id' => $student->id,
            'exam_schedule_id' => $this->schedule->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);

        $initialOrder = $this->service->orderedQuestionsFor($session, $this->schedule, $this->classroom->id);
        $this->assertCount(5, $initialOrder);

        $newQuestion = Question::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => 'single_choice',
            'question_text' => 'Soal Baru',
            'is_active' => true,
        ]);
        $newQuestion->classrooms()->attach($this->classroom->id);

        $updatedOrder = $this->service->orderedQuestionsFor($session, $this->schedule, $this->classroom->id);
        $this->assertCount(6, $updatedOrder);

        $this->assertEquals($newQuestion->id, $updatedOrder->last()->id);
        $this->assertEquals(6, ExamSessionQuestionOrder::where('exam_session_id', $session->id)->where('question_id', $newQuestion->id)->value('urutan'));
    }

    public function test_toggle_false_keeps_id_order_and_does_not_create_order_rows(): void
    {
        $this->schedule->update(['is_random_question_order' => false]);
        $this->createQuestions(5);

        $student = Student::factory()->create(['classroom_id' => $this->classroom->id]);
        $session = ExamSession::factory()->create([
            'student_id' => $student->id,
            'exam_schedule_id' => $this->schedule->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);

        $result = $this->service->orderedQuestionsFor($session, $this->schedule->fresh(), $this->classroom->id);

        $this->assertSame(
            $result->pluck('id')->sort()->values()->all(),
            $result->pluck('id')->values()->all(),
        );
        $this->assertDatabaseCount('exam_session_question_orders', 0);
    }

    public function test_race_duplicate_insert_for_missing_question_does_not_throw(): void
    {
        $this->createQuestions(5);

        $student = Student::factory()->create(['classroom_id' => $this->classroom->id]);
        $session = ExamSession::factory()->create([
            'student_id' => $student->id,
            'exam_schedule_id' => $this->schedule->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);

        $this->service->orderedQuestionsFor($session, $this->schedule, $this->classroom->id);

        // Guru menambah soal di tengah sesi.
        $newQuestion = Question::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => 'single_choice',
            'is_active' => true,
        ]);
        $newQuestion->classrooms()->attach($this->classroom->id);

        // Simulasi race: request lain sudah append soal baru ini.
        ExamSessionQuestionOrder::create([
            'exam_session_id' => $session->id,
            'question_id' => $newQuestion->id,
            'urutan' => 6,
        ]);

        // Tidak boleh lempar exception; urutan memakai baris yang sudah tersimpan.
        $result = $this->service->orderedQuestionsFor($session, $this->schedule, $this->classroom->id);

        $this->assertCount(6, $result);
        $this->assertSame($newQuestion->id, $result->last()->id);
        $this->assertDatabaseCount('exam_session_question_orders', 6);
    }

    public function test_pre_seeded_order_rows_are_respected_without_regeneration(): void
    {
        $questions = $this->createQuestions(5);

        $student = Student::factory()->create(['classroom_id' => $this->classroom->id]);
        $session = ExamSession::factory()->create([
            'student_id' => $student->id,
            'exam_schedule_id' => $this->schedule->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);

        // Insert manual urutan (seolah-olah request lain menang race).
        $seededOrder = collect($questions)->sortByDesc('id')->pluck('id')->values();
        foreach ($seededOrder as $index => $qId) {
            ExamSessionQuestionOrder::create([
                'exam_session_id' => $session->id,
                'question_id' => $qId,
                'urutan' => $index + 1,
            ]);
        }

        $result = $this->service->orderedQuestionsFor($session, $this->schedule, $this->classroom->id);

        $this->assertSame($seededOrder->all(), $result->pluck('id')->all());
        $this->assertDatabaseCount('exam_session_question_orders', 5);
    }

    public function test_empty_questions_returns_empty_collection(): void
    {
        $student = Student::factory()->create(['classroom_id' => $this->classroom->id]);
        $session = ExamSession::factory()->create([
            'student_id' => $student->id,
            'exam_schedule_id' => $this->schedule->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);

        $result = $this->service->orderedQuestionsFor($session, $this->schedule, $this->classroom->id);

        $this->assertTrue($result->isEmpty());
    }
}

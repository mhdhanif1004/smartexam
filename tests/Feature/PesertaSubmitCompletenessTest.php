<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ExamPeriod;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\ExamType;
use App\Models\GuruMapel;
use App\Models\Question;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeacherSubjectClassAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PesertaSubmitCompletenessTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroom;

    private Student $student;

    private Subject $subject;

    private ExamPeriod $period;

    private ExamSchedule $schedule;

    private Question $q1;

    private Question $q2;

    private Question $q3;

    private ExamSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classroom = Classroom::factory()->create(['name' => 'XI RPL 1']);
        $this->student = Student::factory()->create(['classroom_id' => $this->classroom->id]);
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
            'exam_type_id' => $harian->id,
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
            'exam_period_id' => $this->period->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => now()->toDateString(),
            'start_time' => $start->format('H:i:s'),
            'end_time' => $start->copy()->addHours(2)->format('H:i:s'),
            'duration_minutes' => 120,
            'status' => ExamSchedule::STATUS_ONGOING,
        ]);

        $this->q1 = $this->makeQuestion('Pilihan ganda satu', Question::TYPE_SINGLE_CHOICE);
        $this->q2 = $this->makeQuestion('Pilihan ganda dua', Question::TYPE_SINGLE_CHOICE);
        $this->q3 = $this->makeEssayQuestion('Essay satu');

        $this->session = ExamSession::create([
            'student_id' => $this->student->id,
            'exam_schedule_id' => $this->schedule->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'started_at' => now()->subMinutes(10),
            'attendance_confirmed' => true,
            'attendance_status' => ExamSession::ATTENDANCE_PRESENT,
        ]);
    }

    private function makeQuestion(string $text, string $type): Question
    {
        $question = Question::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => $type,
            'question_text' => $text,
            'options' => ['A' => 'Satu', 'B' => 'Dua', 'C' => 'Tiga', 'D' => 'Empat'],
            'answer_key' => 'B',
            'score_weight' => 10,
            'is_active' => true,
        ]);
        $question->classrooms()->attach($this->classroom->id);

        return $question;
    }

    private function makeEssayQuestion(string $text): Question
    {
        $question = Question::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => Question::TYPE_ESSAY,
            'question_text' => $text,
            'options' => null,
            'answer_key' => null,
            'score_weight' => 10,
            'is_active' => true,
        ]);
        $question->classrooms()->attach($this->classroom->id);

        return $question;
    }

    private function submit(array $answers = [], array $overrides = [])
    {
        return $this->actingAs($this->student->user)
            ->postJson(route('peserta.exams.submit', $this->schedule->id), array_merge(['answers' => $answers], $overrides));
    }

    // ── Manual submit ditolak kalau ada soal kosong ──────────────────

    public function test_manual_submit_rejected_when_all_blank(): void
    {
        $response = $this->submit([]);

        $response->assertStatus(422);

        $unansweredIds = $response->json('unanswered_question_ids');
        $this->assertCount(3, $unansweredIds);
        $this->assertContains($this->q1->id, $unansweredIds);
        $this->assertContains($this->q2->id, $unansweredIds);
        $this->assertContains($this->q3->id, $unansweredIds);

        // Sesi belum berubah.
        $this->assertDatabaseHas('exam_sessions', [
            'id' => $this->session->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'finished_at' => null,
        ]);
    }

    public function test_manual_submit_rejected_when_one_blank_with_correct_numbers(): void
    {
        $response = $this->submit([
            $this->q1->id => 'B',
            $this->q2->id => 'D',
            // q3 essay dikosongkan
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('unanswered_question_ids', [$this->q3->id]);

        $this->assertDatabaseHas('exam_sessions', ['id' => $this->session->id, 'finished_at' => null]);
    }

    public function test_whitespace_only_essay_counts_as_unanswered(): void
    {
        $response = $this->submit([
            $this->q1->id => 'B',
            $this->q2->id => 'D',
            $this->q3->id => '   ',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('unanswered_question_ids', [$this->q3->id]);

        $this->assertDatabaseHas('exam_sessions', ['id' => $this->session->id, 'finished_at' => null]);
    }

    // ── Manual submit berhasil kalau semua terjawab ──────────────────

    public function test_manual_submit_accepted_when_all_answered(): void
    {
        $response = $this->submit([
            $this->q1->id => 'B',
            $this->q2->id => 'D',
            $this->q3->id => 'Jawaban essay lengkap',
        ]);

        $response->assertStatus(302);

        $this->assertDatabaseHas('exam_sessions', [
            'id' => $this->session->id,
            'status' => ExamSession::STATUS_COMPLETED,
        ]);
    }

    // ── Auto-submit (expired) tetap unconditional walau ada kosong ────

    public function test_auto_submit_when_expired_submits_with_blank_questions(): void
    {
        // Period & schedule kemarin (sudah lewat grace) — jalur auto/timer.
        $start = Carbon::yesterday()->setTime(8, 0);
        $expiredPeriod = ExamPeriod::create([
            'name' => 'Harian Kemarin',
            'name_prefix' => 'Harian Kemarin',
            'exam_type_id' => ExamType::where('code', 'harian')->first()->id,
            'grade_level' => 'XI',
            'exam_date' => $start->toDateString(),
            'start_time' => '08:00:00',
            'end_time' => '09:30:00',
            'session_number' => 2,
        ]);

        $expiredSchedule = ExamSchedule::create([
            'subject_id' => $this->subject->id,
            'room_id' => null,
            'classroom_id' => $this->classroom->id,
            'exam_period_id' => $expiredPeriod->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => $start->toDateString(),
            'start_time' => '08:00:00',
            'end_time' => '09:30:00',
            'duration_minutes' => 90,
            'status' => ExamSchedule::STATUS_FINISHED,
        ]);

        // Soal aktif tetap terkait (untuk subjectHasActiveQuestions path tidak
        // dipakai di submit, tetap aman).
        $session = ExamSession::create([
            'student_id' => $this->student->id,
            'exam_schedule_id' => $expiredSchedule->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'started_at' => $start->addMinutes(5),
            'attendance_confirmed' => true,
        ]);

        // Kirim submit TANPA jawaban apa pun — expired → gate kelengkapan
        // di-skip, submit tetap berhasil (unconditional seperti timer).
        $this->actingAs($this->student->user)
            ->postJson(route('peserta.exams.submit', $expiredSchedule->id), ['answers' => []])
            ->assertStatus(302);

        $this->assertDatabaseHas('exam_sessions', [
            'id' => $session->id,
            'status' => ExamSession::STATUS_COMPLETED,
        ]);
    }
}

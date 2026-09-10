<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ExamAnswer;
use App\Models\ExamPeriod;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\ExamType;
use App\Models\GuruMapel;
use App\Models\Question;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectAttendance;
use App\Models\SubjectGrade;
use App\Models\TeacherSubjectClassAssignment;
use App\Services\ExamGradingService;
use App\Services\FinalScoreCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoreRedesignServicesTest extends TestCase
{
    use RefreshDatabase;

    private function makeAmpuScenario(): array
    {
        $guru = GuruMapel::factory()->create();
        $subject = Subject::factory()->create();
        $classroom = Classroom::factory()->create();

        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
        ]);

        $student = Student::factory()->create(['classroom_id' => $classroom->id]);

        $semester = Semester::create(['year' => '2024/2025', 'semester' => 1, 'is_active' => true]);

        return [$guru, $subject, $classroom, $student, $semester];
    }

    private function makeObjectiveQuestion(int $subjectId, Classroom $classroom): Question
    {
        $question = Question::factory()->create([
            'subject_id' => $subjectId,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'question_text' => 'Berapakah hasil dari 2 + 2?',
            'options' => ['A' => 'Satu', 'B' => 'Dua', 'C' => 'Tiga', 'D' => 'Empat'],
            'answer_key' => 'D',
            'score_weight' => 10,
        ]);
        $question->classrooms()->attach($classroom->id);

        return $question;
    }

    private function makeSession(
        int $subjectId,
        Student $student,
        ?ExamPeriod $period = null,
        array $answers = [],
    ): array {
        $schedule = ExamSchedule::factory()->create([
            'subject_id' => $subjectId,
            'class_name' => $student->classroom?->name,
            'exam_period_id' => $period?->id,
            'status' => ExamSchedule::STATUS_FINISHED,
        ]);

        $session = ExamSession::factory()->create([
            'student_id' => $student->id,
            'exam_schedule_id' => $schedule->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
        ]);

        foreach ($answers as $answer) {
            ExamAnswer::create([
                'exam_session_id' => $session->id,
                ...$answer,
            ]);
        }

        return [$schedule, $session];
    }

    // -----------------------------------------------------------------
    // 1. ExamGradingService::finalize() → subject_grades (source=cbt)
    // -----------------------------------------------------------------

    public function test_finalize_creates_cbt_subject_grade_with_period_exam_type(): void
    {
        [$guru, $subject, $classroom, $student, $semester] = $this->makeAmpuScenario();

        $uts = ExamType::query()->where('code', 'uts')->firstOrFail();
        $period = ExamPeriod::create([
            'name' => 'UTS Ganjil 2024/2025',
            'exam_type_id' => $uts->id,
            'exam_date' => now()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

        $q = $this->makeObjectiveQuestion($subject->id, $classroom);
        [$schedule, $session] = $this->makeSession($subject->id, $student, $period, [
            ['question_id' => $q->id, 'student_answer' => 'D'],
        ]);

        $result = app(ExamGradingService::class)->finalize($session, $schedule);

        $this->assertNotNull($result->id);
        $this->assertDatabaseHas('subject_grades', [
            'student_id' => $student->id,
            'exam_schedule_id' => $schedule->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'semester_id' => $semester->id,
            'exam_type_id' => $uts->id,
            'title' => 'CBT',
            'score' => '10.00',
            'source' => 'cbt',
            'is_override' => false,
        ]);
    }

    public function test_finalize_falls_back_to_uas_when_period_has_no_exam_type(): void
    {
        [$guru, $subject, $classroom, $student, $semester] = $this->makeAmpuScenario();

        $uas = ExamType::query()->where('code', 'uas')->firstOrFail();
        $period = ExamPeriod::create([
            'name' => 'Periode Lama Tanpa Jenis',
            'exam_type_id' => null,
            'exam_date' => now()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

        $q = $this->makeObjectiveQuestion($subject->id, $classroom);
        [$schedule, $session] = $this->makeSession($subject->id, $student, $period, [
            ['question_id' => $q->id, 'student_answer' => 'D'],
        ]);

        app(ExamGradingService::class)->finalize($session, $schedule);

        $this->assertDatabaseHas('subject_grades', [
            'student_id' => $student->id,
            'exam_schedule_id' => $schedule->id,
            'exam_type_id' => $uas->id,
            'title' => 'CBT',
            'source' => 'cbt',
        ]);
    }

    public function test_finalize_updates_subject_grade_on_second_finalize(): void
    {
        [$guru, $subject, $classroom, $student, $semester] = $this->makeAmpuScenario();

        $q = $this->makeObjectiveQuestion($subject->id, $classroom);
        [$schedule, $session] = $this->makeSession($subject->id, $student, null, [
            ['question_id' => $q->id, 'student_answer' => 'D'],
        ]);

        $service = app(ExamGradingService::class);
        $service->finalize($session, $schedule);
        // Ubah sesi ke completed + finalize ulang — harus updateOrCreate (1 baris)
        $session->update(['status' => ExamSession::STATUS_COMPLETED]);
        $service->finalize($session, $schedule);

        $this->assertDatabaseCount('subject_grades', 1);
        $this->assertDatabaseHas('subject_grades', [
            'student_id' => $student->id,
            'exam_schedule_id' => $schedule->id,
            'score' => '10.00',
        ]);
    }

    // -----------------------------------------------------------------
    // 2. FinalScoreCalculator (Opsi A)
    // -----------------------------------------------------------------

    public function test_final_calculator_averages_categories_then_averages_categories(): void
    {
        [$guru, $subject, $classroom, $student, $semester] = $this->makeAmpuScenario();

        $harian = ExamType::query()->where('code', 'harian')->firstOrFail();
        $uts = ExamType::query()->where('code', 'uts')->firstOrFail();
        $uas = ExamType::query()->where('code', 'uas')->firstOrFail();
        $hadir = ExamType::query()->where('code', 'kehadiran')->firstOrFail();

        $make = fn (ExamType $type, float $score, string $title = '') => SubjectGrade::create([
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'guru_mapel_id' => $guru->id,
            'semester_id' => $semester->id,
            'exam_type_id' => $type->id,
            'title' => $title !== '' ? $title : $type->code,
            'score' => $score,
            'source' => 'manual',
            'is_override' => true,
        ]);

        // Harian: 80, 90 → avg 85; UTS: 70; UAS: 60; Kehadiran: 100
        $make($harian, 80, 'UH 1');
        $make($harian, 90, 'UH 2');
        $make($uts, 70);
        $make($uas, 60);
        $make($hadir, 100);

        $result = app(FinalScoreCalculator::class)->calculate($student->id, $subject->id, $semester->id);

        // Rata2 kategori: (85 + 70 + 60 + 100) / 4 = 78.75
        $this->assertEqualsWithDelta(85.0, $result['categories'][0]['average'], 0.001);
        $this->assertEqualsWithDelta(78.75, $result['final_score'], 0.001);
        $this->assertCount(4, $result['categories']);
    }

    public function test_final_calculator_skips_empty_categories_not_counts_as_zero(): void
    {
        [$guru, $subject, $classroom, $student, $semester] = $this->makeAmpuScenario();

        $uts = ExamType::query()->where('code', 'uts')->firstOrFail();

        // Hanya UTS terisi → final = 70 (bukan 70/4=17.5)
        SubjectGrade::create([
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'guru_mapel_id' => $guru->id,
            'semester_id' => $semester->id,
            'exam_type_id' => $uts->id,
            'title' => 'UTS',
            'score' => 70,
            'source' => 'manual',
            'is_override' => true,
        ]);

        $result = app(FinalScoreCalculator::class)->calculate($student->id, $subject->id, $semester->id);

        $this->assertCount(1, $result['categories']);
        $this->assertEqualsWithDelta(70.0, $result['final_score'], 0.001);
    }

    public function test_final_calculator_returns_null_when_no_grades(): void
    {
        [$guru, $subject, $classroom, $student, $semester] = $this->makeAmpuScenario();

        $result = app(FinalScoreCalculator::class)->calculate($student->id, $subject->id, $semester->id);

        $this->assertSame([], $result['categories']);
        $this->assertNull($result['final_score']);
    }

    public function test_final_calculator_ignores_null_scores(): void
    {
        [$guru, $subject, $classroom, $student, $semester] = $this->makeAmpuScenario();

        $uts = ExamType::query()->where('code', 'uts')->firstOrFail();

        // Baris tanpa score (essay belum dinilai) tidak boleh dihitung
        SubjectGrade::create([
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'guru_mapel_id' => $guru->id,
            'semester_id' => $semester->id,
            'exam_type_id' => $uts->id,
            'title' => 'UTS',
            'score' => null,
            'source' => 'manual',
            'is_override' => false,
        ]);

        $result = app(FinalScoreCalculator::class)->calculate($student->id, $subject->id, $semester->id);

        $this->assertSame([], $result['categories']);
        $this->assertNull($result['final_score']);
    }

    // -----------------------------------------------------------------
    // 3. Kehadiran sync (materialize)
    // -----------------------------------------------------------------

    public function test_attendance_materialize_creates_subject_grade_with_constant_title(): void
    {
        [$guru, $subject, $classroom, $student, $semester] = $this->makeAmpuScenario();

        $hadir = ExamType::query()->where('code', 'kehadiran')->firstOrFail();

        $attendance = SubjectAttendance::create([
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'guru_mapel_id' => $guru->id,
            'semester_id' => $semester->id,
            'total_days' => 20,
            'present_days' => 18,
            'absent_days' => 2,
        ]);

        $grade = $attendance->materialize();

        $this->assertNotNull($grade);
        $this->assertDatabaseHas('subject_grades', [
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'semester_id' => $semester->id,
            'exam_type_id' => $hadir->id,
            'title' => 'Kehadiran',
            'score' => '90.00',
            'source' => 'manual',
            'is_override' => true,
        ]);
    }

    public function test_attendance_materialize_is_idempotent(): void
    {
        [$guru, $subject, $classroom, $student, $semester] = $this->makeAmpuScenario();

        $attendance = SubjectAttendance::create([
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'guru_mapel_id' => $guru->id,
            'semester_id' => $semester->id,
            'total_days' => 10,
            'present_days' => 10,
            'absent_days' => 0,
        ]);

        $attendance->materialize();
        $attendance->materialize();

        $this->assertDatabaseCount('subject_grades', 1);
    }

    public function test_attendance_materialize_returns_null_when_no_effective_days(): void
    {
        [$guru, $subject, $classroom, $student, $semester] = $this->makeAmpuScenario();

        $attendance = SubjectAttendance::create([
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'guru_mapel_id' => $guru->id,
            'semester_id' => $semester->id,
            'total_days' => 0,
            'present_days' => 0,
            'absent_days' => 0,
        ]);

        $this->assertNull($attendance->materialize());
        $this->assertDatabaseCount('subject_grades', 0);
    }
}

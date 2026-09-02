<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ExamAnswer;
use App\Models\ExamResult;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\GuruMapel;
use App\Models\Question;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeacherSubjectClassAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Fitur Nilai guru: nilai otomatis terisi dari hasil ujian CBT (ExamResult),
 * detail jawaban per siswa (read-only), koreksi skor essay/override, dan
 * isolasi akses antar guru (isAmpu).
 */
class GuruMapelGradesExamTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guru mapel yang mengampu satu mapel pada satu kelas, lengkap dengan
     * beberapa siswa di kelas itu.
     *
     * @return array{0: GuruMapel, 1: Subject, 2: Classroom, 3: Collection<int, Student>}
     */
    private function makeAmpuGuru(int $studentCount = 1): array
    {
        $guru = GuruMapel::factory()->create();
        $subject = Subject::factory()->create();
        $classroom = Classroom::factory()->create();

        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
        ]);

        $students = Student::factory()->count($studentCount)->create([
            'classroom_id' => $classroom->id,
            'class_name' => $classroom->name,
        ]);

        return [$guru, $subject, $classroom, $students];
    }

    /**
     * Bangun skenario lengkap: jadwal ujian mapel-kelas, sesi selesai, kunci
     * jawaban per soal, dan hasil ujian CBT (ExamResult).
     */
    private function makeGradedSession(
        int $subjectId,
        Classroom $classroom,
        Student $student,
        array $answers,
        float $totalScore,
    ): ExamSession {
        $schedule = ExamSchedule::factory()->create([
            'subject_id' => $subjectId,
            'class_name' => $classroom->name,
            'status' => ExamSchedule::STATUS_FINISHED,
        ]);

        $session = ExamSession::factory()->create([
            'student_id' => $student->id,
            'exam_schedule_id' => $schedule->id,
            'status' => ExamSession::STATUS_COMPLETED,
        ]);

        foreach ($answers as $answer) {
            ExamAnswer::create($answer + ['exam_session_id' => $session->id]);
        }

        ExamResult::factory()->create([
            'exam_session_id' => $session->id,
            'total_score' => $totalScore,
            'is_passed' => true,
        ]);

        return $session;
    }

    private function objectiveQuestion(int $subjectId, Classroom $classroom): Question
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

    private function essayQuestion(int $subjectId, Classroom $classroom): Question
    {
        $question = Question::factory()->create([
            'subject_id' => $subjectId,
            'type' => Question::TYPE_ESSAY,
            'question_text' => 'Jelaskan mengapa langit berwarna biru?',
            'answer_key' => 'Hamburan cahaya Rayleigh.',
            'score_weight' => 20,
        ]);
        $question->classrooms()->attach($classroom->id);

        return $question;
    }

    // -----------------------------------------------------------------
    // NILAI OTOMATIS DARI HASIL UJIAN CBT
    // -----------------------------------------------------------------

    public function test_grade_index_auto_fills_score_from_exam_result(): void
    {
        [$guru, $subject, $classroom, $students] = $this->makeAmpuGuru(1);
        $student = $students->first();

        $this->makeGradedSession($subject->id, $classroom, $student, [], 80.50);

        $this->actingAs($guru->user)
            ->get(route('guru_mapel.grades.index', [
                'subject_id' => $subject->id,
                'classroom_id' => $classroom->id,
            ]))
            ->assertOk()
            ->assertSee($student->user?->name)
            ->assertSee('80.50')
            ->assertSee('Otomatis CBT');

        // Sync otomatis menulis grades (is_override=false) sebagai nilai resmi.
        $this->assertDatabaseHas('grades', [
            'guru_mapel_id' => $guru->id,
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'score' => '80.50',
            'is_override' => false,
        ]);
    }

    public function test_grade_index_marks_student_without_exam_result(): void
    {
        [$guru, $subject, $classroom, $students] = $this->makeAmpuGuru(1);

        $this->actingAs($guru->user)
            ->get(route('guru_mapel.grades.index', [
                'subject_id' => $subject->id,
                'classroom_id' => $classroom->id,
            ]))
            ->assertOk()
            ->assertSee('Belum ada hasil ujian');

        $this->assertDatabaseCount('grades', 0);
    }

    // -----------------------------------------------------------------
    // DETAIL JAWABAN SISWA
    // -----------------------------------------------------------------

    public function test_grade_detail_shows_all_answers_including_ungraded_essay(): void
    {
        [$guru, $subject, $classroom, $students] = $this->makeAmpuGuru(1);
        $student = $students->first();

        $objective = $this->objectiveQuestion($subject->id, $classroom);
        $essay = $this->essayQuestion($subject->id, $classroom);

        $this->makeGradedSession($subject->id, $classroom, $student, [
            [
                'question_id' => $objective->id,
                'student_answer' => 'D',
                'is_correct' => true,
                'score' => 10,
            ],
            [
                'question_id' => $essay->id,
                'student_answer' => 'Karena hamburan cahaya Rayleigh.',
                'is_correct' => null,
                'score' => null,
            ],
        ], 10.0);

        $this->actingAs($guru->user)
            ->get(route('guru_mapel.grades.detail', [
                'subject_id' => $subject->id,
                'classroom_id' => $classroom->id,
                'student_id' => $student->id,
            ]))
            ->assertOk()
            ->assertSee('Berapakah hasil dari 2 + 2?')
            ->assertSee('Jelaskan mengapa langit berwarna biru?')
            ->assertSee('Karena hamburan cahaya Rayleigh.')
            ->assertSee('Kunci')
            ->assertSee('D')
            ->assertSee('10.00')
            ->assertSee('Belum dinilai');
    }

    public function test_grade_detail_marks_missing_session_as_no_result(): void
    {
        [$guru, $subject, $classroom, $students] = $this->makeAmpuGuru(1);
        $student = $students->first();

        $this->actingAs($guru->user)
            ->get(route('guru_mapel.grades.detail', [
                'subject_id' => $subject->id,
                'classroom_id' => $classroom->id,
                'student_id' => $student->id,
            ]))
            ->assertOk()
            ->assertSee('Belum ada hasil ujian');
    }

    // -----------------------------------------------------------------
    // KOREKSI SKOR (ESSAY / OVERRIDE) → RECALCULATE TOTAL
    // -----------------------------------------------------------------

    public function test_guru_can_grade_essay_and_total_recalculates(): void
    {
        [$guru, $subject, $classroom, $students] = $this->makeAmpuGuru(1);
        $student = $students->first();

        $objective = $this->objectiveQuestion($subject->id, $classroom);
        $essay = $this->essayQuestion($subject->id, $classroom);

        $session = $this->makeGradedSession($subject->id, $classroom, $student, [
            [
                'question_id' => $objective->id,
                'student_answer' => 'D',
                'is_correct' => true,
                'score' => 10,
            ],
            [
                'question_id' => $essay->id,
                'student_answer' => 'Karena hamburan cahaya Rayleigh.',
                'is_correct' => null,
                'score' => null,
            ],
        ], 10.0);

        $essayAnswer = ExamAnswer::query()->where('exam_session_id', $session->id)->where('question_id', $essay->id)->firstOrFail();

        $this->actingAs($guru->user)
            ->post(route('guru_mapel.grades.save-scores'), [
                'subject_id' => $subject->id,
                'classroom_id' => $classroom->id,
                'student_id' => $student->id,
                'session_id' => $session->id,
                'scores' => [$essayAnswer->id => 17.5],
                'note' => 'Essay dinilai manual.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        // Skor per soal essay tersimpan; jawaban siswa TIDAK berubah.
        $this->assertDatabaseHas('exam_answers', [
            'id' => $essayAnswer->id,
            'student_answer' => '"Karena hamburan cahaya Rayleigh."',
            'score' => '17.50',
        ]);

        // Total di-recalculate (objektif 10 + essay 17.5) dan disimpan resmi.
        $this->assertDatabaseHas('grades', [
            'guru_mapel_id' => $guru->id,
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'score' => '27.50',
            'is_override' => true,
            'note' => 'Essay dinilai manual.',
        ]);
    }

    public function test_guru_can_override_objective_score(): void
    {
        [$guru, $subject, $classroom, $students] = $this->makeAmpuGuru(1);
        $student = $students->first();

        $objective = $this->objectiveQuestion($subject->id, $classroom);

        $session = $this->makeGradedSession($subject->id, $classroom, $student, [
            [
                'question_id' => $objective->id,
                'student_answer' => 'D',
                'is_correct' => true,
                'score' => 10,
            ],
        ], 10.0);

        $answer = ExamAnswer::query()->where('exam_session_id', $session->id)->where('question_id', $objective->id)->firstOrFail();

        $this->actingAs($guru->user)
            ->post(route('guru_mapel.grades.save-scores'), [
                'subject_id' => $subject->id,
                'classroom_id' => $classroom->id,
                'student_id' => $student->id,
                'session_id' => $session->id,
                'scores' => [$answer->id => 8],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('grades', [
            'guru_mapel_id' => $guru->id,
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'score' => '8.00',
            'is_override' => true,
        ]);
    }

    public function test_retake_with_new_exam_result_does_not_overwrite_guru_override(): void
    {
        [$guru, $subject, $classroom, $students] = $this->makeAmpuGuru(1);
        $student = $students->first();

        $objective = $this->objectiveQuestion($subject->id, $classroom);
        $essay = $this->essayQuestion($subject->id, $classroom);

        // Ujian pertama selesai → nilai otomatis 10.00 (is_override=false).
        $session1 = $this->makeGradedSession($subject->id, $classroom, $student, [
            [
                'question_id' => $objective->id,
                'student_answer' => 'D',
                'is_correct' => true,
                'score' => 10,
            ],
            [
                'question_id' => $essay->id,
                'student_answer' => 'Jawaban pertama.',
                'is_correct' => null,
                'score' => null,
            ],
        ], 10.0);

        // Guru menilai essay → total recalculate 27.50, tersimpan is_override=true.
        $essayAnswer = ExamAnswer::query()->where('exam_session_id', $session1->id)->where('question_id', $essay->id)->firstOrFail();

        $this->actingAs($guru->user)
            ->post(route('guru_mapel.grades.save-scores'), [
                'subject_id' => $subject->id,
                'classroom_id' => $classroom->id,
                'student_id' => $student->id,
                'session_id' => $session1->id,
                'scores' => [$essayAnswer->id => 17.5],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        // Siswa mengulang ujian (re-attempt) → ExamResult BARU dengan skor berbeda (90.00).
        $this->makeGradedSession($subject->id, $classroom, $student, [], 90.0);

        // Buka halaman Nilai: koreksi guru TIDAK boleh tertimpa hasil CBT baru.
        $this->actingAs($guru->user)
            ->get(route('guru_mapel.grades.index', [
                'subject_id' => $subject->id,
                'classroom_id' => $classroom->id,
            ]))
            ->assertOk()
            ->assertSee('Koreksi Guru')
            ->assertSee('27.50')
            ->assertDontSee('90.00');

        $this->assertDatabaseHas('grades', [
            'guru_mapel_id' => $guru->id,
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'score' => '27.50',
            'is_override' => true,
        ]);

        // Sync otomatis tidak membuat baris baru / menimpa baris override.
        $this->assertDatabaseCount('grades', 1);
    }

    public function test_save_scores_rejects_score_above_question_weight(): void
    {
        [$guru, $subject, $classroom, $students] = $this->makeAmpuGuru(1);
        $student = $students->first();

        $essay = $this->essayQuestion($subject->id, $classroom);

        $session = $this->makeGradedSession($subject->id, $classroom, $student, [
            [
                'question_id' => $essay->id,
                'student_answer' => 'Jawaban essay.',
                'is_correct' => null,
                'score' => null,
            ],
        ], 0.0);

        $answer = ExamAnswer::query()->where('exam_session_id', $session->id)->where('question_id', $essay->id)->firstOrFail();

        $this->actingAs($guru->user)
            ->post(route('guru_mapel.grades.save-scores'), [
                'subject_id' => $subject->id,
                'classroom_id' => $classroom->id,
                'student_id' => $student->id,
                'session_id' => $session->id,
                'scores' => [$answer->id => 25],
            ])
            ->assertSessionHasErrors('scores.'.$answer->id);

        $this->assertDatabaseCount('grades', 0);
        $this->assertDatabaseHas('exam_answers', ['id' => $answer->id, 'score' => null]);
    }

    // -----------------------------------------------------------------
    // ISOLASI AKSES (GURU NON-AMPU DITOLAK)
    // -----------------------------------------------------------------

    public function test_non_ampu_guru_cannot_access_detail(): void
    {
        [$ampuGuru, $subject, $classroom, $students] = $this->makeAmpuGuru(1);

        $otherGuru = GuruMapel::factory()->create();

        $this->actingAs($otherGuru->user)
            ->get(route('guru_mapel.grades.detail', [
                'subject_id' => $subject->id,
                'classroom_id' => $classroom->id,
                'student_id' => $students->first()->id,
            ]))
            ->assertForbidden();

        $this->actingAs($otherGuru->user)
            ->post(route('guru_mapel.grades.save-scores'), [
                'subject_id' => $subject->id,
                'classroom_id' => $classroom->id,
                'student_id' => $students->first()->id,
                'session_id' => 1,
                'scores' => [1 => 5],
            ])
            ->assertForbidden();
    }

    public function test_detail_rejects_student_not_in_ampu_classroom(): void
    {
        [$guru, $subject, $classroom] = $this->makeAmpuGuru(0);
        $foreignStudent = Student::factory()->create();

        $this->actingAs($guru->user)
            ->get(route('guru_mapel.grades.detail', [
                'subject_id' => $subject->id,
                'classroom_id' => $classroom->id,
                'student_id' => $foreignStudent->id,
            ]))
            ->assertNotFound();
    }

    // -----------------------------------------------------------------
    // REGRESI: JADWAL DENGAN CLASS_NAME KOSONG (BUG PRODUKSI)
    // -----------------------------------------------------------------

    public function test_grade_index_shows_scores_when_schedule_class_name_empty(): void
    {
        [$guru, $subject, $classroom, $students] = $this->makeAmpuGuru(1);
        $student = $students->first();

        $this->makeGradedSessionWithEmptyClassName($subject->id, $student, 90.00);

        $this->actingAs($guru->user)
            ->get(route('guru_mapel.grades.index', [
                'subject_id' => $subject->id,
                'classroom_id' => $classroom->id,
            ]))
            ->assertOk()
            ->assertSee($student->user?->name)
            ->assertSee('90.00')
            ->assertSee('Otomatis CBT');

        $this->assertDatabaseHas('grades', [
            'guru_mapel_id' => $guru->id,
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'score' => '90.00',
            'is_override' => false,
        ]);
    }

    public function test_grade_detail_opens_when_schedule_class_name_empty(): void
    {
        [$guru, $subject, $classroom, $students] = $this->makeAmpuGuru(1);
        $student = $students->first();

        $objective = $this->objectiveQuestion($subject->id, $classroom);

        $this->makeGradedSessionWithEmptyClassName($subject->id, $student, 80.00, [$objective]);

        $this->actingAs($guru->user)
            ->get(route('guru_mapel.grades.detail', [
                'subject_id' => $subject->id,
                'classroom_id' => $classroom->id,
                'student_id' => $student->id,
            ]))
            ->assertOk()
            ->assertSee('Berapakah hasil dari 2 + 2?');
    }

    public function test_save_scores_allows_session_from_empty_class_name_schedule(): void
    {
        [$guru, $subject, $classroom, $students] = $this->makeAmpuGuru(1);
        $student = $students->first();

        $essay = $this->essayQuestion($subject->id, $classroom);

        // Jawaban essay dibuat manual (tanpa ExamResult) supaya bisa dikoreksi.
        $schedule = ExamSchedule::factory()->create([
            'subject_id' => $subject->id,
            'class_name' => '',
            'status' => ExamSchedule::STATUS_FINISHED,
        ]);

        $session = ExamSession::factory()->create([
            'student_id' => $student->id,
            'exam_schedule_id' => $schedule->id,
            'status' => ExamSession::STATUS_COMPLETED,
        ]);

        $answer = ExamAnswer::create([
            'exam_session_id' => $session->id,
            'question_id' => $essay->id,
            'student_answer' => 'Jawaban siswa',
        ]);

        $this->actingAs($guru->user)
            ->post(route('guru_mapel.grades.save-scores'), [
                'subject_id' => $subject->id,
                'classroom_id' => $classroom->id,
                'student_id' => $student->id,
                'session_id' => $session->id,
                'scores' => [$answer->id => 20],
            ])
            ->assertRedirect(route('guru_mapel.grades.detail', [
                'subject_id' => $subject->id,
                'classroom_id' => $classroom->id,
                'student_id' => $student->id,
            ]))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('grades', [
            'guru_mapel_id' => $guru->id,
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'score' => '20.00',
            'is_override' => true,
        ]);
    }

    /**
     * Bangun sesi ber-nilai untuk jadwal ber-class_name kosong (meniru data
     * produksi) dengan sesi siswa pada jadwal tersebut.
     */
    private function makeGradedSessionWithEmptyClassName(
        int $subjectId,
        Student $student,
        float $totalScore,
        array $questions = [],
    ): ExamSession {
        $schedule = ExamSchedule::factory()->create([
            'subject_id' => $subjectId,
            'class_name' => '',
            'status' => ExamSchedule::STATUS_FINISHED,
        ]);

        $session = ExamSession::factory()->create([
            'student_id' => $student->id,
            'exam_schedule_id' => $schedule->id,
            'status' => ExamSession::STATUS_COMPLETED,
        ]);

        foreach ($questions as $question) {
            ExamAnswer::create([
                'exam_session_id' => $session->id,
                'question_id' => $question->id,
            ]);
        }

        ExamResult::factory()->create([
            'exam_session_id' => $session->id,
            'total_score' => $totalScore,
            'is_passed' => true,
        ]);

        return $session;
    }
}

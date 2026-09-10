<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ExamAnswer;
use App\Models\ExamResult;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\ExamType;
use App\Models\GuruMapel;
use App\Models\Question;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectGrade;
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

    private function makeActiveSemester(): Semester
    {
        return Semester::create(['year' => '2024/2025', 'semester' => 1, 'is_active' => true]);
    }

    private function makeCbtSubjectGrade(
        int $guruId,
        Student $student,
        int $classroomId,
        int $subjectId,
        int $semesterId,
        float $score,
    ): SubjectGrade {
        $uasId = ExamType::query()->where('code', 'uas')->value('id');

        return SubjectGrade::create([
            'student_id' => $student->id,
            'classroom_id' => $classroomId,
            'subject_id' => $subjectId,
            'guru_mapel_id' => $guruId,
            'semester_id' => $semesterId,
            'exam_type_id' => $uasId,
            'title' => 'CBT',
            'score' => $score,
            'source' => SubjectGrade::SOURCE_CBT,
            'is_override' => false,
        ]);
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
        $semester = $this->makeActiveSemester();

        $this->makeCbtSubjectGrade($guru->id, $student, $classroom->id, $subject->id, $semester->id, 80.50);

        $this->actingAs($guru->user)
            ->get(route('guru_mapel.grades.index', [
                'subject_id' => $subject->id,
                'classroom_id' => $classroom->id,
                'semester_id' => $semester->id,
            ]))
            ->assertOk()
            ->assertSee($student->user?->name)
            ->assertSee('80.50');

        // Nilai Akhir tersinkron ke grades (is_override=false).
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
        $semester = $this->makeActiveSemester();

        $this->actingAs($guru->user)
            ->get(route('guru_mapel.grades.index', [
                'subject_id' => $subject->id,
                'classroom_id' => $classroom->id,
                'semester_id' => $semester->id,
            ]))
            ->assertOk()
            ->assertSee('Belum ada nilai');

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
        $semester = $this->makeActiveSemester();

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
                'semester_id' => $semester->id,
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
                'semester_id' => $semester->id,
            ]))
            ->assertOk()
            ->assertSee('27.50')
            ->assertDontSee('90.00');

        $this->assertDatabaseHas('grades', [
            'guru_mapel_id' => $guru->id,
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'score' => '27.50',
            'semester_id' => $semester->id,
            'is_override' => true,
        ]);

        // Sinkronisasi tidak membuat baris baru / menimpa baris override.
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
    // REGRESI: SOAL TIDAK DIJAWAB TETAP MUNCUL
    // -----------------------------------------------------------------

    /**
     * Siswa menjawab 2 dari 3 soal — detail harus menampilkan ketiga soal,
     * termasuk soal tak terjawab dengan label "Tidak dijawab" dan skor 0.
     * Bug lama: query dimulai dari examAnswers → soal tak dijawab hilang.
     */
    public function test_detail_shows_unanswered_questions_with_tidak_dijawab_label(): void
    {
        [$guru, $subject, $classroom, $students] = $this->makeAmpuGuru(1);
        $student = $students->first();

        // Buat 3 soal (2 objektif + 1 essay)
        $q1 = $this->objectiveQuestion($subject->id, $classroom);
        $q2 = Question::factory()->create([
            'subject_id' => $subject->id,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'question_text' => 'Ibu kota Indonesia adalah?',
            'options' => ['A' => 'Bandung', 'B' => 'Jakarta', 'C' => 'Surabaya', 'D' => 'Medan'],
            'answer_key' => 'B',
            'score_weight' => 10,
        ]);
        $q2->classrooms()->attach($classroom->id);

        $q3 = $this->essayQuestion($subject->id, $classroom);

        // Siswa hanya menjawab q1 dan q3 — q2 TIDAK dijawab
        $session = $this->makeGradedSession($subject->id, $classroom, $student, [
            [
                'question_id' => $q1->id,
                'student_answer' => 'D',
                'is_correct' => true,
                'score' => 10,
            ],
            [
                'question_id' => $q3->id,
                'student_answer' => 'Jawaban essay.',
                'is_correct' => null,
                'score' => null,
            ],
        ], 10.0);

        // Hanya 2 jawaban asli di DB (q1 + q3)
        $this->assertDatabaseCount('exam_answers', 2);

        $response = $this->actingAs($guru->user)
            ->get(route('guru_mapel.grades.detail', [
                'subject_id' => $subject->id,
                'classroom_id' => $classroom->id,
                'student_id' => $student->id,
            ]))
            ->assertOk();

        // Ketiga soal harus muncul
        $response->assertSee('Berapakah hasil dari 2 + 2?')
            ->assertSee('Ibu kota Indonesia adalah?')
            ->assertSee('Jelaskan mengapa langit berwarna biru?');

        // Soal tak terjawab (q2) harus menampilkan label "Tidak dijawab"
        $response->assertSee('Tidak dijawab');

        // Judul harus menampilkan total 3 soal (bukan hanya yang dijawab)
        $response->assertSee('3 soal');

        // Skor soal tak terjawab harus 0.00
        $response->assertSee('0.00');

        // PENTING: membuka halaman detail TIDAK boleh membuat row exam_answers
        // baru untuk soal tak terjawab — stub hanya in-memory.
        $this->assertDatabaseCount('exam_answers', 2);
    }

    /**
     * Siswa menjawab 0 dari 2 soal — detail tetap menampilkan kedua soal
     * dengan label "Tidak dijawab" semua.
     */
    public function test_detail_shows_all_questions_when_student_answered_nothing(): void
    {
        [$guru, $subject, $classroom, $students] = $this->makeAmpuGuru(1);
        $student = $students->first();

        $q1 = $this->objectiveQuestion($subject->id, $classroom);
        $q2 = $this->essayQuestion($subject->id, $classroom);

        // Sesi selesai tetapi tanpa jawaban sama sekali
        $schedule = ExamSchedule::factory()->create([
            'subject_id' => $subject->id,
            'class_name' => $classroom->name,
            'status' => ExamSchedule::STATUS_FINISHED,
        ]);

        $session = ExamSession::factory()->create([
            'student_id' => $student->id,
            'exam_schedule_id' => $schedule->id,
            'status' => ExamSession::STATUS_TIMED_OUT,
        ]);

        ExamResult::factory()->create([
            'exam_session_id' => $session->id,
            'total_score' => 0,
            'is_passed' => false,
        ]);

        $response = $this->actingAs($guru->user)
            ->get(route('guru_mapel.grades.detail', [
                'subject_id' => $subject->id,
                'classroom_id' => $classroom->id,
                'student_id' => $student->id,
            ]))
            ->assertOk();

        // Kedua soal harus muncul
        $response->assertSee('Berapakah hasil dari 2 + 2?')
            ->assertSee('Jelaskan mengapa langit berwarna biru?');

        // Semua soal menampilkan "Tidak dijawab"
        $tidakDijawab = substr_count($response->getContent(), 'Tidak dijawab');
        $this->assertGreaterThanOrEqual(2, $tidakDijawab, 'Setiap soal tanpa jawaban harus menampilkan label "Tidak dijawab"');

        // Judul menampilkan 2 soal
        $response->assertSee('2 soal');

        // Membuka halaman detail TIDAK menulis row exam_answers (stub in-memory)
        $this->assertDatabaseCount('exam_answers', 0);
    }

    /**
     * Guru mengisi "Koreksi Guru" untuk soal yang TIDAK dijawab siswa dan
     * klik "Simpan Skor & Nilai" — baru di titik ini row ExamAnswer dibuat.
     * Sebelum submit, soal tak terjawab tidak punya row di database.
     */
    public function test_save_scores_creates_exam_answer_for_unanswered_question_on_submit(): void
    {
        [$guru, $subject, $classroom, $students] = $this->makeAmpuGuru(1);
        $student = $students->first();

        $q1 = $this->objectiveQuestion($subject->id, $classroom);
        $q2 = $this->essayQuestion($subject->id, $classroom);

        // Siswa hanya menjawab q1 — q2 TIDAK dijawab
        $session = $this->makeGradedSession($subject->id, $classroom, $student, [
            [
                'question_id' => $q1->id,
                'student_answer' => 'D',
                'is_correct' => true,
                'score' => 10,
            ],
        ], 10.0);

        $this->assertDatabaseCount('exam_answers', 1);

        // Key `scores` harus answer ID milik q1 (bukan question ID)
        $q1Answer = ExamAnswer::query()
            ->where('exam_session_id', $session->id)
            ->where('question_id', $q1->id)
            ->firstOrFail();

        // Guru menilai soal tak terjawab (q2 essay) via new_scores
        $this->actingAs($guru->user)
            ->post(route('guru_mapel.grades.save-scores'), [
                'subject_id' => $subject->id,
                'classroom_id' => $classroom->id,
                'student_id' => $student->id,
                'session_id' => $session->id,
                'scores' => [$q1Answer->id => 10],
                'new_scores' => [$q2->id => 15],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        // Row ExamAnswer BARU untuk q2 tercipta HANYA setelah submit
        $this->assertDatabaseCount('exam_answers', 2);
        $this->assertDatabaseHas('exam_answers', [
            'exam_session_id' => $session->id,
            'question_id' => $q2->id,
            'student_answer' => null,
            'score' => '15.00',
        ]);

        // Total recalculate: 10 (q1) + 15 (q2 tak terjawab yang dinilai guru)
        $this->assertDatabaseHas('grades', [
            'guru_mapel_id' => $guru->id,
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'score' => '25.00',
            'is_override' => true,
        ]);
    }

    /**
     * new_scores menolak soal yang GAGAL validasi targeting kelas sesi
     * (soal bukan milik mapel/kelas siswa).
     */
    public function test_save_scores_rejects_new_score_for_foreign_question(): void
    {
        [$guru, $subject, $classroom, $students] = $this->makeAmpuGuru(1);
        $student = $students->first();

        $q1 = $this->objectiveQuestion($subject->id, $classroom);

        $session = $this->makeGradedSession($subject->id, $classroom, $student, [
            [
                'question_id' => $q1->id,
                'student_answer' => 'D',
                'is_correct' => true,
                'score' => 10,
            ],
        ], 10.0);

        // Soal mapel LAIN / kelas lain
        $foreign = Question::factory()->create([
            'subject_id' => $subject->id,
            'type' => Question::TYPE_ESSAY,
            'question_text' => 'Soal di kelas lain.',
            'score_weight' => 20,
        ]);
        // Tidak di-attach ke classroom → targetingClassroom gagal

        // Key `scores` harus answer ID milik q1 (bukan question ID)
        $q1Answer = ExamAnswer::query()
            ->where('exam_session_id', $session->id)
            ->where('question_id', $q1->id)
            ->firstOrFail();

        $this->actingAs($guru->user)
            ->post(route('guru_mapel.grades.save-scores'), [
                'subject_id' => $subject->id,
                'classroom_id' => $classroom->id,
                'student_id' => $student->id,
                'session_id' => $session->id,
                'scores' => [$q1Answer->id => 10],
                'new_scores' => [$foreign->id => 5],
            ])
            ->assertSessionHasErrors('new_scores.'.$foreign->id);

        // Tidak ada row baru tercipta
        $this->assertDatabaseCount('exam_answers', 1);
    }

    // -----------------------------------------------------------------
    // REGRESI: JADWAL DENGAN CLASS_NAME KOSONG (BUG PRODUKSI)
    // -----------------------------------------------------------------

    public function test_grade_index_shows_scores_when_schedule_class_name_empty(): void
    {
        [$guru, $subject, $classroom, $students] = $this->makeAmpuGuru(1);
        $student = $students->first();
        $semester = $this->makeActiveSemester();

        $this->makeCbtSubjectGrade($guru->id, $student, $classroom->id, $subject->id, $semester->id, 90.00);

        $this->actingAs($guru->user)
            ->get(route('guru_mapel.grades.index', [
                'subject_id' => $subject->id,
                'classroom_id' => $classroom->id,
                'semester_id' => $semester->id,
            ]))
            ->assertOk()
            ->assertSee($student->user?->name)
            ->assertSee('90.00');

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

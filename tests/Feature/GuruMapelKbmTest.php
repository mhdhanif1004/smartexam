<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ExamAnswer;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\Grade;
use App\Models\GuruMapel;
use App\Models\Question;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeacherSubjectClassAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class GuruMapelKbmTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guru mapel yang mengampu satu mata pelajaran pada satu kelas, lengkap
     * dengan beberapa siswa di kelas itu.
     *
     * @return array{0: GuruMapel, 1: Subject, 2: Classroom, 3: Collection<int, Student>, 4: User}
     */
    private function makeAmpuGuru(int $studentCount = 2): array
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

        return [$guru, $subject, $classroom, $students, $guru->user];
    }

    private function singleChoicePayload(int $subjectId, int $classroomId, array $overrides = []): array
    {
        return array_merge([
            'subject_id' => $subjectId,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'question_text' => 'Berapakah hasil dari 2 + 2?',
            'classroom_ids' => [$classroomId],
            'score_weight' => 10,
            'single_options' => ['A' => 'Satu', 'B' => 'Dua', 'C' => 'Tiga', 'D' => 'Empat'],
            'single_answer' => 'D',
        ], $overrides);
    }

    // -----------------------------------------------------------------
    // SOAL
    // -----------------------------------------------------------------

    public function test_guru_can_create_question_for_ampu_subject_and_classroom(): void
    {
        [$guru, $subject, $classroom] = $this->makeAmpuGuru();

        $this->actingAs($guru->user)
            ->post(route('guru_mapel.questions.store'), $this->singleChoicePayload($subject->id, $classroom->id))
            ->assertRedirect(route('guru_mapel.questions.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('questions', [
            'subject_id' => $subject->id,
            'created_by_user_id' => $guru->user->id,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'question_text' => 'Berapakah hasil dari 2 + 2?',
        ]);

        $question = Question::query()
            ->where('created_by_user_id', $guru->user->id)
            ->first();

        $this->assertSame('D', $question->answer_key);
        $this->assertSame(['A', 'B', 'C', 'D'], array_keys($question->options));
        $this->assertDatabaseHas('question_classroom', [
            'question_id' => $question->id,
            'classroom_id' => $classroom->id,
        ]);
    }

    public function test_guru_cannot_create_question_for_subject_or_classroom_outside_ampu(): void
    {
        [$guru] = $this->makeAmpuGuru();

        $otherSubject = Subject::factory()->create();
        $otherClassroom = Classroom::factory()->create();

        $this->actingAs($guru->user)
            ->post(route('guru_mapel.questions.store'), $this->singleChoicePayload($otherSubject->id, $otherClassroom->id))
            ->assertForbidden();

        $this->assertDatabaseCount('questions', 0);
    }

    public function test_question_index_only_shows_own_questions(): void
    {
        [$guruA, $subjectA, $classroomA] = $this->makeAmpuGuru();
        [$guruB] = $this->makeAmpuGuru();

        $ownQuestion = Question::query()->create([
            'subject_id' => $subjectA->id,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'question_text' => 'Soal rahasia milik guru A',
            'options' => ['A' => 'a', 'B' => 'b'],
            'answer_key' => 'A',
            'score_weight' => 10,
            'created_by_user_id' => $guruA->user->id,
        ]);
        $ownQuestion->classrooms()->attach($classroomA->id);

        Question::query()->create([
            'subject_id' => $subjectA->id,
            'type' => Question::TYPE_ESSAY,
            'question_text' => 'Soal milik guru B yang sama sekali berbeda',
            'score_weight' => 10,
            'created_by_user_id' => $guruB->user->id,
        ]);

        Question::query()->create([
            'subject_id' => $subjectA->id,
            'type' => Question::TYPE_ESSAY,
            'question_text' => 'Soal buatan admin tanpa penanda pembuat',
            'score_weight' => 10,
        ]);

        $this->actingAs($guruA->user)
            ->get(route('guru_mapel.questions.index'))
            ->assertOk()
            ->assertSee('Soal rahasia milik guru A')
            ->assertDontSee('Soal milik guru B yang sama sekali berbeda')
            ->assertDontSee('Soal buatan admin tanpa penanda pembuat');
    }

    public function test_guru_cannot_edit_or_update_another_gurus_question(): void
    {
        [$guruA, $subjectA, $classroomA] = $this->makeAmpuGuru();
        [$guruB, $subjectB, $classroomB] = $this->makeAmpuGuru();

        $otherQuestion = Question::query()->create([
            'subject_id' => $subjectB->id,
            'type' => Question::TYPE_ESSAY,
            'question_text' => 'Soal milik guru B',
            'answer_key' => 'rubrik',
            'score_weight' => 10,
            'created_by_user_id' => $guruB->user->id,
        ]);
        $otherQuestion->classrooms()->attach($classroomB->id);

        $this->actingAs($guruA->user)
            ->get(route('guru_mapel.questions.edit', $otherQuestion))
            ->assertForbidden();

        $this->actingAs($guruA->user)
            ->put(route('guru_mapel.questions.update', $otherQuestion), $this->singleChoicePayload($subjectA->id, $classroomA->id, [
                'question_text' => 'Soal yang dicoba diubah guru A',
            ]))
            ->assertForbidden();

        $this->assertSame('Soal milik guru B', $otherQuestion->fresh()->question_text);
    }

    public function test_guru_cannot_delete_question_that_has_exam_answers(): void
    {
        [$guru, $subject, $classroom] = $this->makeAmpuGuru();

        $question = Question::query()->create([
            'subject_id' => $subject->id,
            'type' => Question::TYPE_ESSAY,
            'question_text' => 'Soal yang sudah pernah dijawab',
            'answer_key' => 'rubrik',
            'score_weight' => 10,
            'created_by_user_id' => $guru->user->id,
        ]);
        $question->classrooms()->attach($classroom->id);

        $session = ExamSession::factory()->create([
            'exam_schedule_id' => ExamSchedule::factory()->create(['subject_id' => $subject->id])->id,
        ]);

        ExamAnswer::create([
            'exam_session_id' => $session->id,
            'question_id' => $question->id,
            'student_answer' => ['A'],
        ]);

        $this->actingAs($guru->user)
            ->delete(route('guru_mapel.questions.destroy', $question))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('questions', ['id' => $question->id]);
    }

    public function test_guru_can_update_own_question_in_ampu_subject(): void
    {
        [$guru, $subject, $classroom] = $this->makeAmpuGuru();

        $question = Question::query()->create([
            'subject_id' => $subject->id,
            'type' => Question::TYPE_ESSAY,
            'question_text' => 'Soal asli',
            'answer_key' => 'rubrik lama',
            'score_weight' => 10,
            'created_by_user_id' => $guru->user->id,
        ]);
        $question->classrooms()->attach($classroom->id);

        $this->actingAs($guru->user)
            ->put(route('guru_mapel.questions.update', $question), [
                'subject_id' => $subject->id,
                'type' => Question::TYPE_ESSAY,
                'question_text' => 'Soal diperbarui',
                'classroom_ids' => [$classroom->id],
                'score_weight' => 12,
                'essay_answer' => 'rubrik baru',
            ])
            ->assertRedirect(route('guru_mapel.questions.index'))
            ->assertSessionHas('success');

        $fresh = $question->fresh();
        $this->assertSame('Soal diperbarui', $fresh->question_text);
        $this->assertSame($subject->id, $fresh->subject_id);
    }

    public function test_guru_cannot_move_own_question_to_another_subject_on_update(): void
    {
        [$guru, $subjectA, $classroomA] = $this->makeAmpuGuru();

        // Guru A ampu mapel kedua, sehingga ia sah mengurus keduanya.
        $otherAmpuSubject = Subject::factory()->create();
        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => $otherAmpuSubject->id,
            'classroom_id' => $classroomA->id,
        ]);

        $question = Question::query()->create([
            'subject_id' => $subjectA->id,
            'type' => Question::TYPE_ESSAY,
            'question_text' => 'Soal di mapel ampu',
            'answer_key' => 'rubrik',
            'score_weight' => 10,
            'created_by_user_id' => $guru->user->id,
        ]);
        $question->classrooms()->attach($classroomA->id);

        $this->actingAs($guru->user)
            ->put(route('guru_mapel.questions.update', $question), [
                'subject_id' => $otherAmpuSubject->id,
                'type' => Question::TYPE_ESSAY,
                'question_text' => 'Soal yang dicoba dipindah mapel',
                'classroom_ids' => [$classroomA->id],
                'score_weight' => 10,
                'essay_answer' => 'rubrik',
            ])
            ->assertSessionHasErrors('subject_id');

        $fresh = $question->fresh();
        $this->assertSame($subjectA->id, $fresh->subject_id);
        $this->assertSame('Soal di mapel ampu', $fresh->question_text);
    }

    // -----------------------------------------------------------------
    // NILAI
    // -----------------------------------------------------------------

    public function test_guru_can_store_grades_for_ampu_class(): void
    {
        [$guru, $subject, $classroom, $students] = $this->makeAmpuGuru(2);

        $this->actingAs($guru->user)
            ->post(route('guru_mapel.grades.store'), [
                'subject_id' => $subject->id,
                'classroom_id' => $classroom->id,
                'grade_type' => Grade::TYPE_TUGAS,
                'title' => 'Tugas 1',
                'score' => [
                    $students[0]->id => '90',
                    $students[1]->id => '75.50',
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('grades', [
            'guru_mapel_id' => $guru->id,
            'student_id' => $students[0]->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'grade_type' => Grade::TYPE_TUGAS,
            'title' => 'Tugas 1',
            'score' => '90.00',
        ]);
        $this->assertDatabaseHas('grades', [
            'student_id' => $students[1]->id,
            'score' => '75.50',
        ]);
    }

    public function test_grade_rejects_score_out_of_range_or_invalid_format(): void
    {
        [$guru, $subject, $classroom, $students] = $this->makeAmpuGuru(1);

        $this->actingAs($guru->user)
            ->post(route('guru_mapel.grades.store'), [
                'subject_id' => $subject->id,
                'classroom_id' => $classroom->id,
                'grade_type' => Grade::TYPE_UAS,
                'score' => [$students[0]->id => '150'],
            ])
            ->assertSessionHasErrors('score.'.$students[0]->id);

        $this->assertDatabaseCount('grades', 0);
    }

    public function test_grade_rejects_invalid_grade_type(): void
    {
        [$guru, $subject, $classroom, $students] = $this->makeAmpuGuru(1);

        $this->actingAs($guru->user)
            ->post(route('guru_mapel.grades.store'), [
                'subject_id' => $subject->id,
                'classroom_id' => $classroom->id,
                'grade_type' => 'quiz',
                'score' => [$students[0]->id => '80'],
            ])
            ->assertSessionHasErrors('grade_type');
    }

    public function test_grade_rejects_input_for_non_ampu_class(): void
    {
        [$guru, $subject] = $this->makeAmpuGuru(1);
        $otherClassroom = Classroom::factory()->create();

        $this->actingAs($guru->user)
            ->post(route('guru_mapel.grades.store'), [
                'subject_id' => $subject->id,
                'classroom_id' => $otherClassroom->id,
                'grade_type' => Grade::TYPE_TUGAS,
                'score' => [1 => '80'],
            ])
            ->assertForbidden();
    }
}

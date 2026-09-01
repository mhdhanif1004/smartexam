<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\GuruMapel;
use App\Models\Question;
use App\Models\Subject;
use App\Models\TeacherSubjectClassAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUploadAtasNamaGuruTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    /**
     * Guru + subject + kelas yang di-assign (classroom_id di pivot) sebagai
     * cakupan penugasan.
     *
     * @return array{0: GuruMapel, 1: Subject, 2: Classroom}
     */
    private function makeAssignedGuru(): array
    {
        $guru = GuruMapel::factory()->create();
        $subject = Subject::factory()->create();
        $classroom = Classroom::factory()->create();

        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
        ]);

        return [$guru, $subject, $classroom];
    }

    private function singleChoicePayload(int $subjectId, array $classroomIds, array $overrides = []): array
    {
        return array_merge([
            'subject_id' => $subjectId,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'question_text' => 'Berapakah hasil dari 2 + 2?',
            'classroom_ids' => $classroomIds,
            'score_weight' => 10,
            'single_options' => ['A' => 'Satu', 'B' => 'Dua', 'C' => 'Tiga', 'D' => 'Empat'],
            'single_answer' => 'D',
        ], $overrides);
    }

    public function test_admin_upload_question_atas_nama_guru_appears_in_guru_soal_page(): void
    {
        [$guru, $subject, $classroom] = $this->makeAssignedGuru();

        $this->actingAs($this->admin)
            ->post(route('admin.questions.store'), $this->singleChoicePayload(
                $subject->id,
                [$classroom->id],
                ['creator_user_id' => $guru->user->id]
            ))
            ->assertRedirect(route('admin.questions.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('questions', [
            'created_by_user_id' => $guru->user->id,
            'subject_id' => $subject->id,
            'type' => Question::TYPE_SINGLE_CHOICE,
        ]);

        $question = Question::query()
            ->where('created_by_user_id', $guru->user->id)
            ->firstOrFail();

        $this->assertDatabaseHas('question_classroom', [
            'question_id' => $question->id,
            'classroom_id' => $classroom->id,
        ]);

        // Soal otomatis muncul di halaman "Soal" milik guru tsb.
        $this->actingAs($guru->user)
            ->get(route('guru_mapel.questions.index'))
            ->assertOk()
            ->assertSee('Berapakah hasil dari 2 + 2?');
    }

    public function test_admin_upload_question_atas_nama_guru_rejected_for_class_outside_assignment(): void
    {
        [$guru, $subject] = $this->makeAssignedGuru();
        $outsideClassroom = Classroom::factory()->create();

        $this->actingAs($this->admin)
            ->from(route('admin.questions.create'))
            ->post(route('admin.questions.store'), $this->singleChoicePayload(
                $subject->id,
                [$outsideClassroom->id],
                ['creator_user_id' => $guru->user->id]
            ))
            ->assertSessionHasErrors('classroom_ids')
            ->assertRedirect(route('admin.questions.create'));

        $this->assertDatabaseCount('questions', 0);
    }

    public function test_admin_upload_question_without_guru_keeps_admin_ownership(): void
    {
        $subject = Subject::factory()->create();
        $classroom = Classroom::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.questions.store'), $this->singleChoicePayload($subject->id, [$classroom->id]))
            ->assertRedirect(route('admin.questions.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('questions', [
            'subject_id' => $subject->id,
            'created_by_user_id' => null,
        ]);
    }
}

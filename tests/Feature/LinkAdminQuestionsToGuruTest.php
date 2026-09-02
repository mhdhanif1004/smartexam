<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\GuruMapel;
use App\Models\Question;
use App\Models\Subject;
use App\Models\TeacherSubjectClassAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LinkAdminQuestionsToGuruTest extends TestCase
{
    use RefreshDatabase;

    public function test_question_with_all_target_classes_held_by_single_guru_is_linked(): void
    {
        $guru = GuruMapel::factory()->create();
        $subject = Subject::factory()->create();
        $classA = Classroom::create(['name' => 'X MIPA A']);
        $classB = Classroom::create(['name' => 'X MIPA B']);

        $this->assign($guru, $subject, $classA);
        $this->assign($guru, $subject, $classB);

        $question = $this->adminQuestion($subject, [$classA->id, $classB->id]);

        $this->artisan('exam:link-admin-questions')->assertSuccessful();

        $this->assertSame($guru->user_id, $question->fresh()->created_by_user_id);
    }

    public function test_question_with_classes_split_across_two_gurus_stays_null(): void
    {
        $guruA = GuruMapel::factory()->create();
        $guruB = GuruMapel::factory()->create();
        $subject = Subject::factory()->create();
        $classA = Classroom::create(['name' => 'X MIPA A']);
        $classB = Classroom::create(['name' => 'X MIPA B']);

        $this->assign($guruA, $subject, $classA);
        $this->assign($guruB, $subject, $classB);

        $question = $this->adminQuestion($subject, [$classA->id, $classB->id]);

        $this->artisan('exam:link-admin-questions')->assertSuccessful();

        $this->assertNull($question->fresh()->created_by_user_id);
    }

    public function test_question_without_any_matching_guru_stays_null(): void
    {
        $subject = Subject::factory()->create();
        $classX = Classroom::create(['name' => 'X MIPA A']);

        // Guru mengampu mapel lain, bukan mapel soal.
        $guru = GuruMapel::factory()->create();
        $this->assign($guru, Subject::factory()->create(), $classX);

        $question = $this->adminQuestion($subject, [$classX->id]);

        $this->artisan('exam:link-admin-questions')->assertSuccessful();

        $this->assertNull($question->fresh()->created_by_user_id);
    }

    public function test_command_is_idempotent(): void
    {
        $guru = GuruMapel::factory()->create();
        $subject = Subject::factory()->create();
        $classA = Classroom::create(['name' => 'X MIPA A']);
        $classB = Classroom::create(['name' => 'X MIPA B']);

        $this->assign($guru, $subject, $classA);
        $this->assign($guru, $subject, $classB);

        $linked = $this->adminQuestion($subject, [$classA->id, $classB->id]);
        // Soal yang sudah pernah ter-link ke guru lain tidak boleh berubah lagi.
        $alreadyOwned = $this->adminQuestion($subject, [$classA->id, $classB->id])
            ->update(['created_by_user_id' => GuruMapel::factory()->create()->user_id]);

        $this->artisan('exam:link-admin-questions')->assertSuccessful();

        $firstOwner = $linked->fresh()->created_by_user_id;
        $this->assertNotNull($firstOwner);

        // Jalankan ulang → hasil identik, tidak ada perubahan ataupun dobel proses.
        $this->artisan('exam:link-admin-questions')->assertSuccessful();

        $this->assertSame($firstOwner, $linked->fresh()->created_by_user_id);
        $this->assertTrue($alreadyOwned);
        $this->assertSame(1, Question::where('created_by_user_id', $firstOwner)->count());
    }

    private function assign(GuruMapel $guru, Subject $subject, Classroom $classroom): void
    {
        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
        ]);
    }

    private function adminQuestion(Subject $subject, array $classroomIds): Question
    {
        $question = Question::factory()->create(['subject_id' => $subject->id]);
        $question->classrooms()->attach($classroomIds);

        return $question;
    }
}

<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ExamType;
use App\Models\GuruMapel;
use App\Models\Question;
use App\Models\Subject;
use App\Models\TeacherSubjectClassAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function classroom(): Classroom
    {
        return Classroom::create(['name' => 'XI RPL 1']);
    }

    private function guruWithAssignment(Subject $subject, ?Classroom $classroom): GuruMapel
    {
        $guru = GuruMapel::factory()->create();
        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom?->id,
        ]);

        return $guru;
    }

    public function test_admin_can_store_question_with_owner_and_exam_type(): void
    {
        $subject = Subject::factory()->create();
        $class = $this->classroom();
        $guru = $this->guruWithAssignment($subject, $class);
        $examType = ExamType::firstOrCreate(['code' => 'harian-'.uniqid()], ['name' => 'Harian', 'sort_order' => 1]);

        $this->actingAs($this->admin())->post('/admin/questions', [
            'subject_id' => $subject->id,
            'guru_mapel_id' => $guru->id,
            'exam_type_id' => $examType->id,
            'type' => 'single_choice',
            'question_text' => 'Soal dengan pemilik dan jenis ujian?',
            'score_weight' => 10,
            'classroom_ids' => [$class->id],
            'single_options' => ['A' => 'Ya', 'B' => 'Tidak'],
            'single_answer' => 'A',
        ])->assertRedirect(route('admin.questions.index'));

        $this->assertDatabaseHas('questions', [
            'teacher_guru_mapel_id' => $guru->id,
            'exam_type_id' => $examType->id,
            'created_by_user_id' => $guru->user_id,
        ]);
    }

    public function test_admin_store_without_owner_and_exam_type_leaves_null(): void
    {
        $subject = Subject::factory()->create();
        $class = $this->classroom();

        $this->actingAs($this->admin())->post('/admin/questions', [
            'subject_id' => $subject->id,
            'type' => 'single_choice',
            'question_text' => 'Soal tanpa pemilik?',
            'score_weight' => 10,
            'classroom_ids' => [$class->id],
            'single_options' => ['A' => 'Ya', 'B' => 'Tidak'],
            'single_answer' => 'A',
        ])->assertRedirect(route('admin.questions.index'));

        $this->assertDatabaseHas('questions', [
            'teacher_guru_mapel_id' => null,
            'exam_type_id' => null,
            'created_by_user_id' => null,
        ]);
    }

    public function test_guru_store_auto_sets_teacher_guru_mapel_id_and_exam_type(): void
    {
        $subject = Subject::factory()->create();
        $class = $this->classroom();
        $guru = $this->guruWithAssignment($subject, $class);
        $examType = ExamType::firstOrCreate(['code' => 'uts-'.uniqid()], ['name' => 'UTS', 'sort_order' => 2]);

        $this->actingAs($guru->user)->post('/guru_mapel/questions', [
            'subject_id' => $subject->id,
            'exam_type_id' => $examType->id,
            'type' => 'single_choice',
            'question_text' => 'Soal buatan guru?',
            'score_weight' => 10,
            'single_options' => ['A' => 'Ya', 'B' => 'Tidak'],
            'single_answer' => 'A',
        ])->assertRedirect(route('guru_mapel.questions.index'));

        $this->assertDatabaseHas('questions', [
            'teacher_guru_mapel_id' => $guru->id,
            'exam_type_id' => $examType->id,
            'created_by_user_id' => $guru->user_id,
        ]);
    }

    public function test_admin_index_renders_hierarchy_buckets(): void
    {
        $subject = Subject::factory()->create();
        $class = $this->classroom();
        $guru = $this->guruWithAssignment($subject, $class);
        $examType = ExamType::firstOrCreate(['code' => 'harian-'.uniqid()], ['name' => 'Harian', 'sort_order' => 1]);

        $owned = Question::factory()->create([
            'subject_id' => $subject->id,
            'teacher_guru_mapel_id' => $guru->id,
            'exam_type_id' => $examType->id,
        ]);
        $owned->classrooms()->attach($class->id);

        // Soal tanpa owner (bucket "Belum Ada Guru") + tanpa jenis.
        $orphan = Question::factory()->create(['subject_id' => $subject->id]);
        $orphan->classrooms()->attach($class->id);

        // Filter aktif memaksa preload hierarki dirender di server.
        $html = $this->actingAs($this->admin())
            ->get(route('admin.questions.index', ['subject_id' => $subject->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Belum Ada Guru', $html);
        $this->assertStringContainsString('Belum Ditentukan', $html);
        $this->assertStringContainsString('Guru: '.$guru->user->name, $html);
        $this->assertStringContainsString('Jenis: Harian', $html);
    }
}

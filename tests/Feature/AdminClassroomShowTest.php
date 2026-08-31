<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\GuruMapel;
use App\Models\Question;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeacherSubjectClassAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminClassroomShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_classroom_detail_with_guru_and_students(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = GuruMapel::factory()->create();
        $subject = Subject::factory()->create();
        $classroom = Classroom::factory()->create();

        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
        ]);

        // Guru "mengampu" kelas ini lewat soal yang dibuatnya dan menargetkan
        // kelas tersebut — itulah sumber cakupan guru-per-kelas yang baru.
        $question = Question::query()->create([
            'subject_id' => $subject->id,
            'type' => Question::TYPE_ESSAY,
            'question_text' => 'Soal untuk kelas ini',
            'score_weight' => 10,
            'created_by_user_id' => $guru->user_id,
        ]);
        $question->classrooms()->attach($classroom->id);

        $student = Student::factory()->create([
            'classroom_id' => $classroom->id,
            'class_name' => $classroom->name,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.classrooms.show', $classroom))
            ->assertOk()
            ->assertSee($classroom->name)
            ->assertSee($subject->name)
            ->assertSee($guru->user->name)
            ->assertSee($guru->nip)
            ->assertSee($student->user->name);
    }

    public function test_classroom_show_is_forbidden_for_non_admin(): void
    {
        $guru = GuruMapel::factory()->create();
        $classroom = Classroom::factory()->create();

        $this->actingAs($guru->user)
            ->get(route('admin.classrooms.show', $classroom))
            ->assertForbidden();
    }
}

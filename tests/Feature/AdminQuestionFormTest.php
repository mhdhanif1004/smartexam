<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\QuestionController as AdminQuestionController;
use App\Http\Controllers\GuruMapel\QuestionController as GuruMapelQuestionController;
use App\Models\Classroom;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use App\Traits\BuildsQuestionPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminQuestionFormTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_classroom_picker_on_create_shows_all_classrooms(): void
    {
        $subject = Subject::factory()->create();
        $classrooms = Classroom::factory()->count(4)->create();

        foreach ($classrooms as $classroom) {
            $this->actingAs($this->admin)
                ->get(route('admin.questions.create', ['subject_id' => $subject->id]))
                ->assertOk()
                ->assertSee($classroom->name);
        }
    }

    public function test_classroom_picker_on_edit_shows_all_classrooms(): void
    {
        $classrooms = Classroom::factory()->count(3)->create();
        $question = Question::factory()->create();
        $question->classrooms()->attach($classrooms[0]->id);

        foreach ($classrooms as $classroom) {
            $this->actingAs($this->admin)
                ->get(route('admin.questions.edit', $question))
                ->assertOk()
                ->assertSee($classroom->name);
        }
    }

    public function test_both_question_controllers_share_builds_question_payload_trait(): void
    {
        $this->assertContains(BuildsQuestionPayload::class, class_uses_recursive(AdminQuestionController::class));
        $this->assertContains(BuildsQuestionPayload::class, class_uses_recursive(GuruMapelQuestionController::class));
    }
}

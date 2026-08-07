<?php

namespace Tests\Feature;

use App\Models\ExamAnswer;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_store_persists_each_of_the_five_question_types(): void
    {
        $subject = Subject::factory()->create();

        $this->actingAs($this->admin)->post('/admin/questions', [
            'subject_id' => $subject->id,
            'type' => 'single_choice',
            'question_text' => 'Soal pilihan ganda?',
            'score_weight' => 10,
            'single_options' => ['A' => 'Merah', 'B' => 'Biru', 'C' => 'Hijau'],
            'single_answer' => 'B',
        ])->assertRedirect(route('admin.questions.index'));

        $single = Question::where('type', 'single_choice')->first();
        $this->assertSame(['A' => 'Merah', 'B' => 'Biru', 'C' => 'Hijau'], $single->options);
        $this->assertSame('B', $single->answer_key);

        $this->actingAs($this->admin)->post('/admin/questions', [
            'subject_id' => $subject->id,
            'type' => 'multiple_choice',
            'question_text' => 'Soal banyak jawaban?',
            'score_weight' => 10,
            'multiple_options' => ['A' => 'X', 'B' => 'Y', 'C' => 'Z'],
            'multiple_answer' => ['A', 'C'],
        ])->assertRedirect(route('admin.questions.index'));

        $multiple = Question::where('type', 'multiple_choice')->first();
        $this->assertSame(['A' => 'X', 'B' => 'Y', 'C' => 'Z'], $multiple->options);
        $this->assertSame(['A', 'C'], $multiple->answer_key);

        $this->actingAs($this->admin)->post('/admin/questions', [
            'subject_id' => $subject->id,
            'type' => 'true_false',
            'question_text' => 'Bumi itu bulat?',
            'score_weight' => 10,
            'true_false_answer' => '1',
        ])->assertRedirect(route('admin.questions.index'));

        $trueFalse = Question::where('type', 'true_false')->first();
        $this->assertTrue($trueFalse->answer_key);
        $this->assertNull($trueFalse->options);

        $this->actingAs($this->admin)->post('/admin/questions', [
            'subject_id' => $subject->id,
            'type' => 'matching',
            'question_text' => 'Jodohkan pasangan berikut?',
            'score_weight' => 10,
            'matching_left' => ['Satu', 'Dua', 'Tiga'],
            'matching_right' => ['1', '2', '3'],
        ])->assertRedirect(route('admin.questions.index'));

        $matching = Question::where('type', 'matching')->first();
        $this->assertSame(['left' => ['Satu', 'Dua', 'Tiga'], 'right' => ['1', '2', '3']], $matching->options);
        $this->assertSame(['A' => '1', 'B' => '2', 'C' => '3'], $matching->answer_key);

        $this->actingAs($this->admin)->post('/admin/questions', [
            'subject_id' => $subject->id,
            'type' => 'essay',
            'question_text' => 'Jelaskan dengan bahasamu sendiri?',
            'score_weight' => 20,
            'essay_answer' => 'Kunci jawaban essay untuk koreksi manual.',
        ])->assertRedirect(route('admin.questions.index'));

        $essay = Question::where('type', 'essay')->first();
        $this->assertSame('Kunci jawaban essay untuk koreksi manual.', $essay->answer_key);
        $this->assertNull($essay->options);
    }

    public function test_edit_page_repopulates_each_of_the_five_question_types(): void
    {
        $subject = Subject::factory()->create();

        $single = Question::factory()->create([
            'subject_id' => $subject->id,
            'type' => 'single_choice',
            'question_text' => 'Soal pilihan ganda?',
            'options' => ['A' => 'Merah', 'B' => 'Biru'],
            'answer_key' => 'B',
        ]);
        $this->actingAs($this->admin)->get("/admin/questions/{$single->id}/edit")
            ->assertOk()
            ->assertSee('value="B" checked', false)
            ->assertSee('value="Merah"', false)
            ->assertSee('value="Biru"', false);

        $multiple = Question::factory()->create([
            'subject_id' => $subject->id,
            'type' => 'multiple_choice',
            'question_text' => 'Soal banyak jawaban?',
            'options' => ['A' => 'X', 'B' => 'Y'],
            'answer_key' => ['A', 'B'],
        ]);
        $this->actingAs($this->admin)->get("/admin/questions/{$multiple->id}/edit")
            ->assertOk()
            ->assertSee('value="A" checked', false)
            ->assertSee('value="B" checked', false)
            ->assertSee('value="X"', false)
            ->assertSee('value="Y"', false);

        $trueFalse = Question::factory()->create([
            'subject_id' => $subject->id,
            'type' => 'true_false',
            'question_text' => 'Bumi itu bulat?',
            'options' => null,
            'answer_key' => false,
        ]);
        $this->actingAs($this->admin)->get("/admin/questions/{$trueFalse->id}/edit")
            ->assertOk()
            ->assertSee('value="0" checked', false);

        $trueTrue = Question::factory()->create([
            'subject_id' => $subject->id,
            'type' => 'true_false',
            'question_text' => 'Air membeku pada 0 derajat?',
            'options' => null,
            'answer_key' => true,
        ]);
        $this->actingAs($this->admin)->get("/admin/questions/{$trueTrue->id}/edit")
            ->assertOk()
            ->assertSee('value="1" checked', false);

        $matching = Question::factory()->create([
            'subject_id' => $subject->id,
            'type' => 'matching',
            'question_text' => 'Jodohkan ibukota berikut?',
            'options' => ['left' => ['Indonesia', 'Malaysia'], 'right' => ['Jakarta', 'Kuala Lumpur']],
            'answer_key' => ['A' => '1', 'B' => '2'],
        ]);
        $this->actingAs($this->admin)->get("/admin/questions/{$matching->id}/edit")
            ->assertOk()
            ->assertSee('Indonesia')
            ->assertSee('Jakarta');

        $essay = Question::factory()->create([
            'subject_id' => $subject->id,
            'type' => 'essay',
            'question_text' => 'Jelaskan?',
            'options' => null,
            'answer_key' => 'Kunci jawaban essay untuk koreksi manual.',
        ]);
        $this->actingAs($this->admin)->get("/admin/questions/{$essay->id}/edit")
            ->assertOk()
            ->assertSee('Kunci jawaban essay untuk koreksi manual.');
    }

    public function test_admin_can_update_matching_question_and_pairs_are_repopulated(): void
    {
        $subject = Subject::factory()->create();
        $question = Question::factory()->create([
            'subject_id' => $subject->id,
            'type' => 'matching',
            'question_text' => 'Jodohkan?',
            'options' => ['left' => ['Satu', 'Dua'], 'right' => ['1', '2']],
            'answer_key' => ['A' => '1', 'B' => '2'],
        ]);

        $this->actingAs($this->admin)->put("/admin/questions/{$question->id}", [
            'subject_id' => $subject->id,
            'type' => 'matching',
            'question_text' => 'Jodohkan ulang?',
            'score_weight' => 15,
            'matching_left' => ['Bulan', 'Matahari', 'Bintang'],
            'matching_right' => ['Satelit bumi', 'Pusat tata surya', 'Bersinar sendiri'],
        ])->assertRedirect(route('admin.questions.index'));

        $fresh = $question->fresh();
        $this->assertSame(['left' => ['Bulan', 'Matahari', 'Bintang'], 'right' => ['Satelit bumi', 'Pusat tata surya', 'Bersinar sendiri']], $fresh->options);
        $this->assertSame(['A' => '1', 'B' => '2', 'C' => '3'], $fresh->answer_key);

        $this->actingAs($this->admin)->get("/admin/questions/{$question->id}/edit")
            ->assertOk()
            ->assertSee('Bulan')
            ->assertSee('Satelit bumi');
    }

    public function test_admin_can_change_question_type_and_old_data_is_overwritten(): void
    {
        $subject = Subject::factory()->create();
        $question = Question::factory()->create([
            'subject_id' => $subject->id,
            'type' => 'single_choice',
            'question_text' => 'Soal lama?',
            'options' => ['A' => 'X', 'B' => 'Y'],
            'answer_key' => 'A',
        ]);

        $this->actingAs($this->admin)->put("/admin/questions/{$question->id}", [
            'subject_id' => $subject->id,
            'type' => 'essay',
            'question_text' => 'Jadi soal essay?',
            'score_weight' => 20,
            'essay_answer' => 'Kunci essay baru.',
        ])->assertRedirect(route('admin.questions.index'));

        $fresh = $question->fresh();
        $this->assertSame('essay', $fresh->type);
        $this->assertNull($fresh->options);
        $this->assertSame('Kunci essay baru.', $fresh->answer_key);
    }

    public function test_essay_answer_key_is_optional(): void
    {
        $subject = Subject::factory()->create();

        $this->actingAs($this->admin)->post('/admin/questions', [
            'subject_id' => $subject->id,
            'type' => 'essay',
            'question_text' => 'Jelaskan tanpa kunci jawaban?',
            'score_weight' => 20,
        ])->assertRedirect(route('admin.questions.index'));

        $this->assertNull(Question::first()->answer_key);
    }

    public function test_option_text_zero_is_not_dropped(): void
    {
        $subject = Subject::factory()->create();

        $this->actingAs($this->admin)->post('/admin/questions', [
            'subject_id' => $subject->id,
            'type' => 'single_choice',
            'question_text' => 'Berapa hasil 2 + 2?',
            'score_weight' => 10,
            'single_options' => ['A' => '0', 'B' => '4', 'C' => '8'],
            'single_answer' => 'B',
        ])->assertRedirect(route('admin.questions.index'));

        $this->assertSame(['A' => '0', 'B' => '4', 'C' => '8'], Question::first()->options);
    }

    public function test_fields_of_unselected_question_types_are_ignored(): void
    {
        $subject = Subject::factory()->create();

        $this->actingAs($this->admin)->post('/admin/questions', [
            'subject_id' => $subject->id,
            'type' => 'essay',
            'question_text' => 'Essay saja?',
            'score_weight' => 10,
            'single_options' => ['A' => 'A', 'B' => 'B'],
            'single_answer' => 'A',
            'multiple_options' => ['A' => 'X', 'B' => 'Y'],
            'multiple_answer' => ['A'],
            'true_false_answer' => '1',
            'matching_left' => ['L1', 'L2'],
            'matching_right' => ['R1', 'R2'],
        ])->assertRedirect(route('admin.questions.index'));

        $question = Question::first();
        $this->assertSame('essay', $question->type);
        $this->assertNull($question->options);
        $this->assertNull($question->answer_key);
    }

    public function test_validation_rejects_incomplete_questions(): void
    {
        $subject = Subject::factory()->create();

        $this->actingAs($this->admin)->from('/admin/questions/create')->post('/admin/questions', [
            'subject_id' => $subject->id, 'type' => 'single_choice', 'question_text' => 'SC?', 'score_weight' => 10,
            'single_options' => ['A' => 'Satu', 'B' => ''],
            'single_answer' => 'A',
        ])->assertSessionHasErrors('single_options');

        $this->actingAs($this->admin)->from('/admin/questions/create')->post('/admin/questions', [
            'subject_id' => $subject->id, 'type' => 'single_choice', 'question_text' => 'SC?', 'score_weight' => 10,
            'single_options' => ['A' => 'Satu', 'B' => 'Dua'],
        ])->assertSessionHasErrors('single_answer');

        $this->actingAs($this->admin)->from('/admin/questions/create')->post('/admin/questions', [
            'subject_id' => $subject->id, 'type' => 'multiple_choice', 'question_text' => 'MC?', 'score_weight' => 10,
            'multiple_options' => ['A' => 'Satu', 'B' => 'Dua'],
            'multiple_answer' => [],
        ])->assertSessionHasErrors('multiple_answer');

        $this->actingAs($this->admin)->from('/admin/questions/create')->post('/admin/questions', [
            'subject_id' => $subject->id, 'type' => 'true_false', 'question_text' => 'TF?', 'score_weight' => 10,
        ])->assertSessionHasErrors('true_false_answer');

        $this->actingAs($this->admin)->from('/admin/questions/create')->post('/admin/questions', [
            'subject_id' => $subject->id, 'type' => 'matching', 'question_text' => 'Match?', 'score_weight' => 10,
            'matching_left' => ['Satu'],
            'matching_right' => ['1'],
        ])->assertSessionHasErrors('matching_left');

        $this->actingAs($this->admin)->from('/admin/questions/create')->post('/admin/questions', [
            'subject_id' => $subject->id, 'type' => 'matching', 'question_text' => 'Match?', 'score_weight' => 10,
            'matching_left' => ['Satu', 'Dua', 'Tiga'],
            'matching_right' => ['1', '2'],
        ])->assertSessionHasErrors('matching_right');
    }

    public function test_bulk_delete_removes_selected_questions(): void
    {
        $subject = Subject::factory()->create();
        $questions = Question::factory()->count(3)->create(['subject_id' => $subject->id]);

        $this->actingAs($this->admin)->post(route('admin.questions.bulk-delete'), [
            'ids' => [$questions[0]->id, $questions[1]->id],
        ])->assertRedirect()->assertSessionHas('success', '2 soal berhasil dihapus.');

        $this->assertDatabaseMissing('questions', ['id' => $questions[0]->id]);
        $this->assertDatabaseMissing('questions', ['id' => $questions[1]->id]);
        $this->assertDatabaseHas('questions', ['id' => $questions[2]->id]);
    }

    public function test_bulk_delete_requires_selection(): void
    {
        $this->actingAs($this->admin)->post(route('admin.questions.bulk-delete'), ['ids' => []])
            ->assertRedirect()
            ->assertSessionHas('error', 'Pilih minimal satu soal untuk dihapus.');
    }

    public function test_bulk_delete_rejects_questions_already_answered_by_participants(): void
    {
        $subject = Subject::factory()->create();
        $question = Question::factory()->create(['subject_id' => $subject->id]);
        $session = ExamSession::factory()->create([
            'exam_schedule_id' => ExamSchedule::factory()->create(['subject_id' => $subject->id])->id,
        ]);

        ExamAnswer::create([
            'exam_session_id' => $session->id,
            'question_id' => $question->id,
            'student_answer' => ['A'],
        ]);

        $this->actingAs($this->admin)->post(route('admin.questions.bulk-delete'), ['ids' => [$question->id]])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('questions', ['id' => $question->id]);
    }

    public function test_single_delete_rejects_questions_already_answered_by_participants(): void
    {
        $subject = Subject::factory()->create();
        $question = Question::factory()->create(['subject_id' => $subject->id]);
        $session = ExamSession::factory()->create([
            'exam_schedule_id' => ExamSchedule::factory()->create(['subject_id' => $subject->id])->id,
        ]);

        ExamAnswer::create([
            'exam_session_id' => $session->id,
            'question_id' => $question->id,
            'student_answer' => ['A'],
        ]);

        $this->actingAs($this->admin)->delete("/admin/questions/{$question->id}")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('questions', ['id' => $question->id]);
    }

    public function test_non_admin_cannot_bulk_delete_questions(): void
    {
        $question = Question::factory()->create();
        $peserta = User::factory()->peserta()->create();

        $this->actingAs($peserta)->post(route('admin.questions.bulk-delete'), ['ids' => [$question->id]])
            ->assertForbidden();
    }

    public function test_questions_index_renders_bulk_delete_ui(): void
    {
        Question::factory()->count(3)->create();

        $this->actingAs($this->admin)->get(route('admin.questions.index'))
            ->assertOk()
            ->assertSee('Hapus Massal')
            ->assertSee('smartexam_selected_questions')
            ->assertSee('confirm-bulk-delete');
    }
}

<?php

namespace Tests\Feature;

use App\Imports\QuestionsImport;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class QuestionModuleEnhancementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_can_duplicate_question(): void
    {
        $subject = Subject::factory()->create();
        $question = Question::factory()->create([
            'subject_id' => $subject->id,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'question_text' => 'Soal asli?',
            'options' => ['A' => 'Merah', 'B' => 'Biru'],
            'answer_key' => 'A',
            'score_weight' => 12.50,
            'is_active' => false,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.questions.duplicate', $question))
            ->assertRedirect(route('admin.questions.index'))
            ->assertSessionHas('success', 'Soal berhasil diduplikasi.');

        $copy = Question::where('id', '!=', $question->id)->first();
        $this->assertNotNull($copy);
        $this->assertSame('Soal asli?', $copy->question_text);
        $this->assertSame($question->subject_id, $copy->subject_id);
        $this->assertSame($question->type, $copy->type);
        $this->assertSame($question->options, $copy->options);
        $this->assertSame($question->answer_key, $copy->answer_key);
        $this->assertSame('12.50', $copy->score_weight);
        $this->assertTrue($copy->is_active);
    }

    public function test_admin_can_toggle_question_active_status(): void
    {
        $question = Question::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin)
            ->patch(route('admin.questions.toggle-active', $question))
            ->assertRedirect()
            ->assertSessionHas('success', 'Soal berhasil dinonaktifkan.');

        $this->assertFalse($question->fresh()->is_active);

        $this->actingAs($this->admin)
            ->patch(route('admin.questions.toggle-active', $question))
            ->assertRedirect()
            ->assertSessionHas('success', 'Soal berhasil diaktifkan.');

        $this->assertTrue($question->fresh()->is_active);
    }

    public function test_admin_can_bulk_edit_subject_weight_and_status(): void
    {
        $subjectA = Subject::factory()->create(['name' => 'Matematika']);
        $subjectB = Subject::factory()->create(['name' => 'IPA']);
        $questions = Question::factory()->count(3)->create(['subject_id' => $subjectA->id]);

        $this->actingAs($this->admin)
            ->post(route('admin.questions.bulk-edit'), [
                'ids' => [$questions[0]->id, $questions[1]->id],
                'subject_id' => $subjectB->id,
                'score_weight' => 15.75,
                'is_active' => '0',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Pengaturan 2 soal berhasil diperbarui.');

        foreach ([$questions[0], $questions[1]] as $question) {
            $fresh = $question->fresh();
            $this->assertSame($subjectB->id, $fresh->subject_id);
            $this->assertSame('15.75', $fresh->score_weight);
            $this->assertFalse($fresh->is_active);
        }

        $this->assertSame($subjectA->id, $questions[2]->fresh()->subject_id);
        $this->assertTrue($questions[2]->fresh()->is_active);
    }

    public function test_bulk_edit_requires_selection(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.questions.bulk-edit'), ['ids' => []])
            ->assertRedirect()
            ->assertSessionHas('error', 'Pilih minimal satu soal untuk diubah.');
    }

    public function test_bulk_edit_without_changes_is_rejected(): void
    {
        $question = Question::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.questions.bulk-edit'), ['ids' => [$question->id]])
            ->assertRedirect()
            ->assertSessionHas('error', 'Tidak ada perubahan yang dipilih.');
    }

    public function test_bulk_edit_rejects_invalid_subject(): void
    {
        $question = Question::factory()->create();

        $this->actingAs($this->admin)
            ->from(route('admin.questions.index'))
            ->post(route('admin.questions.bulk-edit'), [
                'ids' => [$question->id],
                'subject_id' => 99999,
            ])
            ->assertSessionHasErrors('subject_id');
    }

    public function test_questions_import_validate_accepts_all_five_types(): void
    {
        Subject::factory()->create(['name' => 'Matematika']);
        Subject::factory()->create(['name' => 'IPA']);

        $csv = "Mata Pelajaran,Jenis,Pertanyaan,Opsi A,Opsi B,Opsi C,Opsi D,Opsi E,Jawaban,Bobot,Kiri,Kanan\n"
            ."Matematika,Pilihan Ganda,Berapa 2+2?,3,4,5,,,B,10,,\n"
            ."Matematika,Pilihan Ganda Banyak,Manakah prima?,2,4,6,,,\"A,C\",10,,\n"
            ."IPA,Benar/Salah,Bumi itu bulat,,,,,,Benar,5,,\n"
            .'IPA,Menjodohkan,Jodohkan,,,,,,,10,"Kucing'.PHP_EOL.'Anjing","Meong'.PHP_EOL."Guk\"\n"
            ."Matematika,Essay,Jelaskan cara kerja listrik,,,,,,,20,,\n";

        $response = $this->actingAs($this->admin)
            ->post(route('admin.questions.import-validate'), [
                'file' => $this->csvFile($csv),
            ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'total' => 5,
                'valid' => 5,
                'invalid' => 0,
                'to_create' => 5,
            ]);
    }

    public function test_questions_import_validate_rejects_unknown_subject_and_type(): void
    {
        $csv = "Mata Pelajaran,Jenis,Pertanyaan\nKimia,Pilihan Ganda,Soal apa?\n";

        $response = $this->actingAs($this->admin)
            ->post(route('admin.questions.import-validate'), [
                'file' => $this->csvFile($csv),
            ])
            ->assertOk()
            ->assertJsonPath('valid', 0)
            ->assertJsonPath('invalid', 1);

        $this->assertStringContainsString('Mata pelajaran tidak ditemukan', $response->json('errors.0'));
    }

    public function test_questions_import_missing_header_is_rejected(): void
    {
        $csv = "Pertanyaan,Jawaban\nSoal apa?,A\n";

        $this->actingAs($this->admin)
            ->post(route('admin.questions.import-validate'), [
                'file' => $this->csvFile($csv),
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'Mata Pelajaran'));
    }

    public function test_questions_import_confirm_creates_questions(): void
    {
        $subject = Subject::factory()->create(['name' => 'Matematika']);

        $import = new QuestionsImport;
        $import->validRows = [
            ['row' => 2, 'subject_id' => $subject->id, 'type' => Question::TYPE_SINGLE_CHOICE, 'question_text' => 'Berapa 2+2?', 'options' => ['A' => '3', 'B' => '4'], 'answer_key' => 'B', 'score_weight' => 10.0],
            ['row' => 3, 'subject_id' => $subject->id, 'type' => Question::TYPE_ESSAY, 'question_text' => 'Jelaskan?', 'options' => null, 'answer_key' => 'Kunci essay.', 'score_weight' => 20.0],
        ];

        $response = $this->actingAs($this->admin)
            ->withSession(['questions_import_pending' => $import])
            ->post(route('admin.questions.import-confirm'))
            ->assertOk()
            ->assertJson(['ok' => true, 'created' => 2]);

        $single = Question::query()->where('type', Question::TYPE_SINGLE_CHOICE)->first();
        $this->assertNotNull($single);
        $this->assertSame(['A' => '3', 'B' => '4'], $single->options);
        $this->assertSame('B', $single->answer_key);
        $this->assertTrue($single->is_active);
        $this->assertSame('10.00', $single->score_weight);

        $essay = Question::query()->where('type', Question::TYPE_ESSAY)->first();
        $this->assertNotNull($essay);
        $this->assertNull($essay->options);
        $this->assertSame('Kunci essay.', $essay->answer_key);
    }

    public function test_questions_import_confirm_without_pending_session_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.questions.import-confirm'))
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'kadaluarsa'));
    }

    public function test_admin_can_download_questions_export(): void
    {
        $question = Question::factory()->create([
            'type' => Question::TYPE_SINGLE_CHOICE,
            'question_text' => 'Berapa 2+2?',
            'options' => ['A' => '3', 'B' => '4'],
            'answer_key' => 'B',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.questions.export'))
            ->assertOk()
            ->assertDownload('bank-soal-'.date('Y-m-d').'.xlsx');

        $csv = $this->actingAs($this->admin)
            ->get(route('admin.questions.export', ['format' => 'csv']))
            ->assertOk();

        $this->assertStringContainsString('Berapa 2+2?', $csv->streamedContent());
    }

    public function test_admin_can_download_questions_template(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.questions.import-template'))
            ->assertOk()
            ->assertDownload('template-import-soal.xlsx');
    }

    public function test_questions_index_filters_by_status(): void
    {
        Question::factory()->create(['question_text' => 'Soal aktif', 'is_active' => true]);
        Question::factory()->create(['question_text' => 'Soal nonaktif', 'is_active' => false]);

        $this->actingAs($this->admin)
            ->get(route('admin.questions.index', ['status' => 'nonaktif']))
            ->assertOk()
            ->assertSee('Soal nonaktif')
            ->assertDontSee('Soal aktif');
    }

    public function test_questions_index_renders_new_ui_elements(): void
    {
        $subject = Subject::factory()->create();
        $question = Question::factory()->create(['subject_id' => $subject->id]);

        $this->actingAs($this->admin)
            ->get(route('admin.questions.index', ['subject_id' => $subject->id]))
            ->assertOk()
            ->assertSee('Impor')
            ->assertSee('Ekspor')
            ->assertSee('Edit Massal')
            ->assertSee('Lihat')
            ->assertSee('Duplikat')
            ->assertSee('preview-question')
            ->assertSee('import-questions')
            ->assertSee('export-questions');
    }

    public function test_by_subject_endpoint_renders_correct_action_urls_per_question(): void
    {
        $subjectA = Subject::factory()->create();
        $subjectB = Subject::factory()->create();

        $questionA1 = Question::factory()->create(['subject_id' => $subjectA->id, 'question_text' => 'Soal A-1?']);
        $questionA2 = Question::factory()->create(['subject_id' => $subjectA->id, 'question_text' => 'Soal A-2?']);
        $questionB1 = Question::factory()->create(['subject_id' => $subjectB->id, 'question_text' => 'Soal B-1?']);

        $htmlA = $this->actingAs($this->admin)
            ->getJson(route('admin.questions.by-subject', $subjectA))
            ->assertOk()
            ->json('html');

        foreach ([$questionA1, $questionA2] as $question) {
            $this->assertStringContainsString(route('admin.questions.destroy', $question), $htmlA);
            $this->assertStringContainsString(route('admin.questions.edit', $question), $htmlA);
            $this->assertStringContainsString(route('admin.questions.duplicate', $question), $htmlA);
            $this->assertStringContainsString(route('admin.questions.toggle-active', $question), $htmlA);
        }

        // Soal dari mata pelajaran lain tidak boleh ikut dirender di HTML mapel ini.
        $this->assertStringNotContainsString(route('admin.questions.destroy', $questionB1), $htmlA);

        $htmlB = $this->actingAs($this->admin)
            ->getJson(route('admin.questions.by-subject', $subjectB))
            ->assertOk()
            ->json('html');

        $this->assertStringContainsString(route('admin.questions.destroy', $questionB1), $htmlB);
        $this->assertStringNotContainsString(route('admin.questions.destroy', $questionA1), $htmlB);
    }

    public function test_index_preloads_correct_destroy_url_when_filtered(): void
    {
        $subjectA = Subject::factory()->create();
        $subjectB = Subject::factory()->create();

        $questionA = Question::factory()->create(['subject_id' => $subjectA->id, 'question_text' => 'Soal A-1?']);
        $questionB = Question::factory()->create(['subject_id' => $subjectB->id, 'question_text' => 'Soal B-1?']);

        $this->actingAs($this->admin)
            ->get(route('admin.questions.index', ['subject_id' => $subjectA->id]))
            ->assertOk()
            ->assertSee(route('admin.questions.destroy', $questionA))
            ->assertSee(route('admin.questions.edit', $questionA))
            ->assertSee('deleteUrl')
            ->assertDontSee(route('admin.questions.destroy', $questionB));
    }

    public function test_inactive_questions_are_hidden_from_exam_work_page(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-07 09:00:00'));

        $room = Room::factory()->create();
        $student = Student::factory()->create(['room_id' => $room->id]);
        $user = $student->user;
        $subject = Subject::factory()->create(['name' => 'Matematika']);
        $schedule = ExamSchedule::factory()->create([
            'room_id' => $room->id,
            'subject_id' => $subject->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => now()->toDateString(),
            'start_time' => '08:30:00',
            'end_time' => '10:30:00',
            'duration_minutes' => 60,
            'status' => 'ongoing',
        ]);

        Question::factory()->create([
            'subject_id' => $subject->id,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'question_text' => 'Soal aktif di ujian',
            'options' => ['A' => 'X', 'B' => 'Y'],
            'answer_key' => 'A',
            'is_active' => true,
        ]);
        Question::factory()->create([
            'subject_id' => $subject->id,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'question_text' => 'Soal nonaktif TIDAK tampil',
            'options' => ['A' => 'X', 'B' => 'Y'],
            'answer_key' => 'A',
            'is_active' => false,
        ]);

        ExamSession::create([
            'student_id' => $student->id,
            'exam_schedule_id' => $schedule->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'started_at' => now(),
            'attendance_confirmed' => true,
        ]);

        $this->actingAs($user)
            ->get(route('peserta.exams.work', $schedule->id))
            ->assertOk()
            ->assertSee('Soal aktif di ujian')
            ->assertDontSee('Soal nonaktif TIDAK tampil');

        Carbon::setTestNow();
    }

    /**
     * Buat fake CSV dengan client mime text/csv agar lolos rule file.
     */
    private function csvFile(string $content, string $name = 'soal.csv'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'import_q_');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, 'text/csv', null, true);
    }
}

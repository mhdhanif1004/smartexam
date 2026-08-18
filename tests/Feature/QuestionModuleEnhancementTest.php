<?php

namespace Tests\Feature;

use App\Imports\Questions\SingleChoiceImport;
use App\Models\Classroom;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
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

    public function test_questions_import_validate_accepts_each_type(): void
    {
        Subject::factory()->create(['name' => 'Matematika']);
        Subject::factory()->create(['name' => 'IPA']);
        $classroom = Classroom::factory()->create();

        $cases = [
            [
                'type' => Question::TYPE_SINGLE_CHOICE,
                'csv' => "Mata Pelajaran,Pertanyaan,Opsi A,Opsi B,Opsi C,Opsi D,Opsi E,Kunci Jawaban,Bobot\nMatematika,Berapa 2+2?,3,4,5,,,B,10\n",
            ],
            [
                'type' => Question::TYPE_MULTIPLE_CHOICE,
                'csv' => "Mata Pelajaran,Pertanyaan,Opsi A,Opsi B,Opsi C,Opsi D,Opsi E,Kunci Jawaban,Bobot\nMatematika,Manakah prima?,2,4,6,,,\"A,C\",10\n",
            ],
            [
                'type' => Question::TYPE_TRUE_FALSE,
                'csv' => "Mata Pelajaran,Pertanyaan,Kunci Jawaban,Bobot\nIPA,Bumi itu bulat,Benar,5\n",
            ],
            [
                'type' => Question::TYPE_MATCHING,
                'csv' => "Mata Pelajaran,Pertanyaan,Kiri,Kanan,Bobot\nIPA,Jodohkan,\"Kucing\nAnjing\",\"Meong\nGuk\",10\n",
            ],
            [
                'type' => Question::TYPE_ESSAY,
                'csv' => "Mata Pelajaran,Pertanyaan,Kunci Jawaban / Rubrik,Bobot\nMatematika,Jelaskan cara kerja listrik,Referensi koreksi,20\n",
            ],
        ];

        foreach ($cases as $case) {
            $this->actingAs($this->admin)
                ->post(route('admin.questions.import-validate'), [
                    'type' => $case['type'],
                    'file' => $this->csvFile($case['csv']),
                    'classroom_ids' => [$classroom->id],
                ])
                ->assertOk()
                ->assertJson([
                    'ok' => true,
                    'total' => 1,
                    'valid' => 1,
                    'invalid' => 0,
                    'to_create' => 1,
                ]);
        }
    }

    public function test_questions_import_validate_skips_example_row(): void
    {
        Subject::factory()->create(['name' => 'Matematika']);
        $classroom = Classroom::factory()->create();

        $csv = "Mata Pelajaran,Pertanyaan,Opsi A,Opsi B,Opsi C,Opsi D,Opsi E,Kunci Jawaban,Bobot\n"
            ."Matematika,CONTOH: Berapa 2+2?,3,4,5,,,B,10\n"
            ."Matematika,Soal sungguhan?,7,8,9,,,C,10\n";

        $this->actingAs($this->admin)
            ->post(route('admin.questions.import-validate'), [
                'type' => Question::TYPE_SINGLE_CHOICE,
                'file' => $this->csvFile($csv),
                'classroom_ids' => [$classroom->id],
            ])
            ->assertOk()
            ->assertJson([
                'total' => 1,
                'valid' => 1,
                'invalid' => 0,
                'to_create' => 1,
            ]);
    }

    public function test_questions_import_validate_requires_type(): void
    {
        $csv = "Mata Pelajaran,Pertanyaan,Opsi A,Opsi B,Opsi C,Opsi D,Opsi E,Kunci Jawaban,Bobot\nMatematika,Berapa 2+2?,3,4,5,,,B,10\n";

        $this->actingAs($this->admin)
            ->post(route('admin.questions.import-validate'), [
                'file' => $this->csvFile($csv),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_questions_import_validate_rejects_unknown_subject(): void
    {
        $classroom = Classroom::factory()->create();
        $csv = "Mata Pelajaran,Pertanyaan,Opsi A,Opsi B,Opsi C,Opsi D,Opsi E,Kunci Jawaban,Bobot\nKimia,Soal apa?,1,2,3,4,5,A,10\n";

        $response = $this->actingAs($this->admin)
            ->post(route('admin.questions.import-validate'), [
                'type' => Question::TYPE_SINGLE_CHOICE,
                'file' => $this->csvFile($csv),
                'classroom_ids' => [$classroom->id],
            ])
            ->assertOk()
            ->assertJsonPath('valid', 0)
            ->assertJsonPath('invalid', 1);

        $this->assertStringContainsString("Mata pelajaran 'Kimia' tidak ditemukan", $response->json('errors.0'));
    }

    public function test_questions_import_validate_requires_classroom_ids(): void
    {
        Subject::factory()->create(['name' => 'Matematika']);
        $csv = "Mata Pelajaran,Pertanyaan,Opsi A,Opsi B,Opsi C,Opsi D,Opsi E,Kunci Jawaban,Bobot\nMatematika,Berapa 2+2?,3,4,5,,,B,10\n";

        $this->actingAs($this->admin)
            ->post(route('admin.questions.import-validate'), [
                'type' => Question::TYPE_SINGLE_CHOICE,
                'file' => $this->csvFile($csv),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('classroom_ids');
    }

    public function test_questions_import_missing_header_is_rejected(): void
    {
        $classroom = Classroom::factory()->create();
        $csv = "Pertanyaan,Jawaban\nSoal apa?,A\n";

        $this->actingAs($this->admin)
            ->post(route('admin.questions.import-validate'), [
                'type' => Question::TYPE_SINGLE_CHOICE,
                'file' => $this->csvFile($csv),
                'classroom_ids' => [$classroom->id],
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'Mata Pelajaran'));
    }

    public function test_questions_import_xlsx_reads_data_sheet_by_name_even_with_extra_sheet(): void
    {
        Subject::factory()->create(['name' => 'Matematika']);
        $classroom = Classroom::factory()->create();

        $spreadsheet = new Spreadsheet;
        $petunjuk = $spreadsheet->getActiveSheet();
        $petunjuk->setTitle('Petunjuk - Pilihan Ganda');
        $petunjuk->setCellValue('A1', 'TEMPLATE SOAL PILIHAN GANDA (SATU JAWABAN)');

        $data = $spreadsheet->createSheet();
        $data->setTitle('Data Pilihan Ganda');
        $data->fromArray([
            ['Mata Pelajaran', 'Pertanyaan', 'Opsi A', 'Opsi B', 'Opsi C', 'Opsi D', 'Opsi E', 'Kunci Jawaban', 'Bobot'],
            ['Matematika', 'CONTOH: Berapa 2+2?', '3', '4', '5', '', '', 'B', 10],
            ['Matematika', 'Berapa 1+1?', '1', '2', '3', '', '', 'B', 10],
        ]);

        $file = $this->spreadsheetFile($spreadsheet, 'soal.xlsx');

        $this->actingAs($this->admin)
            ->post(route('admin.questions.import-validate'), [
                'type' => Question::TYPE_SINGLE_CHOICE,
                'file' => $file,
                'classroom_ids' => [$classroom->id],
            ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'total' => 1,
                'valid' => 1,
                'invalid' => 0,
                'to_create' => 1,
            ]);

        $this->actingAs($this->admin)
            ->post(route('admin.questions.import-confirm'))
            ->assertOk()
            ->assertJson(['ok' => true, 'created' => 1]);

        $this->assertDatabaseHas('questions', ['question_text' => 'Berapa 1+1?']);
    }

    public function test_questions_import_rejects_file_without_matching_data_sheet(): void
    {
        Subject::factory()->create(['name' => 'Matematika']);
        $classroom = Classroom::factory()->create();

        $spreadsheet = new Spreadsheet;
        $data = $spreadsheet->getActiveSheet();
        $data->setTitle('Data Pilihan Ganda');
        $data->fromArray([
            ['Mata Pelajaran', 'Pertanyaan', 'Opsi A', 'Opsi B', 'Opsi C', 'Opsi D', 'Opsi E', 'Kunci Jawaban', 'Bobot'],
            ['Matematika', 'CONTOH: Berapa 2+2?', '3', '4', '5', '', '', 'B', 10],
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.questions.import-validate'), [
                'type' => Question::TYPE_ESSAY,
                'file' => $this->spreadsheetFile($spreadsheet, 'soal.xlsx'),
                'classroom_ids' => [$classroom->id],
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'Data Essay'));
    }

    public function test_questions_import_confirm_creates_questions(): void
    {
        $subject = Subject::factory()->create(['name' => 'Matematika']);
        $classroom = Classroom::factory()->create();

        $import = new SingleChoiceImport;
        $import->applyClassroomIds = [$classroom->id];
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
        $this->assertSame([$classroom->id], $single->classrooms->pluck('id')->all());

        $essay = Question::query()->where('type', Question::TYPE_ESSAY)->first();
        $this->assertNotNull($essay);
        $this->assertNull($essay->options);
        $this->assertSame('Kunci essay.', $essay->answer_key);
        $this->assertSame([$classroom->id], $essay->classrooms->pluck('id')->all());
    }

    public function test_questions_import_confirm_without_pending_session_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.questions.import-confirm'))
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'kadaluarsa'));
    }

    public function test_questions_import_confirm_applies_ui_classrooms_to_all_created_questions(): void
    {
        Subject::factory()->create(['name' => 'Matematika']);
        $classroomA = Classroom::factory()->create();
        $classroomB = Classroom::factory()->create();

        $csv = "Mata Pelajaran,Pertanyaan,Opsi A,Opsi B,Opsi C,Opsi D,Opsi E,Kunci Jawaban,Bobot\n"
            ."Matematika,Soal 1?,3,4,5,,,B,10\n"
            ."Matematika,Soal 2?,7,8,9,,,C,10\n";

        $this->actingAs($this->admin)
            ->post(route('admin.questions.import-validate'), [
                'type' => Question::TYPE_SINGLE_CHOICE,
                'file' => $this->csvFile($csv),
                'classroom_ids' => [$classroomA->id],
            ])
            ->assertOk()
            ->assertJson(['ok' => true, 'total' => 2, 'valid' => 2, 'invalid' => 0]);

        $this->actingAs($this->admin)
            ->post(route('admin.questions.import-confirm'))
            ->assertOk()
            ->assertJson(['ok' => true, 'created' => 2]);

        foreach (Question::query()->orderBy('id')->get() as $question) {
            $this->assertSame([$classroomA->id], $question->classrooms->pluck('id')->all());
        }
    }

    public function test_questions_import_ignores_kelas_target_column_in_file(): void
    {
        $subject = Subject::factory()->create(['name' => 'Matematika']);
        $classroom = Classroom::factory()->create(['name' => 'X RPL 1']);

        $csv = "Mata Pelajaran,Pertanyaan,Opsi A,Opsi B,Opsi C,Opsi D,Opsi E,Kunci Jawaban,Bobot,Kelas Target\n"
            ."Matematika,Soal 1?,3,4,5,,,B,10,X\n"
            ."Matematika,Soal 2?,7,8,9,,,C,10,X\n";

        $this->actingAs($this->admin)
            ->post(route('admin.questions.import-validate'), [
                'type' => Question::TYPE_SINGLE_CHOICE,
                'file' => $this->csvFile($csv),
                'classroom_ids' => [$classroom->id],
            ])
            ->assertOk()
            ->assertJson(['ok' => true, 'total' => 2, 'valid' => 2, 'invalid' => 0]);

        $this->actingAs($this->admin)
            ->post(route('admin.questions.import-confirm'))
            ->assertOk()
            ->assertJson(['ok' => true, 'created' => 2]);

        foreach (Question::query()->orderBy('id')->get() as $question) {
            $this->assertSame([$classroom->id], $question->classrooms->pluck('id')->all());
        }
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

    public function test_admin_can_download_questions_templates(): void
    {
        $expectedSheets = [
            Question::TYPE_SINGLE_CHOICE => 'Data Pilihan Ganda',
            Question::TYPE_MULTIPLE_CHOICE => 'Data Pilihan Ganda Banyak',
            Question::TYPE_TRUE_FALSE => 'Data Benar Salah',
            Question::TYPE_MATCHING => 'Data Menjodohkan',
            Question::TYPE_ESSAY => 'Data Essay',
        ];

        foreach ($expectedSheets as $type => $sheetTitle) {
            $response = $this->actingAs($this->admin)
                ->get(route('admin.questions.import-template', $type))
                ->assertOk();

            $spreadsheet = $this->loadSpreadsheet($response->streamedContent());

            $this->assertSame(
                [$sheetTitle],
                $spreadsheet->getSheetNames(),
                "Template {$type} harus hanya berisi 1 sheet data tanpa sheet Petunjuk."
            );

            if ($type === Question::TYPE_MATCHING) {
                $sheet = $spreadsheet->getSheet(0);
                $this->assertStringContainsString('BARIS TERPISAH', $sheet->getComment('C1')->getText()->getPlainText());
                $this->assertStringContainsString('sama dengan kolom Kiri', $sheet->getComment('D1')->getText()->getPlainText());
            }
        }
    }

    public function test_unknown_import_template_type_is_404(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.questions.import-template', 'not-a-type'))
            ->assertNotFound();
    }

    public function test_admin_can_create_question_with_image(): void
    {
        Storage::fake('public');
        $subject = Subject::factory()->create();
        $classroom = Classroom::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.questions.store'), [
                'subject_id' => $subject->id,
                'type' => Question::TYPE_SINGLE_CHOICE,
                'question_text' => 'Soal dengan gambar?',
                'score_weight' => 10,
                'classroom_ids' => [$classroom->id],
                'single_options' => ['A' => 'Merah', 'B' => 'Biru'],
                'single_answer' => 'A',
                'image' => UploadedFile::fake()->image('gambar.png', 400, 300),
            ])
            ->assertRedirect(route('admin.questions.index'))
            ->assertSessionHas('success', 'Soal berhasil ditambahkan.');

        $question = Question::query()->first();
        $this->assertNotNull($question->image_path);
        Storage::disk('public')->assertExists($question->image_path);
    }

    public function test_admin_can_remove_question_image_on_update(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('question-images/lama.png', 'dummy content');

        $subject = Subject::factory()->create();
        $classroom = Classroom::factory()->create();
        $question = Question::factory()->create([
            'subject_id' => $subject->id,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'options' => ['A' => 'Merah', 'B' => 'Biru'],
            'answer_key' => 'A',
            'image_path' => 'question-images/lama.png',
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.questions.update', $question), [
                'subject_id' => $subject->id,
                'type' => Question::TYPE_SINGLE_CHOICE,
                'question_text' => 'Soal diperbarui?',
                'score_weight' => 10,
                'classroom_ids' => [$classroom->id],
                'single_options' => ['A' => 'Merah', 'B' => 'Biru'],
                'single_answer' => 'A',
                'remove_image' => '1',
            ])
            ->assertRedirect(route('admin.questions.index'))
            ->assertSessionHas('success', 'Soal berhasil diperbarui.');

        $this->assertNull($question->fresh()->image_path);
        Storage::disk('public')->assertMissing('question-images/lama.png');
    }

    public function test_admin_can_replace_question_image_on_update(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('question-images/lama.png', 'dummy content');

        $subject = Subject::factory()->create();
        $classroom = Classroom::factory()->create();
        $question = Question::factory()->create([
            'subject_id' => $subject->id,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'options' => ['A' => 'Merah', 'B' => 'Biru'],
            'answer_key' => 'A',
            'image_path' => 'question-images/lama.png',
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.questions.update', $question), [
                'subject_id' => $subject->id,
                'type' => Question::TYPE_SINGLE_CHOICE,
                'question_text' => 'Soal diperbarui?',
                'score_weight' => 10,
                'classroom_ids' => [$classroom->id],
                'single_options' => ['A' => 'Merah', 'B' => 'Biru'],
                'single_answer' => 'A',
                'image' => UploadedFile::fake()->image('baru.png', 400, 300),
            ])
            ->assertRedirect(route('admin.questions.index'))
            ->assertSessionHas('success', 'Soal berhasil diperbarui.');

        $this->assertNotSame('question-images/lama.png', $question->fresh()->image_path);
        Storage::disk('public')->assertMissing('question-images/lama.png');
        Storage::disk('public')->assertExists($question->fresh()->image_path);
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

        Question::query()->where('subject_id', $subject->id)->get()->each(fn (Question $question) => $question->classrooms()->sync($student->classroom_id));

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

    public function test_exam_work_page_image_url_follows_request_host(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-07 09:00:00'));

        Storage::fake('public');
        Storage::disk('public')->put('question-images/gambar-uji.png', 'dummy');

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
            'type' => Question::TYPE_ESSAY,
            'question_text' => 'Soal dengan gambar?',
            'image_path' => 'question-images/gambar-uji.png',
            'is_active' => true,
        ])->classrooms()->sync($student->classroom_id);

        ExamSession::create([
            'student_id' => $student->id,
            'exam_schedule_id' => $schedule->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'started_at' => now(),
            'attendance_confirmed' => true,
        ]);

        // Simulasikan akses lewat http://localhost:8000 (bukan APP_URL yang
        // tidak memuat port). URL gambar harus mengikuti root akses ini.
        URL::forceRootUrl('http://localhost:8000');

        $response = $this->actingAs($user)
            ->get(route('peserta.exams.work', $schedule->id))
            ->assertOk();

        // Js::from() meng-escape slash menjadi \/ dalam JSON.parse; normalisasi
        // agar bisa dicek sebagai string URL biasa.
        $html = str_replace('\\', '', $response->getContent());

        $this->assertStringContainsString('http://localhost:8000/storage/question-images/gambar-uji.png', $html);
        $this->assertStringNotContainsString('http://localhost/storage/question-images/gambar-uji.png', $html);
        $this->assertStringContainsString('Mulai Ujian', $html);

        URL::forceRootUrl(null);
        Carbon::setTestNow();
    }

    public function test_student_does_not_see_questions_targeted_to_other_classrooms_on_work_page(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-07 09:00:00'));

        $room = Room::factory()->create();
        $classroom = Classroom::factory()->create(['name' => 'X RPL 3']);
        $otherClassrooms = [
            Classroom::factory()->create(['name' => 'X RPL 1']),
            Classroom::factory()->create(['name' => 'X RPL 2']),
        ];
        $student = Student::factory()->create([
            'room_id' => $room->id,
            'class_name' => $classroom->name,
            'classroom_id' => $classroom->id,
        ]);
        $user = $student->user;
        $subject = Subject::factory()->create(['name' => 'Matematika']);
        $schedule = ExamSchedule::factory()->create([
            'room_id' => $room->id,
            'subject_id' => $subject->id,
            'class_name' => $classroom->name,
            'exam_date' => now()->toDateString(),
            'start_time' => '08:30:00',
            'end_time' => '10:30:00',
            'duration_minutes' => 60,
            'status' => 'ongoing',
        ]);

        $foreignQuestion = Question::factory()->create([
            'subject_id' => $subject->id,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'question_text' => 'Soal kelas X RPL 1 dan X RPL 2 (TIDAK untuk X RPL 3)',
            'options' => ['A' => 'X', 'B' => 'Y'],
            'answer_key' => 'A',
            'is_active' => true,
        ]);
        $foreignQuestion->classrooms()->sync(array_map(fn ($classroom) => $classroom->id, $otherClassrooms));

        $ownQuestion = Question::factory()->create([
            'subject_id' => $subject->id,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'question_text' => 'Soal kelas X RPL 3',
            'options' => ['A' => 'X', 'B' => 'Y'],
            'answer_key' => 'A',
            'is_active' => true,
        ]);
        $ownQuestion->classrooms()->sync($classroom->id);

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
            ->assertSee('Soal kelas X RPL 3')
            ->assertDontSee('Soal kelas X RPL 1 dan X RPL 2 (TIDAK untuk X RPL 3)');

        Carbon::setTestNow();
    }

    public function test_subject_delete_preview_returns_simple_mode_for_empty_subject(): void
    {
        $subject = Subject::factory()->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.subjects.delete-preview', $subject))
            ->assertOk()
            ->assertJson([
                'questions_count' => 0,
                'exam_schedules_count' => 0,
                'exam_sessions_count' => 0,
            ]);
    }

    public function test_subject_delete_preview_returns_counts(): void
    {
        $subject = Subject::factory()->create();
        $classroom = Classroom::factory()->create();
        Question::factory()->count(3)->create(['subject_id' => $subject->id]);
        $q = $subject->questions()->first();
        $q->classrooms()->sync([$classroom->id]);
        $examSchedule = ExamSchedule::factory()->create(['subject_id' => $subject->id]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.subjects.delete-preview', $subject))
            ->assertOk()
            ->assertJson([
                'questions_count' => 3,
                'exam_schedules_count' => 1,
                'exam_sessions_count' => 0,
            ]);
    }

    public function test_subject_delete_preview_blocks_when_exam_sessions_exist(): void
    {
        $subject = Subject::factory()->create();
        $examSchedule = ExamSchedule::factory()->create(['subject_id' => $subject->id]);
        $examSession = ExamSession::factory()->create(['exam_schedule_id' => $examSchedule->id, 'started_at' => now(), 'finished_at' => now()->addHour()]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.subjects.delete-preview', $subject))
            ->assertOk()
            ->assertJson([
                'exam_sessions_count' => 1,
            ]);
    }

    public function test_subject_destroy_blocks_when_exam_sessions_exist(): void
    {
        $subject = Subject::factory()->create();
        $examSchedule = ExamSchedule::factory()->create(['subject_id' => $subject->id]);
        ExamSession::factory()->create(['exam_schedule_id' => $examSchedule->id, 'started_at' => now(), 'finished_at' => now()->addHour()]);

        $this->actingAs($this->admin)
            ->delete(route('admin.subjects.destroy', $subject))
            ->assertSessionHas('error', 'Mata pelajaran tidak dapat dihapus karena sudah memiliki histori ujian siswa.');

        $this->assertDatabaseHas('subjects', ['id' => $subject->id]);
    }

    public function test_subject_destroy_succeeds_when_no_exam_sessions(): void
    {
        $subject = Subject::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.subjects.destroy', $subject))
            ->assertRedirect(route('admin.subjects.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('subjects', ['id' => $subject->id]);
    }

    public function test_subject_update_name_via_ajax(): void
    {
        $subject = Subject::factory()->create(['name' => 'Matematika']);

        $this->actingAs($this->admin)
            ->patch(route('admin.subjects.update-name', $subject), [
                'name' => 'Matematika Wajib',
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJson(['ok' => true, 'name' => 'Matematika Wajib']);

        $this->assertDatabaseHas('subjects', ['id' => $subject->id, 'name' => 'Matematika Wajib']);
    }

    public function test_subject_update_name_requires_name_field(): void
    {
        $subject = Subject::factory()->create();

        $this->actingAs($this->admin)
            ->patch(route('admin.subjects.update-name', $subject), [
                'name' => '',
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_questions_index_renders_subject_edit_and_delete_buttons(): void
    {
        $subject = Subject::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.questions.index', ['subject_id' => $subject->id]))
            ->assertOk()
            ->assertSee('subjectEdit.open(')
            ->assertSee('subjectDelete.open(');
    }

    public function test_questions_index_displays_classroom_summary_in_header(): void
    {
        $subject = Subject::factory()->create();
        $classroom = Classroom::factory()->create(['name' => 'X RPL 1']);
        $question = Question::factory()->create(['subject_id' => $subject->id]);
        $question->classrooms()->sync([$classroom->id]);

        $this->actingAs($this->admin)
            ->get(route('admin.questions.index', ['subject_id' => $subject->id]))
            ->assertOk()
            ->assertSee('X RPL 1')
            ->assertSee('Kelas:');
    }

    public function test_admin_can_bulk_update_classrooms_for_question_group(): void
    {
        $classroomA = Classroom::factory()->create();
        $classroomB = Classroom::factory()->create();
        $classroomC = Classroom::factory()->create();
        $question1 = Question::factory()->create();
        $question2 = Question::factory()->create();
        $question1->classrooms()->sync([$classroomA->id]);
        $question2->classrooms()->sync([$classroomA->id]);

        $this->actingAs($this->admin)
            ->patch(route('admin.questions.bulk-classrooms'), [
                'question_ids' => [$question1->id, $question2->id],
                'classroom_ids' => [$classroomB->id, $classroomC->id],
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame([$classroomB->id, $classroomC->id], $question1->fresh()->classrooms->pluck('id')->sort()->values()->all());
        $this->assertSame([$classroomB->id, $classroomC->id], $question2->fresh()->classrooms->pluck('id')->sort()->values()->all());
    }

    public function test_bulk_update_classrooms_requires_valid_questions_and_classrooms(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('admin.questions.bulk-classrooms'), [
                'question_ids' => [99999],
                'classroom_ids' => [99999],
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['question_ids.0', 'classroom_ids.0']);
    }

    public function test_group_delete_preview_returns_simple_when_no_answers(): void
    {
        $questions = Question::factory()->count(3)->create();

        $this->actingAs($this->admin)
            ->post(route('admin.questions.group-delete-preview'), [
                'question_ids' => $questions->pluck('id')->all(),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJson([
                'questions_count' => 3,
                'answered_count' => 0,
            ]);
    }

    public function test_group_delete_preview_blocks_when_answers_exist(): void
    {
        $questions = Question::factory()->count(2)->create();
        $examSession = ExamSession::factory()->create();
        DB::table('exam_answers')->insert([
            'exam_session_id' => $examSession->id,
            'question_id' => $questions->first()->id,
            'student_answer' => null,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.questions.group-delete-preview'), [
                'question_ids' => $questions->pluck('id')->all(),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJson([
                'questions_count' => 2,
                'answered_count' => 1,
            ]);
    }

    public function test_by_subject_endpoint_returns_grouped_html(): void
    {
        $subject = Subject::factory()->create();
        $classroomA = Classroom::factory()->create();
        $classroomB = Classroom::factory()->create();
        $q1 = Question::factory()->create(['subject_id' => $subject->id]);
        $q2 = Question::factory()->create(['subject_id' => $subject->id]);
        $q1->classrooms()->sync([$classroomA->id, $classroomB->id]);
        $q2->classrooms()->sync([$classroomA->id]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.questions.by-subject', $subject), ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonStructure([
                'subject_id',
                'count',
                'ids',
                'html',
            ]);

        $data = $response->json();
        $this->assertCount(2, $data['ids']);
        $this->assertStringContainsString('Kelas:', $data['html']);
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

    private function spreadsheetFile(Spreadsheet $spreadsheet, string $name = 'soal.xlsx'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'import_q_');
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);

        return new UploadedFile(
            $path,
            $name,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    private function loadSpreadsheet(string $content): Spreadsheet
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx_');
        file_put_contents($path, $content);
        $spreadsheet = IOFactory::load($path);
        unlink($path);

        return $spreadsheet;
    }
}

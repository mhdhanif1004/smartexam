<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\GuruMapel;
use App\Models\Question;
use App\Models\Subject;
use App\Models\TeacherSubjectClassAssignment;
use App\Models\User;
use App\Services\ExamGradingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class QuestionOptionImageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private GuruMapel $guru;

    private Subject $subject;

    private Classroom $classroom;

    private int $defaultDuration = 90;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->classroom = Classroom::factory()->create(['name' => 'XI RPL 1']);
        $this->subject = Subject::factory()->create(['name' => 'Matematika']);

        $this->guru = GuruMapel::factory()->create();
        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $this->guru->id,
            'subject_id' => $this->subject->id,
            'classroom_id' => $this->classroom->id,
        ]);
    }

    private function jpegUpload(int $width = 3200, int $height = 2000, string $name = 'foto.jpg'): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'img_opt_');
        $im = imagecreatetruecolor($width, $height);
        imagefilledrectangle($im, 0, 0, $width, $height, imagecolorallocate($im, 200, 100, 50));
        imagejpeg($im, $tmp, 90);
        imagedestroy($im);

        return new UploadedFile($tmp, $name, 'image/jpeg', null, true);
    }

    private function singleChoicePayload(array $overrides = []): array
    {
        return array_merge([
            'subject_id' => $this->subject->id,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'question_text' => 'Soal dengan gambar opsi?',
            'score_weight' => 10,
            'classroom_ids' => [$this->classroom->id],
            'single_options' => ['A' => 'Merah', 'B' => 'Biru', 'C' => 'Hijau'],
            'single_answer' => 'A',
        ], $overrides);
    }

    private function multipleChoicePayload(array $overrides = []): array
    {
        return array_merge([
            'subject_id' => $this->subject->id,
            'type' => Question::TYPE_MULTIPLE_CHOICE,
            'question_text' => 'Soal multiple pilihan?',
            'score_weight' => 10,
            'classroom_ids' => [$this->classroom->id],
            'multiple_options' => ['A' => 'Merah', 'B' => 'Biru', 'C' => 'Hijau'],
            'multiple_answer' => ['A', 'C'],
        ], $overrides);
    }

    public function test_admin_store_converts_option_image_to_webp_and_scales_down(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->post(route('admin.questions.store'), $this->singleChoicePayload([
                'single_options_image' => ['B' => $this->jpegUpload(3200, 2000, 'b.jpg')],
            ]))
            ->assertRedirect(route('admin.questions.index'))
            ->assertSessionHas('success');

        $question = Question::query()->first();
        $this->assertIsArray($question->options['B']);
        $this->assertSame('Biru', $question->options['B']['text']);
        $this->assertTrue(Str::endsWith($question->options['B']['image'], '.webp'));
        $this->assertSame('Merah', $question->options['A']); // opsi lain tetap string murni
        Storage::disk('public')->assertExists($question->options['B']['image']);

        $info = getimagesizefromstring((string) Storage::disk('public')->get($question->options['B']['image']));
        $this->assertLessThanOrEqual(1600, $info[0]);
        $this->assertLessThanOrEqual(1600, $info[1]);
    }

    public function test_admin_multiple_choice_option_image_upload(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->post(route('admin.questions.store'), $this->multipleChoicePayload([
                'multiple_options_image' => ['C' => $this->jpegUpload()],
            ]))
            ->assertRedirect(route('admin.questions.index'))
            ->assertSessionHas('success');

        $question = Question::query()->first();
        $this->assertIsArray($question->options['C']);
        $this->assertSame('Hijau', $question->options['C']['text']);
        Storage::disk('public')->assertExists($question->options['C']['image']);
    }

    public function test_true_false_option_images_and_labels(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->post(route('admin.questions.store'), [
                'subject_id' => $this->subject->id,
                'type' => Question::TYPE_TRUE_FALSE,
                'question_text' => 'Bumi bulat?',
                'score_weight' => 10,
                'classroom_ids' => [$this->classroom->id],
                'true_false_answer' => '1',
                'true_false_image' => [
                    'true' => $this->jpegUpload(400, 300, 'benar.jpg'),
                ],
            ])
            ->assertRedirect(route('admin.questions.index'))
            ->assertSessionHas('success');

        $question = Question::query()->first();
        $this->assertIsArray($question->options);
        $this->assertIsArray($question->options['true']);
        $this->assertSame('Benar', $question->options['true']['text']);
        $this->assertSame('Salah', $question->options['false']['text']);
        $this->assertStringEndsWith('.webp', $question->options['true']['image']);
        Storage::disk('public')->assertExists($question->options['true']['image']);
    }

    public function test_guru_mapel_store_option_image(): void
    {
        Storage::fake('public');

        $this->actingAs($this->guru->user)
            ->post(route('guru_mapel.questions.store'), $this->singleChoicePayload([
                'single_options_image' => ['A' => $this->jpegUpload()],
            ]))
            ->assertRedirect(route('guru_mapel.questions.index'))
            ->assertSessionHas('success');

        $question = Question::query()
            ->where('created_by_user_id', $this->guru->user->id)
            ->first();
        $this->assertIsArray($question->options['A']);
        Storage::disk('public')->assertExists($question->options['A']['image']);
    }

    public function test_update_replaces_option_image_and_deletes_old_file(): void
    {
        Storage::fake('public');
        $oldPath = 'question-images/lama.webp';
        Storage::disk('public')->put($oldPath, 'old');

        $question = Question::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'question_text' => 'Soal?',
            'options' => ['A' => ['text' => 'Lama', 'image' => $oldPath], 'B' => 'Baru'],
            'answer_key' => 'A',
            'score_weight' => 10,
        ]);
        $question->classrooms()->attach($this->classroom->id);

        $this->actingAs($this->admin)
            ->put(route('admin.questions.update', $question), $this->singleChoicePayload([
                'existing_single_options_image' => ['A' => $oldPath],
                'single_options' => ['A' => 'Lama', 'B' => 'Baru', 'C' => 'Hijau'],
            ]))
            ->assertRedirect(route('admin.questions.index'))
            ->assertSessionHas('success');

        // Tanpa upload baru + tanpa remove → path lama dipertahankan.
        $this->assertSame($oldPath, $question->fresh()->options['A']['image']);
        Storage::disk('public')->assertExists($oldPath);
    }

    public function test_update_remove_option_image_deletes_file(): void
    {
        Storage::fake('public');
        $oldPath = 'question-images/hapus.webp';
        Storage::disk('public')->put($oldPath, 'old');

        $question = Question::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'question_text' => 'Soal?',
            'options' => ['A' => ['text' => 'Ada gambar', 'image' => $oldPath], 'B' => 'Biru'],
            'answer_key' => 'A',
            'score_weight' => 10,
        ]);
        $question->classrooms()->attach($this->classroom->id);

        $this->actingAs($this->admin)
            ->put(route('admin.questions.update', $question), $this->singleChoicePayload([
                'remove_single_options_image' => ['A' => '1'],
                'single_options' => ['A' => 'Ada gambar', 'B' => 'Biru', 'C' => 'Hijau'],
            ]))
            ->assertRedirect(route('admin.questions.index'))
            ->assertSessionHas('success');

        $fresh = $question->fresh();
        $this->assertSame('Ada gambar', $fresh->options['A']);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_type_change_to_essay_deletes_old_option_images(): void
    {
        Storage::fake('public');
        $oldPath = 'question-images/typechange.webp';
        Storage::disk('public')->put($oldPath, 'old');

        $question = Question::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'question_text' => 'Soal?',
            'options' => ['A' => ['text' => 'A', 'image' => $oldPath], 'B' => 'B'],
            'answer_key' => 'A',
            'score_weight' => 10,
        ]);
        $question->classrooms()->attach($this->classroom->id);

        $this->actingAs($this->admin)
            ->put(route('admin.questions.update', $question), [
                'subject_id' => $this->subject->id,
                'type' => Question::TYPE_ESSAY,
                'question_text' => 'Soal essay baru',
                'score_weight' => 10,
                'essay_answer' => 'Kunci',
                'classroom_ids' => [$this->classroom->id],
            ])
            ->assertRedirect(route('admin.questions.index'))
            ->assertSessionHas('success');

        $fresh = $question->fresh();
        $this->assertNull($fresh->options);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_removing_one_option_with_image_only_deletes_that_file(): void
    {
        Storage::fake('public');
        $pathA = 'question-images/keep.webp';
        $pathB = 'question-images/drop.webp';
        Storage::disk('public')->put($pathA, 'a');
        Storage::disk('public')->put($pathB, 'b');

        $question = Question::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'question_text' => 'Soal?',
            'options' => [
                'A' => ['text' => 'A', 'image' => $pathA],
                'B' => ['text' => 'B', 'image' => $pathB],
            ],
            'answer_key' => 'A',
            'score_weight' => 10,
        ]);
        $question->classrooms()->attach($this->classroom->id);

        $this->actingAs($this->admin)
            ->put(route('admin.questions.update', $question), $this->singleChoicePayload([
                'existing_single_options_image' => ['A' => $pathA],
                'single_options' => ['A' => 'A', 'C' => 'C', 'D' => 'D'], // B dihapus
                'single_answer' => 'A',
            ]))
            ->assertRedirect(route('admin.questions.index'));

        $this->assertArrayNotHasKey('B', $question->fresh()->options);
        Storage::disk('public')->assertExists($pathA);
        Storage::disk('public')->assertMissing($pathB);
    }

    public function test_duplicate_copies_option_images_physically(): void
    {
        Storage::fake('public');
        $pathA = 'question-images/sumber.webp';
        Storage::disk('public')->put($pathA, 'gambar');

        $question = Question::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'question_text' => 'Soal?',
            'options' => ['A' => ['text' => 'A', 'image' => $pathA], 'B' => 'B'],
            'answer_key' => 'A',
            'score_weight' => 10,
        ]);
        $question->classrooms()->attach($this->classroom->id);

        $this->actingAs($this->admin)
            ->post(route('admin.questions.duplicate', $question))
            ->assertRedirect(route('admin.questions.index'))
            ->assertSessionHas('success');

        $copy = Question::query()->where('id', '!=', $question->id)->first();
        $this->assertIsArray($copy->options['A']);
        $this->assertNotSame($pathA, $copy->options['A']['image']);
        $this->assertSame($pathA, $question->fresh()->options['A']['image']);
        Storage::disk('public')->assertExists($pathA);
        Storage::disk('public')->assertExists($copy->options['A']['image']);
    }

    public function test_export_renders_text_not_array_for_option_images(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('question-images/x.webp', 'x');

        $question = Question::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'question_text' => 'Soal export?',
            'options' => [
                'A' => ['text' => 'Pilihan A', 'image' => 'question-images/x.webp'],
                'B' => 'Pilihan B',
            ],
            'answer_key' => 'A',
            'score_weight' => 10,
        ]);
        $question->classrooms()->attach($this->classroom->id);

        Excel::fake();
        $this->actingAs($this->admin)
            ->get(route('admin.questions.export', ['format' => 'xlsx', 'scope' => 'all']))
            ->assertOk();

        Excel::assertDownloaded('bank-soal-'.date('Y-m-d').'.xlsx');
    }

    public function test_matching_index_contract_preserved_with_option_images(): void
    {
        // Soal matching dengan gambar di salah satu opsi — jawaban benar tetap
        // berdasarkan INDEX (left[i] ↔ right[i]) seperti sebelum fitur gambar.
        $question = Question::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => Question::TYPE_MATCHING,
            'question_text' => 'Jodohkan?',
            'options' => [
                'left' => [
                    ['text' => 'Satu', 'image' => 'question-images/satu.webp'],
                    'Dua',
                    'Tiga',
                ],
                'right' => ['1', '2', '3'],
            ],
            'answer_key' => ['A' => '1', 'B' => '2', 'C' => '3'],
            'score_weight' => 30,
        ]);

        // Jawaban peserta (index kanan 1-based) — bentuk yang sama seperti
        // sebelum fitur gambar; harus tetap ter-grade dengan benar.
        $grading = app(ExamGradingService::class);

        $correct = $grading->grade($question, ['A' => '1', 'B' => '2', 'C' => '3']);
        $this->assertTrue($correct['is_correct']);
        $this->assertSame(30.0, (float) $correct['score']);

        $wrong = $grading->grade($question, ['A' => '2', 'B' => '1', 'C' => '3']);
        $this->assertFalse($wrong['is_correct']);
        $this->assertSame(0.0, (float) $wrong['score']);
    }

    public function test_true_false_grading_bool_still_works_with_option_images(): void
    {
        $question = Question::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => Question::TYPE_TRUE_FALSE,
            'question_text' => 'Bumi bulat?',
            'options' => [
                'true' => ['text' => 'Benar', 'image' => 'question-images/t.webp'],
                'false' => ['text' => 'Salah', 'image' => null],
            ],
            'answer_key' => true,
            'score_weight' => 10,
        ]);

        $grading = app(ExamGradingService::class);

        $this->assertTrue((bool) $grading->grade($question, true)['is_correct']);
        $this->assertFalse((bool) $grading->grade($question, false)['is_correct']);
    }
}

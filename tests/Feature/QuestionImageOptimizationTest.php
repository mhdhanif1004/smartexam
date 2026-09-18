<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\GuruMapel;
use App\Models\Question;
use App\Models\Subject;
use App\Models\TeacherSubjectClassAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuestionImageOptimizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private GuruMapel $guru;

    private Subject $subject;

    private Classroom $classroom;

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

    /**
     * Buat UploadedFile JPEG berukuran width x height via GD (bukan
     * fake()->image() yang selalu 400x300 solid).
     */
    private function jpegUpload(int $width, int $height, string $name = 'foto.jpg', int $quality = 90): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'img_test_');
        $im = imagecreatetruecolor($width, $height);
        imagefilledrectangle($im, 0, 0, $width, $height, imagecolorallocate($im, 200, 100, 50));
        imagejpeg($im, $tmp, $quality);
        imagedestroy($im);

        return new UploadedFile($tmp, $name, 'image/jpeg', null, true);
    }

    /**
     * UploadedFile JPEG corrupt: header jpeg valid (lolos rule image Laravel),
     * tapi bytes terpotong sehingga GD gagal decode.
     */
    private function corruptJpegUpload(): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'img_corrupt_');
        $im = imagecreatetruecolor(200, 100);
        imagejpeg($im, $tmp, 90);
        imagedestroy($im);

        $bytes = file_get_contents($tmp);
        file_put_contents($tmp, substr($bytes, 0, (int) (strlen($bytes) * 0.3)));

        return new UploadedFile($tmp, 'rusak.jpg', 'image/jpeg', null, true);
    }

    /**
     * Buat JPEG dengan EXIF Orientation tag tertentu (misal 6 = rotasi 90° CW)
     * tanpa tool eksternal: generate JPEG polos via GD lalu sisipkan segmen
     * APP1 EXIF manual tepat setelah SOI. Pixel data tetap mentah (belum
     * dirotasi) — hanya metadata yang menyatakan orientasi tampil.
     */
    private function jpegUploadWithExifOrientation(int $width, int $height, int $orientation): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'img_exif_');
        $im = imagecreatetruecolor($width, $height);
        imagefilledrectangle($im, 0, 0, $width, $height, imagecolorallocate($im, 30, 150, 220));
        imagejpeg($im, $tmp, 90);
        imagedestroy($im);

        $jpeg = file_get_contents($tmp);

        // TIFF header little-endian + IFD0 berisi 1 entry: tag 0x0112 (Orientation).
        $ifd = pack('v', 1)                            // entry count = 1
            .pack('v', 0x0112)                         // tag Orientation
            .pack('v', 3)                              // type SHORT
            .pack('V', 1)                              // count
            .pack('V', $orientation)                   // nilai orientation (inline)
            .pack('V', 0);                             // next IFD offset = 0

        $exifPayload = "Exif\0\0"                      // identifier
            ."II\x2A\x00"                              // TIFF header LE, magic 42
            ."\x08\x00\x00\x00"                        // offset ke IFD0 = 8
            .$ifd;

        $app1 = "\xFF\xE1".pack('n', strlen($exifPayload) + 2).$exifPayload;

        // SOI + APP1 EXIF + sisa JPEG asli (tanpa SOI-nya).
        $out = "\xFF\xD8".$app1.substr($jpeg, 2);
        file_put_contents($tmp, $out);

        return new UploadedFile($tmp, 'orientasi-'.$orientation.'.jpg', 'image/jpeg', null, true);
    }

    private function singleChoicePayload(int $subjectId, array $classroomIds): array
    {
        return [
            'subject_id' => $subjectId,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'question_text' => 'Soal dengan gambar?',
            'score_weight' => 10,
            'classroom_ids' => $classroomIds,
            'single_options' => ['A' => 'Merah', 'B' => 'Biru'],
            'single_answer' => 'A',
        ];
    }

    public function test_admin_store_converts_to_webp_and_scales_down_large_image(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->post(route('admin.questions.store'), $this->singleChoicePayload($this->subject->id, [$this->classroom->id]) + [
                'image' => $this->jpegUpload(3200, 2000, 'besar.jpg'),
            ])
            ->assertRedirect(route('admin.questions.index'))
            ->assertSessionHas('success');

        $question = Question::query()->first();
        $this->assertNotNull($question->image_path);
        $this->assertTrue(Str::endsWith($question->image_path, '.webp'));
        Storage::disk('public')->assertExists($question->image_path);

        $info = getimagesizefromstring((string) Storage::disk('public')->get($question->image_path));
        $this->assertSame('image/webp', $info['mime']);
        $this->assertLessThanOrEqual(1600, $info[0]);
        $this->assertLessThanOrEqual(1600, $info[1]);
        // Aspect ratio 16:10 tetap terjaga.
        $this->assertEqualsWithDelta(3200 / 2000, $info[0] / $info[1], 0.01);
    }

    public function test_admin_replaces_image_and_deletes_old_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('question-images/lama.png', 'dummy content');

        $question = Question::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'options' => ['A' => 'Merah', 'B' => 'Biru'],
            'answer_key' => 'A',
            'image_path' => 'question-images/lama.png',
        ]);
        $question->classrooms()->attach($this->classroom->id);

        $this->actingAs($this->admin)
            ->put(route('admin.questions.update', $question), $this->singleChoicePayload($this->subject->id, [$this->classroom->id]) + [
                'image' => $this->jpegUpload(3200, 2000, 'baru.jpg'),
            ])
            ->assertRedirect(route('admin.questions.index'))
            ->assertSessionHas('success');

        $fresh = $question->fresh();
        $this->assertNotSame('question-images/lama.png', $fresh->image_path);
        $this->assertTrue(Str::endsWith($fresh->image_path, '.webp'));
        Storage::disk('public')->assertMissing('question-images/lama.png');
        Storage::disk('public')->assertExists($fresh->image_path);

        $info = getimagesizefromstring((string) Storage::disk('public')->get($fresh->image_path));
        $this->assertLessThanOrEqual(1600, $info[0]);
        $this->assertLessThanOrEqual(1600, $info[1]);
    }

    public function test_small_image_is_not_upscaled(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->post(route('admin.questions.store'), $this->singleChoicePayload($this->subject->id, [$this->classroom->id]) + [
                'image' => $this->jpegUpload(400, 300, 'kecil.jpg'),
            ])
            ->assertRedirect(route('admin.questions.index'))
            ->assertSessionHas('success');

        $question = Question::query()->first();
        $info = getimagesizefromstring((string) Storage::disk('public')->get($question->image_path));
        $this->assertSame(400, $info[0]);
        $this->assertSame(300, $info[1]);
    }

    public function test_high_resolution_image_is_processed_without_fatal_error(): void
    {
        Storage::fake('public');

        // 6000x4000 pixel, kualitas JPEG rendah supaya entri < 8MB.
        $upload = $this->jpegUpload(6000, 4000, 'foto-hires.jpg', 30);
        $this->assertLessThan(8192 * 1024, $upload->getSize(), 'Fixture harus di bawah 8MB agar lolos rule max:8192');

        $response = $this->actingAs($this->admin)
            ->post(route('admin.questions.store'), $this->singleChoicePayload($this->subject->id, [$this->classroom->id]) + [
                'image' => $upload,
            ]);

        if ($response->headers->get('content-type') !== null
            && str_contains((string) $response->headers->get('content-type'), 'application/json')) {
            // Auto-submit JSON error (422) — graceful, bukan crash.
            $response->assertSessionHasErrors('image');
            $this->addToAssertionCount(1);

            return;
        }

        // Sukses diproses normal: redirect + gambar .webp <= 1600px.
        $response->assertRedirect(route('admin.questions.index'))
            ->assertSessionHas('success');

        $question = Question::query()->first();
        $this->assertNotNull($question->image_path);
        $this->assertTrue(Str::endsWith($question->image_path, '.webp'));
        Storage::disk('public')->assertExists($question->image_path);

        $info = getimagesizefromstring((string) Storage::disk('public')->get($question->image_path));
        $this->assertLessThanOrEqual(1600, $info[0]);
        $this->assertLessThanOrEqual(1600, $info[1]);
    }

    public function test_exif_orientation_is_applied_during_optimization(): void
    {
        Storage::fake('public');

        // Pixel mentah 800x400 (landscape) dengan EXIF Orientation=6
        // (harus ditampilkan portrait 400x800 setelah rotasi 90° CW).
        $upload = $this->jpegUploadWithExifOrientation(800, 400, 6);

        // Pastikan fixture memang punya tag orientation (bukan file polos).
        $this->assertSame(6, exif_read_data($upload->getRealPath())['Orientation']);

        $this->actingAs($this->admin)
            ->post(route('admin.questions.store'), $this->singleChoicePayload($this->subject->id, [$this->classroom->id]) + [
                'image' => $upload,
            ])
            ->assertRedirect(route('admin.questions.index'))
            ->assertSessionHas('success');

        $question = Question::query()->first();
        $info = getimagesizefromstring((string) Storage::disk('public')->get($question->image_path));

        // Hasil akhir harus sudah ter-orientasi (portrait), bukan mentah landscape.
        $this->assertSame(400, $info[0], 'width harus 400px setelah rotasi EXIF');
        $this->assertSame(800, $info[1], 'height harus 800px setelah rotasi EXIF');
    }

    public function test_corrupt_image_is_handled_gracefully(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->post(route('admin.questions.store'), $this->singleChoicePayload($this->subject->id, [$this->classroom->id]) + [
                'image' => $this->corruptJpegUpload(),
            ])
            ->assertSessionHasErrors('image');

        $this->assertDatabaseCount('questions', 0);
    }

    public function test_guru_mapel_store_converts_to_webp_and_scales_down(): void
    {
        Storage::fake('public');

        $this->actingAs($this->guru->user)
            ->post(route('guru_mapel.questions.store'), [
                'subject_id' => $this->subject->id,
                'type' => Question::TYPE_SINGLE_CHOICE,
                'question_text' => 'Soal dengan gambar?',
                'score_weight' => 10,
                'single_options' => ['A' => 'Merah', 'B' => 'Biru'],
                'single_answer' => 'A',
                'image' => $this->jpegUpload(3200, 2000, 'besar.jpg'),
            ])
            ->assertRedirect(route('guru_mapel.questions.index'))
            ->assertSessionHas('success');

        $question = Question::query()
            ->where('created_by_user_id', $this->guru->user->id)
            ->first();

        $this->assertNotNull($question->image_path);
        $this->assertTrue(Str::endsWith($question->image_path, '.webp'));
        Storage::disk('public')->assertExists($question->image_path);

        $info = getimagesizefromstring((string) Storage::disk('public')->get($question->image_path));
        $this->assertLessThanOrEqual(1600, $info[0]);
        $this->assertLessThanOrEqual(1600, $info[1]);
    }

    public function test_guru_mapel_replaces_image_and_deletes_old_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('question-images/lama-guru.png', 'dummy content');

        $question = Question::query()->create([
            'subject_id' => $this->subject->id,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'question_text' => 'Soal dengan gambar lama',
            'options' => ['A' => 'Merah', 'B' => 'Biru'],
            'answer_key' => 'A',
            'score_weight' => 10,
            'image_path' => 'question-images/lama-guru.png',
            'created_by_user_id' => $this->guru->user->id,
        ]);
        $question->classrooms()->attach($this->classroom->id);

        $this->actingAs($this->guru->user)
            ->put(route('guru_mapel.questions.update', $question), [
                'subject_id' => $this->subject->id,
                'type' => Question::TYPE_SINGLE_CHOICE,
                'question_text' => 'Soal dengan gambar baru',
                'score_weight' => 10,
                'single_options' => ['A' => 'Merah', 'B' => 'Biru'],
                'single_answer' => 'A',
                'image' => $this->jpegUpload(3200, 2000, 'baru.jpg'),
            ])
            ->assertRedirect(route('guru_mapel.questions.index'))
            ->assertSessionHas('success');

        $fresh = $question->fresh();
        $this->assertNotSame('question-images/lama-guru.png', $fresh->image_path);
        $this->assertTrue(Str::endsWith($fresh->image_path, '.webp'));
        Storage::disk('public')->assertMissing('question-images/lama-guru.png');
        Storage::disk('public')->assertExists($fresh->image_path);

        $info = getimagesizefromstring((string) Storage::disk('public')->get($fresh->image_path));
        $this->assertLessThanOrEqual(1600, $info[0]);
        $this->assertLessThanOrEqual(1600, $info[1]);
    }
}

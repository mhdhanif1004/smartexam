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
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class GuruMapelQuestionImportExportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guru yang mengampu mapel untuk satu kelas (assignment kelas eksplisit,
     * agar snapshot kelas target dari assignment bermakna).
     *
     * @return array{0: GuruMapel, 1: Subject, 2: Classroom, 3: User}
     */
    private function makeGuru(?Subject $subject = null): array
    {
        $guru = GuruMapel::factory()->create();
        $subject = $subject ?? Subject::factory()->create();
        $classroom = Classroom::factory()->create();

        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
        ]);

        return [$guru, $subject, $classroom, $guru->user];
    }

    private function makeQuestionFor(GuruMapel $guru, int $subjectId, string $text = 'Soal saya'): Question
    {
        $question = Question::query()->create([
            'subject_id' => $subjectId,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'question_text' => $text,
            'options' => ['A' => 'Satu', 'B' => 'Dua'],
            'answer_key' => 'A',
            'score_weight' => 10,
            'created_by_user_id' => $guru->user->id,
        ]);
        $question->classrooms()->attach(Classroom::factory()->create()->id);

        return $question;
    }

    private function csvFile(string $content, string $name = 'soal.csv'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'import_gm_');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, 'text/csv', null, true);
    }

    private function spreadsheetFile(Spreadsheet $spreadsheet, string $name = 'soal.xlsx'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'import_gm_');
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);

        return new UploadedFile(
            $path,
            $name,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    private function singleChoiceCsv(string $rows): string
    {
        return "Mata Pelajaran,Pertanyaan,Opsi A,Opsi B,Opsi C,Opsi D,Opsi E,Kunci Jawaban,Bobot\n".$rows;
    }

    // -----------------------------------------------------------------
    // EXPORT
    // -----------------------------------------------------------------

    public function test_export_only_includes_own_ampu_questions(): void
    {
        [$guruA, $subjectA, $classroomA] = $this->makeGuru();
        [$guruB, $subjectB, , $userB] = $this->makeGuru();

        // Soal milik Guru A untuk mapel yang diampunya → masuk export.
        $ownAmpu = $this->makeQuestionFor($guruA, $subjectA->id, 'Soal A sendiri');
        $ownAmpu->classrooms()->sync([$classroomA->id]);

        // Soal milik Guru B → TIDAK boleh bocor ke export Guru A.
        $this->makeQuestionFor($guruB, $subjectB->id, 'Soal B orang lain');
        $this->assertNotSame($userB, $guruA->user);

        $response = $this->actingAs($guruA->user)
            ->get(route('guru_mapel.questions.export'))
            ->assertOk();

        $sheet = IOFactory::load($response->getFile()->getPathname())->getActiveSheet();
        $rows = $sheet->toArray();

        // Baris 1 = header, baris berikutnya = data (hanya 1 soal milik Guru A).
        $this->assertCount(2, $rows);
        $this->assertSame('Soal A sendiri', $rows[1][2]);
    }

    public function test_export_excludes_non_ampu_and_foreign_questions(): void
    {
        [$guruA, $subjectA, $classroomA] = $this->makeGuru();
        // Mapel lain yang TIDAK diampu Guru A, tapi ada soal miliknya.
        $otherSubject = Subject::factory()->create();

        $this->makeQuestionFor($guruA, $subjectA->id, 'Soal diampu');
        $this->makeQuestionFor($guruA, $otherSubject->id, 'Soal di mapel tak diampu');

        $response = $this->actingAs($guruA->user)
            ->get(route('guru_mapel.questions.export'))
            ->assertOk();

        $rows = IOFactory::load($response->getFile()->getPathname())->getActiveSheet()->toArray();

        $this->assertCount(2, $rows);
        $this->assertSame('Soal diampu', $rows[1][2]);
    }

    // -----------------------------------------------------------------
    // IMPORT — otentikasi kepemilikan
    // -----------------------------------------------------------------

    public function test_import_sets_creator_to_logged_in_guru(): void
    {
        [$guru, $subject, $classroom] = $this->makeGuru();

        $csv = $this->singleChoiceCsv("{$subject->name},\"Halo 1+1?\",1,2,3,,,B,10\n");

        $this->actingAs($guru->user)
            ->post(route('guru_mapel.questions.import-validate'), [
                'type' => Question::TYPE_SINGLE_CHOICE,
                'file' => $this->csvFile($csv),
            ])
            ->assertOk()
            ->assertJson(['ok' => true, 'valid' => 1, 'invalid' => 0]);

        $this->actingAs($guru->user)
            ->post(route('guru_mapel.questions.import-confirm'))
            ->assertOk()
            ->assertJson(['ok' => true, 'created' => 1]);

        $this->assertDatabaseHas('questions', [
            'question_text' => 'Halo 1+1?',
            'subject_id' => $subject->id,
            'created_by_user_id' => $guru->user->id,
        ]);

        // Kelas target soal hasil import di-snapshot dari assignment guru untuk
        // mapel baris tsb (bukan input form / bukan pilihan manual).
        $question = Question::query()->where('question_text', 'Halo 1+1?')->firstOrFail();
        $this->assertSame([$classroom->id], $question->classrooms->pluck('id')->sort()->values()->all());
    }

    // -----------------------------------------------------------------
    // IMPORT — pembatasan mapel ampu
    // -----------------------------------------------------------------

    public function test_import_routes_non_ampu_subject_to_failed_rows(): void
    {
        [$guru, $subject, $classroom] = $this->makeGuru();
        $notAmpu = Subject::factory()->create(['name' => 'Kimia']);

        // 2 baris: satu mapel ampu (benar), satu mapel di luar ampuan (salah).
        $csv = $this->singleChoiceCsv(
            "{$subject->name},\"Soal ampu?\",1,2,3,,,B,10\n"
            ."{$notAmpu->name},\"Soal non-ampu?\",1,2,3,,,C,10\n"
        );

        $response = $this->actingAs($guru->user)
            ->post(route('guru_mapel.questions.import-validate'), [
                'type' => Question::TYPE_SINGLE_CHOICE,
                'file' => $this->csvFile($csv),
            ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'valid' => 1,
                'invalid' => 1,
                'to_create' => 1,
            ]);

        $this->assertStringContainsString('di luar mapel yang Anda ampu', $response->json('errors.0'));

        $this->actingAs($guru->user)
            ->post(route('guru_mapel.questions.import-confirm'))
            ->assertOk()
            ->assertJson(['ok' => true, 'created' => 1]);

        // Hanya soal mapel ampu yang tersimpan.
        $this->assertDatabaseHas('questions', ['question_text' => 'Soal ampu?']);
        $this->assertDatabaseMissing('questions', ['question_text' => 'Soal non-ampu?']);
    }

    public function test_import_rejects_file_where_no_row_is_ampu(): void
    {
        [$guru, , $classroom] = $this->makeGuru();
        $notAmpu = Subject::factory()->create(['name' => 'Biologi']);

        $csv = $this->singleChoiceCsv("{$notAmpu->name},\"Semua non-ampu?\",1,2,3,,,A,10\n");

        $response = $this->actingAs($guru->user)
            ->post(route('guru_mapel.questions.import-validate'), [
                'type' => Question::TYPE_SINGLE_CHOICE,
                'file' => $this->csvFile($csv),
            ])
            ->assertOk()
            ->assertJson(['ok' => true, 'valid' => 0, 'invalid' => 1]);

        $this->assertStringContainsString('di luar mapel yang Anda ampu', $response->json('errors.0'));
    }

    // -----------------------------------------------------------------
    // TEMPLATE — hanya mapel ampu
    // -----------------------------------------------------------------

    public function test_import_template_lists_only_ampu_subjects(): void
    {
        // Ampu subject diberi nama eksplisit agar deterministik dan tidak
        // berpotensi bentrok dengan $notAmpu (factory random bisa ikut memilih
        // nama "Fisika", yang membuat assertStringNotContainsString flaky).
        $ampu = Subject::factory()->create(['name' => 'Matematika']);
        [$guru, $subject] = $this->makeGuru($ampu);
        $notAmpu = Subject::factory()->create(['name' => 'Fisika']);

        $response = $this->actingAs($guru->user)
            ->get(route('guru_mapel.questions.import-template', Question::TYPE_SINGLE_CHOICE))
            ->assertOk();

        $sheet = IOFactory::load($response->getFile()->getPathname())->getActiveSheet();
        $validation = $sheet->getDataValidation('A2');
        $formula = $validation->getFormula1();

        // Dropdown "Mata Pelajaran" hanya memuat mapel yang diampu guru.
        $this->assertStringContainsString($subject->name, $formula);
        $this->assertStringNotContainsString($notAmpu->name, $formula);
    }

    // -----------------------------------------------------------------
    // SESI — confirm tanpa validate
    // -----------------------------------------------------------------

    public function test_import_confirm_without_validate_expires_session(): void
    {
        [$guru] = $this->makeGuru();

        $this->actingAs($guru->user)
            ->post(route('guru_mapel.questions.import-confirm'))
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'Sesi import kadaluarsa'));
    }

    // -----------------------------------------------------------------
    // UI — modal import (tanpa picker kelas target)
    // -----------------------------------------------------------------

    public function test_index_renders_import_modal_without_class_picker(): void
    {
        [$guru] = $this->makeGuru();

        $html = $this->actingAs($guru->user)
            ->get(route('guru_mapel.questions.index'))
            ->assertOk()
            ->assertSee('Import Soal', false)
            ->getContent();

        // Root scope memakai factory-call agar state tersarang di bawah
        // `importState.*` (bukan properti tingkat-atas).
        $this->assertStringContainsString('x-data="importState()"', $html);
        $this->assertStringContainsString('importState: {', $html);

        // Tidak ada picker "Kelas Target" / input classroom_ids pada modal
        // import guru — kelas di-snapshot dari assignment guru. (Kolom tabel
        // "Kelas Target" tetap ada sebagai tampilan read-only soal.)
        $this->assertStringNotContainsString('target: importState.classroomIds', $html);
        $this->assertStringNotContainsString('classroom_ids[]', $html);

        // Konten lengkap langkah 1: pilihan jenis, unduh template, upload
        // file, dan tombol validasi. Wajib ada — mencegah modal "kosong".
        $this->assertStringContainsString('Jenis Soal', $html);
        $this->assertStringContainsString('Unduh Template', $html);
        $this->assertStringContainsString('file:mr-4', $html);
        $this->assertStringContainsString('guru_mapel\/questions\/import-validate', $html);
        $this->assertStringContainsString('Validasi & Lanjutkan', $html);
    }

    public function test_create_page_has_no_class_picker(): void
    {
        [$guru, $subject] = $this->makeGuru();

        $html = $this->actingAs($guru->user)
            ->get(route('guru_mapel.questions.create'))
            ->assertOk()
            ->assertSee($subject->name)
            ->getContent();

        // Form create guru tanpa scoped picker kelas: tidak ada `selected`,
        // `importState.classroomIds`, atau label "Kelas Target".
        $this->assertStringNotContainsString('x-model="selected"', $html);
        $this->assertStringNotContainsString('importState.classroomIds', $html);
        $this->assertStringNotContainsString('Kelas Target', $html);
    }
}

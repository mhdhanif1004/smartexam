<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\GuruMapel;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class GuruMapelImportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_can_download_import_template(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.guru-mapels.import-template'))
            ->assertOk()
            ->assertDownload('template-import-guru-mapel.xlsx');
    }

    public function test_import_validate_returns_preview(): void
    {
        $subject = Subject::factory()->create(['name' => 'Matematika']);
        $classroom = Classroom::factory()->create(['name' => 'XI RPL 1']);
        $this->makeClassroom('XI TKJ 1');

        $csv = "Nama,Mapel,Tingkat,Kelas\nAndi Pratama,Matematika,XI,SEMUA\n";

        $this->actingAs($this->admin)
            ->post(route('admin.guru-mapels.import-validate'), [
                'file' => $this->csvFile($csv),
            ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'total' => 1,
                'valid' => 1,
                'invalid' => 0,
                'assignments' => 2,
            ]);
    }

    public function test_import_validate_rejects_missing_header(): void
    {
        $csv = "Nama,Mapel\nAndi Pratama,Matematika\n";

        $this->actingAs($this->admin)
            ->post(route('admin.guru-mapels.import-validate'), [
                'file' => $this->csvFile($csv),
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'Tingkat'));
    }

    public function test_import_validate_rejects_unknown_mapel(): void
    {
        Subject::factory()->create(['name' => 'Matematika']);
        Classroom::factory()->create(['name' => 'X AKL 1']);

        $csv = "Nama,Mapel,Tingkat,Kelas\nAndi Pratama,Sosiologi,X,X AKL 1\n";

        $this->actingAs($this->admin)
            ->post(route('admin.guru-mapels.import-validate'), [
                'file' => $this->csvFile($csv),
            ])
            ->assertOk()
            ->assertJson(['valid' => 0, 'invalid' => 1])
            ->assertJsonPath('errors.0', fn (string $error) => str_contains($error, 'tidak ditemukan di master data'));
    }

    public function test_import_validate_rejects_invalid_tingkat(): void
    {
        $subject = Subject::factory()->create(['name' => 'Matematika']);
        Classroom::factory()->create(['name' => 'X AKL 1']);

        $csv = "Nama,Mapel,Tingkat,Kelas\nAndi Pratama,Matematika,XIII,X AKL 1\n";

        $this->actingAs($this->admin)
            ->post(route('admin.guru-mapels.import-validate'), [
                'file' => $this->csvFile($csv),
            ])
            ->assertOk()
            ->assertJson(['valid' => 0, 'invalid' => 1])
            ->assertJsonPath('errors.0', fn (string $error) => str_contains($error, 'X, XI, atau XII'));
    }

    public function test_import_validate_rejects_class_not_in_master(): void
    {
        Subject::factory()->create(['name' => 'Matematika']);
        Classroom::factory()->create(['name' => 'X AKL 1']);

        $csv = "Nama,Mapel,Tingkat,Kelas\nAndi Pratama,Matematika,X,X AKL 2\n";

        $this->actingAs($this->admin)
            ->post(route('admin.guru-mapels.import-validate'), [
                'file' => $this->csvFile($csv),
            ])
            ->assertOk()
            ->assertJson(['valid' => 0, 'invalid' => 1])
            ->assertJsonPath('errors.0', fn (string $error) => str_contains($error, 'tidak ditemukan di master data'));
    }

    public function test_import_validate_rejects_class_not_matching_tingkat(): void
    {
        Subject::factory()->create(['name' => 'Matematika']);
        Classroom::factory()->create(['name' => 'X AKL 1']);
        Classroom::factory()->create(['name' => 'XI AKL 1']);

        $csv = "Nama,Mapel,Tingkat,Kelas\nAndi Pratama,Matematika,X,XI AKL 1\n";

        $this->actingAs($this->admin)
            ->post(route('admin.guru-mapels.import-validate'), [
                'file' => $this->csvFile($csv),
            ])
            ->assertOk()
            ->assertJson(['valid' => 0, 'invalid' => 1])
            ->assertJsonPath('errors.0', fn (string $error) => str_contains($error, 'bukan kelas tingkat'));
    }

    public function test_import_semua_expands_to_all_classes_of_tingkat_snapshot(): void
    {
        $subject = Subject::factory()->create(['name' => 'Matematika']);
        $xiRpl = Classroom::factory()->create(['name' => 'XI RPL 1']);
        $xiTkj = Classroom::factory()->create(['name' => 'XI TKJ 1']);
        Classroom::factory()->create(['name' => 'X RPL 1']);

        $csv = "Nama,Mapel,Tingkat,Kelas\nAndi Pratama,Matematika,XI,SEMUA\n";

        $this->actingAs($this->admin)
            ->post(route('admin.guru-mapels.import-validate'), [
                'file' => $this->csvFile($csv),
            ])
            ->assertOk()
            ->assertJsonPath('assignments', 2)
            ->assertJsonPath('valid', 1);

        $this->importConfirmRaw();

        $guru = GuruMapel::query()->whereHas('user', fn ($q) => $q->where('email', 'andipratama@gmail.com'))->first();
        $this->assertNotNull($guru);

        $assigned = $guru->assignments()->pluck('classroom_id')->sort()->values();
        $this->assertEquals([$xiRpl->id, $xiTkj->id], $assigned->all());
    }

    public function test_import_reuses_one_user_for_same_guru_across_rows(): void
    {
        $subject = Subject::factory()->create(['name' => 'Matematika']);
        $other = Subject::factory()->create(['name' => 'Fisika']);
        $xiRpl = Classroom::factory()->create(['name' => 'XI RPL 1']);
        $xiTkj = Classroom::factory()->create(['name' => 'XI TKJ 1']);

        $csv = "Nama,Mapel,Tingkat,Kelas\nAndi Pratama,Matematika,XI,XI RPL 1\nAndi Pratama,Fisika,XI,XI TKJ 1\n";

        $this->actingAs($this->admin)
            ->post(route('admin.guru-mapels.import-validate'), [
                'file' => $this->csvFile($csv),
            ])
            ->assertOk()
            ->assertJson(['valid' => 2, 'invalid' => 0]);

        $this->importConfirmRaw();

        $this->assertSame(1, User::query()->where('email', 'andipratama@gmail.com')->count());
        $guru = GuruMapel::query()->whereHas('user', fn ($q) => $q->where('email', 'andipratama@gmail.com'))->first();
        $this->assertSame(2, $guru->assignments()->count());
        $this->assertDatabaseHas('teacher_subject_class_assignments', [
            'guru_mapel_id' => $guru->id, 'subject_id' => $subject->id, 'classroom_id' => $xiRpl->id,
        ]);
        $this->assertDatabaseHas('teacher_subject_class_assignments', [
            'guru_mapel_id' => $guru->id, 'subject_id' => $other->id, 'classroom_id' => $xiTkj->id,
        ]);
    }

    public function test_import_email_auto_generation_matches_manual_pattern(): void
    {
        Subject::factory()->create(['name' => 'Matematika']);
        Classroom::factory()->create(['name' => 'X AKL 1']);

        // Akun guru dengan email andi@gmail.com sudah ada -> import nama "Andi
        // Sudirman" harus berubah jadi andisudirman@gmail.com (lowercase, tanpa
        // spasi, domain gmail), dan yang bentrok diberi angka.
        User::factory()->guruMapel()->create(['email' => 'andisudirman@gmail.com', 'name' => 'Andi Sudirman']);

        $csv = "Nama,Mapel,Tingkat,Kelas\nAndi Sudirman,Matematika,X,X AKL 1\n";

        $this->actingAs($this->admin)
            ->post(route('admin.guru-mapels.import-validate'), [
                'file' => $this->csvFile($csv),
            ])
            ->assertOk()
            ->assertJson(['valid' => 1, 'invalid' => 0]);

        $this->importConfirmRaw();

        $this->assertDatabaseHas('users', ['email' => 'andisudirman2@gmail.com']);
        $this->assertSame(1, GuruMapel::query()->whereHas('user', fn ($q) => $q->where('email', 'andisudirman2@gmail.com'))->count());
    }

    public function test_import_confirm_creates_guru_and_assignments(): void
    {
        $subject = Subject::factory()->create(['name' => 'Matematika']);
        $classroom = Classroom::factory()->create(['name' => 'X AKL 1']);

        $this->importConfirmRaw([
            'validRows' => [[
                'row' => 2,
                'nama' => 'Andi Pratama',
                'email' => 'andipratama@gmail.com',
                'subject_id' => $subject->id,
                'classroom_ids' => [$classroom->id],
            ]],
            'invalidRows' => [],
        ]);

        $user = User::query()->where('email', 'andipratama@gmail.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('Andi Pratama', $user->name);
        $this->assertSame(User::ROLE_GURU_MAPEL, $user->role);
        $this->assertNull($user->username);
        $this->assertTrue((bool) $user->is_active);
        $this->assertNotNull($user->plain_password);
        $this->assertTrue(strlen($user->plain_password) >= 8);
        $this->assertTrue(password_verify($user->plain_password, $user->password));

        $guru = $user->guruMapel;
        $this->assertNotNull($guru);
        $this->assertDatabaseHas('teacher_subject_class_assignments', [
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
        ]);
    }

    public function test_import_confirm_partial_success_generates_failed_file(): void
    {
        $subject = Subject::factory()->create(['name' => 'Matematika']);
        $classroom = Classroom::factory()->create(['name' => 'X AKL 1']);

        $validRow = [
            'row' => 2,
            'nama' => 'Andi Pratama',
            'email' => 'andipratama@gmail.com',
            'subject_id' => $subject->id,
            'classroom_ids' => [$classroom->id],
        ];

        $importData = [
            'validRows' => [$validRow],
            'invalidRows' => [[
                'row' => 3,
                'data' => ['nama' => 'Budi', 'mapel' => 'Sosiologi', 'tingkat' => 'X', 'kelas' => 'X AKL 2'],
                'errors' => ['Mapel tidak ditemukan di master data.'],
            ]],
            'headerError' => '',
        ];

        Cache::put('guru_mapels_import_pending_'.$this->admin->id, $importData, now()->addMinutes(10));

        $response = $this->actingAs($this->admin)
            ->withHeaders(['Cookie' => ''])
            ->post(route('admin.guru-mapels.import-confirm'))
            ->assertOk()
            ->assertJson(['ok' => true, 'created' => 1, 'failed_count' => 1]);

        $failedFile = $response->json('failed_file');
        $this->assertNotNull($failedFile);

        $this->actingAs($this->admin)
            ->get(route('admin.guru-mapels.import-failed', $failedFile))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename='.$failedFile);
    }

    public function test_import_confirm_without_pending_session_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.guru-mapels.import-confirm'))
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'kadaluarsa'));
    }

    public function test_import_reimport_appends_and_never_deletes(): void
    {
        $subject = Subject::factory()->create(['name' => 'Matematika']);
        $xiRpl = Classroom::factory()->create(['name' => 'XI RPL 1']);
        $xiTkj = Classroom::factory()->create(['name' => 'XI TKJ 1']);

        // Impor pertama: guru Andi diampu mapel Matematika hanya untuk XI RPL 1.
        $this->importConfirmRaw([
            'validRows' => [[
                'row' => 2,
                'nama' => 'Andi Pratama',
                'email' => 'andipratama@gmail.com',
                'subject_id' => $subject->id,
                'classroom_ids' => [$xiRpl->id],
            ]],
            'invalidRows' => [],
        ]);

        // Impor kedua: file baru menambahkan XI TKJ 1 untuk guru yang sama.
        // Penugasan lama (XI RPL 1) TIDAK dihapus.
        $this->importConfirmRaw([
            'validRows' => [[
                'row' => 2,
                'nama' => 'Andi Pratama',
                'email' => 'andipratama@gmail.com',
                'subject_id' => $subject->id,
                'classroom_ids' => [$xiTkj->id],
            ]],
            'invalidRows' => [],
        ]);

        $guru = GuruMapel::query()->whereHas('user', fn ($q) => $q->where('email', 'andipratama@gmail.com'))->first();
        $assigned = $guru->assignments()->pluck('classroom_id')->sort()->values();
        $this->assertEquals([$xiRpl->id, $xiTkj->id], $assigned->all());
        $this->assertSame(1, User::query()->where('email', 'andipratama@gmail.com')->count());
    }

    public function test_non_admin_cannot_download_template(): void
    {
        $peserta = User::factory()->peserta()->create();

        $this->actingAs($peserta)
            ->get(route('admin.guru-mapels.import-template'))
            ->assertForbidden();
    }

    /**
     * Kirim konfirmasi impor dengan payload yang sudah disimpan ke cache
     * (alur nyata: validasi -> simpan cache -> konfirmasi).
     *
     * @param  array{validRows?: list<array<string,mixed>>, invalidRows?: list<array<string,mixed>>}|null  $data
     */
    private function importConfirmRaw(?array $data = null): void
    {
        if ($data === null) {
            $this->actingAs($this->admin)
                ->withHeaders(['Cookie' => ''])
                ->post(route('admin.guru-mapels.import-confirm'))
                ->assertOk();

            return;
        }

        Cache::put('guru_mapels_import_pending_'.$this->admin->id, $data, now()->addMinutes(10));

        $this->actingAs($this->admin)
            ->withHeaders(['Cookie' => ''])
            ->post(route('admin.guru-mapels.import-confirm'))
            ->assertOk();
    }

    private function makeClassroom(string $name): Classroom
    {
        return Classroom::factory()->create(['name' => $name]);
    }

    /**
     * Buat fake CSV yang tetap punya client mime text/csv agar lolos rule mimes.
     */
    private function csvFile(string $content, string $name = 'guru-mapel.csv'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'import_');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, 'text/csv', null, true);
    }
}

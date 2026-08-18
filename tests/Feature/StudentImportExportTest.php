<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\ClassroomSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StudentImportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->seed(ClassroomSeeder::class);
    }

    public function test_admin_can_download_export_xlsx(): void
    {
        $student = Student::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.students.export'))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertDownload('data-siswa-'.date('Y-m-d').'.xlsx');
    }

    public function test_admin_can_download_export_csv(): void
    {
        Student::factory()->create(['nisn' => '1111111111', 'class_name' => 'XI RPL 1']);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.students.export', ['format' => 'csv']))
            ->assertOk()
            ->assertDownload('data-siswa-'.date('Y-m-d').'.csv');

        $this->assertStringContainsString('1111111111', $response->streamedContent());
    }

    public function test_export_respects_class_filter(): void
    {
        Student::factory()->create(['nisn' => '1111111111', 'class_name' => 'XI RPL 1']);
        Student::factory()->create(['nisn' => '2222222222', 'class_name' => 'X RPL 1']);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.students.export', ['format' => 'csv', 'class' => 'XI RPL 1']))
            ->assertOk();

        $content = $response->streamedContent();
        $this->assertStringContainsString('1111111111', $content);
        $this->assertStringNotContainsString('2222222222', $content);
    }

    public function test_admin_can_download_import_template(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.students.import-template'))
            ->assertOk()
            ->assertDownload('template-import-siswa.xlsx');
    }

    public function test_import_validate_returns_preview(): void
    {
        $existing = Student::factory()->create(['nisn' => '0098765432', 'class_name' => 'X RPL 1']);

        $csv = "NISN,Nama,Kelas\n0012345678,Andi Pratama,XI RPL 1\n0098765432,Budi Santoso,XI RPL 1\n";

        $response = $this->actingAs($this->admin)
            ->post(route('admin.students.import-validate'), [
                'file' => $this->csvFile($csv),
            ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'total' => 2,
                'valid' => 2,
                'invalid' => 0,
                'to_create' => 1,
                'to_update' => 1,
                'new_classes_count' => 0,
                'new_classes' => [],
            ]);

        $this->assertSame($existing->nisn, $existing->fresh()->nisn);
    }

    public function test_import_validate_rejects_missing_header(): void
    {
        $csv = "NISN,Nama\n0012345678,Andi Pratama\n";

        $this->actingAs($this->admin)
            ->post(route('admin.students.import-validate'), [
                'file' => $this->csvFile($csv),
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'Kelas'));
    }

    public function test_import_validate_rejects_invalid_rows(): void
    {
        $csv = "NISN,Nama,Kelas\n123,Andi Pratama,XI RPL 1\n0012345678,,XI RPL 1\n";

        $this->actingAs($this->admin)
            ->post(route('admin.students.import-validate'), [
                'file' => $this->csvFile($csv),
            ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'total' => 2,
                'valid' => 0,
                'invalid' => 2,
            ])
            ->assertJsonPath('errors.0', fn (string $error) => str_contains($error, 'Baris 2'));
    }

    public function test_import_validate_collects_new_classes_for_autocreate(): void
    {
        $csv = "NISN,Nama,Kelas\n0012345678,Andi Pratama,XI RPL 99\n";

        $response = $this->actingAs($this->admin)
            ->post(route('admin.students.import-validate'), [
                'file' => $this->csvFile($csv),
            ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'valid' => 1,
                'invalid' => 0,
                'new_classes_count' => 1,
            ]);

        $this->assertSame(['XI RPL 99'], $response->json('new_classes'));
    }

    public function test_import_validate_deduplicates_new_classes(): void
    {
        $csv = "NISN,Nama,Kelas\n0012345678,Andi Pratama,XI RPL 99\n0098765432,Budi Santoso,XI RPL 99\n";

        $response = $this->actingAs($this->admin)
            ->post(route('admin.students.import-validate'), [
                'file' => $this->csvFile($csv),
            ])
            ->assertOk()
            ->assertJsonPath('valid', 2)
            ->assertJsonPath('new_classes_count', 1);

        $this->assertSame(['XI RPL 99'], $response->json('new_classes'));
    }

    public function test_import_validate_still_rejects_invalid_nisn_in_row_with_new_class(): void
    {
        $csv = "NISN,Nama,Kelas\n123,Andi Pratama,XI RPL 99\n";

        $response = $this->actingAs($this->admin)
            ->post(route('admin.students.import-validate'), [
                'file' => $this->csvFile($csv),
            ])
            ->assertOk()
            ->assertJson([
                'valid' => 0,
                'invalid' => 1,
                'new_classes_count' => 0,
                'new_classes' => [],
            ]);

        $this->assertStringContainsString('NISN harus 10 digit angka.', $response->json('errors.0'));
    }

    public function test_import_validate_rejects_duplicate_nisn_in_file(): void
    {
        $csv = "NISN,Nama,Kelas\n0012345678,Andi Pratama,XI RPL 1\n0012345678,Budi Santoso,X RPL 1\n";

        $response = $this->actingAs($this->admin)
            ->post(route('admin.students.import-validate'), [
                'file' => $this->csvFile($csv),
            ])
            ->assertOk();

        $response->assertJsonPath('valid', 1);
        $response->assertJsonPath('invalid', 1);
        $this->assertStringContainsString('duplikat', $response->json('errors.0'));
    }

    public function test_import_validate_rejects_non_excel_file(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.students.import-validate'), [
                'file' => UploadedFile::fake()->create('catatan.txt', 10),
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_import_confirm_without_pending_session_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.students.import-confirm'))
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'kadaluarsa'));
    }

    public function test_import_confirm_creates_and_updates_students(): void
    {
        $existing = Student::factory()->create(['nisn' => '0098765432', 'class_name' => 'X RPL 1']);
        $oldUsername = $existing->user->username;

        $importData = [
            'validRows' => [
                ['row' => 2, 'nisn' => '0012345678', 'name' => 'Andi Pratama', 'class_name' => 'XI RPL 1', 'mode' => 'create'],
                ['row' => 3, 'nisn' => '0098765432', 'name' => 'Budi Santoso', 'class_name' => 'XI RPL 1', 'mode' => 'update'],
            ],
            'invalidRows' => [],
            'newClasses' => [],
            'headerError' => '',
        ];

        Cache::put('import_pending_'.$this->admin->id, $importData, now()->addMinutes(10));

        $response = $this->actingAs($this->admin)
            ->post(route('admin.students.import-confirm'))
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'created' => 1,
                'updated' => 1,
                'new_classes_created' => 0,
            ]);

        $created = Student::query()->where('nisn', '0012345678')->first();
        $this->assertNotNull($created);
        $this->assertSame('XI RPL 1', $created->class_name);
        $this->assertNull($created->room_id);
        $this->assertNotNull($created->user);
        $this->assertSame(User::ROLE_PESERTA, $created->user->role);
        $this->assertTrue((bool) $created->user->is_active);
        $this->assertNotNull($created->user->username);
        $this->assertNotEmpty($created->user->plain_password);

        $existing->refresh();
        $this->assertSame('XI RPL 1', $existing->class_name);
        $this->assertSame('Budi Santoso', $existing->user->name);
        $this->assertSame($oldUsername, $existing->user->username);
    }

    public function test_import_confirm_stores_failed_rows_file(): void
    {
        $importData = [
            'validRows' => [
                ['row' => 2, 'nisn' => '0012345678', 'name' => 'Andi Pratama', 'class_name' => 'XI RPL 1', 'mode' => 'create'],
            ],
            'invalidRows' => [
                [
                    'row' => 3,
                    'data' => ['nisn' => '123', 'name' => 'Budi Santoso', 'class_name' => 'X RPL 1'],
                    'errors' => ['NISN harus 10 digit angka.'],
                ],
            ],
            'newClasses' => [],
            'headerError' => '',
        ];

        Cache::put('import_pending_'.$this->admin->id, $importData, now()->addMinutes(10));

        $response = $this->actingAs($this->admin)
            ->withHeaders(['Cookie' => ''])
            ->post(route('admin.students.import-confirm'))
            ->assertOk()
            ->assertJson(['ok' => true, 'created' => 1, 'failed_count' => 1]);

        $failedFile = $response->json('failed_file');
        $this->assertNotNull($failedFile);

        $this->actingAs($this->admin)
            ->get(route('admin.students.import-failed', $failedFile))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename='.$failedFile);
    }

    public function test_import_confirm_autocreates_new_classes_with_students(): void
    {
        $importData = [
            'validRows' => [
                ['row' => 2, 'nisn' => '0012345678', 'name' => 'Andi Pratama', 'class_name' => 'XI RPL 99', 'mode' => 'create'],
                ['row' => 3, 'nisn' => '0098765432', 'name' => 'Budi Santoso', 'class_name' => 'XII MM 3', 'mode' => 'create'],
            ],
            'invalidRows' => [],
            'newClasses' => ['XI RPL 99', 'XII MM 3'],
            'headerError' => '',
        ];

        Cache::put('import_pending_'.$this->admin->id, $importData, now()->addMinutes(10));

        $response = $this->actingAs($this->admin)
            ->withHeaders(['Cookie' => ''])
            ->post(route('admin.students.import-confirm'))
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'created' => 2,
                'new_classes_created' => 2,
                'new_classes' => ['XI RPL 99', 'XII MM 3'],
            ]);

        $this->assertDatabaseHas('classes', ['name' => 'XI RPL 99']);
        $this->assertDatabaseHas('classes', ['name' => 'XII MM 3']);
        $this->assertSame(1, Student::query()->where('class_name', 'XI RPL 99')->count());
        $this->assertSame(1, Student::query()->where('class_name', 'XII MM 3')->count());

        $this->assertStringContainsString('2 kelas baru ditambahkan (XI RPL 99, XII MM 3)', session('success'));
    }

    public function test_import_confirm_does_not_recreate_existing_classes(): void
    {
        $importData = [
            'validRows' => [
                ['row' => 2, 'nisn' => '0012345678', 'name' => 'Andi Pratama', 'class_name' => 'XI RPL 1', 'mode' => 'create'],
            ],
            'invalidRows' => [],
            'newClasses' => ['XI RPL 1'],
            'headerError' => '',
        ];

        Cache::put('import_pending_'.$this->admin->id, $importData, now()->addMinutes(10));

        $response = $this->actingAs($this->admin)
            ->withHeaders(['Cookie' => ''])
            ->post(route('admin.students.import-confirm'))
            ->assertOk()
            ->assertJsonPath('new_classes_created', 0);

        $this->assertSame(1, Classroom::query()->where('name', 'XI RPL 1')->count());
    }

    public function test_bulk_delete_removes_selected_students(): void
    {
        $students = Student::factory()->count(3)->create();

        $this->actingAs($this->admin)
            ->post(route('admin.students.bulk-delete'), [
                'ids' => [$students[0]->id, $students[1]->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', '2 data siswa berhasil dihapus.');

        $this->assertDatabaseMissing('students', ['id' => $students[0]->id]);
        $this->assertDatabaseMissing('users', ['id' => $students[0]->user_id]);
        $this->assertDatabaseHas('students', ['id' => $students[2]->id]);
    }

    public function test_bulk_delete_requires_selection(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.students.bulk-delete'), ['ids' => []])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_non_admin_cannot_export(): void
    {
        $peserta = User::factory()->peserta()->create();

        $this->actingAs($peserta)
            ->get(route('admin.students.export'))
            ->assertForbidden();
    }

    /**
     * Buat fake CSV yang tetap punya client mime text/csv agar lolos rule mimes.
     */
    private function csvFile(string $content, string $name = 'siswa.csv'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'import_');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, 'text/csv', null, true);
    }
}

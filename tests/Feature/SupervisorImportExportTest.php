<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\Supervisor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SupervisorImportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_can_download_export_xlsx(): void
    {
        Supervisor::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.supervisors.export'))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertDownload('data-pengawas-'.date('Y-m-d').'.xlsx');
    }

    public function test_admin_can_download_export_csv(): void
    {
        $supervisor = Supervisor::factory()->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.supervisors.export', ['format' => 'csv']))
            ->assertOk()
            ->assertDownload('data-pengawas-'.date('Y-m-d').'.csv');

        $content = $response->streamedContent();
        $this->assertStringContainsString($supervisor->user->email, $content);
        $this->assertStringContainsString($supervisor->user->plain_password, $content);
    }

    public function test_export_respects_room_filter(): void
    {
        $roomA = Room::factory()->create(['room_number' => 1]);
        $roomB = Room::factory()->create(['room_number' => 2]);

        $inRoom = Supervisor::factory()->create(['room_id' => $roomA->id]);
        Supervisor::factory()->create(['room_id' => $roomB->id]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.supervisors.export', ['format' => 'csv', 'room' => $roomA->id]))
            ->assertOk();

        $content = $response->streamedContent();
        $this->assertStringContainsString($inRoom->user->email, $content);
        $this->assertStringNotContainsString('Ruang 2', $content);
    }

    public function test_admin_can_download_import_template(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.supervisors.import-template'))
            ->assertOk()
            ->assertDownload('template-import-pengawas.xlsx');
    }

    public function test_import_validate_returns_preview(): void
    {
        $existing = Supervisor::factory()->create();

        $csv = "Nama,Email\nAndi Pratama,andi@example.com\n{$existing->user->name},{$existing->user->email}\n";

        $response = $this->actingAs($this->admin)
            ->post(route('admin.supervisors.import-validate'), [
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
            ]);

        $this->assertSame($existing->user->email, $existing->fresh()->user->email);
    }

    public function test_import_validate_rejects_missing_header(): void
    {
        $csv = "Nama\nAndi Pratama\n";

        $this->actingAs($this->admin)
            ->post(route('admin.supervisors.import-validate'), [
                'file' => $this->csvFile($csv),
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'Email'));
    }

    public function test_import_validate_rejects_invalid_rows(): void
    {
        $csv = "Nama,Email\nAndi Pratama,bukan-email\n,andi@example.com\n";

        $this->actingAs($this->admin)
            ->post(route('admin.supervisors.import-validate'), [
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

    public function test_import_validate_rejects_duplicate_email_in_file(): void
    {
        $csv = "Nama,Email\nAndi Pratama,andi@example.com\nBudi Santoso,andi@example.com\n";

        $response = $this->actingAs($this->admin)
            ->post(route('admin.supervisors.import-validate'), [
                'file' => $this->csvFile($csv),
            ])
            ->assertOk();

        $response->assertJsonPath('valid', 1);
        $response->assertJsonPath('invalid', 1);
        $this->assertStringContainsString('duplikat', $response->json('errors.0'));
    }

    public function test_import_validate_rejects_email_used_by_other_role(): void
    {
        User::factory()->peserta()->create(['email' => 'andi@example.com']);

        $csv = "Nama,Email\nAndi Pratama,andi@example.com\n";

        $response = $this->actingAs($this->admin)
            ->post(route('admin.supervisors.import-validate'), [
                'file' => $this->csvFile($csv),
            ])
            ->assertOk()
            ->assertJson(['valid' => 0, 'invalid' => 1]);

        $this->assertStringContainsString('bukan pengawas', $response->json('errors.0'));
    }

    public function test_import_validate_rejects_non_excel_file(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.supervisors.import-validate'), [
                'file' => UploadedFile::fake()->create('catatan.txt', 10),
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_import_confirm_without_pending_session_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.supervisors.import-confirm'))
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'kadaluarsa'));
    }

    public function test_import_confirm_creates_and_updates_supervisors(): void
    {
        $existing = Supervisor::factory()->create();
        $oldPassword = $existing->user->plain_password;
        $oldRoom = $existing->room;

        $importData = [
            'validRows' => [
                ['row' => 2, 'name' => 'Andi Pratama', 'email' => 'andi@example.com', 'mode' => 'create'],
                ['row' => 3, 'name' => 'Budi Santoso', 'email' => $existing->user->email, 'mode' => 'update'],
            ],
            'invalidRows' => [],
            'headerError' => '',
        ];

        Cache::put('import_pending_'.$this->admin->id, $importData, now()->addMinutes(10));

        $response = $this->actingAs($this->admin)
            ->withHeaders(['Cookie' => ''])
            ->post(route('admin.supervisors.import-confirm'))
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'created' => 1,
                'updated' => 1,
            ]);

        $created = User::query()->where('email', 'andi@example.com')->first();
        $this->assertNotNull($created);
        $this->assertSame('Andi Pratama', $created->name);
        $this->assertSame(User::ROLE_PENGAWAS, $created->role);
        $this->assertTrue((bool) $created->is_active);
        $this->assertNull($created->username);
        $this->assertNotNull($created->plain_password);
        $this->assertTrue(strlen($created->plain_password) >= 8);
        $this->assertTrue(password_verify($created->plain_password, $created->password));
        $this->assertNotSame('Password123', $created->plain_password);
        $this->assertNull($created->supervisor->room_id);

        $existing->refresh();
        $this->assertSame('Budi Santoso', $existing->user->name);
        $this->assertSame($oldPassword, $existing->user->plain_password);
        $this->assertSame($oldRoom->id, $existing->room_id);
    }

    public function test_import_confirm_generates_password_and_leaves_room_empty(): void
    {
        $importData = [
            'validRows' => [
                ['row' => 2, 'name' => 'Andi Pratama', 'email' => 'andi@example.com', 'mode' => 'create'],
            ],
            'invalidRows' => [],
            'headerError' => '',
        ];

        Cache::put('import_pending_'.$this->admin->id, $importData, now()->addMinutes(10));

        $response = $this->actingAs($this->admin)
            ->withHeaders(['Cookie' => ''])
            ->post(route('admin.supervisors.import-confirm'))
            ->assertOk()
            ->assertJson(['ok' => true, 'created' => 1]);

        $created = User::query()->where('email', 'andi@example.com')->first();
        $this->assertNotNull($created->plain_password);
        $this->assertTrue(strlen($created->plain_password) >= 8);
        $this->assertTrue(password_verify($created->plain_password, $created->password));
        $this->assertNull($created->supervisor->room_id);
    }

    public function test_import_confirm_keeps_password_and_room_on_update(): void
    {
        $existing = Supervisor::factory()->create();
        $oldPassword = $existing->user->plain_password;
        $oldRoomId = $existing->room_id;

        $importData = [
            'validRows' => [
                ['row' => 2, 'name' => 'Nama Baru', 'email' => $existing->user->email, 'mode' => 'update'],
            ],
            'invalidRows' => [],
            'headerError' => '',
        ];

        Cache::put('import_pending_'.$this->admin->id, $importData, now()->addMinutes(10));

        $this->actingAs($this->admin)
            ->post(route('admin.supervisors.import-confirm'))
            ->assertOk();

        $existing->refresh();
        $this->assertSame('Nama Baru', $existing->user->name);
        $this->assertSame($oldPassword, $existing->user->plain_password);
        $this->assertSame($oldRoomId, $existing->room_id);
    }

    public function test_import_confirm_stores_failed_rows_file(): void
    {
        $importData = [
            'validRows' => [
                ['row' => 2, 'name' => 'Andi Pratama', 'email' => 'andi@example.com', 'mode' => 'create'],
            ],
            'invalidRows' => [
                [
                    'row' => 3,
                    'data' => ['name' => 'Budi Santoso', 'email' => 'budi@example.com'],
                    'errors' => ['Email budi@example.com tidak valid.'],
                ],
            ],
            'headerError' => '',
        ];

        Cache::put('import_pending_'.$this->admin->id, $importData, now()->addMinutes(10));

        $response = $this->actingAs($this->admin)
            ->withHeaders(['Cookie' => ''])
            ->post(route('admin.supervisors.import-confirm'))
            ->assertOk()
            ->assertJson(['ok' => true, 'created' => 1, 'failed_count' => 1]);

        $failedFile = $response->json('failed_file');
        $this->assertNotNull($failedFile);

        $this->actingAs($this->admin)
            ->get(route('admin.supervisors.import-failed', $failedFile))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename='.$failedFile);
    }

    public function test_bulk_delete_removes_selected_supervisors(): void
    {
        $supervisors = Supervisor::factory()->count(3)->create();

        $this->actingAs($this->admin)
            ->post(route('admin.supervisors.bulk-delete'), [
                'ids' => [$supervisors[0]->id, $supervisors[1]->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', '2 data pengawas berhasil dihapus.');

        $this->assertDatabaseMissing('supervisors', ['id' => $supervisors[0]->id]);
        $this->assertDatabaseMissing('users', ['id' => $supervisors[0]->user_id]);
        $this->assertDatabaseHas('supervisors', ['id' => $supervisors[2]->id]);
    }

    public function test_bulk_delete_requires_selection(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.supervisors.bulk-delete'), ['ids' => []])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_non_admin_cannot_export(): void
    {
        $peserta = User::factory()->peserta()->create();

        $this->actingAs($peserta)
            ->get(route('admin.supervisors.export'))
            ->assertForbidden();
    }

    /**
     * Buat fake CSV yang tetap punya client mime text/csv agar lolos rule mimes.
     */
    private function csvFile(string $content, string $name = 'pengawas.csv'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'import_');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, 'text/csv', null, true);
    }
}

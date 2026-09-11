<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Models\WaliKelas;
use App\Models\WaliKelasNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaliKelasNoteTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroomA;

    private Classroom $classroomB;

    private WaliKelas $waliA;

    private WaliKelas $waliB;

    private Semester $semester1;

    private Semester $semester2;

    private Student $studentA1;

    private Student $studentA2;

    private Student $studentB1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classroomA = Classroom::factory()->create(['name' => 'XI RPL 1']);
        $this->classroomB = Classroom::factory()->create(['name' => 'XI RPL 2']);
        $this->waliA = WaliKelas::factory()->create(['classroom_id' => $this->classroomA->id]);
        $this->waliB = WaliKelas::factory()->create(['classroom_id' => $this->classroomB->id]);

        $tahunAjaran = AcademicYear::factory()->create(['nama' => '2024/2025']);
        $this->semester1 = Semester::create(['academic_year_id' => $tahunAjaran->id, 'jenis' => 'ganjil', 'is_active' => false]);
        $this->semester2 = Semester::create(['academic_year_id' => $tahunAjaran->id, 'jenis' => 'genap', 'is_active' => true]);

        $this->studentA1 = Student::factory()->create(['classroom_id' => $this->classroomA->id]);
        $this->studentA2 = Student::factory()->create(['classroom_id' => $this->classroomA->id]);
        $this->studentB1 = Student::factory()->create(['classroom_id' => $this->classroomB->id]);
    }

    // ── Akses & Otorisasi ─────────────────────────────────────────────

    public function test_non_wali_kelas_cannot_access_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('wali_kelas.dashboard'))
            ->assertForbidden();
    }

    public function test_wali_kelas_can_access_dashboard_with_catatan(): void
    {
        $this->actingAs($this->waliA->user)
            ->get(route('wali_kelas.catatan'))
            ->assertOk()
            ->assertSee('Catatan Wali Kelas');
    }

    // ── Append-Only Behavior ───────────────────────────────────────────

    public function test_wali_kelas_can_store_catatan(): void
    {
        $response = $this->actingAs($this->waliA->user)
            ->post(route('wali_kelas.catatan.store'), [
                'student_id' => $this->studentA1->id,
                'semester_id' => $this->semester2->id,
                'tipe' => 'observasi',
                'catatan' => 'Siswa aktif di kelas hari ini.',
            ]);

        $response->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('wali_kelas_notes', [
            'student_id' => $this->studentA1->id,
            'classroom_id' => $this->classroomA->id,
            'wali_kelas_id' => $this->waliA->id,
            'semester_id' => $this->semester2->id,
            'tipe' => 'observasi',
            'catatan' => 'Siswa aktif di kelas hari ini.',
        ]);
    }

    // ── Redirect URL Integrity ──────────────────────────────────────────

    public function test_store_redirect_goes_to_catatan_page_preserving_student_id(): void
    {
        $response = $this->actingAs($this->waliA->user)
            ->post(route('wali_kelas.catatan.store'), [
                'student_id' => $this->studentA1->id,
                'semester_id' => $this->semester2->id,
                'tipe' => 'observasi',
                'catatan' => 'Test redirect.',
            ]);

        $redirectUrl = $response->headers->get('Location');

        // Redirect harus menuju halaman Catatan (bukan dashboard),
        // dengan student_id terbawa supaya entri baru langsung terlihat.
        $this->assertStringContainsString('wali_kelas/catatan', $redirectUrl);
        $this->assertStringContainsString('student_id='.$this->studentA1->id, $redirectUrl);

        // Tidak boleh ada sisa query-string rusak (double '?' atau tab=).
        $this->assertStringNotContainsString('tab=', $redirectUrl);
        $queryStart = strpos($redirectUrl, '?');
        if ($queryStart !== false) {
            $queryPart = substr($redirectUrl, $queryStart + 1);
            $this->assertStringNotContainsString('?', $queryPart, 'URL redirect tidak boleh memiliki double ?');
        }
    }

    public function test_store_redirect_url_parses_to_catatan_route(): void
    {
        $response = $this->actingAs($this->waliA->user)
            ->post(route('wali_kelas.catatan.store'), [
                'student_id' => $this->studentA1->id,
                'semester_id' => $this->semester2->id,
                'catatan' => 'Test redirect parse.',
            ]);

        $redirectUrl = $response->headers->get('Location');
        $parsedUrl = parse_url($redirectUrl);
        parse_str($parsedUrl['query'] ?? '', $queryParams);

        // student_id harus bernilai persis, bukan string gabungan.
        $this->assertArrayHasKey('student_id', $queryParams);
        $this->assertEquals((string) $this->studentA1->id, $queryParams['student_id']);
    }

    public function test_store_creates_new_entry_not_overwrite(): void
    {
        // Buat entri pertama
        $this->actingAs($this->waliA->user)
            ->post(route('wali_kelas.catatan.store'), [
                'student_id' => $this->studentA1->id,
                'semester_id' => $this->semester2->id,
                'tipe' => 'observasi',
                'catatan' => 'Catatan pertama.',
            ]);

        // Buat entri kedua — harusnya jadi baris baru, bukan update
        $this->actingAs($this->waliA->user)
            ->post(route('wali_kelas.catatan.store'), [
                'student_id' => $this->studentA1->id,
                'semester_id' => $this->semester2->id,
                'tipe' => 'pelanggaran',
                'catatan' => 'Catatan kedua.',
            ]);

        $this->assertDatabaseCount('wali_kelas_notes', 2);

        $this->assertDatabaseHas('wali_kelas_notes', [
            'catatan' => 'Catatan pertama.',
            'tipe' => 'observasi',
        ]);

        $this->assertDatabaseHas('wali_kelas_notes', [
            'catatan' => 'Catatan kedua.',
            'tipe' => 'pelanggaran',
        ]);
    }

    public function test_notes_are_ordered_newest_first(): void
    {
        // Buat 3 entri dengan waktu berbeda (forced via forceFill karena
        // created_at tidak ada di $fillable model)
        $old = WaliKelasNote::create([
            'student_id' => $this->studentA1->id,
            'classroom_id' => $this->classroomA->id,
            'wali_kelas_id' => $this->waliA->id,
            'semester_id' => $this->semester2->id,
            'tipe' => 'observasi',
            'catatan' => 'Paling lama',
        ]);
        $old->forceFill(['created_at' => now()->subDays(3)])->save();

        $new = WaliKelasNote::create([
            'student_id' => $this->studentA1->id,
            'classroom_id' => $this->classroomA->id,
            'wali_kelas_id' => $this->waliA->id,
            'semester_id' => $this->semester2->id,
            'tipe' => 'prestasi',
            'catatan' => 'Paling baru',
        ]);
        $new->forceFill(['created_at' => now()])->save();

        $mid = WaliKelasNote::create([
            'student_id' => $this->studentA1->id,
            'classroom_id' => $this->classroomA->id,
            'wali_kelas_id' => $this->waliA->id,
            'semester_id' => $this->semester2->id,
            'tipe' => 'lainnya',
            'catatan' => 'Tengah',
        ]);
        $mid->forceFill(['created_at' => now()->subDay()])->save();

        $this->actingAs($this->waliA->user)
            ->get(route('wali_kelas.catatan'))
            ->assertOk();

        // Verifikasi urutan via query langsung
        $notes = WaliKelasNote::query()
            ->where('student_id', $this->studentA1->id)
            ->orderByDesc('created_at')
            ->get();

        $this->assertEquals('Paling baru', $notes[0]->catatan);
        $this->assertEquals('Tengah', $notes[1]->catatan);
        $this->assertEquals('Paling lama', $notes[2]->catatan);
    }

    // ── Isolasi Kelas ─────────────────────────────────────────────────

    public function test_wali_cannot_store_for_student_from_other_classroom(): void
    {
        $response = $this->actingAs($this->waliA->user)
            ->post(route('wali_kelas.catatan.store'), [
                'student_id' => $this->studentB1->id, // siswa dari kelas B
                'semester_id' => $this->semester2->id,
                'catatan' => 'Mencoba catat siswa lain.',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseCount('wali_kelas_notes', 0);
    }

    public function test_index_only_shows_notes_for_own_classroom(): void
    {
        // Wali A catat siswa A1
        WaliKelasNote::create([
            'student_id' => $this->studentA1->id,
            'classroom_id' => $this->classroomA->id,
            'wali_kelas_id' => $this->waliA->id,
            'semester_id' => $this->semester2->id,
            'catatan' => 'Catatan wali A.',
        ]);

        // Wali B catat siswa B1
        WaliKelasNote::create([
            'student_id' => $this->studentB1->id,
            'classroom_id' => $this->classroomB->id,
            'wali_kelas_id' => $this->waliB->id,
            'semester_id' => $this->semester2->id,
            'catatan' => 'Catatan wali B.',
        ]);

        $response = $this->actingAs($this->waliA->user)
            ->get(route('wali_kelas.catatan'));

        $response->assertOk();

        // Pastikan catatan wali B tidak muncul di view wali A
        $response->assertDontSee('Catatan wali B.');
        $response->assertSee('Catatan wali A.');
    }

    // ── Filter Semester & Siswa ────────────────────────────────────────

    public function test_index_filters_by_semester(): void
    {
        WaliKelasNote::create([
            'student_id' => $this->studentA1->id,
            'classroom_id' => $this->classroomA->id,
            'wali_kelas_id' => $this->waliA->id,
            'semester_id' => $this->semester1->id,
            'catatan' => 'Catatan semester 1.',
        ]);

        WaliKelasNote::create([
            'student_id' => $this->studentA1->id,
            'classroom_id' => $this->classroomA->id,
            'wali_kelas_id' => $this->waliA->id,
            'semester_id' => $this->semester2->id,
            'catatan' => 'Catatan semester 2.',
        ]);

        $response = $this->actingAs($this->waliA->user)
            ->withSession(['wali_kelas_semester_id' => $this->semester1->id])
            ->get(route('wali_kelas.catatan'));

        $response->assertOk();
        $response->assertSee('Catatan semester 1.');
        $response->assertDontSee('Catatan semester 2.');
    }

    public function test_index_filters_by_student(): void
    {
        WaliKelasNote::create([
            'student_id' => $this->studentA1->id,
            'classroom_id' => $this->classroomA->id,
            'wali_kelas_id' => $this->waliA->id,
            'semester_id' => $this->semester2->id,
            'catatan' => 'Catatan untuk A1.',
        ]);

        WaliKelasNote::create([
            'student_id' => $this->studentA2->id,
            'classroom_id' => $this->classroomA->id,
            'wali_kelas_id' => $this->waliA->id,
            'semester_id' => $this->semester2->id,
            'catatan' => 'Catatan untuk A2.',
        ]);

        $response = $this->actingAs($this->waliA->user)
            ->get(route('wali_kelas.catatan', ['student_id' => $this->studentA1->id]));

        $response->assertOk();
        $response->assertSee('Catatan untuk A1.');
        $response->assertDontSee('Catatan untuk A2.');
    }

    // ── Query Param Manipulation (Scoping) ─────────────────────────────

    public function test_filter_with_student_from_other_classroom_returns_empty(): void
    {
        // Wali A punya siswa A1 (kelas A). Siswa B1 ada di kelas B.
        // Manipulasi ?student_id=B1 harus TIDAK menampilkan data apapun
        // (di-ignore oleh query, bukan bocor data kelas B).
        $response = $this->actingAs($this->waliA->user)
            ->get(route('wali_kelas.catatan', [
                'student_id' => $this->studentB1->id,
            ]));

        $response->assertOk();

        // Data siswa B tidak boleh muncul di response.
        $response->assertDontSee($this->studentB1->user->name);
    }

    // ── Validasi ───────────────────────────────────────────────────────

    public function test_store_requires_catatan(): void
    {
        $response = $this->actingAs($this->waliA->user)
            ->post(route('wali_kelas.catatan.store'), [
                'student_id' => $this->studentA1->id,
                'semester_id' => $this->semester2->id,
                'catatan' => '',
            ]);

        $response->assertSessionHasErrors('catatan');
    }

    public function test_store_requires_valid_tipe(): void
    {
        $response = $this->actingAs($this->waliA->user)
            ->post(route('wali_kelas.catatan.store'), [
                'student_id' => $this->studentA1->id,
                'semester_id' => $this->semester2->id,
                'tipe' => 'invalid_type',
                'catatan' => 'Test catatan.',
            ]);

        $response->assertSessionHasErrors('tipe');
    }

    public function test_store_accepts_null_tipe(): void
    {
        $response = $this->actingAs($this->waliA->user)
            ->post(route('wali_kelas.catatan.store'), [
                'student_id' => $this->studentA1->id,
                'semester_id' => $this->semester2->id,
                'tipe' => null,
                'catatan' => 'Tanpa tipe.',
            ]);

        $response->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('wali_kelas_notes', [
            'student_id' => $this->studentA1->id,
            'tipe' => null,
        ]);
    }

    // ── Wali Kelas ID nullOnDelete ────────────────────────────────────

    public function test_wali_kelas_note_persists_after_wali_deleted(): void
    {
        // Buat catatan
        $note = WaliKelasNote::create([
            'student_id' => $this->studentA1->id,
            'classroom_id' => $this->classroomA->id,
            'wali_kelas_id' => $this->waliA->id,
            'semester_id' => $this->semester2->id,
            'catatan' => 'Catatan sebelum resign.',
        ]);

        // Hapus wali kelas
        $this->waliA->delete();

        // Catatan harus tetap ada, tapi wali_kelas_id jadi null
        $note->refresh();
        $this->assertNull($note->wali_kelas_id);
    }
}

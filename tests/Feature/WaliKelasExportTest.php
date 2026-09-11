<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\AttitudeAspect;
use App\Models\AttitudeGrade;
use App\Models\Classroom;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\Grade;
use App\Models\GuruMapel;
use App\Models\Room;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Violation;
use App\Models\WaliKelas;
use App\Models\WaliKelasNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class WaliKelasExportTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $tahunAjaran;

    private Semester $semesterGanjil;

    private Semester $semesterGenap;

    private Classroom $classroom;

    private WaliKelas $wali;

    private Student $studentA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classroom = Classroom::factory()->create(['name' => 'XI RPL 1']);
        $this->wali = WaliKelas::factory()->create(['classroom_id' => $this->classroom->id]);
        $this->tahunAjaran = AcademicYear::factory()->create(['nama' => '2024/2025']);
        $this->semesterGanjil = Semester::factory()->ganjil($this->tahunAjaran)->create(['is_active' => false]);
        $this->semesterGenap = Semester::factory()->genap($this->tahunAjaran)->aktif()->create();
        $this->studentA = Student::factory()->create(['classroom_id' => $this->classroom->id]);
    }

    private function download(array $session = []): TestResponse
    {
        return $this->actingAs($this->wali->user)
            ->withSession($session ?: ['wali_kelas_semester_id' => $this->semesterGenap->id])
            ->get(route('wali_kelas.export-excel'));
    }

    private function loadSpreadsheet(TestResponse $response)
    {
        return IOFactory::load($response->getFile()->getPathname());
    }

    public function test_downloads_valid_xlsx_with_expected_filename(): void
    {
        $response = $this->download(['wali_kelas_semester_id' => $this->semesterGenap->id]);

        $response->assertOk()
            ->assertDownload('Rekap_XIRPL1_2024-2025-Genap.xlsx');

        // File harus BUKAN corrupt: bisa dimuat PhpSpreadsheet.
        $this->assertNotNull($this->loadSpreadsheet($response));
    }

    public function test_filename_for_ganjil_semester_and_year_with_slash(): void
    {
        $response = $this->download(['wali_kelas_semester_id' => $this->semesterGanjil->id]);

        $response->assertDownload('Rekap_XIRPL1_2024-2025-Ganjil.xlsx');
    }

    public function test_all_four_sheets_present_with_expected_data(): void
    {
        $guru = GuruMapel::factory()->create();
        $subject = Subject::factory()->create(['name' => 'Matematika']);
        Grade::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
            'classroom_id' => $this->classroom->id,
            'student_id' => $this->studentA->id,
            'score' => 88.50,
            'is_override' => true,
            'semester_id' => $this->semesterGenap->id,
            'note' => 'Remedial',
        ]);

        $aspect = AttitudeAspect::create(['name' => 'Kedisiplinan']);
        AttitudeGrade::create([
            'student_id' => $this->studentA->id,
            'classroom_id' => $this->classroom->id,
            'wali_kelas_id' => $this->wali->id,
            'attitude_aspect_id' => $aspect->id,
            'semester_id' => $this->semesterGenap->id,
            'score' => 90,
        ]);

        WaliKelasNote::create([
            'student_id' => $this->studentA->id,
            'classroom_id' => $this->classroom->id,
            'wali_kelas_id' => $this->wali->id,
            'semester_id' => $this->semesterGenap->id,
            'tipe' => 'observasi',
            'catatan' => 'Siswa rajin.',
        ]);

        $room = Room::factory()->create();
        $schedule = ExamSchedule::factory()->create(['subject_id' => $subject->id, 'room_id' => $room->id]);
        $session = ExamSession::factory()->create(['student_id' => $this->studentA->id, 'exam_schedule_id' => $schedule->id]);
        Violation::create([
            'exam_session_id' => $session->id,
            'violation_type' => 'berpindah_tab',
            'occurred_at' => now(),
            'handled_by_supervisor' => false,
        ]);

        $spreadsheet = $this->loadSpreadsheet($this->download());

        $this->assertEquals(
            ['Nilai Akademik', 'Nilai Sikap', 'Rekap Pelanggaran', 'Catatan Wali Kelas'],
            $spreadsheet->getSheetNames()
        );

        // Sheet 1: Nilai Akademik
        $nilaiAkademik = $spreadsheet->getSheetByName('Nilai Akademik')->toArray();
        $this->assertSame(['NISN', 'Nama Siswa', 'Mata Pelajaran', 'Nilai Akhir', 'Sumber'], $nilaiAkademik[0]);
        // Baris data [NISN, Nama, Mapel, 88.5, Override Guru]
        $dataRow = $nilaiAkademik[1];
        $this->assertSame($this->studentA->nisn, $dataRow[0]);
        $this->assertSame('Matematika', $dataRow[2]);
        $this->assertSame('Override Guru', $dataRow[4]);

        // Sheet 2: Nilai Sikap
        $nilaiSikap = $spreadsheet->getSheetByName('Nilai Sikap')->toArray();
        $this->assertSame(['NISN', 'Nama Siswa', 'Kedisiplinan'], array_slice($nilaiSikap[0], 0, 3));
        $this->assertSame(90.0, (float) $nilaiSikap[1][2]);

        // Sheet 3: Pelanggaran
        $pelanggaran = $spreadsheet->getSheetByName('Rekap Pelanggaran')->toArray();
        $this->assertSame(['NISN', 'Nama Siswa', 'Jenis Pelanggaran', 'Tanggal', 'Status Penanganan'], $pelanggaran[0]);
        $this->assertSame(Violation::typeLabel('berpindah_tab'), $pelanggaran[1][2]);
        $this->assertSame('Belum ditangani', $pelanggaran[1][4]);

        // Sheet 4: Catatan
        $catatan = $spreadsheet->getSheetByName('Catatan Wali Kelas')->toArray();
        $this->assertSame(['Tanggal', 'NISN', 'Nama Siswa', 'Tipe', 'Isi Catatan'], $catatan[0]);
        $this->assertSame('Observasi', $catatan[1][3]);
        $this->assertSame('Siswa rajin.', $catatan[1][4]);
    }

    public function test_sheets_reflect_semester_from_session_not_other_semester(): void
    {
        $aspect = AttitudeAspect::create(['name' => 'Kerja Sama']);

        // Nilai + catatan di semester Ganjil
        AttitudeGrade::create([
            'student_id' => $this->studentA->id,
            'classroom_id' => $this->classroom->id,
            'wali_kelas_id' => $this->wali->id,
            'attitude_aspect_id' => $aspect->id,
            'semester_id' => $this->semesterGanjil->id,
            'score' => 75,
        ]);
        WaliKelasNote::create([
            'student_id' => $this->studentA->id,
            'classroom_id' => $this->classroom->id,
            'wali_kelas_id' => $this->wali->id,
            'semester_id' => $this->semesterGanjil->id,
            'tipe' => 'prestasi',
            'catatan' => 'Catatan semester ganjil.',
        ]);

        // Nilai + catatan di semester Genap
        AttitudeGrade::create([
            'student_id' => $this->studentA->id,
            'classroom_id' => $this->classroom->id,
            'wali_kelas_id' => $this->wali->id,
            'attitude_aspect_id' => $aspect->id,
            'semester_id' => $this->semesterGenap->id,
            'score' => 95,
        ]);
        WaliKelasNote::create([
            'student_id' => $this->studentA->id,
            'classroom_id' => $this->classroom->id,
            'wali_kelas_id' => $this->wali->id,
            'semester_id' => $this->semesterGenap->id,
            'tipe' => 'observasi',
            'catatan' => 'Catatan semester genap.',
        ]);

        // Export dengan session = Ganjil → data Ganjil yang muncul.
        $spreadsheet = $this->loadSpreadsheet($this->download(['wali_kelas_semester_id' => $this->semesterGanjil->id]));

        $nilaiSikap = $spreadsheet->getSheetByName('Nilai Sikap')->toArray();
        $this->assertSame(75.0, (float) $nilaiSikap[1][2]);

        $catatan = $spreadsheet->getSheetByName('Catatan Wali Kelas')->toArray();
        $this->assertSame('Catatan semester ganjil.', $catatan[1][4]);
        $this->assertCount(2, $catatan); // header + 1 baris data saja
    }

    public function test_wali_only_exports_own_classroom_data(): void
    {
        // Kelas B + siswa B dengan nilai (tidak boleh bocor ke export wali A)
        $classroomB = Classroom::factory()->create(['name' => 'X TKR 1']);
        $waliB = WaliKelas::factory()->create(['classroom_id' => $classroomB->id]);
        $studentB = Student::factory()->create(['classroom_id' => $classroomB->id]);

        $guru = GuruMapel::factory()->create();
        $subject = Subject::factory()->create();
        Grade::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroomB->id,
            'student_id' => $studentB->id,
            'score' => 99.00,
            'is_override' => true,
            'semester_id' => $this->semesterGenap->id,
        ]);

        // Juga beri nilai untuk siswa A (kelas wali A).
        Grade::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
            'classroom_id' => $this->classroom->id,
            'student_id' => $this->studentA->id,
            'score' => 70.00,
            'is_override' => false,
            'semester_id' => $this->semesterGenap->id,
        ]);

        $spreadsheet = $this->loadSpreadsheet($this->download());

        $nilaiAkademik = $spreadsheet->getSheetByName('Nilai Akademik')->toArray();
        $namaSiswa = collect($nilaiAkademik)->pluck(1)->map(fn ($v) => (string) $v)->values();

        $this->assertContains($this->studentA->user->name, $namaSiswa);
        $this->assertNotContains($studentB->user->name, $namaSiswa);
    }

    public function test_catatan_sheet_still_present_with_headers_when_empty(): void
    {
        // Tidak ada catatan sama sekali di semester Genap.
        AttitudeAspect::create(['name' => 'Kedisiplinan']);

        $spreadsheet = $this->loadSpreadsheet($this->download());

        $catatan = $spreadsheet->getSheetByName('Catatan Wali Kelas');
        $this->assertNotNull($catatan);
        $rows = $catatan->toArray();
        $this->assertSame(['Tanggal', 'NISN', 'Nama Siswa', 'Tipe', 'Isi Catatan'], $rows[0]);
        $this->assertCount(1, $rows); // hanya header, tanpa baris data, tanpa error
    }

    public function test_export_blocked_with_clear_error_when_class_has_no_students(): void
    {
        $classroomKosong = Classroom::factory()->create(['name' => 'X AKL 1']);
        $waliKosong = WaliKelas::factory()->create(['classroom_id' => $classroomKosong->id]);

        $response = $this->actingAs($waliKosong->user)
            ->withSession(['wali_kelas_semester_id' => $this->semesterGenap->id])
            ->get(route('wali_kelas.export-excel'));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }
}

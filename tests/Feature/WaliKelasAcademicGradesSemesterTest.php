<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Grade;
use App\Models\GuruMapel;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\WaliKelas;
use App\Services\WaliKelasDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaliKelasAcademicGradesSemesterTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroom;

    private WaliKelas $wali;

    private Semester $semesterGanjil;

    private Semester $semesterGenap;

    private Student $student;

    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classroom = Classroom::factory()->create(['name' => 'XI RPL 1']);
        $this->wali = WaliKelas::factory()->create(['classroom_id' => $this->classroom->id]);
        $tahunAjaran = AcademicYear::factory()->create(['nama' => '2024/2025']);
        $this->semesterGanjil = Semester::factory()->ganjil($tahunAjaran)->create();
        $this->semesterGenap = Semester::factory()->genap($tahunAjaran)->aktif()->create();
        $this->student = Student::factory()->create(['classroom_id' => $this->classroom->id]);
        $this->subject = Subject::factory()->create(['name' => 'Matematika']);
        $guru = GuruMapel::factory()->create();

        // Dua baris untuk MAPEL SAMA + siswa SAMA, beda semester.
        Grade::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => $this->subject->id,
            'classroom_id' => $this->classroom->id,
            'student_id' => $this->student->id,
            'semester_id' => $this->semesterGanjil->id,
            'score' => 85.00,
            'is_override' => false,
        ]);
        Grade::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => $this->subject->id,
            'classroom_id' => $this->classroom->id,
            'student_id' => $this->student->id,
            'semester_id' => $this->semesterGenap->id,
            'score' => 90.00,
            'is_override' => true,
            'note' => 'Remedial',
        ]);
    }

    public function test_academic_grades_returns_only_requested_semester(): void
    {
        $service = app(WaliKelasDataService::class);

        $ganjil = $service->academicGrades($this->classroom->id, $this->semesterGanjil->id);
        $this->assertSame(85.0, (float) $ganjil[$this->student->id]['grades'][$this->subject->id]['score']);
        $this->assertSame('auto', $ganjil[$this->student->id]['grades'][$this->subject->id]['source']);

        $genap = $service->academicGrades($this->classroom->id, $this->semesterGenap->id);
        $this->assertSame(90.0, (float) $genap[$this->student->id]['grades'][$this->subject->id]['score']);
        $this->assertSame('override', $genap[$this->student->id]['grades'][$this->subject->id]['source']);
        $this->assertSame('Remedial', $genap[$this->student->id]['grades'][$this->subject->id]['note']);
    }

    public function test_academic_grades_does_not_mix_the_other_semester(): void
    {
        $service = app(WaliKelasDataService::class);

        $ganjil = $service->academicGrades($this->classroom->id, $this->semesterGanjil->id);
        $scoresGanjil = collect($ganjil[$this->student->id]['grades'])->pluck('score')->all();

        // Hanya skor 85 (semester Ganjil) — 90 (Genap) harus TIDAK muncul.
        $this->assertCount(1, $scoresGanjil);
        $this->assertNotContains(90.0, array_map(fn ($s) => (float) $s, $scoresGanjil));
    }

    public function test_academic_grades_returns_empty_for_invalid_semester(): void
    {
        $service = app(WaliKelasDataService::class);

        $this->assertSame([], $service->academicGrades($this->classroom->id, 0));
        $this->assertSame([], $service->academicGrades($this->classroom->id, 99999));
    }

    public function test_nilai_akademik_page_shows_only_session_semester(): void
    {
        // Halaman nilai-akademik dengan session = Ganjil → tampil 85.00, bukan 90.00.
        $this->actingAs($this->wali->user)
            ->withSession(['wali_kelas_semester_id' => $this->semesterGanjil->id])
            ->get(route('wali_kelas.nilai-akademik'))
            ->assertOk()
            ->assertSee('85.00')
            ->assertDontSee('90.00');
    }
}

<?php

namespace Tests\Unit;

use App\Models\AcademicYear;
use App\Models\Semester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SemesterTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_scope_returns_only_active_semesters(): void
    {
        $tahunAjaran = AcademicYear::factory()->create(['nama' => '2024/2025']);
        Semester::create(['academic_year_id' => $tahunAjaran->id, 'jenis' => 'ganjil', 'is_active' => false]);
        $active = Semester::create(['academic_year_id' => $tahunAjaran->id, 'jenis' => 'genap', 'is_active' => true]);

        $result = Semester::active()->get();

        $this->assertCount(1, $result);
        $this->assertEquals($active->id, $result->first()->id);
    }

    public function test_get_active_returns_active_semester(): void
    {
        $tahunAjaran = AcademicYear::factory()->create(['nama' => '2024/2025']);
        Semester::create(['academic_year_id' => $tahunAjaran->id, 'jenis' => 'ganjil', 'is_active' => false]);
        $active = Semester::create(['academic_year_id' => $tahunAjaran->id, 'jenis' => 'genap', 'is_active' => true]);

        $result = Semester::getActive();

        $this->assertNotNull($result);
        $this->assertEquals($active->id, $result->id);
    }

    public function test_get_active_falls_back_to_newest_when_none_active(): void
    {
        $lama = AcademicYear::factory()->create(['nama' => '2023/2024']);
        $baru = AcademicYear::factory()->create(['nama' => '2024/2025']);

        Semester::create(['academic_year_id' => $lama->id, 'jenis' => 'genap', 'is_active' => false]);
        $newer = Semester::create(['academic_year_id' => $baru->id, 'jenis' => 'genap', 'is_active' => false]);

        $result = Semester::getActive();

        $this->assertNotNull($result);
        $this->assertEquals($newer->id, $result->id);
    }

    public function test_get_active_returns_null_when_no_semesters(): void
    {
        $result = Semester::getActive();

        $this->assertNull($result);
    }

    public function test_nama_lengkap_accessor_uses_academic_year_relation(): void
    {
        $tahunAjaran = AcademicYear::factory()->create(['nama' => '2024/2025']);
        $semester = Semester::create(['academic_year_id' => $tahunAjaran->id, 'jenis' => 'genap', 'is_active' => true]);

        $this->assertEquals('2024/2025 - Semester Genap', $semester->nama_lengkap);
    }

    public function test_semester_belongs_to_academic_year(): void
    {
        $tahunAjaran = AcademicYear::factory()->create(['nama' => '2024/2025']);
        $semester = Semester::factory()->create(['academic_year_id' => $tahunAjaran->id]);

        $this->assertTrue($semester->academicYear->is($tahunAjaran));
    }
}

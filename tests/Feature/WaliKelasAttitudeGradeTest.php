<?php

namespace Tests\Feature;

use App\Models\AttitudeAspect;
use App\Models\AttitudeGrade;
use App\Models\Classroom;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Models\WaliKelas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaliKelasAttitudeGradeTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroomA;

    private Classroom $classroomB;

    private WaliKelas $waliA;

    private WaliKelas $waliB;

    private Semester $semester1;

    private Semester $semester2;

    private AttitudeAspect $discipline;

    private AttitudeAspect $neatness;

    private Student $studentA;

    private Student $studentB;

    protected function setUp(): void
    {
        parent::setUp();

        // Dua kelas + dua wali kelas (isolasi)
        $this->classroomA = Classroom::factory()->create(['name' => 'XI RPL 1']);
        $this->classroomB = Classroom::factory()->create(['name' => 'XI RPL 2']);
        $this->waliA = WaliKelas::factory()->create(['classroom_id' => $this->classroomA->id]);
        $this->waliB = WaliKelas::factory()->create(['classroom_id' => $this->classroomB->id]);

        // Dua semester berbeda agar bisa diuji isolasi per semester
        $this->semester1 = Semester::create(['year' => '2024/2025', 'semester' => 1, 'is_active' => false]);
        $this->semester2 = Semester::create(['year' => '2024/2025', 'semester' => 2, 'is_active' => true]);

        // Dua aspek sikap
        $this->discipline = AttitudeAspect::create(['name' => 'Kedisiplinan', 'description' => null]);
        $this->neatness = AttitudeAspect::create(['name' => 'Kerapian', 'description' => null]);

        // Siswa di tiap kelas
        $this->studentA = Student::factory()->create(['classroom_id' => $this->classroomA->id]);
        $this->studentB = Student::factory()->create(['classroom_id' => $this->classroomB->id]);
    }

    public function test_wali_kelas_can_store_attitude_grade_for_own_student(): void
    {
        $response = $this->actingAs($this->waliA->user)
            ->post(route('wali_kelas.attitude-grades.store'), [
                'semester_id' => $this->semester2->id,
                'student_id' => $this->studentA->id,
                'attitude_aspect_id' => $this->discipline->id,
                'score' => 85,
                'note' => 'Rapi dan tertib',
            ]);

        $response->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('attitude_grades', [
            'student_id' => $this->studentA->id,
            'classroom_id' => $this->classroomA->id,
            'wali_kelas_id' => $this->waliA->id,
            'attitude_aspect_id' => $this->discipline->id,
            'semester_id' => $this->semester2->id,
            'score' => 85,
            'note' => 'Rapi dan tertib',
        ]);
    }

    public function test_wali_kelas_cannot_store_grade_for_student_in_other_classroom(): void
    {
        // Wali A mencoba menilai siswa B (kelas lain) — harus 403 / validation error
        $response = $this->actingAs($this->waliA->user)
            ->post(route('wali_kelas.attitude-grades.store'), [
                'semester_id' => $this->semester2->id,
                'student_id' => $this->studentB->id,
                'attitude_aspect_id' => $this->discipline->id,
                'score' => 90,
            ]);

        $response->assertSessionHasErrors('student_id');

        $this->assertDatabaseMissing('attitude_grades', [
            'student_id' => $this->studentB->id,
            'classroom_id' => $this->classroomA->id,
        ]);
    }

    public function test_wali_kelas_can_update_own_grade(): void
    {
        $grade = AttitudeGrade::create([
            'student_id' => $this->studentA->id,
            'classroom_id' => $this->classroomA->id,
            'wali_kelas_id' => $this->waliA->id,
            'attitude_aspect_id' => $this->discipline->id,
            'semester_id' => $this->semester2->id,
            'score' => 75,
        ]);

        $response = $this->actingAs($this->waliA->user)
            ->put(route('wali_kelas.attitude-grades.update', $grade), [
                'semester_id' => $this->semester2->id,
                'student_id' => $this->studentA->id,
                'attitude_aspect_id' => $this->discipline->id,
                'score' => 92,
                'note' => 'Perbaikan sikap',
            ]);

        $response->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('attitude_grades', [
            'id' => $grade->id,
            'score' => 92,
            'note' => 'Perbaikan sikap',
        ]);
    }

    public function test_wali_kelas_cannot_update_grade_from_other_classroom(): void
    {
        // Nilai milik wali B (kelas B)
        $grade = AttitudeGrade::create([
            'student_id' => $this->studentB->id,
            'classroom_id' => $this->classroomB->id,
            'wali_kelas_id' => $this->waliB->id,
            'attitude_aspect_id' => $this->discipline->id,
            'semester_id' => $this->semester2->id,
            'score' => 80,
        ]);

        // Wali A mencoba update nilai dari kelas B → ditolak (validation error)
        $response = $this->actingAs($this->waliA->user)
            ->put(route('wali_kelas.attitude-grades.update', $grade), [
                'semester_id' => $this->semester2->id,
                'student_id' => $this->studentB->id,
                'attitude_aspect_id' => $this->discipline->id,
                'score' => 50,
            ]);

        $response->assertSessionHasErrors('student_id');

        $this->assertDatabaseHas('attitude_grades', [
            'id' => $grade->id,
            'score' => 80,
        ]);
    }

    public function test_wali_kelas_can_delete_own_grade(): void
    {
        $grade = AttitudeGrade::create([
            'student_id' => $this->studentA->id,
            'classroom_id' => $this->classroomA->id,
            'wali_kelas_id' => $this->waliA->id,
            'attitude_aspect_id' => $this->discipline->id,
            'semester_id' => $this->semester2->id,
            'score' => 80,
        ]);

        $response = $this->actingAs($this->waliA->user)
            ->delete(route('wali_kelas.attitude-grades.destroy', $grade));

        $response->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseMissing('attitude_grades', ['id' => $grade->id]);
    }

    public function test_wali_kelas_cannot_delete_grade_from_other_classroom(): void
    {
        $grade = AttitudeGrade::create([
            'student_id' => $this->studentB->id,
            'classroom_id' => $this->classroomB->id,
            'wali_kelas_id' => $this->waliB->id,
            'attitude_aspect_id' => $this->discipline->id,
            'semester_id' => $this->semester2->id,
            'score' => 80,
        ]);

        $response = $this->actingAs($this->waliA->user)
            ->delete(route('wali_kelas.attitude-grades.destroy', $grade));

        $response->assertForbidden();

        $this->assertDatabaseHas('attitude_grades', ['id' => $grade->id]);
    }

    public function test_non_wali_kelas_role_cannot_access_attitude_grade_routes(): void
    {
        $admin = User::factory()->admin()->create();

        // GET index
        $this->actingAs($admin)
            ->get(route('wali_kelas.attitude-grades.index'))
            ->assertForbidden();

        // POST store
        $this->actingAs($admin)
            ->post(route('wali_kelas.attitude-grades.store'), [
                'semester_id' => $this->semester2->id,
                'student_id' => $this->studentA->id,
                'attitude_aspect_id' => $this->discipline->id,
                'score' => 80,
            ])
            ->assertForbidden();
    }

    public function test_grades_saved_per_semester_do_not_overwrite_each_other(): void
    {
        // Nilai semester 1
        $this->actingAs($this->waliA->user)
            ->post(route('wali_kelas.attitude-grades.store'), [
                'semester_id' => $this->semester1->id,
                'student_id' => $this->studentA->id,
                'attitude_aspect_id' => $this->discipline->id,
                'score' => 70,
            ]);

        // Nilai semester 2 (aspek yang sama, siswa yang sama)
        $this->actingAs($this->waliA->user)
            ->post(route('wali_kelas.attitude-grades.store'), [
                'semester_id' => $this->semester2->id,
                'student_id' => $this->studentA->id,
                'attitude_aspect_id' => $this->discipline->id,
                'score' => 95,
            ]);

        // Keduanya tersimpan terpisah — semester 1 tidak tertimpa
        $this->assertDatabaseHas('attitude_grades', [
            'student_id' => $this->studentA->id,
            'classroom_id' => $this->classroomA->id,
            'attitude_aspect_id' => $this->discipline->id,
            'semester_id' => $this->semester1->id,
            'score' => 70,
        ]);

        $this->assertDatabaseHas('attitude_grades', [
            'student_id' => $this->studentA->id,
            'classroom_id' => $this->classroomA->id,
            'attitude_aspect_id' => $this->discipline->id,
            'semester_id' => $this->semester2->id,
            'score' => 95,
        ]);
    }

    public function test_wali_kelas_cannot_update_own_grade_with_manipulated_student_id_from_other_classroom(): void
    {
        // Nilai milik wali A untuk siswa A (kelas A)
        $grade = AttitudeGrade::create([
            'student_id' => $this->studentA->id,
            'classroom_id' => $this->classroomA->id,
            'wali_kelas_id' => $this->waliA->id,
            'attitude_aspect_id' => $this->discipline->id,
            'semester_id' => $this->semester2->id,
            'score' => 80,
        ]);

        // Wali A memanipulasi payload: student_id diganti ke siswa kelas B —
        // validasi silang FormRequest menolak sebelum controller body jalan.
        $response = $this->actingAs($this->waliA->user)
            ->put(route('wali_kelas.attitude-grades.update', $grade), [
                'semester_id' => $this->semester2->id,
                'student_id' => $this->studentB->id,
                'attitude_aspect_id' => $this->discipline->id,
                'score' => 50,
            ]);

        $response->assertSessionHasErrors('student_id');

        // Data asli tidak berubah: skor tetap dan student_id tetap milik kelas A
        $this->assertDatabaseHas('attitude_grades', [
            'id' => $grade->id,
            'student_id' => $this->studentA->id,
            'classroom_id' => $this->classroomA->id,
            'score' => 80,
        ]);
    }

    public function test_index_page_shows_grades_only_for_selected_semester_and_own_classroom(): void
    {
        // Nilai siswa A (kelas A) semester 2
        AttitudeGrade::create([
            'student_id' => $this->studentA->id,
            'classroom_id' => $this->classroomA->id,
            'wali_kelas_id' => $this->waliA->id,
            'attitude_aspect_id' => $this->discipline->id,
            'semester_id' => $this->semester2->id,
            'score' => 88,
        ]);

        // Nilai siswa B (kelas B) semester 2 — tidak boleh tampil
        AttitudeGrade::create([
            'student_id' => $this->studentB->id,
            'classroom_id' => $this->classroomB->id,
            'wali_kelas_id' => $this->waliB->id,
            'attitude_aspect_id' => $this->discipline->id,
            'semester_id' => $this->semester2->id,
            'score' => 99,
        ]);

        $response = $this->actingAs($this->waliA->user)
            ->get(route('wali_kelas.attitude-grades.index', ['semester_id' => $this->semester2->id]));

        $response->assertOk()
            ->assertSee($this->studentA->user->name)
            ->assertSee('88')
            ->assertDontSee($this->studentB->user->name);
    }
}

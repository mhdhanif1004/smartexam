<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ExamResult;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\Grade;
use App\Models\GuruMapel;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use App\Models\WaliKelas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaliKelasNilaiAkademikTest extends TestCase
{
    use RefreshDatabase;

    public function test_wali_kelas_can_only_see_academic_grades_of_own_classroom_students(): void
    {
        // Wali Kelas A & Kelas A
        $classroomA = Classroom::factory()->create(['name' => 'Kelas 10A']);
        $waliA = WaliKelas::factory()->create(['classroom_id' => $classroomA->id]);

        // Wali Kelas B & Kelas B
        $classroomB = Classroom::factory()->create(['name' => 'Kelas 10B']);
        $waliB = WaliKelas::factory()->create(['classroom_id' => $classroomB->id]);

        $subject = Subject::factory()->create(['name' => 'Matematika']);
        $guru = GuruMapel::factory()->create();

        // Siswa A di Kelas A dengan nilai
        $studentA = Student::factory()->create(['classroom_id' => $classroomA->id]);
        Grade::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroomA->id,
            'student_id' => $studentA->id,
            'score' => 88.50,
            'is_override' => true,
        ]);

        // Siswa B di Kelas B dengan nilai
        $studentB = Student::factory()->create(['classroom_id' => $classroomB->id]);
        Grade::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroomB->id,
            'student_id' => $studentB->id,
            'score' => 95.00,
            'is_override' => true,
        ]);

        // Wali Kelas A lihat dashboard
        $response = $this->actingAs($waliA->user)
            ->get(route('wali_kelas.dashboard'));

        $response->assertOk()
            ->assertSee($studentA->user->name)
            ->assertSee('88.50')
            ->assertDontSee($studentB->user->name)
            ->assertDontSee('95.00');
    }

    public function test_displays_override_score_when_is_override_is_true(): void
    {
        $classroom = Classroom::factory()->create();
        $wali = WaliKelas::factory()->create(['classroom_id' => $classroom->id]);
        $student = Student::factory()->create(['classroom_id' => $classroom->id]);
        $subject = Subject::factory()->create(['name' => 'Fisika']);
        $guru = GuruMapel::factory()->create();

        // Nilai manual override dari guru = 90.00
        Grade::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'student_id' => $student->id,
            'score' => 90.00,
            'is_override' => true,
            'note' => 'Remedial tugas',
        ]);

        $response = $this->actingAs($wali->user)
            ->get(route('wali_kelas.dashboard'));

        $response->assertOk()
            ->assertSee('Fisika')
            ->assertSee('90.00')
            ->assertSee('Override Guru')
            ->assertSee('Remedial tugas');
    }

    public function test_displays_cbt_score_when_no_override_exists(): void
    {
        $classroom = Classroom::factory()->create();
        $wali = WaliKelas::factory()->create(['classroom_id' => $classroom->id]);
        $student = Student::factory()->create(['classroom_id' => $classroom->id]);
        $subject = Subject::factory()->create(['name' => 'Biologi']);
        $room = Room::factory()->create();

        $schedule = ExamSchedule::factory()->create([
            'subject_id' => $subject->id,
            'room_id' => $room->id,
            'class_name' => $classroom->name,
        ]);

        $session = ExamSession::factory()->create([
            'student_id' => $student->id,
            'exam_schedule_id' => $schedule->id,
            'status' => ExamSession::STATUS_COMPLETED,
        ]);

        ExamResult::create([
            'exam_session_id' => $session->id,
            'total_score' => 78.25,
            'is_passed' => true,
        ]);

        $response = $this->actingAs($wali->user)
            ->get(route('wali_kelas.dashboard'));

        $response->assertOk()
            ->assertSee('Biologi')
            ->assertSee('78.25')
            ->assertSee('CBT Otomatis');
    }

    public function test_student_without_grades_or_exam_results_shows_empty_state_gracefully(): void
    {
        $classroom = Classroom::factory()->create();
        $wali = WaliKelas::factory()->create(['classroom_id' => $classroom->id]);
        // Siswa tanpa nilai/CBT sama sekali
        Student::factory()->create(['classroom_id' => $classroom->id]);

        $response = $this->actingAs($wali->user)
            ->get(route('wali_kelas.dashboard'));

        $response->assertOk()
            ->assertSee('Belum ada data nilai akademik untuk siswa di kelas ini.');
    }
}

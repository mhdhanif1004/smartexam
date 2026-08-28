<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Grade;
use App\Models\GuruMapel;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeacherSubjectClassAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class GuruMapelDashboardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guru mapel yang mengampu satu mapel pada satu kelas, lengkap dengan
     * beberapa siswa di kelas itu.
     *
     * @return array{0: GuruMapel, 1: Subject, 2: Classroom, 3: Collection<int, Student>, 4: User}
     */
    private function makeAmpuGuru(int $studentCount = 2): array
    {
        $guru = GuruMapel::factory()->create();
        $subject = Subject::factory()->create();
        $classroom = Classroom::factory()->create();

        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
        ]);

        $students = Student::factory()->count($studentCount)->create([
            'classroom_id' => $classroom->id,
            'class_name' => $classroom->name,
        ]);

        return [$guru, $subject, $classroom, $students, $guru->user];
    }

    // -----------------------------------------------------------------
    // DASHBOARD
    // -----------------------------------------------------------------

    public function test_dashboard_only_shows_own_assignments_with_student_counts(): void
    {
        [$guruA, $subjectA, $classroomA] = $this->makeAmpuGuru(3);
        [$guruB, $subjectB, $classroomB] = $this->makeAmpuGuru(1);

        $this->actingAs($guruA->user)
            ->get(route('guru_mapel.dashboard'))
            ->assertOk()
            ->assertSee($subjectA->name)
            ->assertSee($classroomA->name)
            ->assertSee('3 siswa')
            ->assertDontSee($subjectB->name)
            ->assertDontSee($classroomB->name);
    }

    public function test_dashboard_shows_only_own_recent_grades(): void
    {
        [$guruA, $subjectA, $classroomA, $studentsA] = $this->makeAmpuGuru(1);
        [$guruB, $subjectB, $classroomB, $studentsB] = $this->makeAmpuGuru(1);

        Grade::create([
            'guru_mapel_id' => $guruA->id,
            'subject_id' => $subjectA->id,
            'classroom_id' => $classroomA->id,
            'student_id' => $studentsA[0]->id,
            'grade_type' => Grade::TYPE_TUGAS,
            'title' => 'Tugas Milik Guru A',
            'score' => '88.00',
        ]);

        Grade::create([
            'guru_mapel_id' => $guruB->id,
            'subject_id' => $subjectB->id,
            'classroom_id' => $classroomB->id,
            'student_id' => $studentsB[0]->id,
            'grade_type' => Grade::TYPE_UTS,
            'title' => 'UTS Milik Guru B',
            'score' => '50.00',
        ]);

        $response = $this->actingAs($guruA->user)
            ->get(route('guru_mapel.dashboard'))
            ->assertOk();

        $response->assertSee($studentsA[0]->user->name);
        $response->assertSee(number_format(88.00, 2));
        $response->assertDontSee($studentsB[0]->user->name);
        $response->assertDontSee(number_format(50.00, 2));
    }

    public function test_dashboard_rejects_non_guru_user(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('guru_mapel.dashboard'))
            ->assertForbidden();
    }

    // -----------------------------------------------------------------
    // RIWAYAT NILAI PER SISWA
    // -----------------------------------------------------------------

    public function test_students_page_lists_only_students_of_ampu_class(): void
    {
        [$guruA, $subjectA, $classroomA, $studentsA] = $this->makeAmpuGuru(2);

        $this->actingAs($guruA->user)
            ->get(route('guru_mapel.grades.students', [
                'subject_id' => $subjectA->id,
                'classroom_id' => $classroomA->id,
            ]))
            ->assertOk()
            ->assertSee($studentsA[0]->user->name)
            ->assertSee($studentsA[1]->user->name);
    }

    public function test_students_page_forbids_non_ampu_subject_class_combo(): void
    {
        [$guruA] = $this->makeAmpuGuru();
        $foreignSubject = Subject::factory()->create();
        $foreignClassroom = Classroom::factory()->create();

        $this->actingAs($guruA->user)
            ->get(route('guru_mapel.grades.students', [
                'subject_id' => $foreignSubject->id,
                'classroom_id' => $foreignClassroom->id,
            ]))
            ->assertOk()
            ->assertSee('Pilih mata pelajaran dan kelas yang valid');
    }

    public function test_student_history_shows_all_grades_for_ampu_student_subject(): void
    {
        [$guruA, $subjectA, $classroomA, $studentsA] = $this->makeAmpuGuru(1);
        $student = $studentsA[0];

        Grade::create([
            'guru_mapel_id' => $guruA->id,
            'subject_id' => $subjectA->id,
            'classroom_id' => $classroomA->id,
            'student_id' => $student->id,
            'grade_type' => Grade::TYPE_TUGAS,
            'title' => 'Tugas 1',
            'score' => '80.00',
        ]);
        Grade::create([
            'guru_mapel_id' => $guruA->id,
            'subject_id' => $subjectA->id,
            'classroom_id' => $classroomA->id,
            'student_id' => $student->id,
            'grade_type' => Grade::TYPE_UTS,
            'title' => 'UTS',
            'score' => '90.00',
        ]);

        $this->actingAs($guruA->user)
            ->get(route('guru_mapel.grades.student-history', [
                'subject_id' => $subjectA->id,
                'classroom_id' => $classroomA->id,
                'student_id' => $student->id,
            ]))
            ->assertOk()
            ->assertSee($student->user->name)
            ->assertSee('Tugas 1')
            ->assertSee('UTS')
            ->assertSee(number_format(80.00, 2))
            ->assertSee(number_format(90.00, 2));
    }

    public function test_student_history_forbids_access_to_non_ampu_subject(): void
    {
        [$guruA, $subjectA, $classroomA, $studentsA] = $this->makeAmpuGuru(1);
        [$guruB, $subjectB, $classroomB, $studentsB] = $this->makeAmpuGuru(1);

        $this->actingAs($guruA->user)
            ->get(route('guru_mapel.grades.student-history', [
                'subject_id' => $subjectB->id,
                'classroom_id' => $classroomB->id,
                'student_id' => $studentsB[0]->id,
            ]))
            ->assertForbidden();
    }

    public function test_student_history_does_not_leak_other_gurus_grades(): void
    {
        [$guruA, $subjectA, $classroomA, $studentsA] = $this->makeAmpuGuru(1);
        [$guruB, $subjectB, $classroomB] = $this->makeAmpuGuru(1);

        Grade::create([
            'guru_mapel_id' => $guruB->id,
            'subject_id' => $subjectB->id,
            'classroom_id' => $classroomB->id,
            'student_id' => $studentsA[0]->id,
            'grade_type' => Grade::TYPE_UAS,
            'title' => 'Rahasia Guru B',
            'score' => '99.00',
        ]);

        $this->actingAs($guruA->user)
            ->get(route('guru_mapel.grades.student-history', [
                'subject_id' => $subjectA->id,
                'classroom_id' => $classroomA->id,
                'student_id' => $studentsA[0]->id,
            ]))
            ->assertOk()
            ->assertDontSee('Rahasia Guru B')
            ->assertDontSee(number_format(99.00, 2));
    }

    public function test_student_history_returns_404_for_student_not_in_ampu_class(): void
    {
        [$guruA, $subjectA, $classroomA] = $this->makeAmpuGuru(1);
        [$guruB, , $classroomB, $studentsB] = $this->makeAmpuGuru(1);

        $this->actingAs($guruA->user)
            ->get(route('guru_mapel.grades.student-history', [
                'subject_id' => $subjectA->id,
                'classroom_id' => $classroomA->id,
                'student_id' => $studentsB[0]->id,
            ]))
            ->assertNotFound();
    }

    // -----------------------------------------------------------------
    // EXPORT NILAI
    // -----------------------------------------------------------------

    public function test_export_excel_returns_file_for_ampu_class(): void
    {
        [$guru, $subject, $classroom, $students] = $this->makeAmpuGuru(1);

        Grade::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'student_id' => $students[0]->id,
            'grade_type' => Grade::TYPE_TUGAS,
            'title' => 'Tugas Export',
            'score' => '78.00',
        ]);

        $this->actingAs($guru->user)
            ->get(route('guru_mapel.grades.export-excel', [
                'subject_id' => $subject->id,
                'classroom_id' => $classroom->id,
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_export_pdf_returns_file_for_ampu_class(): void
    {
        [$guru, $subject, $classroom, $students] = $this->makeAmpuGuru(1);

        Grade::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'student_id' => $students[0]->id,
            'grade_type' => Grade::TYPE_TUGAS,
            'title' => 'Tugas PDF',
            'score' => '85.00',
        ]);

        $this->actingAs($guru->user)
            ->get(route('guru_mapel.grades.export-pdf', [
                'subject_id' => $subject->id,
                'classroom_id' => $classroom->id,
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_export_forbids_other_gurus_class_subject(): void
    {
        [$guruA] = $this->makeAmpuGuru();
        [$guruB, $subjectB, $classroomB, $studentsB] = $this->makeAmpuGuru(1);

        Grade::create([
            'guru_mapel_id' => $guruB->id,
            'subject_id' => $subjectB->id,
            'classroom_id' => $classroomB->id,
            'student_id' => $studentsB[0]->id,
            'grade_type' => Grade::TYPE_UAS,
            'title' => 'Sangat Rahasia',
            'score' => '95.00',
        ]);

        $this->actingAs($guruA->user)
            ->get(route('guru_mapel.grades.export-excel', [
                'subject_id' => $subjectB->id,
                'classroom_id' => $classroomB->id,
            ]))
            ->assertForbidden();

        $this->actingAs($guruA->user)
            ->get(route('guru_mapel.grades.export-pdf', [
                'subject_id' => $subjectB->id,
                'classroom_id' => $classroomB->id,
            ]))
            ->assertForbidden();
    }
}

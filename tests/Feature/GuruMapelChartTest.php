<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Grade;
use App\Models\GuruMapel;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeacherSubjectClassAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuruMapelChartTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: GuruMapel, 1: Subject, 2: Classroom, 3: mixed}
     */
    private function makeAmpuGuru(int $studentCount = 1): array
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

        return [$guru, $subject, $classroom, $students];
    }

    public function test_grades_index_renders_chart_summary_for_ampu_class(): void
    {
        [$guru, $subject, $classroom, $students] = $this->makeAmpuGuru(1);

        Grade::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'student_id' => $students[0]->id,
            'grade_type' => Grade::TYPE_TUGAS,
            'title' => 'Tugas Chart',
            'score' => '80.00',
        ]);

        $this->actingAs($guru->user)
            ->get(route('guru_mapel.grades.index', [
                'subject_id' => $subject->id,
                'classroom_id' => $classroom->id,
            ]))
            ->assertOk()
            ->assertSee('Ringkasan Nilai per Jenis Nilai')
            ->assertSee('chart-grade-summary');
    }
}

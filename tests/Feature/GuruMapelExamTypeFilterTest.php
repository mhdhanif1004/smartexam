<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ExamPeriod;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\ExamType;
use App\Models\GuruMapel;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectGrade;
use App\Models\TeacherSubjectClassAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuruMapelExamTypeFilterTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroom;

    private Classroom $otherClassroom;

    private GuruMapel $guru;

    private Subject $subject;

    private Semester $semester;

    private Student $student;

    private ExamType $harian;

    private ExamType $uts;

    private ExamType $uas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classroom = Classroom::factory()->create(['name' => 'XI RPL 1']);
        $this->otherClassroom = Classroom::factory()->create(['name' => 'XI RPL 2']);

        $this->guru = GuruMapel::factory()->create();
        $this->subject = Subject::factory()->create(['name' => 'Matematika']);

        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $this->guru->id,
            'subject_id' => $this->subject->id,
            'classroom_id' => $this->classroom->id,
        ]);

        $tahunAjaran = AcademicYear::factory()->create(['nama' => '2025/2026']);
        $this->semester = Semester::factory()->ganjil($tahunAjaran)->aktif()->create();

        $this->student = Student::factory()->create(['classroom_id' => $this->classroom->id]);

        $this->harian = ExamType::where('code', 'harian')->first();
        $this->uts = ExamType::where('code', 'uts')->first();
        $this->uas = ExamType::where('code', 'uas')->first();
    }

    private function gradesUrl(?int $examTypeId = null): string
    {
        return route('guru_mapel.grades.index', array_filter([
            'subject_id' => $this->subject->id,
            'classroom_id' => $this->classroom->id,
            'semester_id' => $this->semester->id,
            'jenis_ujian' => $examTypeId,
        ], fn ($v) => $v !== null));
    }

    // ── Rekap Nilai: filter jenis ujian ──────────────────────────────

    public function test_rekap_filter_harian_shows_only_harian_data(): void
    {
        SubjectGrade::create([
            'student_id' => $this->student->id,
            'classroom_id' => $this->classroom->id,
            'subject_id' => $this->subject->id,
            'guru_mapel_id' => $this->guru->id,
            'semester_id' => $this->semester->id,
            'exam_type_id' => $this->harian->id,
            'title' => 'UH 1',
            'score' => 91.11,
            'source' => SubjectGrade::SOURCE_MANUAL,
        ]);
        SubjectGrade::create([
            'student_id' => $this->student->id,
            'classroom_id' => $this->classroom->id,
            'subject_id' => $this->subject->id,
            'guru_mapel_id' => $this->guru->id,
            'semester_id' => $this->semester->id,
            'exam_type_id' => $this->uts->id,
            'title' => 'UTS',
            'score' => 63.37,
            'source' => SubjectGrade::SOURCE_MANUAL,
        ]);

        $this->actingAs($this->guru->user)
            ->get($this->gradesUrl($this->harian->id))
            ->assertOk()
            ->assertSee('UH 1')
            ->assertSee('91.11')
            ->assertDontSee('63.37');
    }

    public function test_rekap_filter_uts_shows_only_uts_data(): void
    {
        SubjectGrade::create([
            'student_id' => $this->student->id,
            'classroom_id' => $this->classroom->id,
            'subject_id' => $this->subject->id,
            'guru_mapel_id' => $this->guru->id,
            'semester_id' => $this->semester->id,
            'exam_type_id' => $this->harian->id,
            'title' => 'UH 1',
            'score' => 91.11,
            'source' => SubjectGrade::SOURCE_MANUAL,
        ]);
        SubjectGrade::create([
            'student_id' => $this->student->id,
            'classroom_id' => $this->classroom->id,
            'subject_id' => $this->subject->id,
            'guru_mapel_id' => $this->guru->id,
            'semester_id' => $this->semester->id,
            'exam_type_id' => $this->uts->id,
            'title' => 'UTS',
            'score' => 63.37,
            'source' => SubjectGrade::SOURCE_MANUAL,
        ]);

        $this->actingAs($this->guru->user)
            ->get($this->gradesUrl($this->uts->id))
            ->assertOk()
            ->assertSee('63.37')
            ->assertDontSee('91.11')
            ->assertDontSee('UH 1');
    }

    public function test_rekap_no_filter_shows_all_types(): void
    {
        SubjectGrade::create([
            'student_id' => $this->student->id,
            'classroom_id' => $this->classroom->id,
            'subject_id' => $this->subject->id,
            'guru_mapel_id' => $this->guru->id,
            'semester_id' => $this->semester->id,
            'exam_type_id' => $this->harian->id,
            'title' => 'UH 1',
            'score' => 91.11,
            'source' => SubjectGrade::SOURCE_MANUAL,
        ]);
        SubjectGrade::create([
            'student_id' => $this->student->id,
            'classroom_id' => $this->classroom->id,
            'subject_id' => $this->subject->id,
            'guru_mapel_id' => $this->guru->id,
            'semester_id' => $this->semester->id,
            'exam_type_id' => $this->uts->id,
            'title' => 'UTS',
            'score' => 63.37,
            'source' => SubjectGrade::SOURCE_MANUAL,
        ]);

        $this->actingAs($this->guru->user)
            ->get($this->gradesUrl(null))
            ->assertOk()
            ->assertSee('91.11')
            ->assertSee('63.37');
    }

    public function test_rekap_filter_respects_assignment_scoping(): void
    {
        // Siswa di kelas LAIN (bukan ampu) dengan nilai — tidak boleh tampil.
        $foreignStudent = Student::factory()->create(['classroom_id' => $this->otherClassroom->id]);
        SubjectGrade::create([
            'student_id' => $foreignStudent->id,
            'classroom_id' => $this->otherClassroom->id,
            'subject_id' => $this->subject->id,
            'guru_mapel_id' => $this->guru->id,
            'semester_id' => $this->semester->id,
            'exam_type_id' => $this->harian->id,
            'title' => 'UH 99',
            'score' => 88.88,
            'source' => SubjectGrade::SOURCE_MANUAL,
        ]);

        $this->actingAs($this->guru->user)
            ->get($this->gradesUrl($this->harian->id))
            ->assertOk()
            ->assertDontSee('UH 99')
            ->assertDontSee('88.88');
    }

    // ── Absensi Ujian: filter jenis ujian ────────────────────────────

    private function makeAttendanceSchedule(ExamType $type, string $date): ExamSchedule
    {
        $period = ExamPeriod::create([
            'name' => 'P '.$type->name,
            'name_prefix' => 'P '.$type->name,
            'exam_type_id' => $type->id,
            'grade_level' => 'XI',
            'exam_date' => $date,
            'start_time' => '08:00:00',
            'end_time' => '09:30:00',
        ]);

        $schedule = ExamSchedule::create([
            'subject_id' => $this->subject->id,
            'room_id' => null,
            'classroom_id' => $this->classroom->id,
            'exam_period_id' => $period->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => $date,
            'start_time' => '08:00:00',
            'end_time' => '09:30:00',
            'duration_minutes' => 90,
            'status' => ExamSchedule::STATUS_SCHEDULED,
        ]);

        ExamSession::create([
            'student_id' => $this->student->id,
            'exam_schedule_id' => $schedule->id,
            'status' => ExamSession::STATUS_NOT_STARTED,
            'attendance_confirmed' => true,
            'attendance_status' => ExamSession::ATTENDANCE_PRESENT,
        ]);

        return $schedule;
    }

    private function attendanceUrl(?int $examTypeId = null): string
    {
        return route('guru_mapel.attendances.index', array_filter([
            'subject_id' => $this->subject->id,
            'classroom_id' => $this->classroom->id,
            'jenis_ujian' => $examTypeId,
        ], fn ($v) => $v !== null));
    }

    public function test_attendance_filter_harian_shows_only_harian_schedule(): void
    {
        $harianSchedule = $this->makeAttendanceSchedule($this->harian, '2026-12-01');
        $uasSchedule = $this->makeAttendanceSchedule($this->uas, '2026-12-05');

        $this->actingAs($this->guru->user)
            ->get($this->attendanceUrl($this->harian->id))
            ->assertOk()
            ->assertSee('01 Dec 2026')
            ->assertDontSee('05 Dec 2026');

        // Tanpa filter → keduanya tampil.
        $this->actingAs($this->guru->user)
            ->get($this->attendanceUrl(null))
            ->assertOk()
            ->assertSee('01 Dec 2026')
            ->assertSee('05 Dec 2026');
    }

    public function test_attendance_filter_still_read_only_and_scoped(): void
    {
        $this->makeAttendanceSchedule($this->harian, '2026-12-01');

        // Siswa kelas lain — jadwalnya tidak muncul utk guru ini.
        $foreignStudent = Student::factory()->create(['classroom_id' => $this->otherClassroom->id]);
        $foreignPeriod = ExamPeriod::create([
            'name' => 'P lain',
            'name_prefix' => 'P lain',
            'exam_type_id' => $this->harian->id,
            'grade_level' => 'XI',
            'exam_date' => '2026-12-09',
            'start_time' => '08:00:00',
            'end_time' => '09:30:00',
        ]);
        $foreignSchedule = ExamSchedule::create([
            'subject_id' => $this->subject->id,
            'room_id' => null,
            'classroom_id' => $this->otherClassroom->id,
            'exam_period_id' => $foreignPeriod->id,
            'class_name' => 'XI RPL 2',
            'exam_date' => '2026-12-09',
            'start_time' => '08:00:00',
            'end_time' => '09:30:00',
            'duration_minutes' => 90,
            'status' => ExamSchedule::STATUS_SCHEDULED,
        ]);
        ExamSession::create([
            'student_id' => $foreignStudent->id,
            'exam_schedule_id' => $foreignSchedule->id,
            'status' => ExamSession::STATUS_NOT_STARTED,
        ]);

        $this->actingAs($this->guru->user)
            ->get($this->attendanceUrl($this->harian->id))
            ->assertOk()
            ->assertSee('01 Dec 2026')
            ->assertDontSee('09 Dec 2026');
    }
}

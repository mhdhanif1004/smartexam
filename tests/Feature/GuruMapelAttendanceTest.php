<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\GuruMapel;
use App\Models\Question;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeacherSubjectClassAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class GuruMapelAttendanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guru yang mengampu satu mapel di satu kelas. Default sudah punya
     * cakupan kelas (soal miliknya menargetkan kelas) karena akses absensi
     * kini diturunkan dari soal yang dibuat guru.
     *
     * @return array{0: GuruMapel, 1: Subject, 2: Classroom, 3: mixed}
     */
    private function makeAmpuGuru(int $studentCount = 1, bool $withScope = true): array
    {
        $guru = GuruMapel::factory()->create();
        $subject = Subject::factory()->create();
        $classroom = Classroom::factory()->create();

        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
        ]);

        $students = Student::factory()->count($studentCount)->create([
            'classroom_id' => $classroom->id,
            'class_name' => $classroom->name,
        ]);

        if ($withScope) {
            $question = Question::query()->create([
                'subject_id' => $subject->id,
                'type' => Question::TYPE_ESSAY,
                'question_text' => 'Soal pembuka cakupan kelas',
                'score_weight' => 10,
                'created_by_user_id' => $guru->user_id,
            ]);
            $question->classrooms()->attach($classroom->id);
        }

        return [$guru, $subject, $classroom, $students];
    }

    /**
     * Guru ampu + jadwal ujian dengan sesi yang sudah punya status absensi.
     *
     * @return array{0: GuruMapel, 1: Subject, 2: Classroom, 3: mixed, 4: ExamSchedule, 5: mixed}
     */
    private function makeScheduleWithAttendance(int $studentCount = 1): array
    {
        [$guru, $subject, $classroom, $students] = $this->makeAmpuGuru($studentCount);

        $room = Room::factory()->create();

        $schedule = ExamSchedule::factory()->create([
            'subject_id' => $subject->id,
            'room_id' => $room->id,
            'class_name' => $classroom->name,
            'exam_date' => now()->toDateString(),
        ]);

        Student::query()->whereKey($students->pluck('id'))->update(['room_id' => $room->id]);

        $sessions = collect();
        foreach ($students as $student) {
            $sessions->push(ExamSession::factory()->create([
                'student_id' => $student->id,
                'exam_schedule_id' => $schedule->id,
                'status' => ExamSession::STATUS_COMPLETED,
                'attendance_status' => ExamSession::ATTENDANCE_PRESENT,
                'attendance_confirmed' => true,
            ]));
        }

        return [$guru, $subject, $classroom, $students, $schedule, $sessions];
    }

    public function test_attendance_shows_present_and_absent_per_student(): void
    {
        [$guru, , , , $schedule, $sessions] = $this->makeScheduleWithAttendance(2);

        $sessions[1]->update([
            'attendance_status' => ExamSession::ATTENDANCE_ABSENT,
            'attendance_confirmed' => true,
        ]);

        $response = $this->actingAs($guru->user)
            ->get(route('guru_mapel.attendances.schedule', $schedule->id))
            ->assertOk();

        // Present badge, absent badge, dan summary card hadir/tidak-hadir.
        $response->assertSee('Hadir')
            ->assertSee('Tidak Hadir');
    }

    public function test_attendance_index_lists_only_ampu_schedules(): void
    {
        [$guruA, $subjectA, $classroomA, , $scheduleA] = $this->makeScheduleWithAttendance(1);

        $otherSubject = Subject::factory()->create();
        $room = Room::factory()->create();
        $otherSchedule = ExamSchedule::factory()->create([
            'subject_id' => $otherSubject->id,
            'room_id' => $room->id,
            'class_name' => $classroomA->name,
            'exam_date' => now()->toDateString(),
        ]);
        $foreignStudent = Student::factory()->create(['class_name' => $classroomA->name, 'classroom_id' => $classroomA->id]);
        Student::query()->whereKey($foreignStudent->id)->update(['room_id' => $room->id]);
        ExamSession::factory()->create([
            'student_id' => $foreignStudent->id,
            'exam_schedule_id' => $otherSchedule->id,
            'status' => ExamSession::STATUS_COMPLETED,
            'attendance_status' => ExamSession::ATTENDANCE_ABSENT,
        ]);

        $response = $this->actingAs($guruA->user)
            ->get(route('guru_mapel.attendances.index', [
                'subject_id' => $subjectA->id,
                'classroom_id' => $classroomA->id,
            ]))
            ->assertOk();

        $response->assertSee(route('guru_mapel.attendances.schedule', $scheduleA->id));
        $response->assertDontSee(route('guru_mapel.attendances.schedule', $otherSchedule->id));
    }

    public function test_attendance_schedule_forbids_other_gurus_schedule(): void
    {
        [$guruA] = $this->makeAmpuGuru();
        [, , , , $scheduleB] = $this->makeScheduleWithAttendance(1);

        $this->actingAs($guruA->user)
            ->get(route('guru_mapel.attendances.schedule', $scheduleB->id))
            ->assertForbidden();
    }

    public function test_attendance_index_shows_empty_state_for_non_ampu_combo(): void
    {
        [$guruA] = $this->makeAmpuGuru();
        $foreignSubject = Subject::factory()->create();
        $foreignClassroom = Classroom::factory()->create();

        $this->actingAs($guruA->user)
            ->get(route('guru_mapel.attendances.index', [
                'subject_id' => $foreignSubject->id,
                'classroom_id' => $foreignClassroom->id,
            ]))
            ->assertOk()
            ->assertSee('Pilih mata pelajaran dan kelas yang valid');
    }

    public function test_attendance_routes_are_read_only(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (str_starts_with((string) $route->uri(), 'guru_mapel/attendances')) {
                $this->assertContains(
                    $route->methods()[0],
                    ['GET', 'HEAD'],
                    'Endpoint absensi guru seharusnya hanya-baca, tapi '.$route->uri().' membuka '.implode(',', $route->methods())
                );
            }
        }
    }

    public function test_attendance_schedule_forbids_non_participant(): void
    {
        [$guru, , , $students, $schedule] = $this->makeScheduleWithAttendance(1);

        $outsider = Student::factory()->create();

        // Siswa bukan peserta jadwal ini memang dibatasi, dan halaman ini
        // hanya menampilkan peserta jadwal (di luar mereka tidak muncul).
        $response = $this->actingAs($guru->user)
            ->get(route('guru_mapel.attendances.schedule', $schedule->id))
            ->assertOk();

        $response->assertSee($students[0]->user->name)
            ->assertDontSee($outsider->user->name);
    }
}

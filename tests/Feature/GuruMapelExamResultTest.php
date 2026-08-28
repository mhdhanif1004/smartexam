<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ExamAnswer;
use App\Models\ExamResult;
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

class GuruMapelExamResultTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guru yang mengampu satu mapel di satu kelas.
     *
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

    /**
     * Guru ampu + jadwal ujian dengan peserta (sesi selesai) dan hasil.
     *
     * @return array{0: GuruMapel, 1: Subject, 2: Classroom, 3: mixed, 4: ExamSchedule, 5: mixed}
     */
    private function makeScheduleWithSessions(int $studentCount = 1): array
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
            $session = ExamSession::factory()->create([
                'student_id' => $student->id,
                'exam_schedule_id' => $schedule->id,
                'status' => ExamSession::STATUS_COMPLETED,
                'attendance_status' => ExamSession::ATTENDANCE_PRESENT,
                'attendance_confirmed' => true,
                'finished_at' => now(),
            ]);

            ExamResult::factory()->create([
                'exam_session_id' => $session->id,
                'total_score' => '85.00',
                'is_passed' => true,
            ]);

            $sessions->push($session);
        }

        return [$guru, $subject, $classroom, $students, $schedule, $sessions];
    }

    public function test_index_lists_only_schedules_of_ampu_subject_and_class(): void
    {
        [$guruA, $subjectA, $classroomA, , $scheduleA] = $this->makeScheduleWithSessions(1);

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
        ]);

        $response = $this->actingAs($guruA->user)
            ->get(route('guru_mapel.exam-results.index', [
                'subject_id' => $subjectA->id,
                'classroom_id' => $classroomA->id,
            ]))
            ->assertOk();

        $response->assertSee(route('guru_mapel.exam-results.schedule', $scheduleA->id));
        $response->assertDontSee(route('guru_mapel.exam-results.schedule', $otherSchedule->id));
    }

    public function test_index_shows_empty_state_for_non_ampu_combo(): void
    {
        [$guruA] = $this->makeAmpuGuru();
        $foreignSubject = Subject::factory()->create();
        $foreignClassroom = Classroom::factory()->create();

        $this->actingAs($guruA->user)
            ->get(route('guru_mapel.exam-results.index', [
                'subject_id' => $foreignSubject->id,
                'classroom_id' => $foreignClassroom->id,
            ]))
            ->assertOk()
            ->assertSee('Pilih mata pelajaran dan kelas yang valid');
    }

    public function test_schedule_page_lists_participants_with_scores(): void
    {
        [$guruA, , , $students, $schedule] = $this->makeScheduleWithSessions(1);

        $this->actingAs($guruA->user)
            ->get(route('guru_mapel.exam-results.schedule', $schedule->id))
            ->assertOk()
            ->assertSee($students[0]->user->name)
            ->assertSee(number_format(85.00, 2))
            ->assertSee('Detail Jawaban');
    }

    public function test_schedule_forbids_other_gurus_schedule(): void
    {
        [$guruA] = $this->makeAmpuGuru();
        [, , , , $scheduleB] = $this->makeScheduleWithSessions(1);

        $this->actingAs($guruA->user)
            ->get(route('guru_mapel.exam-results.schedule', $scheduleB->id))
            ->assertForbidden();
    }

    public function test_student_page_shows_per_question_answers(): void
    {
        [$guru, $subject, $classroom, $students, $schedule, $sessions] = $this->makeScheduleWithSessions(1);
        $session = $sessions->first();

        $question = Question::factory()->create([
            'subject_id' => $subject->id,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'options' => ['A' => 'Pilihan A', 'B' => 'Pilihan B', 'C' => 'Pilihan C', 'D' => 'Pilihan D'],
            'answer_key' => 'A',
            'score_weight' => '10.00',
        ]);
        $question->classrooms()->attach($classroom->id);

        ExamAnswer::create([
            'exam_session_id' => $session->id,
            'question_id' => $question->id,
            'student_answer' => 'A',
            'is_correct' => true,
            'score' => '10.00',
        ]);

        $this->actingAs($guru->user)
            ->get(route('guru_mapel.exam-results.student', [$schedule->id, $students[0]->id]))
            ->assertOk()
            ->assertSee($question->question_text)
            ->assertSee('Benar')
            ->assertSee(number_format(85.00, 2));
    }

    public function test_student_page_marks_unanswered_question(): void
    {
        [$guru, $subject, $classroom, $students, $schedule, $sessions] = $this->makeScheduleWithSessions(1);

        $question = Question::factory()->create(['subject_id' => $subject->id, 'type' => Question::TYPE_SINGLE_CHOICE]);
        $question->classrooms()->attach($classroom->id);

        $this->actingAs($guru->user)
            ->get(route('guru_mapel.exam-results.student', [$schedule->id, $students[0]->id]))
            ->assertOk()
            ->assertSee('Tidak dijawab');
    }

    public function test_student_forbids_other_gurus_schedule(): void
    {
        [, , , $studentsB, $scheduleB] = $this->makeScheduleWithSessions(1);
        [$guruA] = $this->makeAmpuGuru();

        $this->actingAs($guruA->user)
            ->get(route('guru_mapel.exam-results.student', [$scheduleB->id, $studentsB[0]->id]))
            ->assertForbidden();
    }

    public function test_student_forbids_non_participant_student(): void
    {
        [$guru, , , , $schedule] = $this->makeScheduleWithSessions(1);

        $outsider = Student::factory()->create();

        $this->actingAs($guru->user)
            ->get(route('guru_mapel.exam-results.student', [$schedule->id, $outsider->id]))
            ->assertForbidden();
    }

    public function test_exam_results_routes_are_read_only(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (str_starts_with((string) $route->uri(), 'guru_mapel/exam-results')) {
                $this->assertContains(
                    $route->methods()[0],
                    ['GET', 'HEAD'],
                    'Endpoint hasil ujian guru seharusnya hanya-baca, tapi '.$route->uri().' membuka '.implode(',', $route->methods())
                );
            }
        }
    }
}

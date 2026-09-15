<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ExamPeriod;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\ExamType;
use App\Models\Question;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminExamScheduleEditLockTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Subject $subjectA;

    private Subject $subjectB;

    private Room $roomA;

    private Room $roomB;

    private Classroom $classroom;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->subjectA = Subject::factory()->create(['name' => 'Matematika']);
        $this->subjectB = Subject::factory()->create(['name' => 'Fisika']);
        $this->roomA = Room::factory()->create();
        $this->roomB = Room::factory()->create();
        $this->classroom = Classroom::factory()->create(['name' => 'XI RPL 1']);
        $this->student = Student::factory()->create(['classroom_id' => $this->classroom->id]);
    }

    private function makeRoomSchedule(): ExamSchedule
    {
        $period = ExamPeriod::create([
            'name' => 'Sesi UTS',
            'name_prefix' => 'Sesi UTS',
            'exam_type_id' => ExamType::where('code', 'uts')->first()->id,
            'grade_level' => 'XI',
            'exam_date' => '2026-11-10',
            'start_time' => '08:00:00',
            'end_time' => '09:30:00',
        ]);

        return ExamSchedule::create([
            'subject_id' => $this->subjectA->id,
            'room_id' => $this->roomA->id,
            'classroom_id' => null,
            'exam_period_id' => $period->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => '2026-11-10',
            'start_time' => '08:00:00',
            'end_time' => '09:30:00',
            'duration_minutes' => 90,
            'status' => ExamSchedule::STATUS_SCHEDULED,
        ]);
    }

    private function updatePayload(ExamSchedule $schedule, array $overrides = []): array
    {
        return array_merge([
            'subject_id' => $schedule->subject_id,
            'room_id' => $schedule->room_id,
            'class_name' => $schedule->class_name,
            'exam_date' => $schedule->exam_date->format('Y-m-d'),
            'start_time' => substr((string) $schedule->start_time, 0, 5),
            'duration_minutes' => $schedule->duration_minutes,
            'status' => $schedule->status,
        ], $overrides);
    }

    public function test_structural_fields_locked_when_session_started(): void
    {
        $schedule = $this->makeRoomSchedule();

        ExamSession::create([
            'student_id' => $this->student->id,
            'exam_schedule_id' => $schedule->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'started_at' => now(),
            'attendance_confirmed' => true,
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.exam-schedules.update', $schedule), $this->updatePayload($schedule, [
                'subject_id' => $this->subjectB->id,
                'room_id' => $this->roomB->id,
                'class_name' => 'XI RPL 2',
            ]))
            ->assertSessionHasErrors('subject_id');

        $this->assertDatabaseHas('exam_schedules', [
            'id' => $schedule->id,
            'subject_id' => $this->subjectA->id,
            'room_id' => $this->roomA->id,
            'class_name' => 'XI RPL 1',
        ]);
    }

    public function test_time_fields_still_editable_when_session_started(): void
    {
        $schedule = $this->makeRoomSchedule();

        ExamSession::create([
            'student_id' => $this->student->id,
            'exam_schedule_id' => $schedule->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'started_at' => now(),
            'attendance_confirmed' => true,
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.exam-schedules.update', $schedule), $this->updatePayload($schedule, [
                'start_time' => '13:00',
                'duration_minutes' => 60,
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('exam_schedules', [
            'id' => $schedule->id,
            'start_time' => '13:00:00',
            'duration_minutes' => 60,
            'subject_id' => $this->subjectA->id,
        ]);
    }

    public function test_all_fields_free_when_not_started(): void
    {
        $schedule = $this->makeRoomSchedule();

        // Mapel baru harus punya soal aktif + bobot total 100 (validasi existing).
        $question = Question::factory()->create([
            'subject_id' => $this->subjectB->id,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'question_text' => 'Soal fisika',
            'options' => ['A' => 'Satu', 'B' => 'Dua', 'C' => 'Tiga', 'D' => 'Empat'],
            'answer_key' => 'B',
            'score_weight' => 100,
            'is_active' => true,
        ]);
        $question->classrooms()->attach($this->classroom->id);

        $this->actingAs($this->admin)
            ->put(route('admin.exam-schedules.update', $schedule), $this->updatePayload($schedule, [
                'subject_id' => $this->subjectB->id,
                'room_id' => $this->roomB->id,
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('exam_schedules', [
            'id' => $schedule->id,
            'subject_id' => $this->subjectB->id,
            'room_id' => $this->roomB->id,
        ]);
    }
}

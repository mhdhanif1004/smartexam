<?php

namespace Tests\Feature;

use App\Models\ExamPeriod;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Supervisor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AttendancePropagationTest extends TestCase
{
    use RefreshDatabase;

    private User $pengawas;

    private Room $room;

    private ExamPeriod $period;

    private ExamSchedule $scheduleA;

    private ExamSchedule $scheduleB;

    private ExamSchedule $scheduleC;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-24 08:05:00'));

        $this->room = Room::factory()->create(['room_number' => 1]);
        $supervisor = Supervisor::factory()->create(['room_id' => $this->room->id]);
        $this->pengawas = $supervisor->user;

        $this->period = ExamPeriod::factory()->create([
            'name' => 'Sesi 1',
            'exam_date' => '2026-08-24',
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
        ]);

        DB::table('supervisor_room_assignments')->insert([
            'exam_period_id' => $this->period->id,
            'exam_date' => now()->toDateString(),
            'supervisor_id' => $supervisor->id,
            'room_id' => $this->room->id,
            'rotation_index' => 1,
        ]);

        $this->scheduleA = $this->mapelSchedule('Matematika', '08:00:00', '09:15:00', 75, ExamSchedule::STATUS_ONGOING);
        $this->scheduleB = $this->mapelSchedule('Bahasa Indonesia', '09:30:00', '10:30:00', 60);
        $this->scheduleC = $this->mapelSchedule('Bahasa Inggris', '10:30:00', '11:30:00', 60);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_confirm_present_propagates_to_other_schedules_in_same_period(): void
    {
        $student = $this->createStudent();

        $this->confirmAttendance($this->scheduleA, $student->id, true)
            ->assertOk()
            ->assertJson(['ok' => true]);

        $sessionA = $this->sessionFor($student->id, $this->scheduleA->id);
        $sessionB = $this->sessionFor($student->id, $this->scheduleB->id);
        $sessionC = $this->sessionFor($student->id, $this->scheduleC->id);

        $this->assertTrue($sessionA->attendance_confirmed);
        $this->assertSame(ExamSession::ATTENDANCE_PRESENT, $sessionA->attendance_status);

        $this->assertTrue($sessionB->attendance_confirmed);
        $this->assertSame(ExamSession::ATTENDANCE_PRESENT, $sessionB->attendance_status);

        $this->assertTrue($sessionC->attendance_confirmed);
        $this->assertSame(ExamSession::ATTENDANCE_PRESENT, $sessionC->attendance_status);

        $scheduleIds = [$sessionA->exam_schedule_id, $sessionB->exam_schedule_id, $sessionC->exam_schedule_id];
        $this->assertCount(3, array_unique($scheduleIds));
    }

    public function test_confirm_absent_does_not_propagate(): void
    {
        $student = $this->createStudent();

        $this->confirmAttendance($this->scheduleA, $student->id, false)
            ->assertOk()
            ->assertJson(['ok' => true]);

        $sessionA = $this->sessionFor($student->id, $this->scheduleA->id);
        $this->assertFalse($sessionA->attendance_confirmed);
        $this->assertSame(ExamSession::ATTENDANCE_ABSENT, $sessionA->attendance_status);

        $propagatedSessions = ExamSession::query()
            ->where('student_id', $student->id)
            ->whereIn('exam_schedule_id', [$this->scheduleB->id, $this->scheduleC->id])
            ->get();

        $this->assertCount(0, $propagatedSessions);
    }

    public function test_absent_change_does_not_affect_previous_schedules(): void
    {
        $student = $this->createStudent();

        $this->confirmAttendance($this->scheduleA, $student->id, true)->assertOk();

        Carbon::setTestNow(Carbon::parse('2026-08-24 10:40:00'));

        $this->confirmAttendance($this->scheduleC, $student->id, false)->assertOk();

        $this->assertDatabaseHas('exam_sessions', [
            'student_id' => $student->id,
            'exam_schedule_id' => $this->scheduleC->id,
            'attendance_confirmed' => false,
            'attendance_status' => ExamSession::ATTENDANCE_ABSENT,
        ]);

        $this->assertDatabaseHas('exam_sessions', [
            'student_id' => $student->id,
            'exam_schedule_id' => $this->scheduleA->id,
            'attendance_confirmed' => true,
            'attendance_status' => ExamSession::ATTENDANCE_PRESENT,
        ]);

        $this->assertDatabaseHas('exam_sessions', [
            'student_id' => $student->id,
            'exam_schedule_id' => $this->scheduleB->id,
            'attendance_confirmed' => true,
            'attendance_status' => ExamSession::ATTENDANCE_PRESENT,
        ]);
    }

    public function test_separate_rows_per_schedule(): void
    {
        $student = $this->createStudent();

        $this->confirmAttendance($this->scheduleA, $student->id, true)->assertOk();

        $rows = ExamSession::query()
            ->where('student_id', $student->id)
            ->whereIn('exam_schedule_id', $this->period->schedules()->pluck('id'))
            ->get();

        $this->assertCount(3, $rows);
        $this->assertCount(3, $rows->pluck('exam_schedule_id')->unique());
        $this->assertEqualsCanonicalizing(
            [$this->scheduleA->id, $this->scheduleB->id, $this->scheduleC->id],
            $rows->pluck('exam_schedule_id')->all(),
        );
    }

    public function test_propagation_does_not_cross_period_boundaries(): void
    {
        $student = $this->createStudent();

        $periodTwo = ExamPeriod::factory()->create([
            'name' => 'Sesi 2',
            'exam_date' => '2026-08-24',
            'start_time' => '13:00:00',
            'end_time' => '17:00:00',
        ]);

        DB::table('exam_room_assignments')->insert([
            'exam_period_id' => $periodTwo->id,
            'student_id' => $student->id,
            'room_id' => $this->room->id,
            'seat_number' => $student->id,
        ]);

        $scheduleD = ExamSchedule::factory()->create([
            'subject_id' => Subject::factory()->create(['name' => 'Informatika'])->id,
            'room_id' => $this->room->id,
            'exam_period_id' => $periodTwo->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => '2026-08-24',
            'start_time' => '13:00:00',
            'end_time' => '14:15:00',
            'duration_minutes' => 75,
            'status' => ExamSchedule::STATUS_SCHEDULED,
        ]);

        $this->confirmAttendance($this->scheduleA, $student->id, true)->assertOk();

        $this->assertDatabaseHas('exam_sessions', [
            'student_id' => $student->id,
            'exam_schedule_id' => $this->scheduleA->id,
            'attendance_confirmed' => true,
            'attendance_status' => ExamSession::ATTENDANCE_PRESENT,
        ]);

        $this->assertDatabaseHas('exam_sessions', [
            'student_id' => $student->id,
            'exam_schedule_id' => $this->scheduleB->id,
            'attendance_confirmed' => true,
            'attendance_status' => ExamSession::ATTENDANCE_PRESENT,
        ]);

        $this->assertDatabaseMissing('exam_sessions', [
            'student_id' => $student->id,
            'exam_schedule_id' => $scheduleD->id,
        ]);
    }

    private function createStudent(): Student
    {
        $student = Student::factory()->create([
            'class_name' => 'XI RPL 1',
            'room_id' => $this->room->id,
        ]);

        DB::table('exam_room_assignments')->insert([
            'exam_period_id' => $this->period->id,
            'student_id' => $student->id,
            'room_id' => $this->room->id,
            'seat_number' => $student->id,
        ]);

        return $student;
    }

    private function mapelSchedule(string $subjectName, string $startTime, string $endTime, int $durationMinutes, string $status = ExamSchedule::STATUS_SCHEDULED): ExamSchedule
    {
        return ExamSchedule::factory()->create([
            'subject_id' => Subject::factory()->create(['name' => $subjectName])->id,
            'room_id' => $this->room->id,
            'exam_period_id' => $this->period->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => '2026-08-24',
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration_minutes' => $durationMinutes,
            'status' => $status,
        ]);
    }

    private function confirmAttendance(ExamSchedule $schedule, int $studentId, bool $confirmed): TestResponse
    {
        return $this->actingAs($this->pengawas)
            ->patchJson(route('pengawas.attendance.confirm', $schedule->id), [
                'student_id' => $studentId,
                'confirmed' => $confirmed,
            ]);
    }

    private function sessionFor(int $studentId, int $scheduleId): ExamSession
    {
        return ExamSession::query()
            ->where('student_id', $studentId)
            ->where('exam_schedule_id', $scheduleId)
            ->firstOrFail();
    }
}

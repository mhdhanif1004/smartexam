<?php

namespace Tests\Feature;

use App\Models\ExamPeriod;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Supervisor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConsolidatedAttendancePropagationTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    private Supervisor $supervisor;

    private ExamPeriod $period;

    private ExamSchedule $scheduleA;

    private ExamSchedule $scheduleB;

    private ExamSchedule $scheduleC;

    protected function setUp(): void
    {
        parent::setUp();

        // 08:05 — only schedule A's window is open (08:00-09:15+tolerance)
        Carbon::setTestNow(Carbon::parse('2026-08-24 08:05:00'));

        $this->room = Room::factory()->create();
        $this->supervisor = Supervisor::factory()->create(['room_id' => $this->room->id]);

        $this->period = ExamPeriod::create([
            'name' => 'Sesi 1',
            'exam_date' => '2026-08-24',
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
        ]);

        DB::table('supervisor_room_assignments')->insert([
            'exam_period_id' => $this->period->id,
            'exam_date' => '2026-08-24',
            'supervisor_id' => $this->supervisor->id,
            'room_id' => $this->room->id,
            'rotation_index' => 1,
        ]);

        $this->scheduleA = $this->createSchedule('Matematika', '08:00:00', '09:15:00', 75);
        $this->scheduleB = $this->createSchedule('Bahasa Indonesia', '09:30:00', '10:30:00', 60);
        $this->scheduleC = $this->createSchedule('Bahasa Inggris', '10:30:00', '11:30:00', 60);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Confirm at session start (before B & C begin) propagates to ALL
     * schedules in the period, including those still SCHEDULED.
     */
    public function test_confirm_at_session_start_propagates_to_all_schedules_including_future(): void
    {
        $student = $this->createStudent();

        $this->actingAs($this->supervisor->user)
            ->patchJson(route('pengawas.attendance.confirm', $this->scheduleA->id), [
                'student_id' => $student->id,
                'confirmed' => true,
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertTrue($this->isConfirmed($student->id, $this->scheduleA->id));
        $this->assertTrue($this->isConfirmed($student->id, $this->scheduleB->id));
        $this->assertTrue($this->isConfirmed($student->id, $this->scheduleC->id));

        $sessions = ExamSession::where('student_id', $student->id)
            ->whereIn('exam_schedule_id', [
                $this->scheduleA->id,
                $this->scheduleB->id,
                $this->scheduleC->id,
            ])
            ->get();

        $this->assertCount(3, $sessions);
        $this->assertTrue($sessions->every('attendance_confirmed'));
    }

    /**
     * Consolidated attendance page shows students from ALL schedules
     * in the period, not just the currently active one.
     */
    public function test_consolidated_page_lists_students_from_all_schedules(): void
    {
        $student = $this->createStudent();

        $response = $this->actingAs($this->supervisor->user)
            ->get(route('pengawas.attendance.index'));

        $response->assertOk();
        $response->assertSee($student->user->name);
        $response->assertDontSee('Pilih Mata Pelajaran');
    }

    /**
     * Anchor schedule is always the first by start_time.
     */
    public function test_anchor_schedule_is_earliest_by_start_time(): void
    {
        $this->createStudent();

        $response = $this->actingAs($this->supervisor->user)
            ->get(route('pengawas.attendance.index'));

        $response->assertOk();
        $response->assertSee(route('pengawas.attendance.confirm', $this->scheduleA->id));
    }

    /**
     * Absent change on one schedule does not override propagated present.
     */
    public function test_absent_on_one_schedule_does_not_override_propagated_present(): void
    {
        $student = $this->createStudent();

        $this->actingAs($this->supervisor->user)
            ->patchJson(route('pengawas.attendance.confirm', $this->scheduleA->id), [
                'student_id' => $student->id,
                'confirmed' => true,
            ])->assertOk();

        $this->assertTrue($this->isConfirmed($student->id, $this->scheduleA->id));
        $this->assertTrue($this->isConfirmed($student->id, $this->scheduleB->id));
        $this->assertTrue($this->isConfirmed($student->id, $this->scheduleC->id));

        // Mark ABSENT on B (direct DB update — B's window not yet open at 08:05)
        ExamSession::where('student_id', $student->id)
            ->where('exam_schedule_id', $this->scheduleB->id)
            ->update([
                'attendance_confirmed' => false,
                'attendance_status' => ExamSession::ATTENDANCE_ABSENT,
            ]);

        // A: still confirmed (unchanged)
        $this->assertTrue($this->isConfirmed($student->id, $this->scheduleA->id));

        // B: now absent
        $this->assertFalse($this->isConfirmed($student->id, $this->scheduleB->id));

        // C: still confirmed (from A's propagation, not affected by B)
        $this->assertTrue($this->isConfirmed($student->id, $this->scheduleC->id));
    }

    // --- Helpers ---

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

    private function createSchedule(string $subjectName, string $start, string $end, int $duration): ExamSchedule
    {
        return ExamSchedule::factory()->create([
            'subject_id' => Subject::factory()->create(['name' => $subjectName])->id,
            'room_id' => $this->room->id,
            'exam_period_id' => $this->period->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => '2026-08-24',
            'start_time' => $start,
            'end_time' => $end,
            'duration_minutes' => $duration,
        ]);
    }

    private function isConfirmed(int $studentId, int $scheduleId): bool
    {
        return ExamSession::where('student_id', $studentId)
            ->where('exam_schedule_id', $scheduleId)
            ->value('attendance_confirmed') === true;
    }
}

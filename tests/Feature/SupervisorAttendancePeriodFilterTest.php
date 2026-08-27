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
use Tests\TestCase;

class SupervisorAttendancePeriodFilterTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    private Supervisor $supervisor;

    private User $user;

    private ExamPeriod $period1;

    private ExamPeriod $period2;

    private ExamPeriod $period3;

    private ExamSchedule $schedulePeriod1;

    private ExamSchedule $schedulePeriod2;

    private ExamSchedule $schedulePeriod3;

    protected function setUp(): void
    {
        parent::setUp();

        // Set time to 12:50 — within Sesi 2 window (12:00-14:00), outside Sesi 1 and Sesi 3 windows
        Carbon::setTestNow(Carbon::parse('2026-08-25 12:50:00'));

        $this->room = Room::factory()->create();

        $this->supervisor = Supervisor::factory()->create(['room_id' => $this->room->id]);
        $this->user = $this->supervisor->user;

        $this->period1 = ExamPeriod::create([
            'name' => 'Sesi 1',
            'name_prefix' => 'S1',
            'grade_level' => null,
            'session_number' => 1,
            'exam_date' => now()->toDateString(),
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
        ]);

        $this->period2 = ExamPeriod::create([
            'name' => 'Sesi 2',
            'name_prefix' => 'S2',
            'grade_level' => null,
            'session_number' => 2,
            'exam_date' => now()->toDateString(),
            'start_time' => '12:00:00',
            'end_time' => '14:00:00',
        ]);

        $this->period3 = ExamPeriod::create([
            'name' => 'Sesi 3',
            'name_prefix' => 'S3',
            'grade_level' => null,
            'session_number' => 3,
            'exam_date' => now()->toDateString(),
            'start_time' => '14:30:00',
            'end_time' => '16:30:00',
        ]);

        $subject1 = Subject::factory()->create(['name' => 'Indonesia']);
        $subject2 = Subject::factory()->create(['name' => 'Inggris']);
        $subject3 = Subject::factory()->create(['name' => 'Matematika']);

        // Period 1 (08:00-10:00) — FINISHED (now 12:50)
        $this->schedulePeriod1 = ExamSchedule::factory()->create([
            'room_id' => $this->room->id,
            'subject_id' => $subject1->id,
            'class_name' => 'XI RPL 1',
            'exam_period_id' => $this->period1->id,
            'exam_date' => now()->toDateString(),
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'duration_minutes' => 90,
            'status' => ExamSchedule::STATUS_FINISHED,
        ]);

        // Period 2 (12:00-14:00) — ONGOING (now 12:50)
        $this->schedulePeriod2 = ExamSchedule::factory()->create([
            'room_id' => $this->room->id,
            'subject_id' => $subject2->id,
            'class_name' => 'XI RPL 1',
            'exam_period_id' => $this->period2->id,
            'exam_date' => now()->toDateString(),
            'start_time' => '12:00:00',
            'end_time' => '14:00:00',
            'duration_minutes' => 90,
            'status' => ExamSchedule::STATUS_ONGOING,
        ]);

        // Period 3 (14:30-16:30) — SCHEDULED (not yet open)
        $this->schedulePeriod3 = ExamSchedule::factory()->create([
            'room_id' => $this->room->id,
            'subject_id' => $subject3->id,
            'class_name' => 'XI RPL 1',
            'exam_period_id' => $this->period3->id,
            'exam_date' => now()->toDateString(),
            'start_time' => '14:30:00',
            'end_time' => '16:30:00',
            'duration_minutes' => 90,
            'status' => ExamSchedule::STATUS_SCHEDULED,
        ]);

        // Assign supervisor ONLY to Sesi 2
        DB::table('supervisor_room_assignments')->insert([
            'exam_period_id' => $this->period2->id,
            'exam_date' => now()->toDateString(),
            'supervisor_id' => $this->supervisor->id,
            'room_id' => $this->room->id,
            'rotation_index' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_attendance_page_shows_only_assigned_period_schedule(): void
    {
        $this->actingAs($this->user)
            ->get(route('pengawas.attendance.index'))
            ->assertOk()
            ->assertSee('Inggris')        // Sesi 2 → assigned
            ->assertDontSee('Indonesia')   // Sesi 1 → NOT assigned
            ->assertDontSee('Matematika'); // Sesi 3 → NOT assigned
    }

    public function test_attendance_with_multiple_assignments_shows_all_assigned(): void
    {
        // Advance time to 14:15 — past Sesi 2 tolerance window, before Sesi 3 opens.
        // No schedule is in window → upcomingSchedules will render.
        Carbon::setTestNow(Carbon::parse('2026-08-25 14:15:00'));

        // Add assignment to Sesi 3 too
        DB::table('supervisor_room_assignments')->insert([
            'exam_period_id' => $this->period3->id,
            'exam_date' => now()->toDateString(),
            'supervisor_id' => $this->supervisor->id,
            'room_id' => $this->room->id,
            'rotation_index' => 2,
        ]);

        $this->actingAs($this->user)
            ->get(route('pengawas.attendance.index'))
            ->assertOk()
            ->assertSee('Matematika'); // Sesi 3 → assigned, shows in upcoming
    }

    public function test_non_assigned_period_schedule_does_not_appear(): void
    {
        // Non-assigned schedules (Sesi 1) should not appear on consolidated page
        $this->actingAs($this->user)
            ->get(route('pengawas.attendance.index'))
            ->assertOk()
            ->assertDontSee('Indonesia'); // Sesi 1 → NOT assigned
    }

    public function test_confirm_returns_403_for_non_assigned_period(): void
    {
        // Create a student to confirm
        $student = Student::factory()->create([
            'class_name' => 'XI RPL 1',
            'room_id' => $this->room->id,
        ]);

        DB::table('exam_room_assignments')->insert([
            'exam_period_id' => $this->period1->id,
            'student_id' => $student->id,
            'room_id' => $this->room->id,
            'seat_number' => $student->id,
        ]);

        // Try to confirm attendance for schedule from Sesi 1 (not assigned)
        $this->actingAs($this->user)
            ->patchJson(route('pengawas.attendance.confirm', $this->schedulePeriod1->id), [
                'student_id' => $student->id,
                'confirmed' => true,
            ])
            ->assertStatus(403)
            ->assertJson(['error' => 'Anda tidak ditugaskan pada periode ujian jadwal ini.']);
    }

    public function test_confirm_returns_200_for_assigned_period(): void
    {
        // Create a student assigned to period 2
        $student = Student::factory()->create([
            'class_name' => 'XI RPL 1',
            'room_id' => $this->room->id,
        ]);

        DB::table('exam_room_assignments')->insert([
            'exam_period_id' => $this->period2->id,
            'student_id' => $student->id,
            'room_id' => $this->room->id,
            'seat_number' => $student->id,
        ]);

        $this->actingAs($this->user)
            ->patchJson(route('pengawas.attendance.confirm', $this->schedulePeriod2->id), [
                'student_id' => $student->id,
                'confirmed' => true,
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('exam_sessions', [
            'student_id' => $student->id,
            'exam_schedule_id' => $this->schedulePeriod2->id,
            'attendance_confirmed' => true,
            'attendance_status' => ExamSession::ATTENDANCE_PRESENT,
        ]);
    }

    public function test_upcoming_schedules_also_filtered_by_assigned_period(): void
    {
        // At 12:50, Sesi 3 is SCHEDULED but not in window yet
        // Since supervisor is NOT assigned to Sesi 3, it shouldn't show in upcoming
        $this->actingAs($this->user)
            ->get(route('pengawas.attendance.index'))
            ->assertOk()
            ->assertDontSee('Matematika'); // Sesi 3 → NOT assigned, shouldn't appear anywhere
    }

    public function test_upcoming_shows_when_assigned(): void
    {
        // Add assignment to Sesi 3
        DB::table('supervisor_room_assignments')->insert([
            'exam_period_id' => $this->period3->id,
            'exam_date' => now()->toDateString(),
            'supervisor_id' => $this->supervisor->id,
            'room_id' => $this->room->id,
            'rotation_index' => 2,
        ]);

        // Advance time to 14:15 — past Sesi 2 tolerance, before Sesi 3 opens
        // No schedule is in window → upcomingSchedules renders
        Carbon::setTestNow(Carbon::parse('2026-08-25 14:15:00'));

        // At 14:15, Sesi 3 is SCHEDULED and not in window → should appear in upcoming
        $this->actingAs($this->user)
            ->get(route('pengawas.attendance.index'))
            ->assertOk()
            ->assertSee('Matematika'); // Sesi 3 → now assigned, should appear in upcoming
    }
}

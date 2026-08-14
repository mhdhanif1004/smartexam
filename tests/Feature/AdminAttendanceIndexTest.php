<?php

namespace Tests\Feature;

use App\Models\ExamPeriod;
use App\Models\ExamRoomAssignment;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Supervisor;
use App\Models\SupervisorAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminAttendanceIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-12 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_attendance_index_shows_period_room_and_participant_summary(): void
    {
        $admin = User::factory()->admin()->create();
        $period = $this->period('Sesi 1');
        $subject = Subject::factory()->create(['name' => 'Matematika']);
        $room = Room::factory()->create(['name' => 'Ruang 1']);
        $schedule = $this->schedule($period, $subject, $room);

        $adam = $this->studentWithName('Adam Peserta', $room);
        $bella = $this->studentWithName('Bella Peserta', $room);
        $this->assign($period, $adam, $room);
        $this->assign($period, $bella, $room);

        ExamSession::factory()->create([
            'student_id' => $adam->id,
            'exam_schedule_id' => $schedule->id,
            'attendance_status' => ExamSession::ATTENDANCE_PRESENT,
            'attendance_confirmed' => true,
        ]);
        ExamSession::factory()->create([
            'student_id' => $bella->id,
            'exam_schedule_id' => $schedule->id,
            'attendance_status' => ExamSession::ATTENDANCE_ABSENT,
            'attendance_confirmed' => false,
        ]);

        $supervisor = Supervisor::factory()->create(['room_id' => $room->id]);
        SupervisorAttendance::factory()->create([
            'supervisor_id' => $supervisor->id,
            'exam_schedule_id' => $schedule->id,
            'room_id' => $room->id,
            'status' => SupervisorAttendance::STATUS_PRESENT,
            'checked_in_at' => '2026-08-12 08:20:00',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.attendance.index'))
            ->assertOk()
            ->assertSee('Sesi 1')
            ->assertSee('Matematika')
            ->assertSee('Ruang 1')
            ->assertSee('Adam Peserta')
            ->assertSee('Bella Peserta')
            ->assertSee('Check-in:')
            ->assertSee('Auto-refresh aktif (60 dtk)');
    }

    public function test_admin_attendance_summary_counts_student_once_per_room_regardless_of_subjects(): void
    {
        $admin = User::factory()->admin()->create();
        $period = $this->period('Sesi 1');
        $matematika = Subject::factory()->create(['name' => 'Matematika']);
        $inggris = Subject::factory()->create(['name' => 'Bahasa Inggris']);
        $room = Room::factory()->create(['name' => 'Ruang 1']);

        $scheduleA = $this->schedule($period, $matematika, $room);
        $scheduleB = ExamSchedule::factory()->create([
            'subject_id' => $inggris->id,
            'room_id' => $room->id,
            'exam_period_id' => $period->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => '2026-08-12',
            'start_time' => '09:40:00',
            'end_time' => '10:40:00',
            'duration_minutes' => 60,
            'status' => ExamSchedule::STATUS_ONGOING,
        ]);

        $adam = $this->studentWithName('Adam Peserta', $room);
        $bella = $this->studentWithName('Bella Peserta', $room);
        $this->assign($period, $adam, $room);
        $this->assign($period, $bella, $room);

        foreach ([$scheduleA, $scheduleB] as $schedule) {
            ExamSession::factory()->create([
                'student_id' => $adam->id,
                'exam_schedule_id' => $schedule->id,
                'attendance_status' => ExamSession::ATTENDANCE_PRESENT,
                'attendance_confirmed' => true,
            ]);
            ExamSession::factory()->create([
                'student_id' => $bella->id,
                'exam_schedule_id' => $schedule->id,
                'attendance_status' => ExamSession::ATTENDANCE_ABSENT,
                'attendance_confirmed' => false,
            ]);
        }

        $this->actingAs($admin)
            ->getJson(route('admin.attendance.summary'))
            ->assertOk()
            ->assertJson([
                'totals' => ['present' => 1, 'absent' => 1, 'supervisorPresent' => 0, 'supervisorAbsent' => 0],
                'periods' => [
                    [
                        'present' => 1,
                        'absent' => 1,
                        'supervisorPresent' => 0,
                        'supervisorAbsent' => 0,
                        'rooms' => [
                            ['present' => 1, 'absent' => 1, 'unchecked' => 0, 'total' => 2],
                        ],
                    ],
                ],
            ]);
    }

    public function test_admin_attendance_summary_endpoint_returns_json_counts(): void
    {
        $admin = User::factory()->admin()->create();
        $period = $this->period('Sesi 1');
        $subject = Subject::factory()->create(['name' => 'Matematika']);
        $room = Room::factory()->create(['name' => 'Ruang 1']);
        $schedule = $this->schedule($period, $subject, $room);

        $adam = $this->studentWithName('Adam Peserta', $room);
        $bella = $this->studentWithName('Bella Peserta', $room);
        $this->assign($period, $adam, $room);
        $this->assign($period, $bella, $room);

        ExamSession::factory()->create([
            'student_id' => $adam->id,
            'exam_schedule_id' => $schedule->id,
            'attendance_status' => ExamSession::ATTENDANCE_PRESENT,
            'attendance_confirmed' => true,
        ]);
        ExamSession::factory()->create([
            'student_id' => $bella->id,
            'exam_schedule_id' => $schedule->id,
            'attendance_status' => ExamSession::ATTENDANCE_ABSENT,
            'attendance_confirmed' => false,
        ]);

        $supervisor = Supervisor::factory()->create(['room_id' => $room->id]);
        SupervisorAttendance::factory()->create([
            'supervisor_id' => $supervisor->id,
            'exam_schedule_id' => $schedule->id,
            'room_id' => $room->id,
            'status' => SupervisorAttendance::STATUS_PRESENT,
            'checked_in_at' => '2026-08-12 08:20:00',
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.attendance.summary'))
            ->assertOk()
            ->assertJson([
                'totals' => ['present' => 1, 'absent' => 1, 'supervisorPresent' => 1, 'supervisorAbsent' => 0],
                'periods' => [
                    [
                        'present' => 1,
                        'absent' => 1,
                        'supervisorPresent' => 1,
                        'supervisorAbsent' => 0,
                        'rooms' => [
                            [
                                'present' => 1,
                                'absent' => 1,
                                'unchecked' => 0,
                                'total' => 2,
                                'supervisorPresent' => 1,
                                'supervisorAbsent' => 0,
                                'supervisorUnchecked' => 0,
                                'needsAttention' => false,
                            ],
                        ],
                    ],
                ],
            ]);
    }

    public function test_admin_attendance_index_finished_session_defaults_collapsed(): void
    {
        $admin = User::factory()->admin()->create();
        $period = ExamPeriod::factory()->create([
            'name' => 'Sesi 0',
            'exam_date' => '2026-08-12',
            'start_time' => '07:00:00',
            'end_time' => '08:30:00',
        ]);
        $subject = Subject::factory()->create();
        $room = Room::factory()->create();
        ExamSchedule::factory()->create([
            'subject_id' => $subject->id,
            'room_id' => $room->id,
            'exam_period_id' => $period->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => '2026-08-12',
            'start_time' => '07:00:00',
            'end_time' => '08:30:00',
            'duration_minutes' => 60,
            'status' => ExamSchedule::STATUS_FINISHED,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.attendance.index'))
            ->assertOk()
            ->assertSee("JSON.parse('[false]')", false);
    }

    public function test_admin_attendance_index_ongoing_session_defaults_open(): void
    {
        $admin = User::factory()->admin()->create();
        $period = $this->period('Sesi 1');
        $subject = Subject::factory()->create();
        $room = Room::factory()->create();
        $this->schedule($period, $subject, $room);

        $this->actingAs($admin)
            ->get(route('admin.attendance.index'))
            ->assertOk()
            ->assertSee("JSON.parse('[true]')", false);
    }

    public function test_admin_attendance_index_warns_when_supervisor_absent_during_ongoing_session(): void
    {
        $admin = User::factory()->admin()->create();
        $period = $this->period('Sesi 1');
        $subject = Subject::factory()->create(['name' => 'Matematika']);
        $room = Room::factory()->create(['name' => 'Ruang 1']);
        $schedule = $this->schedule($period, $subject, $room);

        $supervisor = Supervisor::factory()->create(['room_id' => $room->id]);
        SupervisorAttendance::factory()->create([
            'supervisor_id' => $supervisor->id,
            'exam_schedule_id' => $schedule->id,
            'room_id' => $room->id,
            'status' => SupervisorAttendance::STATUS_ABSENT,
            'checked_in_at' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.attendance.index'))
            ->assertOk()
            ->assertSee('Perhatian: pengawas tidak hadir saat ujian berlangsung/selesai.');
    }

    public function test_admin_attendance_index_shows_empty_state_when_no_schedules_on_date(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.attendance.index', ['date' => '2026-12-31']))
            ->assertOk()
            ->assertSee('Tidak ada sesi ujian pada tanggal ini.');
    }

    private function period(string $name): ExamPeriod
    {
        return ExamPeriod::factory()->create([
            'name' => $name,
            'exam_date' => '2026-08-12',
            'start_time' => '07:30:00',
            'end_time' => '11:00:00',
        ]);
    }

    private function schedule(ExamPeriod $period, Subject $subject, Room $room): ExamSchedule
    {
        return ExamSchedule::factory()->create([
            'subject_id' => $subject->id,
            'room_id' => $room->id,
            'exam_period_id' => $period->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => '2026-08-12',
            'start_time' => '08:30:00',
            'end_time' => '10:30:00',
            'duration_minutes' => 60,
            'status' => ExamSchedule::STATUS_ONGOING,
        ]);
    }

    private function studentWithName(string $name, Room $room): Student
    {
        return Student::factory()->create([
            'class_name' => 'XI RPL 1',
            'room_id' => $room->id,
            'user_id' => User::factory()->peserta()->create(['name' => $name])->id,
        ]);
    }

    private function assign(ExamPeriod $period, Student $student, Room $room): void
    {
        ExamRoomAssignment::factory()->create([
            'exam_period_id' => $period->id,
            'student_id' => $student->id,
            'room_id' => $room->id,
        ]);
    }
}

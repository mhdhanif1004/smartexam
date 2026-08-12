<?php

namespace Tests\Feature;

use App\Http\Controllers\Pengawas\DashboardController;
use App\Models\ExamPeriod;
use App\Models\ExamRoomAssignment;
use App\Models\ExamSchedule;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Supervisor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use ReflectionMethod;
use Tests\TestCase;

class ExamAccessMigrationTest extends TestCase
{
    use RefreshDatabase;

    private ExamPeriod $period;

    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-12 09:00:00'));

        $this->subject = Subject::factory()->create(['name' => 'Matematika']);

        $this->period = ExamPeriod::factory()->create([
            'name' => 'Sesi 1',
            'exam_date' => '2026-08-12',
            'start_time' => '07:30:00',
            'end_time' => '11:00:00',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function studentWithName(string $name, Room $room): Student
    {
        return Student::factory()->create([
            'class_name' => 'XI RPL 1',
            'room_id' => $room->id,
            'user_id' => User::factory()->peserta()->create(['name' => $name])->id,
        ]);
    }

    private function periodSchedule(ExamPeriod $period, Room $room): ExamSchedule
    {
        return ExamSchedule::factory()->create([
            'subject_id' => $this->subject->id,
            'room_id' => $room->id,
            'exam_period_id' => $period->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => now()->toDateString(),
            'start_time' => '08:30:00',
            'end_time' => '10:30:00',
            'duration_minutes' => 60,
            'status' => ExamSchedule::STATUS_ONGOING,
        ]);
    }

    private function legacySchedule(Room $room): ExamSchedule
    {
        return ExamSchedule::factory()->create([
            'subject_id' => $this->subject->id,
            'room_id' => $room->id,
            'exam_period_id' => null,
            'class_name' => 'XI RPL 1',
            'exam_date' => now()->toDateString(),
            'start_time' => '08:30:00',
            'end_time' => '10:30:00',
            'duration_minutes' => 60,
            'status' => ExamSchedule::STATUS_ONGOING,
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

    public function test_period_assigned_student_can_access_schedule_in_room_different_from_home_room(): void
    {
        $homeRoom = Room::factory()->create();
        $examRoom = Room::factory()->create();
        $student = $this->studentWithName('Siswa Terdaftar', $homeRoom);
        $schedule = $this->periodSchedule($this->period, $examRoom);
        $this->assign($this->period, $student, $examRoom);

        $this->assertTrue($student->isAssignedToSchedule($schedule));

        $this->actingAs($student->user)
            ->getJson(route('peserta.exams.status', $schedule->id))
            ->assertOk()
            ->assertJson(['active' => false]);
    }

    public function test_period_unassigned_student_cannot_access_schedule_in_home_room(): void
    {
        $room = Room::factory()->create();
        $student = $this->studentWithName('Bukan Peserta', $room);
        $schedule = $this->periodSchedule($this->period, $room);

        $this->assertFalse($student->isAssignedToSchedule($schedule));

        $this->actingAs($student->user)
            ->getJson(route('peserta.exams.status', $schedule->id))
            ->assertForbidden()
            ->assertJson(['error' => 'Anda tidak memiliki akses ke ujian tersebut.']);
    }

    public function test_legacy_schedule_without_period_falls_back_to_room_placement(): void
    {
        $room = Room::factory()->create();
        $otherRoom = Room::factory()->create();
        $assigned = $this->studentWithName('Peserta Lama', $room);
        $outsider = $this->studentWithName('Siswa Ruang Lain', $otherRoom);
        $schedule = $this->legacySchedule($room);

        $this->assertTrue($assigned->isAssignedToSchedule($schedule));
        $this->assertFalse($outsider->isAssignedToSchedule($schedule));

        $this->actingAs($assigned->user)
            ->getJson(route('peserta.exams.status', $schedule->id))
            ->assertOk();

        $this->actingAs($outsider->user)
            ->getJson(route('peserta.exams.status', $schedule->id))
            ->assertForbidden();
    }

    public function test_participant_ids_use_exam_room_assignments_for_period_schedules(): void
    {
        $examRoom = Room::factory()->create();
        $otherRoom = Room::factory()->create();
        $adam = $this->studentWithName('Adam Peserta', $examRoom);
        $bella = $this->studentWithName('Bella Peserta', $examRoom);
        $cinta = $this->studentWithName('Cinta Bukan', $examRoom);
        $dian = $this->studentWithName('Dian Ruang Lain', $otherRoom);

        $this->assign($this->period, $adam, $examRoom);
        $this->assign($this->period, $bella, $examRoom);
        $this->assign($this->period, $dian, $otherRoom);

        $schedule = $this->periodSchedule($this->period, $examRoom);

        $this->assertEqualsCanonicalizing(
            [$adam->id, $bella->id],
            $schedule->participantStudentIds(),
        );
        $this->assertCount(2, $schedule->participantStudents());
        $this->assertTrue($schedule->hasParticipant($adam->id));
        $this->assertFalse($schedule->hasParticipant($cinta->id));
    }

    public function test_participant_ids_fallback_to_room_placement_for_legacy_schedules(): void
    {
        $room = Room::factory()->create();
        $otherRoom = Room::factory()->create();
        $adam = $this->studentWithName('Adam Lama', $room);
        $bella = $this->studentWithName('Bella Lama', $room);
        $cinta = $this->studentWithName('Cinta Luar', $otherRoom);

        $schedule = $this->legacySchedule($room);

        $this->assertEqualsCanonicalizing(
            [$adam->id, $bella->id],
            $schedule->participantStudentIds(),
        );
        $this->assertCount(2, $schedule->participantStudents());
        $this->assertTrue($schedule->hasParticipant($bella->id));
        $this->assertFalse($schedule->hasParticipant($cinta->id));
    }

    public function test_pengawas_schedule_stats_counts_only_assigned_students(): void
    {
        $examRoom = Room::factory()->create();
        $assigned = $this->studentWithName('Adam Terdaftar', $examRoom);
        $this->studentWithName('Bella Tidak', $examRoom);
        $this->assign($this->period, $assigned, $examRoom);

        $schedule = $this->periodSchedule($this->period, $examRoom);

        $stats = $this->invokeScheduleStats($schedule);

        $this->assertSame(1, $stats['total']);
    }

    public function test_dashboard_shows_period_schedule_in_room_different_from_home_room(): void
    {
        $homeRoom = Room::factory()->create();
        $examRoom = Room::factory()->create();
        $student = $this->studentWithName('Adam Terdaftar', $homeRoom);
        $this->assign($this->period, $student, $examRoom);

        $this->periodSchedule($this->period, $examRoom);

        $this->actingAs($student->user)
            ->get(route('peserta.dashboard'))
            ->assertOk()
            ->assertSee('Matematika');
    }

    public function test_dashboard_hides_period_schedule_when_student_not_assigned_even_if_home_room_matches(): void
    {
        $room = Room::factory()->create();
        $student = $this->studentWithName('Bukan Peserta', $room);
        $fisika = Subject::factory()->create(['name' => 'Fisika']);

        ExamSchedule::factory()->create([
            'subject_id' => $fisika->id,
            'room_id' => $room->id,
            'exam_period_id' => $this->period->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => now()->toDateString(),
            'start_time' => '08:30:00',
            'end_time' => '10:30:00',
            'duration_minutes' => 60,
            'status' => ExamSchedule::STATUS_ONGOING,
        ]);

        $this->actingAs($student->user)
            ->get(route('peserta.dashboard'))
            ->assertOk()
            ->assertDontSee('Fisika');
    }

    public function test_dashboard_shows_legacy_schedule_in_home_room_and_hides_other_rooms(): void
    {
        $homeRoom = Room::factory()->create();
        $otherRoom = Room::factory()->create();
        $student = $this->studentWithName('Peserta Lama', $homeRoom);
        $fisika = Subject::factory()->create(['name' => 'Fisika']);

        $this->legacySchedule($homeRoom);

        ExamSchedule::factory()->create([
            'subject_id' => $fisika->id,
            'room_id' => $otherRoom->id,
            'exam_period_id' => null,
            'class_name' => 'XI RPL 1',
            'exam_date' => now()->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'duration_minutes' => 60,
            'status' => ExamSchedule::STATUS_ONGOING,
        ]);

        $this->actingAs($student->user)
            ->get(route('peserta.dashboard'))
            ->assertOk()
            ->assertSee('Matematika')
            ->assertDontSee('Fisika');
    }

    public function test_pengawas_attendance_confirm_rejects_unassigned_student(): void
    {
        $examRoom = Room::factory()->create();
        $supervisor = Supervisor::factory()->create(['room_id' => $examRoom->id]);
        $assigned = $this->studentWithName('Adam Peserta', $examRoom);
        $unassigned = $this->studentWithName('Bella Tidak', $examRoom);
        $this->assign($this->period, $assigned, $examRoom);

        $schedule = $this->periodSchedule($this->period, $examRoom);

        $this->actingAs($supervisor->user)
            ->patchJson(route('pengawas.attendance.confirm', $schedule->id), [
                'student_id' => $unassigned->id,
                'confirmed' => true,
            ])
            ->assertStatus(422)
            ->assertJson(['error' => 'Siswa bukan peserta pada sesi ujian ini.']);

        $this->actingAs($supervisor->user)
            ->patchJson(route('pengawas.attendance.confirm', $schedule->id), [
                'student_id' => $assigned->id,
                'confirmed' => true,
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_admin_attendance_index_lists_only_assigned_students(): void
    {
        $admin = User::factory()->admin()->create();
        $room = Room::factory()->create();
        $assigned = $this->studentWithName('Adam Terdaftar', $room);
        $unassigned = $this->studentWithName('Bella Tidak', $room);
        $this->assign($this->period, $assigned, $room);

        $this->periodSchedule($this->period, $room);

        $this->actingAs($admin)
            ->get(route('admin.attendance.index', ['room_id' => $room->id]))
            ->assertOk()
            ->assertSee('Adam Terdaftar')
            ->assertDontSee('Bella Tidak');
    }

    /**
     * @return array{total: int, hadir: int, sedang_mengerjakan: int, selesai: int}
     */
    private function invokeScheduleStats(ExamSchedule $schedule): array
    {
        $method = new ReflectionMethod(DashboardController::class, 'scheduleStats');
        $method->setAccessible(true);

        return $method->invoke(new DashboardController, $schedule);
    }
}

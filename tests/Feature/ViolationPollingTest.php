<?php

namespace Tests\Feature;

use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Supervisor;
use App\Models\User;
use App\Models\Violation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViolationPollingTest extends TestCase
{
    use RefreshDatabase;

    private Room $roomA;

    private Room $roomB;

    private User $pengawasA;

    private User $admin;

    private ExamSchedule $scheduleA;

    private ExamSchedule $scheduleB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->roomA = Room::factory()->create(['room_number' => 1]);
        $this->roomB = Room::factory()->create(['room_number' => 2]);
        $this->pengawasA = Supervisor::factory()->create(['room_id' => $this->roomA->id])->user;
        $this->admin = User::factory()->admin()->create();

        $subjectA = Subject::factory()->create(['name' => 'Matematika']);
        $subjectB = Subject::factory()->create(['name' => 'Fisika']);

        $this->scheduleA = ExamSchedule::factory()->create([
            'room_id' => $this->roomA->id,
            'subject_id' => $subjectA->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => now()->toDateString(),
            'start_time' => now()->subMinutes(30)->format('H:i:s'),
            'end_time' => now()->addMinutes(30)->format('H:i:s'),
            'status' => 'ongoing',
        ]);

        $this->scheduleB = ExamSchedule::factory()->create([
            'room_id' => $this->roomB->id,
            'subject_id' => $subjectB->id,
            'class_name' => 'XI TKJ 1',
            'exam_date' => now()->toDateString(),
            'start_time' => now()->subMinutes(30)->format('H:i:s'),
            'end_time' => now()->addMinutes(30)->format('H:i:s'),
            'status' => 'ongoing',
        ]);
    }

    private function createViolation(ExamSchedule $schedule, string $type = Violation::TYPE_TAB_SWITCH): Violation
    {
        $student = Student::factory()->create([
            'class_name' => $schedule->class_name,
            'room_id' => $schedule->room_id,
        ]);

        $session = ExamSession::factory()->create([
            'student_id' => $student->id,
            'exam_schedule_id' => $schedule->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);

        return Violation::factory()->create([
            'exam_session_id' => $session->id,
            'violation_type' => $type,
            'occurred_at' => now(),
        ]);
    }

    public function test_pengawas_polling_returns_only_own_room_violations(): void
    {
        $vA = $this->createViolation($this->scheduleA);
        $vB = $this->createViolation($this->scheduleB);

        $response = $this->actingAs($this->pengawasA)
            ->getJson(route('pengawas.violations.polling'));

        $response->assertOk();
        $ids = $response->json('violations.*.id');
        $this->assertContains($vA->id, $ids);
        $this->assertNotContains($vB->id, $ids);
    }

    public function test_admin_polling_returns_all_violations(): void
    {
        $vA = $this->createViolation($this->scheduleA);
        $vB = $this->createViolation($this->scheduleB);

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.violations.polling'));

        $response->assertOk();
        $ids = $response->json('violations.*.id');
        $this->assertContains($vA->id, $ids);
        $this->assertContains($vB->id, $ids);
    }

    public function test_since_parameter_excludes_old_violations(): void
    {
        $old = $this->createViolation($this->scheduleA);
        $fresh = $this->createViolation($this->scheduleA);

        $response = $this->actingAs($this->pengawasA)
            ->getJson(route('pengawas.violations.polling', ['since' => $old->id]));

        $response->assertOk();
        $ids = $response->json('violations.*.id');
        $this->assertNotContains($old->id, $ids);
        $this->assertContains($fresh->id, $ids);
    }

    public function test_since_zero_returns_all_violations(): void
    {
        $v1 = $this->createViolation($this->scheduleA);
        $v2 = $this->createViolation($this->scheduleA);

        $response = $this->actingAs($this->pengawasA)
            ->getJson(route('pengawas.violations.polling', ['since' => 0]));

        $response->assertOk();
        $ids = $response->json('violations.*.id');
        $this->assertContains($v1->id, $ids);
        $this->assertContains($v2->id, $ids);
    }

    public function test_polling_response_contains_required_fields(): void
    {
        $violation = $this->createViolation($this->scheduleA, Violation::TYPE_BLUR);

        $response = $this->actingAs($this->pengawasA)
            ->getJson(route('pengawas.violations.polling'));

        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $violation->id,
            'violation_type' => 'kehilangan_fokus',
            'violation_label' => 'Kehilangan Fokus Jendela',
        ]);

        $item = collect($response->json('violations'))->firstWhere('id', $violation->id);
        $this->assertArrayHasKey('student_name', $item);
        $this->assertArrayHasKey('room_name', $item);
        $this->assertArrayHasKey('subject', $item);
        $this->assertArrayHasKey('occurred_at', $item);
    }

    public function test_admin_polling_includes_room_name(): void
    {
        $violation = $this->createViolation($this->scheduleA);

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.violations.polling'));

        $item = collect($response->json('violations'))->firstWhere('id', $violation->id);
        $this->assertNotNull($item);
        $this->assertEquals($this->roomA->display_name, $item['room_name']);
    }

    public function test_since_with_no_new_violations_returns_empty(): void
    {
        $violation = $this->createViolation($this->scheduleA);

        $response = $this->actingAs($this->pengawasA)
            ->getJson(route('pengawas.violations.polling', ['since' => $violation->id]));

        $response->assertOk();
        $response->assertJsonCount(0, 'violations');
    }

    public function test_unauthenticated_polling_returns_401(): void
    {
        $this->getJson(route('pengawas.violations.polling'))
            ->assertUnauthorized();

        $this->getJson(route('admin.violations.polling'))
            ->assertUnauthorized();
    }
}

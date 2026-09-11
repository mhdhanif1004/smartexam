<?php

namespace Tests\Feature;

use App\Models\ExamPeriod;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Supervisor;
use App\Models\SupervisorRoomAssignment;
use App\Models\User;
use App\Models\Violation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViolationMultiRoomPollingTest extends TestCase
{
    use RefreshDatabase;

    private Room $roomA;

    private Room $roomB;

    private Room $roomC;

    private Subject $subjectA;

    private Subject $subjectB;

    private Supervisor $supervisorMulti;

    private User $pengawasMulti;

    private ExamPeriod $periodA;

    private ExamPeriod $periodB;

    private ExamSchedule $scheduleA;

    private ExamSchedule $scheduleB;

    private ExamSchedule $scheduleC;

    protected function setUp(): void
    {
        parent::setUp();

        $this->roomA = Room::factory()->create(['room_number' => 11]);
        $this->roomB = Room::factory()->create(['room_number' => 12]);
        $this->roomC = Room::factory()->create(['room_number' => 13]);

        $this->subjectA = Subject::factory()->create(['name' => 'Matematika']);
        $this->subjectB = Subject::factory()->create(['name' => 'Fisika']);

        // Dua gelombang berbeda di hari yang sama — unique constraint
        // sra_period_date_supervisor_unique hanya melarang 1 pengawas 2 baris
        // dalam period yang SAMA; multi-ruangan terjadi lintas gelombang (period).
        $this->periodA = ExamPeriod::factory()->create([
            'exam_date' => now()->toDateString(),
            'start_time' => '07:00:00',
            'end_time' => '09:00:00',
        ]);
        $this->periodB = ExamPeriod::factory()->create([
            'exam_date' => now()->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
        ]);

        $this->supervisorMulti = Supervisor::factory()->create(['room_id' => null]);
        $this->pengawasMulti = $this->supervisorMulti->user;
        $this->pengawasMulti->update(['role' => User::ROLE_PENGAWAS]);

        SupervisorRoomAssignment::factory()->create([
            'exam_period_id' => $this->periodA->id,
            'exam_date' => now()->toDateString(),
            'supervisor_id' => $this->supervisorMulti->id,
            'room_id' => $this->roomA->id,
        ]);
        SupervisorRoomAssignment::factory()->create([
            'exam_period_id' => $this->periodB->id,
            'exam_date' => now()->toDateString(),
            'supervisor_id' => $this->supervisorMulti->id,
            'room_id' => $this->roomB->id,
        ]);

        $this->scheduleA = ExamSchedule::factory()->create([
            'room_id' => $this->roomA->id,
            'subject_id' => $this->subjectA->id,
            'exam_period_id' => $this->periodA->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => now()->toDateString(),
            'start_time' => now()->subMinutes(30)->format('H:i:s'),
            'end_time' => now()->addMinutes(30)->format('H:i:s'),
            'status' => 'ongoing',
        ]);
        $this->scheduleB = ExamSchedule::factory()->create([
            'room_id' => $this->roomB->id,
            'subject_id' => $this->subjectB->id,
            'exam_period_id' => $this->periodB->id,
            'class_name' => 'XI TKJ 1',
            'exam_date' => now()->toDateString(),
            'start_time' => now()->subMinutes(30)->format('H:i:s'),
            'end_time' => now()->addMinutes(30)->format('H:i:s'),
            'status' => 'ongoing',
        ]);
        $this->scheduleC = ExamSchedule::factory()->create([
            'room_id' => $this->roomC->id,
            'subject_id' => $this->subjectA->id,
            'exam_period_id' => $this->periodA->id,
            'class_name' => 'XI RPL 2',
            'exam_date' => now()->toDateString(),
            'start_time' => now()->subMinutes(30)->format('H:i:s'),
            'end_time' => now()->addMinutes(30)->format('H:i:s'),
            'status' => 'ongoing',
        ]);
    }

    private function createViolation(ExamSchedule $schedule): Violation
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
            'occurred_at' => now(),
        ]);
    }

    public function test_polling_mengembalikan_pelanggaran_dari_kedua_ruangan(): void
    {
        $vA = $this->createViolation($this->scheduleA);
        $vB = $this->createViolation($this->scheduleB);
        $vC = $this->createViolation($this->scheduleC);

        $res = $this->actingAs($this->pengawasMulti)->getJson(route('pengawas.violations.polling'));

        $res->assertOk();
        $ids = $res->json('violations.*.id');
        $this->assertContains($vA->id, $ids, 'violation room A harus terlihat');
        $this->assertContains($vB->id, $ids, 'violation room B harus terlihat');
        $this->assertNotContains($vC->id, $ids, 'violation room C (bukan tugasnya) harus tidak terlihat');

        $roomIds = $res->json('room_ids');
        $this->assertIsArray($roomIds);
        $this->assertContains($this->roomA->id, $roomIds);
        $this->assertContains($this->roomB->id, $roomIds);
        $this->assertNotContains($this->roomC->id, $roomIds);
    }

    public function test_recent_mengembalikan_pelanggaran_multi_room(): void
    {
        $vA = $this->createViolation($this->scheduleA);
        $vB = $this->createViolation($this->scheduleB);

        $res = $this->actingAs($this->pengawasMulti)->getJson(route('pengawas.violations.recent'));

        $res->assertOk();
        $ids = $res->json('violations.*.id');
        $this->assertContains($vA->id, $ids);
        $this->assertContains($vB->id, $ids);
    }

    public function test_handle_hanya_boleh_untuk_ruangan_sendiri(): void
    {
        $vA = $this->createViolation($this->scheduleA);
        $vC = $this->createViolation($this->scheduleC);

        $this->actingAs($this->pengawasMulti)
            ->patchJson(route('pengawas.violations.handle', $vA->id))
            ->assertOk()->assertJson(['handled' => true]);

        $this->actingAs($this->pengawasMulti)
            ->patchJson(route('pengawas.violations.handle', $vC->id))
            ->assertStatus(403);
    }

    public function test_badge_menghitung_total_tanpa_limit_dari_semua_ruangan(): void
    {
        for ($i = 0; $i < 13; $i++) {
            $this->createViolation($this->scheduleA);
        }
        for ($i = 0; $i < 12; $i++) {
            $this->createViolation($this->scheduleB);
        }

        $res = $this->actingAs($this->pengawasMulti)->getJson(route('pengawas.violations.polling'));
        $res->assertOk();

        $this->assertCount(20, $res->json('violations'));
        $this->assertSame(25, $res->json('unhandled_count'));
    }

    public function test_single_room_tetap_kompatibel(): void
    {
        $single = Supervisor::factory()->create(['room_id' => $this->roomC->id]);
        $pengawasSingle = $single->user;
        $pengawasSingle->update(['role' => User::ROLE_PENGAWAS]);

        $vA = $this->createViolation($this->scheduleA);
        $vC = $this->createViolation($this->scheduleC);

        $res = $this->actingAs($pengawasSingle)->getJson(route('pengawas.violations.polling'));
        $res->assertOk();
        $ids = $res->json('violations.*.id');
        $this->assertNotContains($vA->id, $ids);
        $this->assertContains($vC->id, $ids);
        $this->assertSame(1, $res->json('unhandled_count'));
    }
}

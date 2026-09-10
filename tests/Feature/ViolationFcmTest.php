<?php

namespace Tests\Feature;

use App\Jobs\SendViolationFcmNotification;
use App\Models\ExamPeriod;
use App\Models\ExamRoomAssignment;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Supervisor;
use App\Models\SupervisorRoomAssignment;
use App\Models\User;
use App\Models\UserFcmToken;
use App\Models\Violation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\MessageTarget;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\SendReport;
use Tests\TestCase;

class ViolationFcmTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-07-31 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeSchedule(Room $room, Subject $subject, ExamPeriod $period): ExamSchedule
    {
        return ExamSchedule::factory()->create([
            'room_id' => $room->id,
            'subject_id' => $subject->id,
            'exam_period_id' => $period->id,
            'exam_date' => $period->exam_date,
            'start_time' => $period->start_time,
            'end_time' => $period->end_time,
            'status' => ExamSchedule::STATUS_ONGOING,
        ]);
    }

    public function test_violation_store_dispatches_fcm_job(): void
    {
        Queue::fake();

        $room = Room::factory()->create();
        $student = Student::factory()->create(['room_id' => $room->id, 'class_name' => 'XI RPL 1']);
        $subject = Subject::factory()->create();
        $period = ExamPeriod::factory()->create([
            'exam_date' => now()->toDateString(),
            'start_time' => '08:00:00',
            'end_time' => '10:30:00',
        ]);
        $schedule = $this->makeSchedule($room, $subject, $period);
        ExamRoomAssignment::factory()->create([
            'exam_period_id' => $period->id,
            'student_id' => $student->id,
            'room_id' => $room->id,
        ]);

        ExamSession::create([
            'student_id' => $student->id,
            'exam_schedule_id' => $schedule->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'started_at' => now(),
            'attendance_confirmed' => true,
        ]);

        $this->actingAs($student->user)
            ->postJson(route('peserta.exams.violation', $schedule->id), ['violation_type' => Violation::TYPE_TAB_SWITCH])
            ->assertOk()->assertJson(['redirect' => true]);

        Queue::assertPushed(SendViolationFcmNotification::class, fn ($job) => $job->violationId > 0);
    }

    public function test_fullscreen_exit_juga_dispatch_fcm(): void
    {
        Queue::fake();

        $room = Room::factory()->create();
        $student = Student::factory()->create(['room_id' => $room->id, 'class_name' => 'XI RPL 1']);
        $subject = Subject::factory()->create();
        $period = ExamPeriod::factory()->create([
            'exam_date' => now()->toDateString(),
            'start_time' => '08:00:00',
            'end_time' => '10:30:00',
        ]);
        $schedule = $this->makeSchedule($room, $subject, $period);
        ExamRoomAssignment::factory()->create([
            'exam_period_id' => $period->id,
            'student_id' => $student->id,
            'room_id' => $room->id,
        ]);

        ExamSession::create([
            'student_id' => $student->id,
            'exam_schedule_id' => $schedule->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'started_at' => now(),
            'attendance_confirmed' => true,
        ]);

        $this->actingAs($student->user)
            ->postJson(route('peserta.exams.violation', $schedule->id), ['violation_type' => Violation::TYPE_FULLSCREEN_EXIT])
            ->assertOk()->assertJson(['recorded' => true]);

        Queue::assertPushed(SendViolationFcmNotification::class);
    }

    public function test_job_mengirim_hanya_ke_pengawas_ruangan_dan_admin(): void
    {
        $roomA = Room::factory()->create(['room_number' => 101]);
        $roomB = Room::factory()->create(['room_number' => 102]);
        $subject = Subject::factory()->create();
        $period = ExamPeriod::factory()->create([
            'exam_date' => now()->toDateString(),
            'start_time' => '08:00:00',
            'end_time' => '10:30:00',
        ]);
        $scheduleA = $this->makeSchedule($roomA, $subject, $period);

        $student = Student::factory()->create(['room_id' => $roomA->id, 'class_name' => 'XI RPL 1']);
        $session = ExamSession::factory()->create([
            'student_id' => $student->id,
            'exam_schedule_id' => $scheduleA->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);
        $violation = Violation::factory()->create(['exam_session_id' => $session->id]);

        // Pengawas ruangan A (rotasi hari ini) + pengawas ruangan B + admin + peserta sembarang
        $pengawasA = Supervisor::factory()->create(['room_id' => Room::factory()->create()->id])->user;
        $pengawasB = Supervisor::factory()->create(['room_id' => $roomB->id])->user;
        $admin = User::factory()->admin()->create();
        $otherPeserta = User::factory()->peserta()->create();

        // Rotasi: pengawasA bertugas di roomA hari ini, pengawasB di roomB
        SupervisorRoomAssignment::factory()->create([
            'exam_period_id' => $period->id,
            'exam_date' => $period->exam_date,
            'supervisor_id' => Supervisor::where('user_id', $pengawasA->id)->first()->id,
            'room_id' => $roomA->id,
        ]);
        SupervisorRoomAssignment::factory()->create([
            'exam_period_id' => $period->id,
            'exam_date' => $period->exam_date,
            'supervisor_id' => Supervisor::where('user_id', $pengawasB->id)->first()->id,
            'room_id' => $roomB->id,
        ]);

        $tokenA = UserFcmToken::factory()->create(['user_id' => $pengawasA->id, 'token' => str_repeat('a', 40).'_A']);
        $tokenB = UserFcmToken::factory()->create(['user_id' => $pengawasB->id, 'token' => str_repeat('b', 40).'_B']);
        $tokenAdmin = UserFcmToken::factory()->create(['user_id' => $admin->id, 'token' => str_repeat('c', 40).'_Admin']);
        $tokenPeserta = UserFcmToken::factory()->create(['user_id' => $otherPeserta->id, 'token' => str_repeat('d', 40).'_Peserta']);

        $capturedTokens = null;
        $report = MulticastSendReport::withItems([
            SendReport::success(MessageTarget::with(MessageTarget::TOKEN, $tokenA->token), ['name' => 'projects/x/messages/1']),
            SendReport::success(MessageTarget::with(MessageTarget::TOKEN, $tokenAdmin->token), ['name' => 'projects/x/messages/2']),
        ]);

        $this->mock(Messaging::class, function ($mock) use (&$capturedTokens, $report) {
            $mock->shouldReceive('sendMulticast')
                ->once()
                ->andReturnUsing(function (CloudMessage $msg, array $tokens) use (&$capturedTokens, $report) {
                    $capturedTokens = $tokens;

                    return $report;
                });
        });

        (new SendViolationFcmNotification($violation->id))->handle(app(Messaging::class));

        $this->assertNotNull($capturedTokens);
        $this->assertContains($tokenA->token, $capturedTokens);
        $this->assertContains($tokenAdmin->token, $capturedTokens);
        $this->assertNotContains($tokenB->token, $capturedTokens);
        $this->assertNotContains($tokenPeserta->token, $capturedTokens);
    }

    public function test_job_fallback_ke_room_statis_bila_tidak_ada_rotasi(): void
    {
        $roomA = Room::factory()->create();
        $subject = Subject::factory()->create();
        $period = ExamPeriod::factory()->create([
            'exam_date' => now()->toDateString(),
            'start_time' => '08:00:00',
            'end_time' => '10:30:00',
        ]);
        $schedule = $this->makeSchedule($roomA, $subject, $period);
        $student = Student::factory()->create(['room_id' => $roomA->id, 'class_name' => 'XI RPL 1']);
        $session = ExamSession::factory()->create([
            'student_id' => $student->id,
            'exam_schedule_id' => $schedule->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);
        $violation = Violation::factory()->create(['exam_session_id' => $session->id]);

        // Tidak ada SupervisorRoomAssignment hari ini → fallback ke supervisors.room_id
        $pengawasA = Supervisor::factory()->create(['room_id' => $roomA->id])->user;
        $tokenA = UserFcmToken::factory()->create(['user_id' => $pengawasA->id, 'token' => str_repeat('f', 40).'_fallback']);

        $report = MulticastSendReport::withItems([
            SendReport::success(MessageTarget::with(MessageTarget::TOKEN, $tokenA->token), ['name' => 'ok']),
        ]);

        $captured = null;
        $this->mock(Messaging::class, function ($mock) use (&$captured, $report) {
            $mock->shouldReceive('sendMulticast')->once()
                ->andReturnUsing(function ($msg, $tokens) use (&$captured, $report) {
                    $captured = $tokens;

                    return $report;
                });
        });

        (new SendViolationFcmNotification($violation->id))->handle(app(Messaging::class));

        $this->assertContains($tokenA->token, $captured);
    }

    public function test_job_menghapus_token_unknown_dan_invalid(): void
    {
        $room = Room::factory()->create();
        $subject = Subject::factory()->create();
        $period = ExamPeriod::factory()->create([
            'exam_date' => now()->toDateString(),
            'start_time' => '08:00:00',
            'end_time' => '10:30:00',
        ]);
        $schedule = $this->makeSchedule($room, $subject, $period);
        $student = Student::factory()->create(['room_id' => $room->id, 'class_name' => 'XI RPL 1']);
        $session = ExamSession::factory()->create([
            'student_id' => $student->id,
            'exam_schedule_id' => $schedule->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);
        $violation = Violation::factory()->create(['exam_session_id' => $session->id]);

        $admin = User::factory()->admin()->create();
        $good = UserFcmToken::factory()->create(['user_id' => $admin->id, 'token' => str_repeat('g', 40).'_good']);
        $unknown = UserFcmToken::factory()->create(['user_id' => $admin->id, 'token' => str_repeat('u', 40).'_unknown']);
        $invalid = UserFcmToken::factory()->create(['user_id' => $admin->id, 'token' => str_repeat('i', 40).'_invalid']);

        // Pengawas agar ruangan ter-cover (fallback)
        Supervisor::factory()->create(['room_id' => $room->id]);

        // Rotasi kosong → fallback ke pengawas statis, tapi admin tetap ke-cover
        $report = MulticastSendReport::withItems([
            SendReport::success(MessageTarget::with(MessageTarget::TOKEN, $good->token), ['name' => 'ok']),
            SendReport::failure(MessageTarget::with(MessageTarget::TOKEN, $unknown->token), NotFound::becauseTokenNotFound($unknown->token)),
            SendReport::failure(MessageTarget::with(MessageTarget::TOKEN, $invalid->token), new \RuntimeException('The registration token is not a valid FCM registration token: '.$invalid->token)),
        ]);

        $this->mock(Messaging::class, fn ($mock) => $mock->shouldReceive('sendMulticast')->once()->andReturn($report));

        (new SendViolationFcmNotification($violation->id))->handle(app(Messaging::class));

        $this->assertDatabaseHas('user_fcm_tokens', ['token' => $good->token]);
        $this->assertDatabaseMissing('user_fcm_tokens', ['token' => $unknown->token]);
        $this->assertDatabaseMissing('user_fcm_tokens', ['token' => $invalid->token]);
    }

    public function test_job_tidak_error_bila_tidak_ada_token(): void
    {
        $room = Room::factory()->create();
        $subject = Subject::factory()->create();
        $period = ExamPeriod::factory()->create([
            'exam_date' => now()->toDateString(),
            'start_time' => '08:00:00',
            'end_time' => '10:30:00',
        ]);
        $schedule = $this->makeSchedule($room, $subject, $period);
        $student = Student::factory()->create(['room_id' => $room->id, 'class_name' => 'XI RPL 1']);
        $session = ExamSession::factory()->create([
            'student_id' => $student->id,
            'exam_schedule_id' => $schedule->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);
        $violation = Violation::factory()->create(['exam_session_id' => $session->id]);

        // Tidak ada token sama sekali → sendMulticast tidak boleh dipanggil
        $this->mock(Messaging::class, fn ($mock) => $mock->shouldNotReceive('sendMulticast'));

        (new SendViolationFcmNotification($violation->id))->handle(app(Messaging::class));

        $this->assertTrue(true);
    }

    public function test_job_tidak_error_bila_violation_tidak_ditemukan(): void
    {
        $this->mock(Messaging::class, fn ($mock) => $mock->shouldNotReceive('sendMulticast'));

        (new SendViolationFcmNotification(999999))->handle(app(Messaging::class));

        $this->assertTrue(true);
    }
}

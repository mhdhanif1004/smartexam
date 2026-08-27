<?php

namespace Tests\Feature;

use App\Models\ExamPeriod;
use App\Models\ExamSchedule;
use App\Models\Room;
use App\Models\Subject;
use App\Models\Supervisor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SupervisorDashboardPeriodFilterTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    private Supervisor $supervisor;

    private User $user;

    private ExamPeriod $period1;

    private ExamPeriod $period2;

    private ExamPeriod $period3;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-25 13:00:00'));

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

        // Buat 1 schedule per period di room yang sama
        $subject1 = Subject::factory()->create(['name' => 'Indonesia']);
        $subject2 = Subject::factory()->create(['name' => 'Inggris']);
        $subject3 = Subject::factory()->create(['name' => 'Matematika']);

        ExamSchedule::factory()->create([
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

        ExamSchedule::factory()->create([
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

        ExamSchedule::factory()->create([
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

        // Assign pengawas HANYA ke Sesi 2 di room ini
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

    public function test_dashboard_shows_only_schedules_from_assigned_period(): void
    {
        $this->actingAs($this->user)
            ->get(route('pengawas.dashboard'))
            ->assertOk()
            ->assertSee('Inggris')       // Sesi 2 → assigned
            ->assertDontSee('Indonesia')  // Sesi 1 → NOT assigned
            ->assertDontSee('Matematika'); // Sesi 3 → NOT assigned
    }

    public function test_dashboard_with_multiple_assignments_shows_all_assigned(): void
    {
        // Tambah assignment ke Sesi 3 juga
        DB::table('supervisor_room_assignments')->insert([
            'exam_period_id' => $this->period3->id,
            'exam_date' => now()->toDateString(),
            'supervisor_id' => $this->supervisor->id,
            'room_id' => $this->room->id,
            'rotation_index' => 2,
        ]);

        $this->actingAs($this->user)
            ->get(route('pengawas.dashboard'))
            ->assertOk()
            ->assertSee('Inggris')       // Sesi 2 → assigned
            ->assertSee('Matematika')     // Sesi 3 → now assigned
            ->assertDontSee('Indonesia'); // Sesi 1 → still NOT assigned
    }

    public function test_dashboard_without_assignments_shows_nothing(): void
    {
        // Hapus semua assignment
        DB::table('supervisor_room_assignments')
            ->where('supervisor_id', $this->supervisor->id)
            ->delete();

        $this->actingAs($this->user)
            ->get(route('pengawas.dashboard'))
            ->assertOk()
            ->assertSee('Tidak ada jadwal ujian di ruangan Anda hari ini.')
            ->assertDontSee('Indonesia')
            ->assertDontSee('Inggris')
            ->assertDontSee('Matematika');
    }
}

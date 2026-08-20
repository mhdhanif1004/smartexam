<?php

namespace Tests\Feature;

use App\Models\ExamPeriod;
use App\Models\ExamSchedule;
use App\Models\Question;
use App\Models\Room;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ExamScheduleByDateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Subject $subject;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->subject = Subject::factory()->create(['name' => 'Matematika']);
        Question::factory()->create(['subject_id' => $this->subject->id]);
        $this->room = Room::factory()->create(['room_number' => 1]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeSchedule(array $attributes = []): ExamSchedule
    {
        return ExamSchedule::factory()->create(array_merge([
            'room_id' => $this->room->id,
            'subject_id' => $this->subject->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => '2026-08-10',
            'start_time' => '08:00:00',
            'end_time' => '09:30:00',
            'duration_minutes' => 90,
            'status' => ExamSchedule::STATUS_SCHEDULED,
        ], $attributes));
    }

    public function test_index_lists_unique_dates_with_schedule_count(): void
    {
        $this->makeSchedule();
        $this->makeSchedule(['start_time' => '10:00:00', 'end_time' => '11:30:00']);
        $this->makeSchedule(['exam_date' => '2026-08-11']);

        $this->actingAs($this->admin)
            ->get(route('admin.exam-schedules.index'))
            ->assertOk()
            ->assertSee('10 Agustus 2026')
            ->assertSee('11 Agustus 2026')
            ->assertSee('2 jadwal')
            ->assertDontSee('Matematika');
    }

    public function test_by_date_shows_only_schedules_of_that_date(): void
    {
        $this->makeSchedule();
        $this->makeSchedule(['exam_date' => '2026-08-11']);

        Carbon::setTestNow(Carbon::parse('2026-08-09 12:00:00'));

        $this->actingAs($this->admin)
            ->get(route('admin.exam-schedules.by-date', ['date' => '2026-08-10']))
            ->assertOk()
            ->assertSee('Matematika')
            ->assertSee('1 ruangan')
            ->assertSee('90 menit');
    }

    public function test_by_date_groups_multiple_sessions_of_same_subject_into_one_row(): void
    {
        $room2 = Room::factory()->create(['room_number' => 2]);
        $room3 = Room::factory()->create(['room_number' => 3]);

        // 3 sessions of Matematika on the same day, different times
        $this->makeSchedule(['room_id' => $this->room->id, 'start_time' => '07:30:00', 'end_time' => '08:30:00', 'duration_minutes' => 60]);
        $this->makeSchedule(['room_id' => $room2->id, 'start_time' => '09:45:00', 'end_time' => '10:45:00', 'duration_minutes' => 60]);
        $this->makeSchedule(['room_id' => $room3->id, 'start_time' => '12:00:00', 'end_time' => '13:00:00', 'duration_minutes' => 60]);

        Carbon::setTestNow(Carbon::parse('2026-08-09 12:00:00'));

        $response = $this->actingAs($this->admin)
            ->get(route('admin.exam-schedules.by-date', ['date' => '2026-08-10']))
            ->assertOk();

        // Only 1 row for Matematika (grouped by subject + date)
        $response->assertSee('Matematika');
        $response->assertSee('07:30 - 13:00');
        $response->assertSee('3 ruangan');
        $response->assertSee('60 menit');
    }

    public function test_detail_endpoint_returns_sessions_grouped_by_period(): void
    {
        $room2 = Room::factory()->create(['room_number' => 2]);

        $period1 = ExamPeriod::factory()->create([
            'name' => 'Sesi 1',
            'grade_level' => 'X',
            'exam_date' => '2026-08-10',
            'start_time' => '07:30:00',
            'end_time' => '08:30:00',
        ]);

        $period2 = ExamPeriod::factory()->create([
            'name' => 'Sesi 2',
            'grade_level' => 'XI',
            'exam_date' => '2026-08-10',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
        ]);

        // Sesi 1: 2 rooms
        $schedule1 = $this->makeSchedule([
            'room_id' => $this->room->id,
            'exam_period_id' => $period1->id,
            'start_time' => '07:30:00',
            'end_time' => '08:30:00',
            'duration_minutes' => 60,
            'class_name' => 'X',
        ]);
        $this->makeSchedule([
            'room_id' => $room2->id,
            'exam_period_id' => $period1->id,
            'start_time' => '07:30:00',
            'end_time' => '08:30:00',
            'duration_minutes' => 60,
            'class_name' => 'X',
        ]);

        // Sesi 2: 2 rooms
        $this->makeSchedule([
            'room_id' => $this->room->id,
            'exam_period_id' => $period2->id,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'duration_minutes' => 60,
            'class_name' => 'XI',
        ]);
        $this->makeSchedule([
            'room_id' => $room2->id,
            'exam_period_id' => $period2->id,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'duration_minutes' => 60,
            'class_name' => 'XI',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.exam-schedules.detail', $schedule1))
            ->assertOk()
            ->assertJsonStructure([
                'subject_name',
                'date',
                'total_rooms',
                'total_students',
                'sessions' => [
                    '*' => ['label', 'period_name', 'grade_level', 'start_time', 'end_time', 'rooms'],
                ],
            ]);

        $json = $response->json();

        $this->assertCount(2, $json['sessions']);
        $this->assertEquals('Matematika', $json['subject_name']);
        $this->assertEquals(2, $json['total_rooms']);
    }

    public function test_by_date_requires_valid_date(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.exam-schedules.by-date'))
            ->assertSessionHasErrors('date');
    }
}

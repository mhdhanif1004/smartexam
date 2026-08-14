<?php

namespace Tests\Feature;

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
            ->assertSee('Ruang 1')
            ->assertSee('XI RPL 1');
    }

    public function test_by_date_requires_valid_date(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.exam-schedules.by-date'))
            ->assertSessionHasErrors('date');
    }
}

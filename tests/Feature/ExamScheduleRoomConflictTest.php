<?php

namespace Tests\Feature;

use App\Models\ExamSchedule;
use App\Models\Question;
use App\Models\Room;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamScheduleRoomConflictTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Subject $subject;

    private Subject $subjectWithoutQuestions;

    private Room $room;

    private Room $otherRoom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->subject = Subject::factory()->create();
        $this->subjectWithoutQuestions = Subject::factory()->create();
        Question::factory()->create(['subject_id' => $this->subject->id]);
        $this->room = Room::factory()->create();
        $this->otherRoom = Room::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'subject_id' => $this->subject->id,
            'room_id' => $this->room->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => '2026-08-10',
            'start_time' => '08:00',
            'duration_minutes' => 90,
            'status' => ExamSchedule::STATUS_SCHEDULED,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeSchedule(array $attributes = []): ExamSchedule
    {
        return ExamSchedule::factory()->create(array_merge([
            'room_id' => $this->room->id,
            'subject_id' => $this->subject->id,
            'exam_date' => '2026-08-10',
            'start_time' => '08:00:00',
            'end_time' => '09:30:00',
            'duration_minutes' => 90,
        ], $attributes));
    }

    public function test_edit_schedule_without_any_changes_does_not_trigger_false_room_conflict(): void
    {
        $schedule = $this->makeSchedule();

        $this->actingAs($this->admin)
            ->put(route('admin.exam-schedules.update', $schedule), $this->payload())
            ->assertRedirect(route('admin.exam-schedules.index'));

        $this->assertDatabaseHas('exam_schedules', [
            'id' => $schedule->id,
            'room_id' => $this->room->id,
            'start_time' => '08:00:00',
        ]);
    }

    public function test_editing_schedule_into_real_overlap_with_another_schedule_is_rejected(): void
    {
        $scheduleA = $this->makeSchedule();
        $this->makeSchedule([
            'class_name' => 'XII RPL 1',
            'start_time' => '09:00:00',
            'end_time' => '10:30:00',
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.exam-schedules.update', $scheduleA), $this->payload([
                'class_name' => 'XII RPL 1',
                'start_time' => '09:00',
                'duration_minutes' => 90,
            ]))
            ->assertSessionHasErrors('room_id');

        $this->assertDatabaseHas('exam_schedules', [
            'id' => $scheduleA->id,
            'start_time' => '08:00:00',
        ]);
    }

    public function test_creating_schedule_with_real_room_overlap_is_rejected(): void
    {
        $this->makeSchedule();

        $this->actingAs($this->admin)
            ->post(route('admin.exam-schedules.store'), $this->payload([
                'class_name' => 'XII RPL 1',
                'start_time' => '09:00',
                'duration_minutes' => 90,
            ]))
            ->assertSessionHasErrors('room_id');

        $this->assertDatabaseCount('exam_schedules', 1);
    }

    public function test_editing_one_of_two_overlapping_schedules_to_an_available_room_succeeds(): void
    {
        $scheduleA = $this->makeSchedule();
        $scheduleB = $this->makeSchedule([
            'class_name' => 'XII RPL 1',
            'start_time' => '09:00:00',
            'end_time' => '10:30:00',
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.exam-schedules.update', $scheduleB), $this->payload([
                'room_id' => $this->otherRoom->id,
                'class_name' => 'XII RPL 1',
                'start_time' => '09:00',
                'duration_minutes' => 90,
            ]))
            ->assertRedirect(route('admin.exam-schedules.index'));

        $this->assertDatabaseHas('exam_schedules', [
            'id' => $scheduleB->id,
            'room_id' => $this->otherRoom->id,
        ]);

        $this->assertSame($this->room->id, $scheduleA->fresh()->room_id);
    }

    public function test_creating_schedule_for_subject_without_active_questions_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.exam-schedules.store'), $this->payload([
                'subject_id' => $this->subjectWithoutQuestions->id,
            ]))
            ->assertSessionHasErrors('subject_id');

        $this->assertDatabaseCount('exam_schedules', 0);
    }

    public function test_same_room_allows_back_to_back_schedules_without_overlap(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.exam-schedules.store'), $this->payload([
                'start_time' => '07:00',
                'duration_minutes' => 120,
            ]))
            ->assertRedirect(route('admin.exam-schedules.index'));

        $this->actingAs($this->admin)
            ->post(route('admin.exam-schedules.store'), $this->payload([
                'start_time' => '09:00',
                'duration_minutes' => 120,
            ]))
            ->assertRedirect(route('admin.exam-schedules.index'));

        $this->assertDatabaseCount('exam_schedules', 2);
    }

    public function test_same_room_rejects_overlapping_schedules(): void
    {
        $this->makeSchedule([
            'start_time' => '07:00:00',
            'end_time' => '09:00:00',
            'duration_minutes' => 120,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.exam-schedules.store'), $this->payload([
                'start_time' => '08:00',
                'duration_minutes' => 120,
            ]))
            ->assertSessionHasErrors('room_id');

        $this->assertDatabaseCount('exam_schedules', 1);
    }

    public function test_editing_schedule_to_non_overlapping_time_is_allowed(): void
    {
        $schedule = $this->makeSchedule();

        $this->actingAs($this->admin)
            ->put(route('admin.exam-schedules.update', $schedule), $this->payload([
                'start_time' => '10:00',
                'duration_minutes' => 60,
            ]))
            ->assertRedirect(route('admin.exam-schedules.index'));

        $this->assertDatabaseHas('exam_schedules', [
            'id' => $schedule->id,
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
        ]);
    }

    public function test_overlap_error_message_mentions_subject_and_time_range(): void
    {
        $subject = Subject::factory()->create(['name' => 'Matematika']);
        Question::factory()->create(['subject_id' => $subject->id]);

        $this->makeSchedule([
            'subject_id' => $subject->id,
            'start_time' => '07:00:00',
            'end_time' => '09:00:00',
            'duration_minutes' => 120,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.exam-schedules.store'), $this->payload([
                'subject_id' => $subject->id,
                'start_time' => '08:00',
                'duration_minutes' => 120,
            ]))
            ->assertSessionHasErrors('room_id');

        $message = session('errors')->get('room_id')[0];
        $this->assertStringContainsString('bentrok dengan ujian Matematika pukul 07:00–09:00', $message);
    }

    public function test_same_room_detects_overlap_across_midnight(): void
    {
        $this->makeSchedule([
            'start_time' => '23:30:00',
            'end_time' => '00:30:00',
            'duration_minutes' => 60,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.exam-schedules.store'), $this->payload([
                'start_time' => '00:00',
                'duration_minutes' => 60,
            ]))
            ->assertSessionHasErrors('room_id');

        $this->assertDatabaseCount('exam_schedules', 1);
    }

    public function test_switching_schedule_to_subject_without_active_questions_is_rejected(): void
    {
        $schedule = $this->makeSchedule();

        $this->actingAs($this->admin)
            ->put(route('admin.exam-schedules.update', $schedule), $this->payload([
                'subject_id' => $this->subjectWithoutQuestions->id,
            ]))
            ->assertSessionHasErrors('subject_id');

        $this->assertDatabaseHas('exam_schedules', [
            'id' => $schedule->id,
            'subject_id' => $this->subject->id,
        ]);
    }
}

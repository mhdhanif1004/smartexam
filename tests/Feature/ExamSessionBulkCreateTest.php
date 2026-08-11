<?php

namespace Tests\Feature;

use App\Models\ExamPeriod;
use App\Models\ExamSchedule;
use App\Models\Question;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamSessionBulkCreateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ExamPeriod $period;

    private Subject $subjectA;

    private Subject $subjectB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        Student::factory()->create(['class_name' => 'XI RPL 1']);

        $this->subjectA = Subject::factory()->create(['name' => 'Matematika']);
        $this->subjectB = Subject::factory()->create(['name' => 'B. Indonesia']);
        Question::factory()->create(['subject_id' => $this->subjectA->id]);
        Question::factory()->create(['subject_id' => $this->subjectB->id]);

        $this->period = ExamPeriod::factory()->create([
            'name' => 'Sesi 1',
            'exam_date' => '2026-08-10',
            'start_time' => '07:30:00',
            'end_time' => '11:00:00',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'class_name' => 'XI RPL 1',
            'rooms' => [1],
            'subjects' => [
                ['subject_id' => $this->subjectA->id, 'start_time' => '07:30', 'duration_minutes' => 70],
                ['subject_id' => $this->subjectB->id, 'start_time' => '08:45', 'duration_minutes' => 70],
            ],
        ], $overrides);
    }

    public function test_groups_create_page_submits_room_checkboxes_under_rooms_key(): void
    {
        $room = Room::factory()->create(['name' => 'R. 101']);

        $this->actingAs($this->admin)
            ->get(route('admin.exam-periods.groups.create', $this->period))
            ->assertOk()
            ->assertSee('name="rooms[]"', false)
            ->assertSee("name=\"rooms[]\" :value=\"{$room->id}\"", false);
    }

    public function test_bulk_create_success_creates_room_times_subject_combinations(): void
    {
        $room1 = Room::factory()->create(['name' => 'R. 101']);
        $room2 = Room::factory()->create(['name' => 'R. 102']);
        $room3 = Room::factory()->create(['name' => 'R. 103']);

        $this->actingAs($this->admin)
            ->from(route('admin.exam-periods.groups.create', $this->period))
            ->post(route('admin.exam-periods.groups.store', $this->period), $this->payload([
                'rooms' => [$room1->id, $room2->id, $room3->id],
            ]))
            ->assertRedirect(route('admin.exam-periods.show', $this->period))
            ->assertSessionHas('success', '6 jadwal ujian berhasil dibuat untuk Sesi 1.');

        $this->assertDatabaseCount('exam_schedules', 6);

        foreach ([$room1, $room2, $room3] as $room) {
            foreach ([
                [$this->subjectA->id, '07:30:00', '08:40:00', 70],
                [$this->subjectB->id, '08:45:00', '09:55:00', 70],
            ] as [$subjectId, $start, $end, $duration]) {
                $this->assertDatabaseHas('exam_schedules', [
                    'room_id' => $room->id,
                    'subject_id' => $subjectId,
                    'exam_period_id' => $this->period->id,
                    'class_name' => 'XI RPL 1',
                    'exam_date' => '2026-08-10',
                    'start_time' => $start,
                    'end_time' => $end,
                    'duration_minutes' => $duration,
                    'status' => ExamSchedule::STATUS_SCHEDULED,
                ]);
            }
        }
    }

    public function test_bulk_create_rejects_overlap_within_same_request_and_rolls_back_fully(): void
    {
        $room = Room::factory()->create(['name' => 'R. 101']);

        $this->actingAs($this->admin)
            ->from(route('admin.exam-periods.groups.create', $this->period))
            ->post(route('admin.exam-periods.groups.store', $this->period), $this->payload([
                'rooms' => [$room->id],
                'subjects' => [
                    ['subject_id' => $this->subjectA->id, 'start_time' => '07:30', 'duration_minutes' => 60],
                    ['subject_id' => $this->subjectB->id, 'start_time' => '08:00', 'duration_minutes' => 60],
                ],
            ]))
            ->assertSessionHasErrors('subjects');

        $this->assertDatabaseCount('exam_schedules', 0);

        $error = session('errors')->get('subjects')[0] ?? '';
        $this->assertStringContainsString('R. 101', $error);
        $this->assertStringContainsString('bentrok', $error);
        $this->assertStringContainsString('kelompok yang sama', $error);
    }

    public function test_bulk_create_rejects_conflict_with_existing_schedule_and_rolls_back_fully(): void
    {
        $room = Room::factory()->create(['name' => 'R. 101']);

        ExamSchedule::factory()->create([
            'room_id' => $room->id,
            'subject_id' => $this->subjectA->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => '2026-08-10',
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'duration_minutes' => 60,
            'status' => ExamSchedule::STATUS_SCHEDULED,
        ]);

        $this->actingAs($this->admin)
            ->from(route('admin.exam-periods.groups.create', $this->period))
            ->post(route('admin.exam-periods.groups.store', $this->period), $this->payload([
                'rooms' => [$room->id],
                'subjects' => [
                    ['subject_id' => $this->subjectB->id, 'start_time' => '08:30', 'duration_minutes' => 60],
                ],
            ]))
            ->assertSessionHasErrors('subjects');

        $this->assertDatabaseCount('exam_schedules', 1);

        $error = session('errors')->get('subjects')[0] ?? '';
        $this->assertStringContainsString('R. 101', $error);
        $this->assertStringContainsString('bentrok', $error);
        $this->assertStringContainsString('Matematika', $error);
        $this->assertStringContainsString('sudah ada', $error);
    }

    public function test_two_groups_in_same_period_with_different_rooms_do_not_conflict(): void
    {
        $roomA = Room::factory()->create(['name' => 'R. Kelas 12']);
        $roomB = Room::factory()->create(['name' => 'R. Kelas 11']);

        $this->actingAs($this->admin)
            ->post(route('admin.exam-periods.groups.store', $this->period), $this->payload([
                'class_name' => 'XI RPL 1',
                'rooms' => [$roomA->id],
                'subjects' => [
                    ['subject_id' => $this->subjectA->id, 'start_time' => '07:30', 'duration_minutes' => 60],
                ],
            ]))
            ->assertSessionHas('success');

        $this->actingAs($this->admin)
            ->post(route('admin.exam-periods.groups.store', $this->period), $this->payload([
                'class_name' => 'XI RPL 1',
                'rooms' => [$roomB->id],
                'subjects' => [
                    ['subject_id' => $this->subjectB->id, 'start_time' => '07:30', 'duration_minutes' => 60],
                ],
            ]))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('exam_schedules', 2);
    }
}

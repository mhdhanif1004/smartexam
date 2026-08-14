<?php

namespace Tests\Feature;

use App\Models\ExamPeriod;
use App\Models\ExamRoomAssignment;
use App\Models\ExamSchedule;
use App\Models\Question;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginCardSessionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_card_preview_shows_session_name_from_exam_period(): void
    {
        $room = Room::factory()->create(['room_number' => 101]);
        $student = Student::factory()->create(['class_name' => 'XI RPL 1', 'room_id' => $room->id]);
        $subject = Subject::factory()->create(['name' => 'Matematika']);
        Question::factory()->create(['subject_id' => $subject->id]);

        $period = ExamPeriod::factory()->create([
            'name' => 'Sesi 1',
            'exam_date' => '2026-08-10',
            'start_time' => '07:30:00',
            'end_time' => '11:00:00',
        ]);

        ExamSchedule::factory()->create([
            'room_id' => $room->id,
            'subject_id' => $subject->id,
            'exam_period_id' => $period->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => '2026-08-10',
            'start_time' => '07:30:00',
            'end_time' => '08:40:00',
            'duration_minutes' => 70,
        ]);

        // Sumber kebenaran kartu: exam_room_assignments
        ExamRoomAssignment::factory()->create([
            'student_id' => $student->id,
            'exam_period_id' => $period->id,
            'room_id' => $room->id,
            'seat_number' => 5,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.student-cards.preview'), ['student_ids' => [$student->id]])
            ->assertOk()
            ->assertSee('<td class="lbl">Sesi</td>', false)
            ->assertSee('Sesi 1');
    }

    public function test_card_preview_merges_multiple_sessions_separated_by_comma(): void
    {
        $room = Room::factory()->create(['room_number' => 101]);
        $student = Student::factory()->create(['class_name' => 'XI RPL 1', 'room_id' => $room->id]);
        $subjectA = Subject::factory()->create(['name' => 'Matematika']);
        $subjectB = Subject::factory()->create(['name' => 'B. Indonesia']);
        Question::factory()->create(['subject_id' => $subjectA->id]);
        Question::factory()->create(['subject_id' => $subjectB->id]);

        $period1 = ExamPeriod::factory()->create(['name' => 'Sesi 1', 'exam_date' => '2026-08-10', 'start_time' => '07:30:00']);
        $period2 = ExamPeriod::factory()->create(['name' => 'Sesi 2', 'exam_date' => '2026-08-10', 'start_time' => '11:05:00']);

        ExamSchedule::factory()->create([
            'room_id' => $room->id,
            'subject_id' => $subjectA->id,
            'exam_period_id' => $period1->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => '2026-08-10',
            'start_time' => '07:30:00',
            'end_time' => '08:40:00',
            'duration_minutes' => 70,
        ]);
        ExamSchedule::factory()->create([
            'room_id' => $room->id,
            'subject_id' => $subjectB->id,
            'exam_period_id' => $period2->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => '2026-08-10',
            'start_time' => '11:05:00',
            'end_time' => '12:15:00',
            'duration_minutes' => 70,
        ]);

        // Sumber kebenaran kartu: exam_room_assignments
        ExamRoomAssignment::factory()->create([
            'student_id' => $student->id,
            'exam_period_id' => $period1->id,
            'room_id' => $room->id,
            'seat_number' => 5,
        ]);
        ExamRoomAssignment::factory()->create([
            'student_id' => $student->id,
            'exam_period_id' => $period2->id,
            'room_id' => $room->id,
            'seat_number' => 6,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.student-cards.preview'), ['student_ids' => [$student->id]])
            ->assertOk()
            ->assertSee('Sesi 1, Sesi 2');
    }

    public function test_card_preview_falls_back_to_dash_when_schedule_has_no_session(): void
    {
        $room = Room::factory()->create(['room_number' => 101]);
        $student = Student::factory()->create(['class_name' => 'XI RPL 1', 'room_id' => $room->id]);
        $subject = Subject::factory()->create(['name' => 'Matematika']);
        Question::factory()->create(['subject_id' => $subject->id]);

        ExamSchedule::factory()->create([
            'room_id' => $room->id,
            'subject_id' => $subject->id,
            'exam_period_id' => null,
            'class_name' => 'XI RPL 1',
            'exam_date' => '2026-08-10',
            'start_time' => '08:00:00',
            'end_time' => '09:30:00',
            'duration_minutes' => 90,
        ]);

        // Tidak ada ExamRoomAssignment -> kartu harus tampil "-"
        $this->actingAs($this->admin)
            ->post(route('admin.student-cards.preview'), ['student_ids' => [$student->id]])
            ->assertOk()
            ->assertSee('<td class="lbl">Sesi</td>', false)
            ->assertSee('<td class="val">-</td>', false);
    }

    public function test_index_page_shows_session_badge_from_exam_period(): void
    {
        $room = Room::factory()->create(['room_number' => 101]);
        $student = Student::factory()->create(['class_name' => 'XI RPL 1', 'room_id' => $room->id]);
        $subject = Subject::factory()->create(['name' => 'Matematika']);
        Question::factory()->create(['subject_id' => $subject->id]);

        $period = ExamPeriod::factory()->create(['name' => 'Sesi 1', 'exam_date' => '2026-08-10']);

        ExamSchedule::factory()->create([
            'room_id' => $room->id,
            'subject_id' => $subject->id,
            'exam_period_id' => $period->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => '2026-08-10',
            'start_time' => '07:30:00',
            'end_time' => '08:40:00',
            'duration_minutes' => 70,
        ]);

        // Index page baca dari exam_room_assignments
        ExamRoomAssignment::factory()->create([
            'student_id' => $student->id,
            'exam_period_id' => $period->id,
            'room_id' => $room->id,
            'seat_number' => 1,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.student-cards.index', ['class' => 'XI RPL 1']))
            ->assertOk()
            ->assertSee('Sesi 1');
    }
}

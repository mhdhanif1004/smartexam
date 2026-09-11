<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ExamSchedule;
use App\Models\Room;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExamScheduleClassroomPlacementTest extends TestCase
{
    use RefreshDatabase;

    // ── Migration & Schema (functional) ──────────────────────────────

    public function test_migration_adds_classroom_id_column(): void
    {
        $classroom = Classroom::factory()->create();

        $schedule = ExamSchedule::factory()->create([
            'room_id' => null,
            'classroom_id' => $classroom->id,
        ]);

        $this->assertDatabaseHas('exam_schedules', [
            'id' => $schedule->id,
            'classroom_id' => $classroom->id,
            'room_id' => null,
        ]);
    }

    // ── XOR Validation (model boot defense) ──────────────────────────

    public function test_xor_both_null_rejected(): void
    {
        $this->expectException(ValidationException::class);

        ExamSchedule::factory()->create([
            'room_id' => null,
            'classroom_id' => null,
        ]);
    }

    public function test_xor_both_set_rejected(): void
    {
        $room = Room::factory()->create();
        $classroom = Classroom::factory()->create();

        $this->expectException(ValidationException::class);

        ExamSchedule::factory()->create([
            'room_id' => $room->id,
            'classroom_id' => $classroom->id,
        ]);
    }

    public function test_xor_room_only_accepted(): void
    {
        $room = Room::factory()->create();

        $schedule = ExamSchedule::factory()->create([
            'room_id' => $room->id,
            'classroom_id' => null,
        ]);

        $this->assertNotNull($schedule);
        $this->assertNotNull($schedule->room_id);
        $this->assertNull($schedule->classroom_id);
    }

    public function test_xor_classroom_only_accepted(): void
    {
        $classroom = Classroom::factory()->create();

        $schedule = ExamSchedule::factory()->create([
            'room_id' => null,
            'classroom_id' => $classroom->id,
        ]);

        $this->assertNotNull($schedule);
        $this->assertNull($schedule->room_id);
        $this->assertNotNull($schedule->classroom_id);
    }

    // ── participantStudentIds — classroom-based ──────────────────────

    public function test_participant_student_ids_returns_classroom_students_when_room_null(): void
    {
        $classroom = Classroom::factory()->create();
        $student1 = Student::factory()->create(['classroom_id' => $classroom->id]);
        $student2 = Student::factory()->create(['classroom_id' => $classroom->id]);
        $otherStudent = Student::factory()->create(); // different classroom

        $schedule = ExamSchedule::factory()->classroomBased($classroom)->create();

        $ids = $schedule->participantStudentIds();

        $this->assertContains($student1->id, $ids);
        $this->assertContains($student2->id, $ids);
        $this->assertNotContains($otherStudent->id, $ids);
    }

    // ── scopeAccessibleToStudent — classroom-based ───────────────────

    public function test_accessible_to_student_includes_classroom_based_schedule(): void
    {
        $classroom = Classroom::factory()->create();
        $student = Student::factory()->create(['classroom_id' => $classroom->id]);
        $schedule = ExamSchedule::factory()->classroomBased($classroom)->create();

        $found = ExamSchedule::query()->accessibleToStudent($student)->whereKey($schedule->id)->exists();
        $this->assertTrue($found);
    }

    public function test_accessible_to_student_rejects_classroom_mismatch(): void
    {
        $classroomA = Classroom::factory()->create();
        $classroomB = Classroom::factory()->create();
        $student = Student::factory()->create(['classroom_id' => $classroomA->id]);
        $schedule = ExamSchedule::factory()->classroomBased($classroomB)->create();

        $found = ExamSchedule::query()->accessibleToStudent($student)->whereKey($schedule->id)->exists();
        $this->assertFalse($found);
    }

    // ── scopeForParticipant — classroom-based ────────────────────────

    public function test_scope_for_participant_includes_classroom_based_schedule(): void
    {
        $classroom = Classroom::factory()->create();
        $student = Student::factory()->create(['classroom_id' => $classroom->id]);
        $schedule = ExamSchedule::factory()->classroomBased($classroom)->create();

        $found = ExamSchedule::query()->forParticipant($student->id)->whereKey($schedule->id)->exists();
        $this->assertTrue($found);
    }

    // ── findConflicting classroom variant ────────────────────────────

    public function test_find_conflicting_classroom_variant_detects_overlap(): void
    {
        $classroom = Classroom::factory()->create();

        $existing = ExamSchedule::factory()->classroomBased($classroom)->create([
            'exam_date' => '2026-10-15',
            'start_time' => '08:00:00',
            'duration_minutes' => 90,
        ]);

        $conflict = ExamSchedule::findConflicting(
            roomId: null,
            examDate: '2026-10-15',
            startMinutes: 480, // 08:00
            endMinutes: 600, // 10:00 — overlaps 08:00-09:30
            classroomId: $classroom->id,
        );

        $this->assertNotNull($conflict);
        $this->assertSame($existing->id, $conflict->id);
    }

    public function test_find_conflicting_classroom_variant_no_overlap(): void
    {
        $classroom = Classroom::factory()->create();
        ExamSchedule::factory()->classroomBased($classroom)->create([
            'exam_date' => '2026-10-15',
            'start_time' => '08:00:00',
            'duration_minutes' => 90,
        ]);

        $conflict = ExamSchedule::findConflicting(
            roomId: null,
            examDate: '2026-10-15',
            startMinutes: 600, // 10:00 — after existing
            endMinutes: 660, // 11:00
            classroomId: $classroom->id,
        );

        $this->assertNull($conflict);
    }

    public function test_find_conflicting_classroom_variant_ignores_other_classrooms(): void
    {
        $classroomA = Classroom::factory()->create();
        $classroomB = Classroom::factory()->create();

        ExamSchedule::factory()->classroomBased($classroomA)->create([
            'exam_date' => '2026-10-15',
            'start_time' => '08:00:00',
            'duration_minutes' => 90,
        ]);

        $conflict = ExamSchedule::findConflicting(
            roomId: null,
            examDate: '2026-10-15',
            startMinutes: 480,
            endMinutes: 570,
            classroomId: $classroomB->id,
        );

        $this->assertNull($conflict, 'Different classroom should not conflict.');
    }

    public function test_find_conflicting_null_both_returns_null(): void
    {
        $result = ExamSchedule::findConflicting(roomId: null, examDate: '2026-10-15', startMinutes: 480, endMinutes: 540, classroomId: null);
        $this->assertNull($result);
    }

    // ── Student::isAssignedToSchedule via classroom path ─────────────

    public function test_student_is_assigned_to_schedule_via_classroom(): void
    {
        $classroom = Classroom::factory()->create();
        $student = Student::factory()->create(['classroom_id' => $classroom->id]);
        $schedule = ExamSchedule::factory()->classroomBased($classroom)->create();

        $this->assertTrue($student->isAssignedToSchedule($schedule));
    }

    public function test_student_not_assigned_to_schedule_in_different_classroom(): void
    {
        $classroomA = Classroom::factory()->create();
        $classroomB = Classroom::factory()->create();
        $student = Student::factory()->create(['classroom_id' => $classroomA->id]);
        $schedule = ExamSchedule::factory()->classroomBased($classroomB)->create();

        $this->assertFalse($student->isAssignedToSchedule($schedule));
    }

    // ── K4: Admin attendance view "Tanpa Ruangan" for classroom schedules ──

    public function test_admin_by_date_does_not_crash_with_classroom_schedule(): void
    {
        $classroom = Classroom::factory()->create();
        ExamSchedule::factory()->classroomBased($classroom)->create([
            'exam_date' => now()->toDateString(),
            'start_time' => '08:00',
            'duration_minutes' => 90,
            'class_name' => $classroom->name,
        ]);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.exam-schedules.by-date', ['date' => now()->toDateString()]));

        $response->assertOk();
    }

    // ── Existing room-only regression ─────────────────────────────────

    public function test_room_based_schedule_still_works(): void
    {
        $room = Room::factory()->create();
        $student = Student::factory()->create(['room_id' => $room->id]);
        $schedule = ExamSchedule::factory()->create(['room_id' => $room->id, 'classroom_id' => null]);

        $ids = $schedule->participantStudentIds();
        $this->assertContains($student->id, $ids);

        $found = ExamSchedule::query()->accessibleToStudent($student)->whereKey($schedule->id)->exists();
        $this->assertTrue($found);
    }
}

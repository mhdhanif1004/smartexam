<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ExamPeriod;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\ExamType;
use App\Models\GuruMapel;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeacherSubjectClassAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuruMapelExamScheduleEditDeleteTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroomA;

    private Classroom $classroomB;

    private GuruMapel $guruA;

    private GuruMapel $guruB;

    private Subject $subjectA;

    private Subject $subjectB;

    private ExamType $harian;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classroomA = Classroom::factory()->create(['name' => 'XI RPL 1']);
        $this->classroomB = Classroom::factory()->create(['name' => 'XI RPL 2']);

        $this->subjectA = Subject::factory()->create(['name' => 'Matematika']);
        $this->subjectB = Subject::factory()->create(['name' => 'Bahasa Indonesia']);

        $this->guruA = GuruMapel::factory()->create();
        $this->guruB = GuruMapel::factory()->create();

        // Guru A ampu: mapel A×kelas A, mapel B×kelas B
        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $this->guruA->id,
            'subject_id' => $this->subjectA->id,
            'classroom_id' => $this->classroomA->id,
        ]);
        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $this->guruA->id,
            'subject_id' => $this->subjectB->id,
            'classroom_id' => $this->classroomB->id,
        ]);
        // Guru B juga ampu mapel A×kelas A
        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $this->guruB->id,
            'subject_id' => $this->subjectA->id,
            'classroom_id' => $this->classroomA->id,
        ]);

        $this->harian = ExamType::where('code', 'harian')->first();
        $this->harian->update(['boleh_dijadwalkan_guru' => true]);

        $this->student = Student::factory()->create(['classroom_id' => $this->classroomA->id]);
    }

    private function makeOwnedPeriod(?GuruMapel $guru = null): ExamPeriod
    {
        $guru ??= $this->guruA;

        $period = ExamPeriod::create([
            'name' => 'Matematika — XI RPL 1',
            'name_prefix' => 'Matematika — XI RPL 1',
            'exam_type_id' => $this->harian->id,
            'grade_level' => 'XI',
            'exam_date' => '2026-11-05',
            'start_time' => '08:00:00',
            'end_time' => '09:30:00',
            'created_by_user_id' => $guru->user_id,
        ]);

        ExamSchedule::create([
            'subject_id' => $this->subjectA->id,
            'room_id' => null,
            'classroom_id' => $this->classroomA->id,
            'exam_period_id' => $period->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => '2026-11-05',
            'start_time' => '08:00:00',
            'end_time' => '09:30:00',
            'duration_minutes' => 90,
            'status' => ExamSchedule::STATUS_SCHEDULED,
        ]);

        return $period;
    }

    private function confirmAttendance(ExamPeriod $period): ExamSession
    {
        $schedule = $period->schedules()->first();

        return ExamSession::create([
            'student_id' => $this->student->id,
            'exam_schedule_id' => $schedule->id,
            'status' => ExamSession::STATUS_NOT_STARTED,
            'attendance_confirmed' => true,
            'attendance_status' => ExamSession::ATTENDANCE_PRESENT,
        ]);
    }

    private function beginSession(ExamPeriod $period): ExamSession
    {
        $session = $this->confirmAttendance($period);
        $session->update(['status' => ExamSession::STATUS_IN_PROGRESS, 'started_at' => now()]);

        return $session;
    }

    private function updatePayload(array $overrides = []): array
    {
        return array_merge([
            'exam_type_id' => $this->harian->id,
            'subject_id' => $this->subjectA->id,
            'classroom_id' => $this->classroomA->id,
            'exam_date' => '2026-11-05',
            'start_time' => '08:00',
            'duration_minutes' => 90,
        ], $overrides);
    }

    // ── EDIT: bebas saat belum ada sesi & belum ada absensi ──────────

    public function test_free_edit_when_no_session_and_no_attendance(): void
    {
        $period = $this->makeOwnedPeriod();

        $this->actingAs($this->guruA->user)
            ->put(route('guru_mapel.exam-schedules.update', $period), $this->updatePayload([
                'subject_id' => $this->subjectB->id,
                'classroom_id' => $this->classroomB->id,
                'start_time' => '13:00',
                'duration_minutes' => 60,
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('exam_periods', ['id' => $period->id, 'start_time' => '13:00:00']);
        $this->assertDatabaseHas('exam_schedules', [
            'exam_period_id' => $period->id,
            'classroom_id' => $this->classroomB->id,
            'duration_minutes' => 60,
        ]);
    }

    // ── EDIT: field terkunci saat sesi sudah mulai ───────────────────

    public function test_structural_fields_locked_when_session_started(): void
    {
        $period = $this->makeOwnedPeriod();
        $this->beginSession($period);

        // Paksa ubah kelas → harus ditolak, data tidak berubah.
        $this->actingAs($this->guruA->user)
            ->put(route('guru_mapel.exam-schedules.update', $period), $this->updatePayload([
                'classroom_id' => $this->classroomB->id,
                'start_time' => '08:00',
            ]))
            ->assertSessionHasErrors('exam_type_id');

        $this->assertDatabaseHas('exam_schedules', [
            'exam_period_id' => $period->id,
            'classroom_id' => $this->classroomA->id,
        ]);
    }

    public function test_time_fields_still_editable_when_session_started(): void
    {
        $period = $this->makeOwnedPeriod();
        $this->beginSession($period);

        $this->actingAs($this->guruA->user)
            ->put(route('guru_mapel.exam-schedules.update', $period), $this->updatePayload([
                'start_time' => '09:00',
                'duration_minutes' => 60,
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('exam_periods', ['id' => $period->id, 'start_time' => '09:00:00']);
        $this->assertDatabaseMissing('exam_schedules', [
            'exam_period_id' => $period->id,
            'classroom_id' => $this->classroomB->id,
        ]);
    }

    // ── EDIT: ubah struktural saat attendance confirmed → wajib flag ─

    public function test_structural_change_with_confirmed_attendance_requires_flag(): void
    {
        $period = $this->makeOwnedPeriod();
        $this->confirmAttendance($period);

        $this->actingAs($this->guruA->user)
            ->put(route('guru_mapel.exam-schedules.update', $period), $this->updatePayload([
                'classroom_id' => $this->classroomB->id,
                'start_time' => '08:00',
            ]))
            ->assertSessionHasErrors('confirm_attendance_reset');

        $this->assertDatabaseHas('exam_schedules', [
            'exam_period_id' => $period->id,
            'classroom_id' => $this->classroomA->id,
        ]);
    }

    public function test_structural_change_with_flag_resets_confirmed_attendance(): void
    {
        $period = $this->makeOwnedPeriod();
        $this->confirmAttendance($period);

        $this->actingAs($this->guruA->user)
            ->put(route('guru_mapel.exam-schedules.update', $period), $this->updatePayload([
                'subject_id' => $this->subjectB->id,
                'classroom_id' => $this->classroomB->id,
                'confirm_attendance_reset' => 1,
                'start_time' => '08:00',
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('exam_schedules', [
            'exam_period_id' => $period->id,
            'classroom_id' => $this->classroomB->id,
        ]);

        // Absensi confirmed di-reset (bukan dihapus baris, tapi clear).
        $this->assertDatabaseHas('exam_sessions', [
            'student_id' => $this->student->id,
            'attendance_confirmed' => false,
            'attendance_status' => null,
        ]);
    }

    public function test_time_only_change_does_not_require_flag_when_attendance_confirmed(): void
    {
        $period = $this->makeOwnedPeriod();
        $this->confirmAttendance($period);

        $this->actingAs($this->guruA->user)
            ->put(route('guru_mapel.exam-schedules.update', $period), $this->updatePayload([
                'start_time' => '10:00',
                'duration_minutes' => 60,
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        // Absensi TETAP confirmed (tidak di-reset).
        $this->assertDatabaseHas('exam_sessions', [
            'student_id' => $this->student->id,
            'attendance_confirmed' => true,
        ]);
    }

    // ── EDIT: non-owner 403 ──────────────────────────────────────────

    public function test_other_guru_cannot_edit(): void
    {
        $period = $this->makeOwnedPeriod($this->guruA);

        $this->actingAs($this->guruB->user)
            ->get(route('guru_mapel.exam-schedules.edit', $period))
            ->assertForbidden();

        $this->actingAs($this->guruB->user)
            ->put(route('guru_mapel.exam-schedules.update', $period), $this->updatePayload())
            ->assertForbidden();
    }

    // ── EDIT: conflict check exclude self ────────────────────────────

    public function test_edit_keeps_own_schedule_and_rejects_others_conflict(): void
    {
        $period = $this->makeOwnedPeriod();

        // Sesi kedua di kelas A jam 10:00 (tidak bentrok).
        $this->actingAs($this->guruA->user)
            ->post(route('guru_mapel.exam-schedules.store'), [
                'exam_type_id' => $this->harian->id,
                'subject_id' => $this->subjectA->id,
                'classroom_id' => $this->classroomA->id,
                'exam_date' => '2026-11-05',
                'start_time' => '10:00',
                'duration_minutes' => 90,
            ])
            ->assertRedirect();

        // Edit period pertama: ubah jam jadi 10:30 → bentrok dengan sesi kedua (10:00-11:30).
        $this->actingAs($this->guruA->user)
            ->put(route('guru_mapel.exam-schedules.update', $period), $this->updatePayload([
                'start_time' => '10:30',
                'duration_minutes' => 60,
            ]))
            ->assertSessionHasErrors('classroom_id');

        $this->assertDatabaseHas('exam_periods', ['id' => $period->id, 'start_time' => '08:00:00']);
    }

    // ── DELETE: 3-tier ───────────────────────────────────────────────

    public function test_delete_simple_tier(): void
    {
        $period = $this->makeOwnedPeriod();

        $this->actingAs($this->guruA->user)
            ->delete(route('guru_mapel.exam-schedules.destroy', $period))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('exam_periods', ['id' => $period->id]);
        $this->assertDatabaseMissing('exam_schedules', ['exam_period_id' => $period->id]);
    }

    public function test_delete_warning_tier_with_confirmed_attendance(): void
    {
        $period = $this->makeOwnedPeriod();
        $this->confirmAttendance($period);

        $this->actingAs($this->guruA->user)
            ->delete(route('guru_mapel.exam-schedules.destroy', $period))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('exam_periods', ['id' => $period->id]);
    }

    public function test_delete_blocked_when_session_started(): void
    {
        $period = $this->makeOwnedPeriod();
        $this->beginSession($period);

        $this->actingAs($this->guruA->user)
            ->delete(route('guru_mapel.exam-schedules.destroy', $period))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('exam_periods', ['id' => $period->id]);
    }

    public function test_delete_other_guru_forbidden(): void
    {
        $period = $this->makeOwnedPeriod($this->guruA);

        $this->actingAs($this->guruB->user)
            ->delete(route('guru_mapel.exam-schedules.destroy', $period))
            ->assertForbidden();

        $this->assertDatabaseHas('exam_periods', ['id' => $period->id]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ExamSchedule;
use App\Models\ExamType;
use App\Models\GuruMapel;
use App\Models\Subject;
use App\Models\TeacherSubjectClassAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuruMapelExamScheduleTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroom;

    private Classroom $otherClassroom;

    private GuruMapel $guru;

    private Subject $subject;

    private ExamType $harian;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classroom = Classroom::factory()->create(['name' => 'XI RPL 1']);
        $this->otherClassroom = Classroom::factory()->create(['name' => 'XI RPL 2']);

        $this->guru = GuruMapel::factory()->create();
        $this->subject = Subject::factory()->create(['name' => 'Matematika']);

        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $this->guru->id,
            'subject_id' => $this->subject->id,
            'classroom_id' => $this->classroom->id,
        ]);

        $this->harian = ExamType::where('code', 'harian')->first()
            ?? ExamType::create(['name' => 'Harian', 'code' => 'harian', 'sort_order' => 1, 'boleh_dijadwalkan_guru' => true]);
        $this->harian->update(['boleh_dijadwalkan_guru' => true]);
    }

    private function storePayload(array $overrides = []): array
    {
        return array_merge([
            'exam_type_id' => $this->harian->id,
            'subject_id' => $this->subject->id,
            'classroom_id' => $this->classroom->id,
            'exam_date' => '2026-10-20',
            'start_time' => '08:00',
            'duration_minutes' => 90,
        ], $overrides);
    }

    public function test_guru_can_create_own_exam_period_and_schedule(): void
    {
        $response = $this->actingAs($this->guru->user)
            ->post(route('guru_mapel.exam-schedules.store'), $this->storePayload());

        $response->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('exam_periods', [
            'name' => 'Matematika — XI RPL 1',
            'exam_type_id' => $this->harian->id,
            'created_by_user_id' => $this->guru->user_id,
            'exam_date' => '2026-10-20',
        ]);

        $this->assertDatabaseHas('exam_schedules', [
            'classroom_id' => $this->classroom->id,
            'room_id' => null,
            'status' => ExamSchedule::STATUS_SCHEDULED,
        ]);
    }

    public function test_guru_cannot_use_exam_type_not_allowed_for_guru(): void
    {
        $uas = ExamType::where('code', 'uas')->first() ?? ExamType::create(['name' => 'UAS', 'code' => 'uas', 'sort_order' => 3]);
        $uas->update(['boleh_dijadwalkan_guru' => false]);

        $response = $this->actingAs($this->guru->user)
            ->post(route('guru_mapel.exam-schedules.store'), $this->storePayload(['exam_type_id' => $uas->id]));

        $response->assertSessionHasErrors('exam_type_id');
        $this->assertDatabaseCount('exam_periods', 0);
    }

    public function test_guru_cannot_schedule_classroom_not_in_assignment(): void
    {
        $response = $this->actingAs($this->guru->user)
            ->post(route('guru_mapel.exam-schedules.store'), $this->storePayload(['classroom_id' => $this->otherClassroom->id]));

        $response->assertForbidden();
        $this->assertDatabaseCount('exam_periods', 0);
    }

    public function test_guru_cannot_schedule_subject_not_in_assignment(): void
    {
        $otherSubject = Subject::factory()->create();

        $response = $this->actingAs($this->guru->user)
            ->post(route('guru_mapel.exam-schedules.store'), $this->storePayload(['subject_id' => $otherSubject->id]));

        $response->assertForbidden();
        $this->assertDatabaseCount('exam_periods', 0);
    }

    public function test_conflicting_time_for_same_classroom_rejected(): void
    {
        $this->actingAs($this->guru->user)
            ->post(route('guru_mapel.exam-schedules.store'), $this->storePayload())
            ->assertRedirect();

        $response = $this->actingAs($this->guru->user)
            ->post(route('guru_mapel.exam-schedules.store'), $this->storePayload([
                'exam_date' => '2026-10-20',
                'start_time' => '08:30', // overlap dengan jadwal pertama (08:00-09:30)
            ]));

        $response->assertSessionHasErrors('classroom_id');
        $this->assertDatabaseCount('exam_periods', 1);
    }

    public function test_same_classroom_different_time_allowed(): void
    {
        $this->actingAs($this->guru->user)
            ->post(route('guru_mapel.exam-schedules.store'), $this->storePayload())
            ->assertRedirect();

        $response = $this->actingAs($this->guru->user)
            ->post(route('guru_mapel.exam-schedules.store'), $this->storePayload([
                'exam_date' => '2026-10-20',
                'start_time' => '10:00', // tidak overlap
            ]));

        $response->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseCount('exam_periods', 2);
    }

    public function test_index_lists_only_own_periods(): void
    {
        $guruLain = GuruMapel::factory()->create();
        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guruLain->id,
            'subject_id' => $this->subject->id,
            'classroom_id' => $this->classroom->id,
        ]);

        $this->actingAs($this->guru->user)
            ->post(route('guru_mapel.exam-schedules.store'), $this->storePayload());

        $this->actingAs($guruLain->user)
            ->post(route('guru_mapel.exam-schedules.store'), $this->storePayload(['start_time' => '13:00']));

        $response = $this->actingAs($this->guru->user)
            ->get(route('guru_mapel.exam-schedules.index'))
            ->assertOk();

        // Satu period milik guru ini, tidak termasuk punya guru lain.
        $this->assertStringContainsString('Matematika — XI RPL 1', $response->getContent());
        $this->assertDatabaseCount('exam_periods', 2);
    }
}

<?php

namespace Tests\Feature;

use App\Models\ExamSchedule;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectBulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_can_view_subjects_index(): void
    {
        $subject = Subject::factory()->create(['name' => 'Matematika']);

        $this->actingAs($this->admin)->get(route('admin.subjects.index'))
            ->assertOk()
            ->assertSee('Mata Pelajaran')
            ->assertSee($subject->name)
            ->assertSee('Jadwal Ujian');
    }

    public function test_bulk_delete_removes_selected_subjects(): void
    {
        $subjects = Subject::factory()->count(3)->create();

        $this->actingAs($this->admin)
            ->post(route('admin.subjects.bulk-delete'), [
                'ids' => [$subjects[0]->id, $subjects[1]->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', '2 mata pelajaran berhasil dihapus.');

        $this->assertDatabaseMissing('subjects', ['id' => $subjects[0]->id]);
        $this->assertDatabaseMissing('subjects', ['id' => $subjects[1]->id]);
        $this->assertDatabaseHas('subjects', ['id' => $subjects[2]->id]);
    }

    public function test_bulk_delete_cascades_linked_questions_and_schedules(): void
    {
        $subject = Subject::factory()->create();
        $question = Question::factory()->create(['subject_id' => $subject->id]);
        $schedule = ExamSchedule::factory()->create(['subject_id' => $subject->id]);

        $this->actingAs($this->admin)
            ->post(route('admin.subjects.bulk-delete'), ['ids' => [$subject->id]])
            ->assertRedirect()
            ->assertSessionHas('success', '1 mata pelajaran berhasil dihapus.');

        $this->assertDatabaseMissing('subjects', ['id' => $subject->id]);
        $this->assertDatabaseMissing('questions', ['id' => $question->id]);
        $this->assertDatabaseMissing('exam_schedules', ['id' => $schedule->id]);
    }

    public function test_bulk_delete_requires_selection(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.subjects.bulk-delete'), ['ids' => []])
            ->assertRedirect()
            ->assertSessionHas('error', 'Pilih minimal satu mata pelajaran untuk dihapus.');
    }

    public function test_bulk_delete_rejects_more_than_max_ids(): void
    {
        $ids = range(1, 501);

        $this->actingAs($this->admin)
            ->post(route('admin.subjects.bulk-delete'), ['ids' => $ids])
            ->assertRedirect()
            ->assertSessionHas('error', 'Maksimal 500 mata pelajaran dalam satu permintaan.');
    }

    public function test_bulk_delete_preview_reports_linked_count(): void
    {
        $linked = Subject::factory()->create();
        Question::factory()->create(['subject_id' => $linked->id]);
        $plain = Subject::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.subjects.bulk-delete-preview'), [
                'ids' => [$linked->id, $plain->id],
            ])
            ->assertOk()
            ->assertJson([
                'total' => 2,
                'linked_count' => 1,
            ]);
    }

    public function test_bulk_delete_preview_requires_selection(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.subjects.bulk-delete-preview'), ['ids' => []])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Pilih minimal satu mata pelajaran untuk dihapus.');
    }

    public function test_non_admin_cannot_bulk_delete(): void
    {
        $subject = Subject::factory()->create();
        $peserta = User::factory()->peserta()->create();

        $this->actingAs($peserta)
            ->post(route('admin.subjects.bulk-delete'), ['ids' => [$subject->id]])
            ->assertForbidden();

        $this->actingAs($peserta)
            ->post(route('admin.subjects.bulk-delete-preview'), ['ids' => [$subject->id]])
            ->assertForbidden();
    }
}

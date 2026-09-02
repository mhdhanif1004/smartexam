<?php

namespace Tests\Feature;

use App\Models\ExamPeriod;
use App\Models\ExamRoomAssignment;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\ExamToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ExamPeriodDeletePreviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_index_page_renders_without_leaked_code(): void
    {
        $period = ExamPeriod::factory()->create([
            'exam_date' => '2026-08-10',
            'name' => 'Sesi Pagi',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.exam-periods.index'));
        $response->assertOk();

        $html = $response->content();
        $this->assertStringNotContainsString('document.body.appendChild(form)', $html);
        // Halaman utama mengelompokkan per tanggal -> tidak memuat nama sesi maupun modal delete
        $this->assertStringContainsString('10 Agustus 2026', $html);
        $this->assertStringNotContainsString('Sesi Pagi', $html);
        $this->assertStringNotContainsString('periodDelete', $html);

        // Detail per tanggal memuat modal delete (periodDelete) dan nama sesi
        $detail = $this->actingAs($this->admin)
            ->get(route('admin.exam-periods.by-date', ['date' => '2026-08-10']))
            ->assertOk();

        $detailHtml = $detail->content();
        $this->assertStringContainsString('periodDelete', $detailHtml);
        $this->assertStringContainsString('Sesi Pagi', $detailHtml);
        $this->assertStringNotContainsString('document.body.appendChild(form)', $detailHtml);
    }

    // ── deletePreview ──────────────────────────────────────────────

    public function test_delete_preview_returns_zero_counts_for_empty_period(): void
    {
        $period = ExamPeriod::factory()->create();

        $this->actingAs($this->admin)
            ->getJson(route('admin.exam-periods.delete-preview', $period))
            ->assertOk()
            ->assertJson([
                'schedules_count' => 0,
                'room_assignments_count' => 0,
                'tokens_count' => 0,
                'started_sessions_count' => 0,
            ]);
    }

    public function test_delete_preview_returns_correct_counts(): void
    {
        $period = ExamPeriod::factory()->create();

        $schedule = ExamSchedule::factory()->create(['exam_period_id' => $period->id]);
        ExamSchedule::factory()->create(['exam_period_id' => $period->id]);
        ExamRoomAssignment::factory()->create(['exam_period_id' => $period->id]);
        ExamRoomAssignment::factory()->create(['exam_period_id' => $period->id]);
        ExamRoomAssignment::factory()->create(['exam_period_id' => $period->id]);
        ExamToken::create([
            'exam_period_id' => $period->id,
            'token_code' => 'ABC12345',
            'rotation_index' => 1,
            'valid_from' => now(),
            'valid_until' => now()->addMinutes(15),
        ]);

        $this->actingAs($this->admin)
            ->getJson(route('admin.exam-periods.delete-preview', $period))
            ->assertOk()
            ->assertJson([
                'schedules_count' => 2,
                'room_assignments_count' => 3,
                'tokens_count' => 1,
                'started_sessions_count' => 0,
            ]);
    }

    public function test_delete_preview_counts_started_sessions(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-20 09:00:00'));

        $period = ExamPeriod::factory()->create([
            'exam_date' => '2026-08-20',
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
        ]);
        $schedule = ExamSchedule::factory()->create(['exam_period_id' => $period->id]);

        ExamSession::factory()->create([
            'exam_schedule_id' => $schedule->id,
            'started_at' => now(),
            'finished_at' => null,
        ]);

        $this->actingAs($this->admin)
            ->getJson(route('admin.exam-periods.delete-preview', $period))
            ->assertOk()
            ->assertJson([
                'started_sessions_count' => 1,
                'active_sessions_count' => 1,
            ]);

        Carbon::setTestNow();
    }

    public function test_delete_preview_distinguishes_active_from_completed_sessions(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-20 09:00:00'));

        $period = ExamPeriod::factory()->create([
            'exam_date' => '2026-08-20',
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
        ]);
        $schedule = ExamSchedule::factory()->create([
            'exam_period_id' => $period->id,
            'exam_date' => '2026-08-20',
            'start_time' => '08:00:00',
            'end_time' => '09:30:00',
            'duration_minutes' => 90,
        ]);

        // Aktif: started, belum finished, period end 10:00 (+ grace) belum lewat 09:00 -> active
        ExamSession::factory()->create([
            'exam_schedule_id' => $schedule->id,
            'started_at' => now()->subMinutes(5),
            'finished_at' => null,
        ]);

        // Selesai: finished_at terisi -> count in started but not active
        ExamSession::factory()->create([
            'exam_schedule_id' => $schedule->id,
            'started_at' => now()->subHours(2),
            'finished_at' => now()->subHour(),
        ]);

        $this->actingAs($this->admin)
            ->getJson(route('admin.exam-periods.delete-preview', $period))
            ->assertOk()
            ->assertJson([
                'started_sessions_count' => 2,
                'active_sessions_count' => 1,
            ]);

        Carbon::setTestNow();
    }

    // ── Mode C: Simple delete (no linked data) ─────────────────────

    public function test_destroy_simple_mode_deletes_period_and_returns_success(): void
    {
        $period = ExamPeriod::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.exam-periods.destroy', $period))
            ->assertRedirect(route('admin.exam-periods.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('exam_periods', ['id' => $period->id]);
    }

    // ── Mode B: Warning — linked data, no started sessions ─────────

    public function test_destroy_warning_mode_deletes_period_and_schedules_explicitly(): void
    {
        $period = ExamPeriod::factory()->create();
        $schedule1 = ExamSchedule::factory()->create(['exam_period_id' => $period->id]);
        $schedule2 = ExamSchedule::factory()->create(['exam_period_id' => $period->id]);
        ExamRoomAssignment::factory()->create(['exam_period_id' => $period->id]);
        ExamToken::create([
            'exam_period_id' => $period->id,
            'token_code' => 'XYZ98765',
            'rotation_index' => 1,
            'valid_from' => now(),
            'valid_until' => now()->addMinutes(15),
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.exam-periods.destroy', $period))
            ->assertRedirect(route('admin.exam-periods.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('exam_periods', ['id' => $period->id]);
        $this->assertDatabaseMissing('exam_schedules', ['id' => $schedule1->id]);
        $this->assertDatabaseMissing('exam_schedules', ['id' => $schedule2->id]);
        // exam_room_assignments cascade deleted by DB FK (cascadeOnDelete)
        $this->assertDatabaseMissing('exam_room_assignments', ['exam_period_id' => $period->id]);
        // exam_tokens cascade deleted by DB FK (cascadeOnDelete)
        $this->assertDatabaseMissing('exam_tokens', ['exam_period_id' => $period->id]);
    }

    // ── Mode A: Warning — has started sessions (active override) ───

    public function test_destroy_mode_a_active_deletes_period_but_keeps_schedules_orphaned_and_sessions(): void
    {
        $period = ExamPeriod::factory()->create();
        $schedule = ExamSchedule::factory()->create(['exam_period_id' => $period->id]);

        $session = ExamSession::factory()->create([
            'exam_schedule_id' => $schedule->id,
            'started_at' => now(),
            'finished_at' => null,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.exam-periods.destroy', $period))
            ->assertRedirect(route('admin.exam-periods.index'))
            ->assertSessionHas('success');

        // Period gone
        $this->assertDatabaseMissing('exam_periods', ['id' => $period->id]);
        // Schedule SURVIVES as orphan (exam_period_id -> NULL via nullOnDelete)
        $this->assertDatabaseHas('exam_schedules', ['id' => $schedule->id, 'exam_period_id' => null]);
        // Session & histori siswa TIDAK hilang
        $this->assertDatabaseHas('exam_sessions', ['id' => $session->id]);
    }

    public function test_destroy_mode_a_completed_deletes_period_but_keeps_schedules_orphaned_and_sessions(): void
    {
        $period = ExamPeriod::factory()->create();
        $schedule = ExamSchedule::factory()->create(['exam_period_id' => $period->id]);

        $session = ExamSession::factory()->create([
            'exam_schedule_id' => $schedule->id,
            'started_at' => now()->subHours(2),
            'finished_at' => now()->subHour(),
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.exam-periods.destroy', $period))
            ->assertRedirect(route('admin.exam-periods.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('exam_periods', ['id' => $period->id]);
        $this->assertDatabaseHas('exam_schedules', ['id' => $schedule->id, 'exam_period_id' => null]);
        $this->assertDatabaseHas('exam_sessions', ['id' => $session->id]);
    }

    public function test_destroy_mode_a_cascades_config_but_keeps_sessions(): void
    {
        $period = ExamPeriod::factory()->create();
        $schedule = ExamSchedule::factory()->create(['exam_period_id' => $period->id]);

        ExamRoomAssignment::factory()->create(['exam_period_id' => $period->id]);
        ExamToken::create([
            'exam_period_id' => $period->id,
            'token_code' => 'TOK99999',
            'rotation_index' => 1,
            'valid_from' => now(),
            'valid_until' => now()->addMinutes(15),
        ]);

        $session = ExamSession::factory()->create([
            'exam_schedule_id' => $schedule->id,
            'started_at' => now(),
            'finished_at' => null,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.exam-periods.destroy', $period))
            ->assertRedirect(route('admin.exam-periods.index'))
            ->assertSessionHas('success');

        // Konfigurasi ikut terhapus (cascade)
        $this->assertDatabaseMissing('exam_room_assignments', ['exam_period_id' => $period->id]);
        $this->assertDatabaseMissing('exam_tokens', ['exam_period_id' => $period->id]);
        // Schedule orphan + sesi siswa tetap ada
        $this->assertDatabaseHas('exam_schedules', ['id' => $schedule->id, 'exam_period_id' => null]);
        $this->assertDatabaseHas('exam_sessions', ['id' => $session->id]);
    }

    public function test_destroy_not_blocked_when_sessions_exist_but_not_started(): void
    {
        $period = ExamPeriod::factory()->create();
        $schedule = ExamSchedule::factory()->create(['exam_period_id' => $period->id]);

        ExamSession::factory()->create([
            'exam_schedule_id' => $schedule->id,
            'started_at' => null,
            'status' => ExamSession::STATUS_NOT_STARTED,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.exam-periods.destroy', $period))
            ->assertRedirect(route('admin.exam-periods.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('exam_periods', ['id' => $period->id]);
    }
}

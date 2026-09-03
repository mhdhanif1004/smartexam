<?php

namespace Tests\Feature;

use App\Models\ExamPeriod;
use App\Models\ExamSchedule;
use App\Models\Room;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamPeriodByDateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_index_lists_unique_dates_with_session_count(): void
    {
        ExamPeriod::factory()->create(['name' => 'Sesi 1', 'exam_date' => '2026-08-10', 'start_time' => '07:30:00']);
        ExamPeriod::factory()->create(['name' => 'Sesi 2', 'exam_date' => '2026-08-10', 'start_time' => '10:00:00']);
        ExamPeriod::factory()->create(['name' => 'Sesi 3', 'exam_date' => '2026-08-11', 'start_time' => '07:30:00']);

        $this->actingAs($this->admin)
            ->get(route('admin.exam-periods.index'))
            ->assertOk()
            ->assertSee('10 Agustus 2026')
            ->assertSee('11 Agustus 2026')
            ->assertSee('2 sesi')
            // 1 baris per tanggal: nama sesi tidak tampil di halaman utama
            ->assertDontSee('Sesi 1')
            ->assertDontSee('Sesi 3');
    }

    public function test_index_search_by_date_filters_dates(): void
    {
        ExamPeriod::factory()->create(['exam_date' => '2026-08-10']);
        ExamPeriod::factory()->create(['exam_date' => '2026-08-20']);

        $this->actingAs($this->admin)
            ->get(route('admin.exam-periods.index', ['search' => '2026-08-10']))
            ->assertOk()
            ->assertSee('10 Agustus 2026')
            ->assertDontSee('20 Agustus 2026');
    }

    public function test_by_date_shows_only_sessions_of_that_date_with_jam_column(): void
    {
        ExamPeriod::factory()->create(['name' => 'Sesi Lain', 'exam_date' => '2026-08-11']);

        $room = Room::factory()->create(['room_number' => 1]);
        $subject = Subject::factory()->create(['name' => 'Matematika']);

        $period = ExamPeriod::factory()->create([
            'name' => 'Sesi Pagi',
            'exam_date' => '2026-08-10',
            'start_time' => '07:30:00',
            'end_time' => '09:30:00',
        ]);
        ExamSchedule::factory()->create([
            'exam_period_id' => $period->id,
            'room_id' => $room->id,
            'subject_id' => $subject->id,
            'exam_date' => '2026-08-10',
        ]);
        ExamSchedule::factory()->create([
            'exam_period_id' => $period->id,
            'room_id' => $room->id,
            'subject_id' => $subject->id,
            'exam_date' => '2026-08-10',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.exam-periods.by-date', ['date' => '2026-08-10']))
            ->assertOk();

        $this->assertStringContainsString('Sesi Pagi', $response->content());
        $this->assertStringContainsString('07:30 - 09:30', $response->content());
        // Jumlah jadwal badge (2 jadwal)
        $this->assertStringContainsString('2', $response->content());
        // Sesi dari tanggal lain tidak tampil
        $this->assertStringNotContainsString('Sesi Lain', $response->content());
    }

    public function test_by_date_orders_sessions_by_start_time(): void
    {
        ExamPeriod::factory()->create(['name' => 'Sesi Siang', 'exam_date' => '2026-08-10', 'start_time' => '12:00:00']);
        ExamPeriod::factory()->create(['name' => 'Sesi Pagi', 'exam_date' => '2026-08-10', 'start_time' => '07:30:00']);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.exam-periods.by-date', ['date' => '2026-08-10']))
            ->assertOk();

        $html = $response->content();
        $this->assertTrue(strpos($html, 'Sesi Pagi') < strpos($html, 'Sesi Siang'));
    }

    public function test_by_date_requires_valid_date(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.exam-periods.by-date'))
            ->assertSessionHasErrors('date');
    }
}

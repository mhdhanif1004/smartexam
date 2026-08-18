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
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ExamPeriodAutoGenerateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Subject $mtk;

    private Subject $bindo;

    private Subject $bing;

    private Room $roomA;

    private Room $roomB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();

        $this->mtk = Subject::factory()->create(['name' => 'Matematika', 'default_duration_minutes' => 60]);
        $this->bindo = Subject::factory()->create(['name' => 'Bahasa Indonesia', 'default_duration_minutes' => 60]);
        $this->bing = Subject::factory()->create(['name' => 'Bahasa Inggris', 'default_duration_minutes' => 90]);

        foreach ([$this->mtk, $this->bindo, $this->bing] as $subject) {
            Question::factory()->create(['subject_id' => $subject->id]);
        }

        $this->roomA = Room::factory()->create(['room_number' => 1, 'capacity' => 25]);
        $this->roomB = Room::factory()->create(['room_number' => 2, 'capacity' => 25]);
    }

    /**
     * Buat siswa dengan nama tertentu agar urutan alfabetis deterministik.
     */
    private function student(string $name, string $class): Student
    {
        return Student::factory()->create([
            'class_name' => $class,
            'user_id' => User::factory()->peserta()->create(['name' => $name])->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'UAS Ganjil Kelas 12',
            'exam_date' => '2026-08-12',
            'class_names' => ['XII RPL 1'],
            'rooms' => [$this->roomA->id, $this->roomB->id],
            'subjects' => [
                ['subject_id' => $this->mtk->id, 'duration_minutes' => 60],
                ['subject_id' => $this->bindo->id, 'duration_minutes' => 60],
                ['subject_id' => $this->bing->id, 'duration_minutes' => 90],
            ],
            'start_time' => '07:30',
            'gap_minutes' => 15,
        ], $overrides);
    }

    private function postGenerate(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->admin)
            ->post(route('admin.exam-periods.auto-generate.store'), $this->payload($overrides));
    }

    public function test_single_session_when_students_fit_in_one_wave(): void
    {
        foreach (range(1, 10) as $i) {
            $this->student('Siswa '.str_pad((string) $i, 3, '0', STR_PAD_LEFT), 'XII RPL 1');
        }

        $this->postGenerate()
            ->assertRedirect(route('admin.exam-periods.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('exam_periods', 1);
        $this->assertDatabaseHas('exam_periods', [
            'name' => 'UAS Ganjil Kelas 12 - Sesi 1',
            'exam_date' => '2026-08-12',
            'start_time' => '07:30:00',
            'end_time' => '11:00:00',
        ]);

        // 2 ruangan × 3 mapel = 6 jadwal per sesi
        $this->assertDatabaseCount('exam_schedules', 6);
        $this->assertDatabaseCount('exam_room_assignments', 10);
    }

    public function test_multiple_sessions_when_students_exceed_capacity(): void
    {
        // 120 siswa vs kapasitas 50/sesi -> ceil(120/50) = 3 sesi (50, 50, 20)
        foreach (range(1, 120) as $i) {
            $this->student('Siswa '.str_pad((string) $i, 3, '0', STR_PAD_LEFT), 'XII RPL 1');
        }

        $this->postGenerate()
            ->assertRedirect(route('admin.exam-periods.index'))
            ->assertSessionHas('success');

        $periods = ExamPeriod::query()->orderBy('id')->get();
        $this->assertCount(3, $periods);

        $expected = [
            ['name' => 'UAS Ganjil Kelas 12 - Sesi 1', 'start' => '07:30:00', 'end' => '11:00:00'],
            ['name' => 'UAS Ganjil Kelas 12 - Sesi 2', 'start' => '11:15:00', 'end' => '14:45:00'],
            ['name' => 'UAS Ganjil Kelas 12 - Sesi 3', 'start' => '15:00:00', 'end' => '18:30:00'],
        ];

        foreach ($expected as $i => $row) {
            $this->assertSame($row['name'], $periods[$i]->name);
            $this->assertSame($row['start'], $periods[$i]->start_time);
            $this->assertSame($row['end'], $periods[$i]->end_time);
        }

        // Sesi 2 mulai tepat = akhir sesi 1 + jeda 15 menit
        $this->assertSame($expected[1]['start'], '11:15:00');

        // Setiap sesi punya 6 jadwal (2 ruangan × 3 mapel)
        foreach ($periods as $period) {
            $this->assertSame(6, ExamSchedule::query()->where('exam_period_id', $period->id)->count());
        }

        // Total assignment = jumlah siswa input, tidak ada duplikat antar sesi
        $this->assertSame(120, ExamRoomAssignment::query()->count());
        $this->assertSame(120, ExamRoomAssignment::query()->distinct('student_id')->count('student_id'));

        // Nomor kursi reset per ruangan per sesi (selalu 1..total)
        $groups = ExamRoomAssignment::query()
            ->selectRaw('exam_period_id, room_id, COUNT(*) as total, MAX(seat_number) as max_seat')
            ->groupBy('exam_period_id', 'room_id')
            ->get();

        foreach ($groups as $group) {
            $seatCount = ExamRoomAssignment::query()
                ->where('exam_period_id', $group->exam_period_id)
                ->where('room_id', $group->room_id)
                ->distinct('seat_number')
                ->count('seat_number');

            $this->assertSame((int) $group->total, $seatCount);
            $this->assertSame((int) $group->total, (int) $group->max_seat);
        }

        // Sisa terakhir: 120 - 100 = 20 siswa hanya di sesi 3
        $this->assertSame(20, ExamRoomAssignment::query()->where('exam_period_id', $periods[2]->id)->count());
    }

    public function test_subjects_are_back_to_back_within_session(): void
    {
        foreach (range(1, 10) as $i) {
            $this->student('Siswa '.str_pad((string) $i, 3, '0', STR_PAD_LEFT), 'XII RPL 1');
        }

        $this->postGenerate()->assertSessionHas('success');

        $period = ExamPeriod::firstOrFail();
        $schedules = ExamSchedule::query()
            ->where('room_id', $this->roomA->id)
            ->where('exam_period_id', $period->id)
            ->orderBy('start_time')
            ->get();

        $this->assertCount(3, $schedules);

        $this->assertSame($this->mtk->id, $schedules[0]->subject_id);
        $this->assertSame('07:30:00', $schedules[0]->start_time);
        $this->assertSame('08:30:00', $schedules[0]->end_time);

        $this->assertSame($this->bindo->id, $schedules[1]->subject_id);
        $this->assertSame('08:30:00', $schedules[1]->start_time);
        $this->assertSame('09:30:00', $schedules[1]->end_time);

        $this->assertSame($this->bing->id, $schedules[2]->subject_id);
        $this->assertSame('09:30:00', $schedules[2]->start_time);
        $this->assertSame('11:00:00', $schedules[2]->end_time);
    }

    public function test_any_session_conflict_rolls_back_the_entire_batch(): void
    {
        foreach (range(1, 120) as $i) {
            $this->student('Siswa '.str_pad((string) $i, 3, '0', STR_PAD_LEFT), 'XII RPL 1');
        }

        // Jadwal existing yang bentrok dengan sesi 1 di ruangan A (07:30-09:00)
        ExamSchedule::factory()->create([
            'subject_id' => $this->mtk->id,
            'room_id' => $this->roomA->id,
            'class_name' => 'XII RPL 2',
            'exam_date' => '2026-08-12',
            'start_time' => '07:30:00',
            'end_time' => '09:00:00',
            'duration_minutes' => 90,
            'status' => ExamSchedule::STATUS_SCHEDULED,
        ]);

        $this->from(route('admin.exam-periods.auto-generate.create'))
            ->postGenerate()
            ->assertSessionHasErrors('subjects');

        // Tidak ada sesi pun yang tersimpan, termasuk sesi 1 yang valid
        $this->assertDatabaseCount('exam_periods', 0);
        $this->assertDatabaseCount('exam_schedules', 1);
        $this->assertDatabaseCount('exam_room_assignments', 0);
    }

    public function test_rejects_missing_rooms_subjects_or_zero_capacity(): void
    {
        $this->student('Siswa 001', 'XII RPL 1');

        $this->postGenerate(['rooms' => []])
            ->assertSessionHasErrors('rooms');

        $this->postGenerate(['subjects' => []])
            ->assertSessionHasErrors('subjects');

        $zeroCapacity = Room::factory()->create(['room_number' => 999, 'capacity' => 0]);
        $this->from(route('admin.exam-periods.auto-generate.create'))
            ->postGenerate(['rooms' => [$zeroCapacity->id]])
            ->assertSessionHasErrors('rooms');

        $this->assertDatabaseCount('exam_periods', 0);
        $this->assertDatabaseCount('exam_schedules', 0);
        $this->assertDatabaseCount('exam_room_assignments', 0);
    }
}

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

    /**
     * Skenario campuran: 12 ruangan × kapasitas 30 = 360 kursi/sesi.
     * X=250, XI=200, XII=150 → total 600 siswa → ceil(600/360) = 2 sesi.
     *
     * Sesi 1 (360 kursi):
     *   R1–R8  : 240 siswa X (isi penuh)
     *   R9     : 10 siswa X (sisa, ditutup → 20 kosong)
     *   R10–R12: 90 siswa XI (isi penuh)
     *   Subtotal: 250 X + 90 XI = 340 terisi, 20 kosong
     *
     * Sesi 2 (360 kursi):
     *   R1–R3  : 90 siswa XI (sisa 200-90=110 → R1-R3 isi penuh 90)
     *   R4     : 20 siswa XI (sisa 20, ditutup → 10 kosong)
     *   R5–R9  : 150 siswa XII (isi penuh)
     *   Subtotal: 110 XI + 150 XII = 260 terisi, 100 kosong
     *
     * Paling penting: TIDAK ada ruangan yang campur 2+ grade.
     */
    public function test_mixed_grade_rooms_maintain_grade_exclusivity(): void
    {
        // Create 12 rooms with capacity 30 each (start at 100 to avoid conflict with setUp's rooms)
        $rooms = [];
        foreach (range(1, 12) as $i) {
            $rooms[] = Room::factory()->create(['room_number' => 100 + $i, 'capacity' => 30]);
        }
        $roomIds = array_map(fn ($r) => $r->id, $rooms);

        // Create 250 students grade X
        foreach (range(1, 250) as $i) {
            $this->student('Xsiswa'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'X RPL '.($i % 5 + 1));
        }
        // Create 200 students grade XI
        foreach (range(1, 200) as $i) {
            $this->student('XIsiswa'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'XI RPL '.($i % 5 + 1));
        }
        // Create 150 students grade XII
        foreach (range(1, 150) as $i) {
            $this->student('XIIsiswa'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'XII RPL '.($i % 5 + 1));
        }

        $payload = [
            'name' => 'UAS Campuran',
            'exam_date' => '2026-08-20',
            'class_names' => [
                'X RPL 1', 'X RPL 2', 'X RPL 3', 'X RPL 4', 'X RPL 5',
                'XI RPL 1', 'XI RPL 2', 'XI RPL 3', 'XI RPL 4', 'XI RPL 5',
                'XII RPL 1', 'XII RPL 2', 'XII RPL 3', 'XII RPL 4', 'XII RPL 5',
            ],
            'rooms' => $roomIds,
            'subjects' => [
                ['subject_id' => $this->mtk->id, 'duration_minutes' => 60],
                ['subject_id' => $this->bindo->id, 'duration_minutes' => 60],
                ['subject_id' => $this->bing->id, 'duration_minutes' => 90],
            ],
            'start_time' => '07:30',
            'gap_minutes' => 15,
        ];

        $this->actingAs($this->admin)
            ->post(route('admin.exam-periods.auto-generate.store'), $payload)
            ->assertRedirect(route('admin.exam-periods.index'))
            ->assertSessionHas('success');

        // ── Assertion 1: Exactly 2 sessions ──
        $periods = ExamPeriod::query()->orderBy('session_number')->get();
        $this->assertCount(2, $periods, 'Expected exactly 2 sessions for 600 students across 360 capacity.');
        $this->assertSame(1, $periods[0]->session_number);
        $this->assertSame(2, $periods[1]->session_number);

        // ── Assertion 2: total 600 assignments, no duplicate students ──
        $this->assertSame(600, ExamRoomAssignment::query()->count());
        $this->assertSame(600, ExamRoomAssignment::query()->distinct('student_id')->count('student_id'));

        // ── Assertion 3: CRITICAL — no room has students from 2+ different grades ──
        $allAssignments = ExamRoomAssignment::query()
            ->with('student')
            ->get();

        $roomGrades = $allAssignments->groupBy(fn ($a) => $a->exam_period_id.'|'.$a->room_id)
            ->map(fn ($assignments, $key) => [
                'period_room' => $key,
                'grades' => $assignments
                    ->map(fn ($a) => ExamPeriod::extractGradeLevel($a->student?->class_name ?? ''))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
            ])
            ->filter(fn ($g) => count($g['grades']) > 1);

        $this->assertTrue(
            $roomGrades->isEmpty(),
            'CRITICAL FAILURE: '.($roomGrades->count()).' room(s) have mixed grades: '
            .$roomGrades->map(fn ($g) => $g['period_room'].' → '.implode('+', $g['grades']))->implode(', ')
        );

        // ── Assertion 4: per-session assignment counts ──
        // Sesi 1: 340 students (250 X + 90 XI), 20 seats empty
        // Sesi 2: 260 students (110 XI + 150 XII), 100 seats empty
        $session1Count = ExamRoomAssignment::query()->where('exam_period_id', $periods[0]->id)->count();
        $session2Count = ExamRoomAssignment::query()->where('exam_period_id', $periods[1]->id)->count();
        $this->assertSame(340, $session1Count, 'Sesi 1 should have 340 students (250 X + 90 XI).');
        $this->assertSame(260, $session2Count, 'Sesi 2 should have 260 students (110 XI + 150 XII).');

        // ── Assertion 5: per-room detail (simplified spot checks) ──
        // Sesi 1, Room 9: exactly 10 X students (partial fill, then closed)
        $s1r9Count = ExamRoomAssignment::query()
            ->where('exam_period_id', $periods[0]->id)
            ->where('room_id', $rooms[8]->id) // rooms[8] = R9 (0-indexed)
            ->count();
        $this->assertSame(10, $s1r9Count, 'Sesi 1 Room 9 should have exactly 10 students (partial X).');

        // Sesi 2, Room 4: exactly 20 XI students (partial fill, then closed)
        $s2r4Count = ExamRoomAssignment::query()
            ->where('exam_period_id', $periods[1]->id)
            ->where('room_id', $rooms[3]->id) // rooms[3] = R4 (0-indexed)
            ->count();
        $this->assertSame(20, $s2r4Count, 'Sesi 2 Room 4 should have exactly 20 students (partial XI).');

        // ── Assertion 6: grade_level on ExamPeriod is null (mixed-grade sessions) ──
        foreach ($periods as $period) {
            $this->assertNull($period->grade_level, 'Auto-generated mixed-grade sessions should have null grade_level.');
        }

        // ── Assertion 7: verify specific grades in specific rooms ──
        // Sesi 1 R1: all students should be X
        $s1r1Grades = ExamRoomAssignment::query()
            ->where('exam_room_assignments.exam_period_id', $periods[0]->id)
            ->where('exam_room_assignments.room_id', $rooms[0]->id)
            ->join('students', 'students.id', '=', 'exam_room_assignments.student_id')
            ->pluck('students.class_name')
            ->map(fn ($cn) => ExamPeriod::extractGradeLevel($cn))
            ->unique()
            ->values();
        $this->assertCount(1, $s1r1Grades, 'Sesi 1 Room 1 should have only 1 grade level.');
        $this->assertSame('X', $s1r1Grades[0], 'Sesi 1 Room 1 should contain only grade X students.');

        // Sesi 2 R1: all students should be XI
        $s2r1Grades = ExamRoomAssignment::query()
            ->where('exam_room_assignments.exam_period_id', $periods[1]->id)
            ->where('exam_room_assignments.room_id', $rooms[0]->id)
            ->join('students', 'students.id', '=', 'exam_room_assignments.student_id')
            ->pluck('students.class_name')
            ->map(fn ($cn) => ExamPeriod::extractGradeLevel($cn))
            ->unique()
            ->values();
        $this->assertCount(1, $s2r1Grades, 'Sesi 2 Room 1 should have only 1 grade level.');
        $this->assertSame('XI', $s2r1Grades[0], 'Sesi 2 Room 1 should contain only grade XI students.');
    }
}

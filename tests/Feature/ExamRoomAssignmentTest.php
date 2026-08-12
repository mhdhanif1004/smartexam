<?php

namespace Tests\Feature;

use App\Models\ExamPeriod;
use App\Models\ExamRoomAssignment;
use App\Models\Question;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamRoomAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ExamPeriod $period;

    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();

        $this->subject = Subject::factory()->create(['name' => 'Matematika']);
        Question::factory()->create(['subject_id' => $this->subject->id]);

        $this->period = ExamPeriod::factory()->create([
            'name' => 'Sesi 1',
            'exam_date' => '2026-08-10',
            'start_time' => '07:30:00',
            'end_time' => '11:00:00',
        ]);
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
            'class_names' => ['XI RPL 1'],
            'rooms' => [],
            'subjects' => [
                ['subject_id' => $this->subject->id, 'start_time' => '07:30', 'duration_minutes' => 60],
            ],
        ], $overrides);
    }

    private function seatOf(Student $student, int $periodId): ?int
    {
        return ExamRoomAssignment::query()
            ->where('exam_period_id', $periodId)
            ->where('student_id', $student->id)
            ->value('seat_number');
    }

    private function roomOf(Student $student, int $periodId): ?int
    {
        return ExamRoomAssignment::query()
            ->where('exam_period_id', $periodId)
            ->where('student_id', $student->id)
            ->value('room_id');
    }

    public function test_one_class_spills_into_next_room_with_sequential_seats(): void
    {
        $roomA = Room::factory()->create(['name' => 'R. 01', 'capacity' => 3]);
        $roomB = Room::factory()->create(['name' => 'R. 02', 'capacity' => 2]);

        $adam = $this->student('Adam', 'XI RPL 1');
        $bella = $this->student('Bella', 'XI RPL 1');
        $cinta = $this->student('Cinta', 'XI RPL 1');
        $dian = $this->student('Dian', 'XI RPL 1');
        $eka = $this->student('Eka', 'XI RPL 1');

        $this->actingAs($this->admin)
            ->post(route('admin.exam-periods.groups.store', $this->period), $this->payload([
                'class_names' => ['XI RPL 1'],
                'rooms' => [$roomA->id, $roomB->id],
            ]))
            ->assertRedirect(route('admin.exam-periods.show', $this->period))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('exam_room_assignments', 5);
        $this->assertDatabaseCount('exam_schedules', 2);

        $this->assertSame($roomA->id, $this->roomOf($adam, $this->period->id));
        $this->assertSame(1, $this->seatOf($adam, $this->period->id));
        $this->assertSame($roomA->id, $this->roomOf($bella, $this->period->id));
        $this->assertSame(2, $this->seatOf($bella, $this->period->id));
        $this->assertSame($roomA->id, $this->roomOf($cinta, $this->period->id));
        $this->assertSame(3, $this->seatOf($cinta, $this->period->id));

        $this->assertSame($roomB->id, $this->roomOf($dian, $this->period->id));
        $this->assertSame(1, $this->seatOf($dian, $this->period->id));
        $this->assertSame($roomB->id, $this->roomOf($eka, $this->period->id));
        $this->assertSame(2, $this->seatOf($eka, $this->period->id));
    }

    public function test_cross_class_placement_mixes_classes_in_the_same_room(): void
    {
        $roomA = Room::factory()->create(['name' => 'R. 01', 'capacity' => 3]);
        $roomB = Room::factory()->create(['name' => 'R. 02', 'capacity' => 3]);

        $adam = $this->student('Adam', 'XI RPL 1');
        $bella = $this->student('Bella', 'XI RPL 1');
        $cinta = $this->student('Cinta', 'XII RPL 1');
        $dian = $this->student('Dian', 'XII RPL 1');
        $eka = $this->student('Eka', 'XII RPL 1');

        $this->actingAs($this->admin)
            ->post(route('admin.exam-periods.groups.store', $this->period), $this->payload([
                'class_names' => ['XI RPL 1', 'XII RPL 1'],
                'rooms' => [$roomA->id, $roomB->id],
            ]))
            ->assertSessionHas('success');

        // Kelas XI RPL 1 penuh 2 siswa, lalu kelas XII RPL 1 melanjutkan di
        // ruangan yang sama (kursi 3) sebelum pindah ke ruangan berikutnya.
        $this->assertSame($roomA->id, $this->roomOf($adam, $this->period->id));
        $this->assertSame(1, $this->seatOf($adam, $this->period->id));
        $this->assertSame($roomA->id, $this->roomOf($bella, $this->period->id));
        $this->assertSame(2, $this->seatOf($bella, $this->period->id));
        $this->assertSame($roomA->id, $this->roomOf($cinta, $this->period->id));
        $this->assertSame(3, $this->seatOf($cinta, $this->period->id));

        $this->assertSame($roomB->id, $this->roomOf($dian, $this->period->id));
        $this->assertSame(1, $this->seatOf($dian, $this->period->id));
        $this->assertSame($roomB->id, $this->roomOf($eka, $this->period->id));
        $this->assertSame(2, $this->seatOf($eka, $this->period->id));
    }

    public function test_insufficient_capacity_fails_atomically_without_saving_anything(): void
    {
        $roomA = Room::factory()->create(['name' => 'R. 01', 'capacity' => 2]);
        $roomB = Room::factory()->create(['name' => 'R. 02', 'capacity' => 2]);

        foreach (['Adam', 'Bella', 'Cinta', 'Dian', 'Eka'] as $name) {
            $this->student($name, 'XI RPL 1');
        }

        $this->actingAs($this->admin)
            ->from(route('admin.exam-periods.groups.create', $this->period))
            ->post(route('admin.exam-periods.groups.store', $this->period), $this->payload([
                'class_names' => ['XI RPL 1'],
                'rooms' => [$roomA->id, $roomB->id],
            ]))
            ->assertSessionHasErrors('rooms');

        $error = session('errors')->get('rooms')[0] ?? '';
        $this->assertStringContainsString('5', $error);
        $this->assertStringContainsString('4', $error);
        $this->assertStringContainsString('kapasitas', $error);

        $this->assertDatabaseCount('exam_room_assignments', 0);
        $this->assertDatabaseCount('exam_schedules', 0);
    }

    public function test_card_preview_shows_room_from_exam_room_assignments(): void
    {
        $room = Room::factory()->create(['name' => 'R. 101', 'capacity' => 40]);
        $student = $this->student('Siti Aminah', 'XI RPL 1');

        ExamRoomAssignment::factory()->create([
            'exam_period_id' => $this->period->id,
            'student_id' => $student->id,
            'room_id' => $room->id,
            'seat_number' => 42,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.student-cards.preview'), ['student_ids' => [$student->id]])
            ->assertOk()
            ->assertSee('<td class="lbl">Ruangan</td>', false)
            ->assertSee('R. 101');
    }

    public function test_duplicate_student_placement_in_same_period_is_rejected(): void
    {
        $roomA = Room::factory()->create(['name' => 'R. 01', 'capacity' => 30]);
        $roomB = Room::factory()->create(['name' => 'R. 02', 'capacity' => 30]);
        $student = $this->student('Adam', 'XI RPL 1');

        $this->actingAs($this->admin)
            ->post(route('admin.exam-periods.groups.store', $this->period), $this->payload([
                'class_names' => ['XI RPL 1'],
                'rooms' => [$roomA->id],
            ]))
            ->assertSessionHas('success');

        // Kelompok kedua untuk kelas yang sama di sesi yang sama ditolak,
        // walaupun memakai ruangan berbeda: satu siswa hanya boleh duduk di
        // satu ruangan per sesi.
        $this->actingAs($this->admin)
            ->from(route('admin.exam-periods.groups.create', $this->period))
            ->post(route('admin.exam-periods.groups.store', $this->period), $this->payload([
                'class_names' => ['XI RPL 1'],
                'rooms' => [$roomB->id],
            ]))
            ->assertSessionHasErrors('class_names');

        $this->assertSame(1, ExamRoomAssignment::query()->where('student_id', $student->id)->count());
        $this->assertDatabaseCount('exam_schedules', 1);
    }
}

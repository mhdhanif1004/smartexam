<?php

namespace Tests\Feature;

use App\Models\ExamPeriod;
use App\Models\ExamSchedule;
use App\Models\Question;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Supervisor;
use App\Models\SupervisorAttendance;
use App\Models\SupervisorRoomAssignment;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SupervisorRoomRotationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Room $roomA;

    private Room $roomB;

    private Room $roomC;

    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();

        $this->roomA = Room::factory()->create(['room_number' => 1, 'capacity' => 25]);
        $this->roomB = Room::factory()->create(['room_number' => 2, 'capacity' => 25]);
        $this->roomC = Room::factory()->create(['room_number' => 3, 'capacity' => 25]);

        $this->subject = Subject::factory()->create(['name' => 'Matematika', 'class_label' => 'XII']);
        Question::factory()->create(['subject_id' => $this->subject->id]);
    }

    /**
     * Buat pengawas dengan nama tertentu (untuk urutan rotasi deterministik).
     */
    private function supervisor(string $name, ?Room $room = null, bool $active = true): User
    {
        return Supervisor::factory()->create([
            'room_id' => $room?->id,
            'user_id' => User::factory()->pengawas()->create([
                'name' => $name,
                'is_active' => $active,
            ])->id,
        ])->user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function autoGeneratePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'UAS Ganjil Kelas 12',
            'exam_date' => '2026-08-12',
            'class_names' => ['XII RPL 1'],
            'rooms' => [$this->roomA->id, $this->roomB->id],
            'subjects' => [
                ['subject_id' => $this->subject->id, 'duration_minutes' => 60],
            ],
            'start_time' => '07:30',
            'gap_minutes' => 15,
        ], $overrides);
    }

    public function test_auto_generate_creates_rotation_for_each_room_using_active_supervisors(): void
    {
        $this->supervisor('Andi');
        $this->supervisor('Budi');

        Student::factory()->create([
            'class_name' => 'XII RPL 1',
            'user_id' => User::factory()->peserta()->create(['name' => 'Siswa 001'])->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.exam-periods.auto-generate.store'), $this->autoGeneratePayload())
            ->assertRedirect(route('admin.exam-periods.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('supervisor_room_assignments', 2);

        $assignments = SupervisorRoomAssignment::query()->get();

        $this->assertSame(2, $assignments->pluck('supervisor_id')->unique()->count());
        $this->assertSame(2, $assignments->pluck('room_id')->unique()->count());

        foreach ($assignments as $assignment) {
            $this->assertSame('2026-08-12', $assignment->exam_date->toDateString());
        }

        // Ruangan diurutkan nama: Ruang 01 -> Andi, Ruang 02 -> Budi
        $roomA = $assignments->first(fn ($a) => $a->room_id === $this->roomA->id);
        $this->assertSame('Andi', $roomA->supervisor->user->name);
        $this->assertSame(1, (int) $roomA->rotation_index);

        $roomB = $assignments->first(fn ($a) => $a->room_id === $this->roomB->id);
        $this->assertSame('Budi', $roomB->supervisor->user->name);
    }

    public function test_auto_generate_excludes_inactive_supervisors(): void
    {
        $this->supervisor('Andi');
        $this->supervisor('Budi');
        $this->supervisor('Caca', active: false);

        Student::factory()->create([
            'class_name' => 'XII RPL 1',
            'user_id' => User::factory()->peserta()->create(['name' => 'Siswa 001'])->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.exam-periods.auto-generate.store'), $this->autoGeneratePayload());

        $assignedSupervisorNames = SupervisorRoomAssignment::query()
            ->with('supervisor.user')
            ->get()
            ->pluck('supervisor.user.name')
            ->all();

        $this->assertContains('Andi', $assignedSupervisorNames);
        $this->assertContains('Budi', $assignedSupervisorNames);
        $this->assertNotContains('Caca', $assignedSupervisorNames);
    }

    public function test_rooms_without_enough_supervisors_are_left_empty(): void
    {
        $this->supervisor('Andi');
        $this->supervisor('Budi');

        Student::factory()->create([
            'class_name' => 'XII RPL 1',
            'user_id' => User::factory()->peserta()->create(['name' => 'Siswa 001'])->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.exam-periods.auto-generate.store'), $this->autoGeneratePayload([
                'rooms' => [$this->roomA->id, $this->roomB->id, $this->roomC->id],
            ]));

        $this->assertDatabaseCount('supervisor_room_assignments', 2);
        $this->assertSame(2, SupervisorRoomAssignment::query()->distinct('supervisor_id')->count('supervisor_id'));
        $this->assertSame(2, SupervisorRoomAssignment::query()->distinct('room_id')->count('room_id'));
    }

    public function test_groups_store_also_generates_rotation(): void
    {
        $this->supervisor('Andi');
        $this->supervisor('Budi');

        Student::factory()->create([
            'class_name' => 'XII RPL 1',
            'user_id' => User::factory()->peserta()->create(['name' => 'Siswa 001'])->id,
        ]);

        $period = ExamPeriod::factory()->create([
            'name' => 'Sesi Tambahan',
            'exam_date' => '2026-08-12',
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.exam-periods.groups.store', $period), [
                'class_names' => ['XII RPL 1'],
                'rooms' => [$this->roomA->id, $this->roomB->id],
                'subjects' => [
                    ['subject_id' => $this->subject->id, 'start_time' => '07:30', 'duration_minutes' => 60],
                ],
            ])
            ->assertRedirect(route('admin.exam-periods.show', $period))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('supervisor_room_assignments', 2);
    }

    public function test_rotation_route_only_fills_empty_rooms_without_overwriting(): void
    {
        $supA = $this->supervisor('Andi');
        $this->supervisor('Budi');

        $period = ExamPeriod::factory()->create([
            'name' => 'Sesi 1',
            'exam_date' => now()->toDateString(),
        ]);

        ExamSchedule::factory()->create([
            'exam_period_id' => $period->id,
            'room_id' => $this->roomA->id,
            'subject_id' => $this->subject->id,
            'exam_date' => now()->toDateString(),
        ]);

        ExamSchedule::factory()->create([
            'exam_period_id' => $period->id,
            'room_id' => $this->roomB->id,
            'subject_id' => $this->subject->id,
            'exam_date' => now()->toDateString(),
        ]);

        SupervisorRoomAssignment::factory()->create([
            'exam_period_id' => $period->id,
            'exam_date' => now()->toDateString(),
            'supervisor_id' => $supA->supervisor->id,
            'room_id' => $this->roomA->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.exam-periods.supervisor-rotation', $period))
            ->assertSessionHas('success');

        // Ruangan A tidak ditimpa, Ruangan B terisi pengawas lain
        $this->assertDatabaseHas('supervisor_room_assignments', [
            'exam_period_id' => $period->id,
            'exam_date' => now()->toDateString(),
            'room_id' => $this->roomA->id,
            'supervisor_id' => $supA->supervisor->id,
        ]);

        $this->assertSame(2, SupervisorRoomAssignment::query()->where('exam_period_id', $period->id)->count());

        $roomB = SupervisorRoomAssignment::query()
            ->where('exam_period_id', $period->id)
            ->where('room_id', $this->roomB->id)
            ->first();

        $this->assertNotSame($supA->supervisor->id, $roomB->supervisor_id);

        // Generate lagi -> semua sudah terisi, tidak menambah baris baru
        $this->actingAs($this->admin)
            ->post(route('admin.exam-periods.supervisor-rotation', $period))
            ->assertSessionHas('info');

        $this->assertSame(2, SupervisorRoomAssignment::query()->where('exam_period_id', $period->id)->count());
    }

    public function test_rotation_route_requires_schedules_in_period(): void
    {
        $this->supervisor('Andi');

        $period = ExamPeriod::factory()->create(['name' => 'Sesi Kosong']);

        $this->actingAs($this->admin)
            ->post(route('admin.exam-periods.supervisor-rotation', $period))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('supervisor_room_assignments', 0);
    }

    public function test_pengawas_portal_reads_room_from_rotation_not_static_room(): void
    {
        $subjectA = Subject::factory()->create(['name' => 'Matematika']);
        $subjectB = Subject::factory()->create(['name' => 'Fisika']);

        $pengawas = $this->supervisor('Andi', $this->roomB);

        $period = ExamPeriod::factory()->create([
            'name' => 'Sesi 1',
            'exam_date' => now()->toDateString(),
        ]);

        SupervisorRoomAssignment::factory()->create([
            'exam_period_id' => $period->id,
            'exam_date' => now()->toDateString(),
            'supervisor_id' => $pengawas->supervisor->id,
            'room_id' => $this->roomA->id,
        ]);

        ExamSchedule::factory()->create([
            'room_id' => $this->roomA->id,
            'subject_id' => $subjectA->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => now()->toDateString(),
            'start_time' => now()->subMinutes(30)->format('H:i:s'),
            'end_time' => now()->addMinutes(30)->format('H:i:s'),
            'status' => ExamSchedule::STATUS_ONGOING,
        ]);

        ExamSchedule::factory()->create([
            'room_id' => $this->roomB->id,
            'subject_id' => $subjectB->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => now()->toDateString(),
            'status' => ExamSchedule::STATUS_ONGOING,
        ]);

        $this->actingAs($pengawas)
            ->get(route('pengawas.dashboard'))
            ->assertOk()
            ->assertSee('Matematika')
            ->assertDontSee('Fisika');
    }

    public function test_login_listener_records_attendance_in_rotated_room(): void
    {
        $pengawas = $this->supervisor('Andi', $this->roomB);

        $period = ExamPeriod::factory()->create([
            'name' => 'Sesi 1',
            'exam_date' => now()->toDateString(),
        ]);

        SupervisorRoomAssignment::factory()->create([
            'exam_period_id' => $period->id,
            'exam_date' => now()->toDateString(),
            'supervisor_id' => $pengawas->supervisor->id,
            'room_id' => $this->roomA->id,
        ]);

        $schedule = ExamSchedule::factory()->create([
            'room_id' => $this->roomA->id,
            'subject_id' => $this->subject->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => now()->toDateString(),
            'start_time' => now()->subMinutes(30)->format('H:i:s'),
            'end_time' => now()->addMinutes(30)->format('H:i:s'),
            'status' => ExamSchedule::STATUS_ONGOING,
        ]);

        Event::dispatch(new Login('web', $pengawas, false));

        $this->assertDatabaseHas('supervisor_attendances', [
            'supervisor_id' => $pengawas->supervisor->id,
            'exam_schedule_id' => $schedule->id,
            'room_id' => $this->roomA->id,
            'status' => SupervisorAttendance::STATUS_PRESENT,
        ]);
    }

    public function test_supervisor_index_shows_detail_link(): void
    {
        $pengawas = $this->supervisor('Andi', $this->roomA);

        $this->actingAs($this->admin)
            ->get(route('admin.supervisors.index'))
            ->assertOk()
            ->assertSee('Detail')
            ->assertSee(route('admin.supervisors.show', $pengawas->supervisor), false);
    }

    public function test_supervisor_show_page_lists_assignment_history(): void
    {
        $pengawas = $this->supervisor('Andi', $this->roomA);

        $periodA = ExamPeriod::factory()->create(['name' => 'Sesi Hari Senin', 'exam_date' => '2026-08-10']);
        $periodB = ExamPeriod::factory()->create(['name' => 'Sesi Hari Selasa', 'exam_date' => '2026-08-11']);

        SupervisorRoomAssignment::factory()->create([
            'exam_period_id' => $periodA->id,
            'exam_date' => '2026-08-10',
            'supervisor_id' => $pengawas->supervisor->id,
            'room_id' => $this->roomA->id,
        ]);

        SupervisorRoomAssignment::factory()->create([
            'exam_period_id' => $periodB->id,
            'exam_date' => '2026-08-11',
            'supervisor_id' => $pengawas->supervisor->id,
            'room_id' => $this->roomB->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.supervisors.show', $pengawas->supervisor))
            ->assertOk()
            ->assertSee('Sesi Hari Senin')
            ->assertSee('Sesi Hari Selasa')
            ->assertSee('Ruang 1')
            ->assertSee('Ruang 2');
    }

    public function test_room_with_supervisor_count_two_gets_two_supervisors(): void
    {
        $this->roomA->update(['supervisor_count' => 2]);

        $this->supervisor('Andi');
        $this->supervisor('Budi');
        $this->supervisor('Caca');

        Student::factory()->create([
            'class_name' => 'XII RPL 1',
            'user_id' => User::factory()->peserta()->create(['name' => 'Siswa 001'])->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.exam-periods.auto-generate.store'), $this->autoGeneratePayload());

        $this->assertDatabaseCount('supervisor_room_assignments', 3);

        $roomASupervisors = SupervisorRoomAssignment::query()
            ->where('room_id', $this->roomA->id)
            ->pluck('supervisor_id')
            ->all();
        $roomBSupervisors = SupervisorRoomAssignment::query()
            ->where('room_id', $this->roomB->id)
            ->pluck('supervisor_id')
            ->all();

        $this->assertCount(2, $roomASupervisors);
        $this->assertCount(1, $roomBSupervisors);
        $this->assertSame(2, count(array_unique($roomASupervisors)));
        $this->assertSame(3, SupervisorRoomAssignment::query()->distinct('supervisor_id')->count('supervisor_id'));
    }

    public function test_rotation_fills_remaining_slots_up_to_supervisor_count_without_overwriting(): void
    {
        $this->roomA->update(['supervisor_count' => 2]);

        $supA = $this->supervisor('Andi');
        $this->supervisor('Budi');
        $this->supervisor('Caca');

        $period = ExamPeriod::factory()->create([
            'name' => 'Sesi 1',
            'exam_date' => now()->toDateString(),
        ]);

        ExamSchedule::factory()->create([
            'exam_period_id' => $period->id,
            'room_id' => $this->roomA->id,
            'subject_id' => $this->subject->id,
            'exam_date' => now()->toDateString(),
        ]);

        SupervisorRoomAssignment::factory()->create([
            'exam_period_id' => $period->id,
            'exam_date' => now()->toDateString(),
            'supervisor_id' => $supA->supervisor->id,
            'room_id' => $this->roomA->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.exam-periods.supervisor-rotation', $period))
            ->assertSessionHas('success');

        $assignments = SupervisorRoomAssignment::query()
            ->where('exam_period_id', $period->id)
            ->where('room_id', $this->roomA->id)
            ->get();

        $this->assertCount(2, $assignments);
        $this->assertSame(2, $assignments->pluck('supervisor_id')->unique()->count());
        $this->assertTrue($assignments->contains('supervisor_id', $supA->supervisor->id));

        // Generate lagi -> slot sudah penuh, tidak menambah baris
        $this->actingAs($this->admin)
            ->post(route('admin.exam-periods.supervisor-rotation', $period))
            ->assertSessionHas('info');

        $this->assertDatabaseCount('supervisor_room_assignments', 2);
    }

    public function test_rotation_with_insufficient_supervisors_leaves_slots_empty_and_warns(): void
    {
        $this->roomA->update(['supervisor_count' => 2]);
        $this->roomB->update(['supervisor_count' => 2]);

        $this->supervisor('Andi');

        $period = ExamPeriod::factory()->create([
            'name' => 'Sesi 1',
            'exam_date' => now()->toDateString(),
        ]);

        ExamSchedule::factory()->create([
            'exam_period_id' => $period->id,
            'room_id' => $this->roomA->id,
            'subject_id' => $this->subject->id,
            'exam_date' => now()->toDateString(),
        ]);

        ExamSchedule::factory()->create([
            'exam_period_id' => $period->id,
            'room_id' => $this->roomB->id,
            'subject_id' => $this->subject->id,
            'exam_date' => now()->toDateString(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.exam-periods.supervisor-rotation', $period))
            ->assertSessionHas('success');

        $this->assertStringContainsString('tidak mencukupi', (string) session('success'));
        $this->assertDatabaseCount('supervisor_room_assignments', 1);

        // Generate lagi -> tidak ada slot baru, diberi info
        $this->actingAs($this->admin)
            ->post(route('admin.exam-periods.supervisor-rotation', $period))
            ->assertSessionHas('info');

        $this->assertDatabaseCount('supervisor_room_assignments', 1);
    }

    public function test_supervisor_with_same_room_same_day_is_not_reassigned_to_that_room(): void
    {
        $supA = $this->supervisor('Andi');
        $this->supervisor('Budi');

        $period1 = ExamPeriod::factory()->create([
            'name' => 'Gelombang 1',
            'exam_date' => now()->toDateString(),
        ]);
        $period2 = ExamPeriod::factory()->create([
            'name' => 'Gelombang 2',
            'exam_date' => now()->toDateString(),
        ]);

        SupervisorRoomAssignment::factory()->create([
            'exam_period_id' => $period1->id,
            'exam_date' => now()->toDateString(),
            'supervisor_id' => $supA->supervisor->id,
            'room_id' => $this->roomA->id,
        ]);

        ExamSchedule::factory()->create([
            'exam_period_id' => $period2->id,
            'room_id' => $this->roomA->id,
            'subject_id' => $this->subject->id,
            'exam_date' => now()->toDateString(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.exam-periods.supervisor-rotation', $period2))
            ->assertSessionHas('success');

        $assignment = SupervisorRoomAssignment::query()
            ->where('exam_period_id', $period2->id)
            ->firstOrFail();

        $this->assertSame($this->roomA->id, $assignment->room_id);
        $this->assertNotSame($supA->supervisor->id, $assignment->supervisor_id);
    }

    public function test_room_form_saves_supervisor_count_and_validates_bounds(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.rooms.store'), [
                'room_number' => 5,
                'capacity' => 25,
                'supervisor_count' => 2,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(2, Room::where('room_number', 5)->firstOrFail()->supervisor_count);

        $this->actingAs($this->admin)
            ->from(route('admin.rooms.create'))
            ->post(route('admin.rooms.store'), [
                'room_number' => 6,
                'capacity' => 25,
                'supervisor_count' => 0,
            ])
            ->assertSessionHasErrors('supervisor_count');

        $this->actingAs($this->admin)
            ->from(route('admin.rooms.create'))
            ->post(route('admin.rooms.store'), [
                'room_number' => 7,
                'capacity' => 25,
                'supervisor_count' => 99,
            ])
            ->assertSessionHasErrors('supervisor_count');
    }

    public function test_room_form_requires_capacity_and_supervisor_count(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.rooms.create'))
            ->post(route('admin.rooms.store'), [
                'room_number' => 8,
                'supervisor_count' => 1,
            ])
            ->assertSessionHasErrors('capacity');

        $this->actingAs($this->admin)
            ->from(route('admin.rooms.create'))
            ->post(route('admin.rooms.store'), [
                'room_number' => 9,
                'capacity' => 0,
                'supervisor_count' => 1,
            ])
            ->assertSessionHasErrors('capacity');

        $this->actingAs($this->admin)
            ->from(route('admin.rooms.create'))
            ->post(route('admin.rooms.store'), [
                'room_number' => 10,
                'capacity' => 25,
            ])
            ->assertSessionHasErrors('supervisor_count');
    }
}

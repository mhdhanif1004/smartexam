<?php

namespace Tests\Feature;

use App\Models\ExamPeriod;
use App\Models\ExamSchedule;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Supervisor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PengawasDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);

        parent::tearDown();
    }

    public function test_dashboard_card_count_matches_table_rows_via_assignments(): void
    {
        // Waktu di dalam jendela ongoing (08:30-11:30) agar schedule berstatus ongoing.
        Carbon::setTestNow(Carbon::parse('2026-09-04 09:15:00'));
        $examDate = Carbon::today()->toDateString(); // 2026-09-04

        $room = Room::factory()->create();
        $supervisor = Supervisor::factory()->create(['room_id' => $room->id]);

        $period = ExamPeriod::create([
            'name' => 'Sesi 1',
            'exam_date' => $examDate,
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
        ]);

        DB::table('supervisor_room_assignments')->insert([
            'exam_period_id' => $period->id,
            'exam_date' => $examDate,
            'supervisor_id' => $supervisor->id,
            'room_id' => $room->id,
            'rotation_index' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $schedule = ExamSchedule::factory()->create([
            'subject_id' => Subject::factory()->create()->id,
            'room_id' => $room->id,
            'exam_period_id' => $period->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => $examDate,
            'start_time' => '08:30:00',
            'end_time' => '11:30:00',
            'duration_minutes' => 90,
            'status' => ExamSchedule::STATUS_SCHEDULED,
        ]);

        $students = collect();
        for ($i = 1; $i <= 3; $i++) {
            $student = Student::factory()->create([
                'class_name' => 'XI RPL 1',
            ]);

            DB::table('exam_room_assignments')->insert([
                'exam_period_id' => $period->id,
                'student_id' => $student->id,
                'room_id' => $room->id,
                'seat_number' => $i,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $students->push($student->fresh()->load('user'));
        }

        $response = $this->actingAs($supervisor->user)
            ->get(route('pengawas.dashboard'));

        $response->assertOk();
        $response->assertSee('dari 3 peserta');

        foreach ($students as $student) {
            $response->assertSee($student->nisn);
            $response->assertSee($student->user->name);
        }

        $response->assertViewHas('students', function ($viewStudents) {
            return $viewStudents->count() === 3;
        });

        // Pastikan scheduleStats konsisten dengan jumlah students di tabel.
        $response->assertViewHas('scheduleStats', function ($stats) use ($schedule) {
            return isset($stats[$schedule->id]) && $stats[$schedule->id]['total'] === 3;
        });
    }

    public function test_dashboard_tidak_mismatch_jika_belum_ongoing(): void
    {
        // Waktu sebelum jendela ongoing — dashboard tidak boleh menampilkan card peserta yang mismatch.
        Carbon::setTestNow(Carbon::parse('2026-09-04 07:00:00'));
        $examDate = Carbon::today()->toDateString();

        $room = Room::factory()->create();
        $supervisor = Supervisor::factory()->create(['room_id' => $room->id]);

        $period = ExamPeriod::create([
            'name' => 'Sesi 1',
            'exam_date' => $examDate,
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
        ]);

        DB::table('supervisor_room_assignments')->insert([
            'exam_period_id' => $period->id,
            'exam_date' => $examDate,
            'supervisor_id' => $supervisor->id,
            'room_id' => $room->id,
            'rotation_index' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ExamSchedule::factory()->create([
            'subject_id' => Subject::factory()->create()->id,
            'room_id' => $room->id,
            'exam_period_id' => $period->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => $examDate,
            'start_time' => '08:30:00',
            'end_time' => '11:30:00',
            'duration_minutes' => 90,
            'status' => ExamSchedule::STATUS_SCHEDULED,
        ]);

        for ($i = 1; $i <= 3; $i++) {
            $student = Student::factory()->create(['class_name' => 'XI RPL 1']);
            DB::table('exam_room_assignments')->insert([
                'exam_period_id' => $period->id,
                'student_id' => $student->id,
                'room_id' => $room->id,
                'seat_number' => $i,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $response = $this->actingAs($supervisor->user)
            ->get(route('pengawas.dashboard'));

        $response->assertOk();
        // Sebelum ongoing, card ringkasan peserta belum ditampilkan sehingga tidak terjadi mismatch.
        $response->assertDontSee('dari 3 peserta');
        $response->assertViewHas('students', function ($viewStudents) {
            return $viewStudents->isEmpty();
        });
        $response->assertSee('Belum Dimulai');
    }
}

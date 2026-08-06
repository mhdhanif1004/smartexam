<?php

namespace Tests\Feature;

use App\Models\ExamResult;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\Room;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\User;
use App\Models\Violation;
use Database\Seeders\ClassroomSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminAdvancedTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->seed(ClassroomSeeder::class);
    }

    public function test_admin_can_view_login_card_page(): void
    {
        $this->actingAs($this->admin)->get(route('admin.student-cards.index'))
            ->assertOk()
            ->assertSee('Kartu Login Peserta');
    }

    public function test_login_card_print_requires_selected_students(): void
    {
        $this->actingAs($this->admin)->post(route('admin.student-cards.print'), [])
            ->assertSessionHasErrors('student_ids');
    }

    public function test_login_card_preview_and_print_show_plain_password(): void
    {
        $student = Student::factory()->create();
        $student->user->update(['plain_password' => 'rahasia123']);

        $this->actingAs($this->admin)
            ->post(route('admin.student-cards.preview'), ['student_ids' => [$student->id]])
            ->assertOk()
            ->assertSee('rahasia123')
            ->assertSee($student->user->username);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.student-cards.print'), ['student_ids' => [$student->id]]);

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }

    public function test_admin_can_view_supervisor_login_card_tab(): void
    {
        $this->actingAs($this->admin)->get(route('admin.student-cards.index', ['type' => 'pengawas']))
            ->assertOk()
            ->assertSee('Kartu Login Pengawas');
    }

    public function test_supervisor_login_card_preview_and_print_show_plain_password(): void
    {
        $supervisor = Supervisor::factory()->create();
        $supervisor->user->update(['plain_password' => 'pengawas123']);

        $this->actingAs($this->admin)
            ->post(route('admin.student-cards.preview'), ['type' => 'pengawas', 'supervisor_ids' => [$supervisor->id]])
            ->assertOk()
            ->assertSee('pengawas123')
            ->assertSee($supervisor->user->email);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.student-cards.print'), ['type' => 'pengawas', 'supervisor_ids' => [$supervisor->id]]);

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }

    public function test_admin_can_fetch_plain_password_via_endpoint(): void
    {
        $student = Student::factory()->create();
        $student->user->update(['plain_password' => 'rahasia123']);

        $this->actingAs($this->admin)
            ->get(route('admin.users.plain-password', $student->user))
            ->assertOk()
            ->assertJson(['plain_password' => 'rahasia123']);
    }

    public function test_plain_password_endpoint_is_forbidden_for_peserta(): void
    {
        $student = Student::factory()->create();

        $this->actingAs($student->user)
            ->get(route('admin.users.plain-password', $student->user))
            ->assertForbidden();
    }

    public function test_plain_password_is_stored_encrypted(): void
    {
        $this->actingAs($this->admin)->post(route('admin.students.store'), [
            'name' => 'Siti Aminah',
            'username' => 'siti123456789',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'nisn' => '0099887766',
            'class_name' => 'XI RPL 1',
            'is_active' => '1',
        ])->assertRedirect(route('admin.students.index'));

        $user = User::where('username', 'siti123456789')->first();

        $this->assertEquals('rahasia123', $user->plain_password);
        $this->assertNotEquals('rahasia123', DB::table('users')->where('id', $user->id)->value('plain_password'));
        $this->assertTrue(password_verify('rahasia123', $user->password));
    }

    public function test_admin_can_view_reports_with_statistics(): void
    {
        $schedule = ExamSchedule::factory()->create(['exam_date' => '2026-08-10']);
        $scores = [100, 80, 60];

        foreach ($scores as $score) {
            ExamResult::factory()->create([
                'exam_session_id' => ExamSession::factory()->create([
                    'student_id' => Student::factory()->create()->id,
                    'exam_schedule_id' => $schedule->id,
                ])->id,
                'total_score' => $score,
                'is_passed' => $score >= 75,
            ]);
        }

        $response = $this->actingAs($this->admin)->get(route('admin.reports.index', [
            'subject_id' => $schedule->subject_id,
        ]));

        $response->assertOk()
            ->assertSee('Laporan Hasil Ujian')
            ->assertSee('80.00')
            ->assertSee('100.00')
            ->assertSee('60.00')
            ->assertSee('2')
            ->assertSee('1');
    }

    public function test_report_exports_excel_and_pdf(): void
    {
        ExamResult::factory()->count(2)->create();

        $excel = $this->actingAs($this->admin)->get(route('admin.reports.export-excel'));
        $excel->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', $excel->headers->get('content-type'));

        $pdf = $this->actingAs($this->admin)->get(route('admin.reports.export-pdf'));
        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', $pdf->headers->get('content-type'));
    }

    public function test_admin_can_view_violations_with_filters(): void
    {
        $room = Room::factory()->create();
        $schedule = ExamSchedule::factory()->create(['room_id' => $room->id]);
        $student = Student::factory()->create();
        Violation::factory()->count(2)->create([
            'exam_session_id' => ExamSession::factory()->create([
                'student_id' => $student->id,
                'exam_schedule_id' => $schedule->id,
            ])->id,
            'violation_type' => 'mencontek',
        ]);

        $this->actingAs($this->admin)->get(route('admin.violations.index'))
            ->assertOk()
            ->assertSee('Riwayat Pelanggaran');

        $this->actingAs($this->admin)->get(route('admin.violations.index', ['room_id' => $room->id]))
            ->assertOk()
            ->assertSee($student->user->name);

        $this->actingAs($this->admin)->get(route('admin.violations.index', ['violation_type' => 'tidak_ada']))
            ->assertOk()
            ->assertSee('Tidak ada data.');
    }

    public function test_dashboard_shows_real_stats_and_upcoming_schedules(): void
    {
        Student::factory()->count(3)->create();
        ExamSchedule::factory()->create(['exam_date' => now()->format('Y-m-d')]);
        $upcoming = ExamSchedule::factory()->create(['exam_date' => now()->addDay()->format('Y-m-d')]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('3')
            ->assertSee('Ujian Hari Ini')
            ->assertSee($upcoming->subject->name)
            ->assertSee('Pelanggaran Terbaru');
    }

    public function test_peserta_cannot_access_new_admin_modules(): void
    {
        $peserta = User::factory()->peserta()->create();

        $this->actingAs($peserta)->get(route('admin.student-cards.index'))->assertForbidden();
        $this->actingAs($peserta)->get(route('admin.reports.index'))->assertForbidden();
        $this->actingAs($peserta)->get(route('admin.violations.index'))->assertForbidden();
    }
}

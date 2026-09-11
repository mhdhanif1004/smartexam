<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ExamPeriod;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\ExamToken;
use App\Models\ExamType;
use App\Models\GuruMapel;
use App\Models\Question;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Supervisor;
use App\Models\TeacherSubjectClassAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuruMapelProctorTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroom;

    private GuruMapel $guruA;

    private GuruMapel $guruB;

    private Subject $subject;

    private ExamType $harian;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classroom = Classroom::factory()->create(['name' => 'XI RPL 1']);
        $this->subject = Subject::factory()->create(['name' => 'Matematika']);

        $this->guruA = GuruMapel::factory()->create();
        $this->guruB = GuruMapel::factory()->create();

        foreach ([$this->guruA, $this->guruB] as $guru) {
            TeacherSubjectClassAssignment::create([
                'guru_mapel_id' => $guru->id,
                'subject_id' => $this->subject->id,
                'classroom_id' => $this->classroom->id,
            ]);
        }

        $this->harian = ExamType::where('code', 'harian')->first();
        $this->harian->update(['boleh_dijadwalkan_guru' => true]);

        $this->student = Student::factory()->create(['classroom_id' => $this->classroom->id]);
    }

    /**
     * Guru A membuat period + schedule classroom-based untuk hari ini
     * (dalam jendela token). Kembalikan period.
     */
    private function makeOwnedPeriod(GuruMapel $guru, array $overrides = []): ExamPeriod
    {
        $start = now()->subMinutes(4);

        $period = ExamPeriod::create(array_merge([
            'name' => 'Matematika — XI RPL 1',
            'name_prefix' => 'Matematika — XI RPL 1',
            'exam_type_id' => $this->harian->id,
            'grade_level' => 'XI',
            'exam_date' => now()->toDateString(),
            'start_time' => $start->format('H:i:s'),
            'end_time' => $start->copy()->addMinutes(90)->format('H:i:s'),
            'created_by_user_id' => $guru->user_id,
        ], $overrides));

        ExamSchedule::create([
            'subject_id' => $this->subject->id,
            'room_id' => null,
            'classroom_id' => $this->classroom->id,
            'exam_period_id' => $period->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => $period->exam_date->toDateString(),
            'start_time' => $period->start_time,
            'end_time' => $period->end_time,
            'duration_minutes' => 90,
            'status' => ExamSchedule::STATUS_SCHEDULED,
        ]);

        ExamToken::create([
            'exam_period_id' => $period->id,
            'token_code' => 'ABCD1234',
            'rotation_index' => 0,
            'valid_from' => now()->subMinute(),
            'valid_until' => now()->addMinutes(10),
        ]);

        return $period;
    }

    // ── Middleware EnsureOwnsExamPeriod ──────────────────────────────

    public function test_owner_can_open_proctor_page(): void
    {
        $period = $this->makeOwnedPeriod($this->guruA);

        $this->actingAs($this->guruA->user)
            ->get(route('guru_mapel.proctor.show', $period))
            ->assertOk()
            ->assertSee('Awasi Sesi');
    }

    public function test_other_guru_cannot_open_proctor_page(): void
    {
        $period = $this->makeOwnedPeriod($this->guruA);

        // Guru B (sama-sama role guru_mapel) — bukan pembuat → 403.
        $this->actingAs($this->guruB->user)
            ->get(route('guru_mapel.proctor.show', $period))
            ->assertForbidden();
    }

    public function test_non_guru_cannot_open_proctor_page(): void
    {
        $period = $this->makeOwnedPeriod($this->guruA);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('guru_mapel.proctor.show', $period))
            ->assertForbidden();
    }

    public function test_admin_created_period_has_no_owner_and_not_proctoring(): void
    {
        // Period tanpa created_by (buatan admin/sistem) — guru tidak boleh awasi.
        $period = $this->makeOwnedPeriod($this->guruA, ['created_by_user_id' => null]);

        $this->actingAs($this->guruA->user)
            ->get(route('guru_mapel.proctor.show', $period))
            ->assertForbidden();
    }

    // ── Absensi proctor ──────────────────────────────────────────────

    public function test_owner_can_confirm_attendance(): void
    {
        $period = $this->makeOwnedPeriod($this->guruA);
        $schedule = $period->schedules()->first();

        $response = $this->actingAs($this->guruA->user)
            ->patchJson(route('guru_mapel.proctor.attendance.confirm', [$period, $schedule]), [
                'student_id' => $this->student->id,
                'status' => 'hadir',
            ]);

        $response->assertOk()
            ->assertJson(['ok' => true, 'attendance_confirmed' => true]);

        $this->assertDatabaseHas('exam_sessions', [
            'student_id' => $this->student->id,
            'exam_schedule_id' => $schedule->id,
            'attendance_confirmed' => true,
            'attendance_status' => ExamSession::ATTENDANCE_PRESENT,
            'attendance_confirmed_by' => $this->guruA->user_id,
        ]);
    }

    public function test_non_owner_cannot_confirm_attendance(): void
    {
        $period = $this->makeOwnedPeriod($this->guruA);
        $schedule = $period->schedules()->first();

        $this->actingAs($this->guruB->user)
            ->patchJson(route('guru_mapel.proctor.attendance.confirm', [$period, $schedule]), [
                'student_id' => $this->student->id,
                'status' => 'hadir',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('exam_sessions', ['student_id' => $this->student->id]);
    }

    public function test_owner_can_revoke_attendance(): void
    {
        $period = $this->makeOwnedPeriod($this->guruA);
        $schedule = $period->schedules()->first();

        ExamSession::create([
            'student_id' => $this->student->id,
            'exam_schedule_id' => $schedule->id,
            'status' => ExamSession::STATUS_NOT_STARTED,
            'attendance_confirmed' => true,
            'attendance_status' => ExamSession::ATTENDANCE_PRESENT,
        ]);

        $this->actingAs($this->guruA->user)
            ->patchJson(route('guru_mapel.proctor.attendance.confirm', [$period, $schedule]), [
                'student_id' => $this->student->id,
                'status' => null,
            ])
            ->assertOk()
            ->assertJson(['attendance_confirmed' => false]);

        $this->assertDatabaseHas('exam_sessions', [
            'student_id' => $this->student->id,
            'attendance_confirmed' => false,
            'attendance_status' => null,
        ]);
    }

    // ── Token & Violations endpoint ──────────────────────────────────

    public function test_token_endpoint_returns_active_token_for_owner(): void
    {
        $period = $this->makeOwnedPeriod($this->guruA);

        $this->actingAs($this->guruA->user)
            ->getJson(route('guru_mapel.proctor.token', $period))
            ->assertOk()
            ->assertJson(['active' => true, 'token_code' => 'ABCD1234']);
    }

    public function test_violations_endpoint_owner_only(): void
    {
        $period = $this->makeOwnedPeriod($this->guruA);

        $this->actingAs($this->guruA->user)
            ->getJson(route('guru_mapel.proctor.violations', $period))
            ->assertOk()
            ->assertJson(['violations' => []]);
    }

    // ── END-TO-END: guru buat → confirm → siswa token → siswa kerjakan ──

    public function test_end_to_end_student_can_take_owned_exam(): void
    {
        // Soal aktif untuk mapel + kelas.
        $question = Question::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'question_text' => 'Berapakah hasil dari 2 + 2?',
            'options' => ['A' => 'Satu', 'B' => 'Dua', 'C' => 'Tiga', 'D' => 'Empat'],
            'answer_key' => 'D',
            'score_weight' => 10,
            'is_active' => true,
        ]);
        $question->classrooms()->attach($this->classroom->id);

        $period = $this->makeOwnedPeriod($this->guruA);
        $schedule = $period->schedules()->first();

        // 1. Guru confirm absensi.
        $this->actingAs($this->guruA->user)
            ->patchJson(route('guru_mapel.proctor.attendance.confirm', [$period, $schedule]), [
                'student_id' => $this->student->id,
                'status' => 'hadir',
            ])
            ->assertOk();

        // 2. Siswa buka halaman token — tidak diblokir absensi.
        $this->actingAs($this->student->user)
            ->get(route('peserta.exams.token', $schedule->id))
            ->assertOk()
            ->assertDontSee('Anda belum diabsen');

        // 3. Siswa submit token valid → sesi in_progress.
        $this->actingAs($this->student->user)
            ->post(route('peserta.exams.token.validate', $schedule->id), ['token_code' => 'ABCD1234'])
            ->assertRedirect(route('peserta.exams.work', $schedule->id));

        $this->assertDatabaseHas('exam_sessions', [
            'student_id' => $this->student->id,
            'exam_schedule_id' => $schedule->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
        ]);

        // 4. Siswa buka halaman kerja — soal tampil.
        $this->actingAs($this->student->user)
            ->get(route('peserta.exams.work', $schedule->id))
            ->assertOk()
            ->assertSee('Berapakah hasil dari 2 + 2?');
    }

    // ── Regresi pengawas asli ────────────────────────────────────────

    public function test_pengawas_role_unchanged_without_assignment(): void
    {
        // Guru tidak berpengaruh ke jalur pengawas; pengawas SAH tanpa
        // penugasan ruangan tetap dapat dashboard empty-state (bukan 403).
        $supervisor = Supervisor::factory()->create(['room_id' => null]);

        $this->actingAs($supervisor->user)
            ->get(route('pengawas.dashboard'))
            ->assertOk();
    }

    public function test_student_role_cannot_reach_proctor_routes(): void
    {
        $period = $this->makeOwnedPeriod($this->guruA);
        $peserta = User::factory()->create(['role' => 'peserta']);

        $this->actingAs($peserta)
            ->get(route('guru_mapel.proctor.show', $period))
            ->assertForbidden();
    }
}

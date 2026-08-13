<?php

namespace Tests\Feature;

use App\Models\ExamAnswer;
use App\Models\ExamResult;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\ExamToken;
use App\Models\Question;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Supervisor;
use App\Models\User;
use App\Models\Violation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PesertaModuleTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;

    private User $user;

    private Subject $subject;

    private ExamSchedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-31 09:00:00'));

        $this->student = Student::factory()->create(['class_name' => 'XI RPL 1']);
        $room = Room::factory()->create();
        $this->student->update(['room_id' => $room->id]);
        $this->user = $this->student->user;
        $this->subject = Subject::factory()->create(['name' => 'Matematika']);
        $this->schedule = $this->scheduleToday('XI RPL 1', '08:30:00', '10:30:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function scheduleToday(string $class, string $start, string $end): ExamSchedule
    {
        return ExamSchedule::factory()->create([
            'subject_id' => $this->subject->id,
            'room_id' => $this->student->room_id,
            'class_name' => $class,
            'exam_date' => now()->toDateString(),
            'start_time' => $start,
            'end_time' => $end,
            'duration_minutes' => 60,
            'status' => 'ongoing',
        ]);
    }

    private function validToken(ExamSchedule $schedule): string
    {
        ExamToken::create([
            'exam_schedule_id' => $schedule->id,
            'token_code' => 'ABC12345',
            'valid_until' => now()->addHour(),
        ]);

        return 'ABC12345';
    }

    private function beginSession(): ExamSession
    {
        return ExamSession::create([
            'student_id' => $this->student->id,
            'exam_schedule_id' => $this->schedule->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);
    }

    private function confirmAttendance(): ExamSession
    {
        return ExamSession::create([
            'student_id' => $this->student->id,
            'exam_schedule_id' => $this->schedule->id,
            'status' => ExamSession::STATUS_NOT_STARTED,
            'attendance_confirmed' => true,
        ]);
    }

    private function scheduleForSubjectWithoutQuestions(): ExamSchedule
    {
        $emptySubject = Subject::factory()->create();
        $schedule = $this->scheduleToday('XI RPL 1', '08:30:00', '10:30:00');
        $schedule->update(['subject_id' => $emptySubject->id]);

        return $schedule;
    }

    public function test_dashboard_lists_only_own_class_today_exams(): void
    {
        $other = Subject::factory()->create(['name' => 'Fisika']);
        ExamSchedule::factory()->create([
            'subject_id' => $other->id,
            'class_name' => 'XI TKJ 1',
            'exam_date' => now()->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'status' => 'ongoing',
        ]);

        $this->actingAs($this->user)->get(route('peserta.dashboard'))
            ->assertOk()
            ->assertSee('Matematika')
            ->assertSee('Masuk Ujian')
            ->assertDontSee('Fisika');
    }

    public function test_peserta_cannot_access_other_class_schedule(): void
    {
        $other = Subject::factory()->create(['name' => 'Fisika']);
        $otherSchedule = ExamSchedule::factory()->create([
            'subject_id' => $other->id,
            'class_name' => 'XI TKJ 1',
            'exam_date' => now()->toDateString(),
            'status' => 'ongoing',
        ]);

        $this->actingAs($this->user)->get(route('peserta.exams.token', $otherSchedule->id))
            ->assertRedirect(route('peserta.dashboard'))
            ->assertSessionHas('error');
    }

    public function test_token_page_blocks_before_start_time(): void
    {
        $schedule = $this->scheduleToday('XI RPL 1', '10:00:00', '11:00:00');

        $this->actingAs($this->user)->get(route('peserta.exams.token', $schedule->id))
            ->assertRedirect(route('peserta.dashboard'))
            ->assertSessionHas('error');
    }

    public function test_token_page_blocks_after_end_time(): void
    {
        $schedule = $this->scheduleToday('XI RPL 1', '08:00:00', '08:30:00');

        $this->actingAs($this->user)->get(route('peserta.exams.token', $schedule->id))
            ->assertRedirect(route('peserta.dashboard'))
            ->assertSessionHas('error');
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->confirmAttendance();

        $this->actingAs($this->user)->post(route('peserta.exams.token.validate', $this->schedule->id), [
            'token_code' => 'SALAH123',
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseMissing('exam_sessions', [
            'student_id' => $this->student->id,
            'exam_schedule_id' => $this->schedule->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
        ]);
    }

    public function test_valid_token_starts_session(): void
    {
        $this->confirmAttendance();
        Question::factory()->create(['subject_id' => $this->subject->id]);
        $token = $this->validToken($this->schedule);

        $this->actingAs($this->user)->post(route('peserta.exams.token.validate', $this->schedule->id), [
            'token_code' => $token,
        ])->assertRedirect(route('peserta.exams.work', $this->schedule->id));

        $this->assertDatabaseHas('exam_sessions', [
            'student_id' => $this->student->id,
            'exam_schedule_id' => $this->schedule->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
        ]);
    }

    public function test_token_rejected_when_subject_has_no_active_questions(): void
    {
        $emptySchedule = $this->scheduleForSubjectWithoutQuestions();
        $this->validToken($emptySchedule);

        ExamSession::create([
            'student_id' => $this->student->id,
            'exam_schedule_id' => $emptySchedule->id,
            'status' => ExamSession::STATUS_NOT_STARTED,
            'attendance_confirmed' => true,
        ]);

        $this->actingAs($this->user)->post(route('peserta.exams.token.validate', $emptySchedule->id), [
            'token_code' => 'ABC12345',
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseMissing('exam_sessions', [
            'student_id' => $this->student->id,
            'exam_schedule_id' => $emptySchedule->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
        ]);
    }

    public function test_work_page_shows_empty_state_when_subject_has_no_active_questions(): void
    {
        $emptySchedule = $this->scheduleForSubjectWithoutQuestions();

        ExamSession::create([
            'student_id' => $this->student->id,
            'exam_schedule_id' => $emptySchedule->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'started_at' => now(),
            'attendance_confirmed' => true,
        ]);

        $this->actingAs($this->user)->get(route('peserta.exams.work', $emptySchedule->id))
            ->assertOk()
            ->assertSee('Belum ada soal yang tersedia untuk ujian ini')
            ->assertDontSee('x-for="(q, index) in questions"');
    }

    public function test_token_blocks_student_without_exam_session_record(): void
    {
        $this->actingAs($this->user)->get(route('peserta.exams.token', $this->schedule->id))
            ->assertOk()
            ->assertSee('Anda belum diabsen oleh pengawas ruangan');

        $this->actingAs($this->user)->post(route('peserta.exams.token.validate', $this->schedule->id), [
            'token_code' => 'ABC12345',
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseMissing('exam_sessions', [
            'student_id' => $this->student->id,
            'exam_schedule_id' => $this->schedule->id,
        ]);
    }

    public function test_token_blocks_student_with_unconfirmed_attendance(): void
    {
        ExamSession::create([
            'student_id' => $this->student->id,
            'exam_schedule_id' => $this->schedule->id,
            'status' => ExamSession::STATUS_NOT_STARTED,
            'attendance_confirmed' => false,
        ]);

        $this->actingAs($this->user)->get(route('peserta.exams.token', $this->schedule->id))
            ->assertOk()
            ->assertSee('Anda belum diabsen oleh pengawas ruangan');

        $this->actingAs($this->user)->post(route('peserta.exams.token.validate', $this->schedule->id), [
            'token_code' => 'ABC12345',
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseMissing('exam_sessions', [
            'student_id' => $this->student->id,
            'exam_schedule_id' => $this->schedule->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
        ]);
    }

    public function test_token_blocks_student_disabled_by_violation(): void
    {
        ExamSession::create([
            'student_id' => $this->student->id,
            'exam_schedule_id' => $this->schedule->id,
            'status' => ExamSession::STATUS_NOT_STARTED,
            'attendance_confirmed' => false,
            'violation_flag_1' => true,
        ]);

        $this->actingAs($this->user)->get(route('peserta.exams.token', $this->schedule->id))
            ->assertOk()
            ->assertSee('Absensi Anda dinonaktifkan sistem karena terdeteksi pelanggaran');

        $this->actingAs($this->user)->post(route('peserta.exams.token.validate', $this->schedule->id), [
            'token_code' => 'ABC12345',
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseMissing('exam_sessions', [
            'student_id' => $this->student->id,
            'exam_schedule_id' => $this->schedule->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
        ]);
    }

    public function test_token_blocks_student_locked_by_admin(): void
    {
        ExamSession::create([
            'student_id' => $this->student->id,
            'exam_schedule_id' => $this->schedule->id,
            'status' => ExamSession::STATUS_NOT_STARTED,
            'attendance_confirmed' => true,
            'locked_by_admin' => true,
        ]);

        $this->actingAs($this->user)->get(route('peserta.exams.token', $this->schedule->id))
            ->assertOk()
            ->assertSee('Ujian Anda dihentikan oleh Administrator')
            ->assertSee('hubungi Administrator');

        $this->actingAs($this->user)->post(route('peserta.exams.token.validate', $this->schedule->id), [
            'token_code' => 'ABC12345',
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseMissing('exam_sessions', [
            'student_id' => $this->student->id,
            'exam_schedule_id' => $this->schedule->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
        ]);
    }

    public function test_work_page_requires_started_session(): void
    {
        $this->actingAs($this->user)->get(route('peserta.exams.work', $this->schedule->id))
            ->assertRedirect(route('peserta.dashboard'))
            ->assertSessionHas('error');
    }

    public function test_work_page_does_not_leak_answer_key(): void
    {
        $this->beginSession();
        Question::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'question_text' => 'Soal rahasia nomor satu?',
            'options' => ['A' => 'Pilihan satu', 'B' => 'Pilihan dua'],
            'answer_key' => 'A',
        ]);
        Question::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => Question::TYPE_ESSAY,
            'question_text' => 'Tuliskan jawabanmu?',
            'answer_key' => 'KUNCI-RAHASIA-ESSAY',
        ]);

        $this->actingAs($this->user)->get(route('peserta.exams.work', $this->schedule->id))
            ->assertOk()
            ->assertSee('Soal rahasia nomor satu?')
            ->assertDontSee('KUNCI-RAHASIA-ESSAY')
            ->assertSee('Pilihan satu')
            ->assertSee('Pilihan dua');
    }

    public function test_exam_work_page_hides_portal_header_and_sidebar(): void
    {
        $this->beginSession();
        Question::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'options' => ['A' => 'Pilihan A', 'B' => 'Pilihan B'],
            'answer_key' => 'A',
        ]);

        $workHtml = $this->actingAs($this->user)
            ->get(route('peserta.exams.work', $this->schedule->id))
            ->assertOk()
            ->getContent();

        // Tanpa header/navbar portal dan tanpa sidebar: tidak ada hamburger,
        // role badge, toggle tema, dropdown profil, atau tombol keluar.
        $this->assertStringNotContainsString('sidebarOpen', $workHtml);
        $this->assertStringNotContainsString('translate-x-0', $workHtml);
        $this->assertStringNotContainsString('Aktifkan mode terang', $workHtml);
        $this->assertStringNotContainsString('Profil', $workHtml);
        $this->assertStringNotContainsString('/logout', $workHtml);

        // Konten ujian tetap dirender lengkap.
        $this->assertStringContainsString('Pilihan A', $workHtml);
        $this->assertStringContainsString('Mulai Ujian', $workHtml);

        // Halaman lain portal peserta tetap menampilkan header/sidebar.
        $this->actingAs($this->user)->get(route('peserta.dashboard'))
            ->assertOk()
            ->assertSee('sidebarOpen', false)
            ->assertSee('Aktifkan mode terang', false);

        $this->actingAs($this->user)->get(route('peserta.exams.token', $this->schedule->id))
            ->assertOk()
            ->assertSee('sidebarOpen', false);
    }

    public function test_save_answer_persists_and_ignores_foreign_question(): void
    {
        $session = $this->beginSession();
        $question = Question::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'options' => ['A' => 'Apa', 'B' => 'Bapa'],
            'answer_key' => 'A',
        ]);
        $foreign = Subject::factory()->create();
        $foreignQuestion = Question::factory()->create([
            'subject_id' => $foreign->id,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'options' => ['A' => 'X', 'B' => 'Y'],
            'answer_key' => 'B',
        ]);

        $this->actingAs($this->user)->postJson(route('peserta.exams.save-answer', $this->schedule->id), [
            'answers' => [$question->id => 'A', $foreignQuestion->id => 'B'],
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('exam_answers', [
            'exam_session_id' => $session->id,
            'question_id' => $question->id,
        ]);
        $saved = ExamAnswer::where('exam_session_id', $session->id)->where('question_id', $question->id)->first();
        $this->assertSame('A', $saved->student_answer);
        $this->assertDatabaseMissing('exam_answers', ['question_id' => $foreignQuestion->id]);

        $this->actingAs($this->user)->postJson(route('peserta.exams.save-answer', $this->schedule->id), [
            'answers' => [$question->id => ''],
        ])->assertOk();

        $this->assertDatabaseMissing('exam_answers', [
            'exam_session_id' => $session->id,
            'question_id' => $question->id,
        ]);
    }

    public function test_expired_session_rejects_save_answer(): void
    {
        ExamSession::create([
            'student_id' => $this->student->id,
            'exam_schedule_id' => $this->schedule->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'started_at' => now()->subHours(2),
        ]);

        $this->actingAs($this->user)->postJson(route('peserta.exams.save-answer', $this->schedule->id), [
            'answers' => [],
        ])->assertStatus(422)->assertJson(['expired' => true]);
    }

    public function test_submit_scores_objective_questions_and_creates_result(): void
    {
        $session = $this->beginSession();

        $single = Question::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'options' => ['A' => 'Opsi A', 'B' => 'Opsi B'],
            'answer_key' => 'A',
            'score_weight' => 10,
        ]);
        $trueFalse = Question::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => Question::TYPE_TRUE_FALSE,
            'answer_key' => true,
            'score_weight' => 5,
        ]);
        $multiple = Question::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => Question::TYPE_MULTIPLE_CHOICE,
            'options' => ['A' => 'Opsi A', 'B' => 'Opsi B', 'C' => 'Opsi C'],
            'answer_key' => ['A', 'C'],
            'score_weight' => 15,
        ]);
        $essay = Question::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => Question::TYPE_ESSAY,
            'answer_key' => 'kunci essay',
            'score_weight' => 20,
        ]);

        $this->actingAs($this->user)->post(route('peserta.exams.submit', $this->schedule->id), [
            'answers' => [
                $single->id => 'A',
                $trueFalse->id => true,
                $multiple->id => ['A', 'C'],
                $essay->id => 'Jawaban essay saya',
            ],
        ])->assertRedirect(route('peserta.exams.finished', $this->schedule->id));

        $this->assertDatabaseHas('exam_sessions', [
            'id' => $session->id,
            'status' => ExamSession::STATUS_COMPLETED,
        ]);
        $session->refresh();
        $this->assertNotNull($session->finished_at);

        $this->assertDatabaseHas('exam_answers', [
            'exam_session_id' => $session->id,
            'question_id' => $single->id,
            'is_correct' => true,
            'score' => 10.00,
        ]);
        $this->assertDatabaseHas('exam_answers', [
            'exam_session_id' => $session->id,
            'question_id' => $multiple->id,
            'is_correct' => true,
            'score' => 15.00,
        ]);
        $this->assertDatabaseHas('exam_answers', [
            'exam_session_id' => $session->id,
            'question_id' => $essay->id,
            'is_correct' => null,
            'score' => null,
        ]);

        $result = ExamResult::where('exam_session_id', $session->id)->first();
        $this->assertNotNull($result);
        $this->assertEquals(30.00, (float) $result->total_score);
        $this->assertTrue($result->is_passed);

        $this->actingAs($this->user)->get(route('peserta.exams.finished', $this->schedule->id))
            ->assertOk()
            ->assertSee('Ujian Berhasil Dikumpulkan')
            ->assertSee('Matematika');
    }

    public function test_submit_when_session_not_started_redirects(): void
    {
        $this->actingAs($this->user)->post(route('peserta.exams.submit', $this->schedule->id), [])
            ->assertRedirect(route('peserta.dashboard'))
            ->assertSessionHas('error');
    }

    public function test_completed_session_redirects_work_to_finished(): void
    {
        $session = $this->beginSession();
        ExamResult::create(['exam_session_id' => $session->id, 'total_score' => 80, 'is_passed' => true]);
        $session->update([
            'status' => ExamSession::STATUS_COMPLETED,
            'finished_at' => now(),
        ]);

        $this->actingAs($this->user)->get(route('peserta.exams.work', $this->schedule->id))
            ->assertRedirect(route('peserta.exams.finished', $this->schedule->id));
    }

    public function test_finished_page_requires_completed_session(): void
    {
        $this->actingAs($this->user)->get(route('peserta.exams.finished', $this->schedule->id))
            ->assertRedirect(route('peserta.dashboard'))
            ->assertSessionHas('error');
    }

    public function test_non_peserta_cannot_access_peserta_exam_pages(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('peserta.exams.work', $this->schedule->id))->assertForbidden();
        $this->actingAs($admin)->postJson(route('peserta.exams.save-answer', $this->schedule->id), [])->assertForbidden();
    }

    public function test_violation_endpoint_records_tab_switch(): void
    {
        $session = $this->beginSession();

        $this->actingAs($this->user)->postJson(route('peserta.exams.violation', $this->schedule->id), [
            'violation_type' => Violation::TYPE_TAB_SWITCH,
        ])->assertOk()
            ->assertJson([
                'redirect' => true,
                'url' => route('peserta.dashboard'),
            ]);

        $this->assertDatabaseHas('violations', [
            'exam_session_id' => $session->id,
            'violation_type' => Violation::TYPE_TAB_SWITCH,
        ]);

        $session->refresh();
        $this->assertTrue($session->violation_flag_1);
        $this->assertFalse($session->attendance_confirmed);
    }

    public function test_violation_endpoint_rejects_unstarted_session(): void
    {
        $this->actingAs($this->user)->postJson(route('peserta.exams.violation', $this->schedule->id))
            ->assertStatus(403);
    }

    public function test_violation_endpoint_rejects_foreign_schedule(): void
    {
        $other = Subject::factory()->create(['name' => 'Fisika']);
        $otherSchedule = ExamSchedule::factory()->create([
            'subject_id' => $other->id,
            'class_name' => 'XI TKJ 1',
            'exam_date' => now()->toDateString(),
            'status' => 'ongoing',
        ]);

        $this->actingAs($this->user)->postJson(route('peserta.exams.violation', $otherSchedule->id))
            ->assertStatus(403);
    }

    public function test_violation_endpoint_rejects_expired_session(): void
    {
        ExamSession::create([
            'student_id' => $this->student->id,
            'exam_schedule_id' => $this->schedule->id,
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'started_at' => now()->subHours(2),
        ]);

        $this->actingAs($this->user)->postJson(route('peserta.exams.violation', $this->schedule->id))
            ->assertStatus(422)->assertJson(['expired' => true]);
    }

    public function test_auto_violation_shows_in_pengawas_and_admin_panels(): void
    {
        $session = $this->beginSession();
        $supervisor = Supervisor::factory()->create(['room_id' => $this->schedule->room_id]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($this->user)->postJson(route('peserta.exams.violation', $this->schedule->id))
            ->assertOk();

        $this->actingAs($supervisor->user)->getJson(route('pengawas.violations.latest'))
            ->assertOk()
            ->assertJsonFragment(['violation_label' => 'Berpindah Tab/Aplikasi Lain', 'violation_type' => Violation::TYPE_TAB_SWITCH]);

        $this->actingAs($admin)->get(route('admin.violations.index'))
            ->assertOk()
            ->assertSee('Berpindah Tab/Aplikasi Lain');

        $this->assertDatabaseHas('violations', [
            'exam_session_id' => $session->id,
            'violation_type' => Violation::TYPE_TAB_SWITCH,
        ]);
    }

    public function test_reset_answer_keeps_row_when_doubtful(): void
    {
        $session = $this->beginSession();
        $question = Question::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'options' => ['A' => 'Opsi A', 'B' => 'Opsi B'],
            'answer_key' => 'A',
        ]);

        $this->actingAs($this->user)->postJson(route('peserta.exams.save-answer', $this->schedule->id), [
            'answers' => [$question->id => 'A'],
        ])->assertOk();

        $this->actingAs($this->user)->postJson(route('peserta.exams.questions.toggle-doubtful', [$this->schedule->id, $question->id]), [])
            ->assertOk()
            ->assertJson(['is_doubtful' => true]);

        $this->actingAs($this->user)->postJson(route('peserta.exams.save-answer', $this->schedule->id), [
            'answers' => [$question->id => null],
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('exam_answers', [
            'exam_session_id' => $session->id,
            'question_id' => $question->id,
            'is_doubtful' => true,
        ]);
        $saved = ExamAnswer::where('exam_session_id', $session->id)->where('question_id', $question->id)->first();
        $this->assertNotNull($saved);
        $this->assertNull($saved->student_answer);
    }

    public function test_reset_answer_deletes_non_doubtful_rows(): void
    {
        $session = $this->beginSession();
        $single = Question::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'options' => ['A' => 'Opsi A', 'B' => 'Opsi B'],
            'answer_key' => 'A',
        ]);
        $matching = Question::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => Question::TYPE_MATCHING,
            'options' => ['left' => ['Kiri 1', 'Kiri 2'], 'right' => ['Kanan 1', 'Kanan 2']],
            'answer_key' => ['A' => '1', 'B' => '2'],
        ]);

        $this->actingAs($this->user)->postJson(route('peserta.exams.save-answer', $this->schedule->id), [
            'answers' => [$single->id => 'A', $matching->id => ['A' => '1', 'B' => '2']],
        ])->assertOk();

        $this->assertDatabaseCount('exam_answers', 2);

        $this->actingAs($this->user)->postJson(route('peserta.exams.save-answer', $this->schedule->id), [
            'answers' => [$single->id => null, $matching->id => []],
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('exam_answers', [
            'exam_session_id' => $session->id,
            'question_id' => $single->id,
        ]);
        $this->assertDatabaseMissing('exam_answers', [
            'exam_session_id' => $session->id,
            'question_id' => $matching->id,
        ]);
    }

    public function test_reset_answer_after_completion_is_rejected(): void
    {
        $session = $this->beginSession();
        $question = Question::factory()->create([
            'subject_id' => $this->subject->id,
            'type' => Question::TYPE_ESSAY,
            'answer_key' => 'kunci essay',
        ]);
        $session->update([
            'status' => ExamSession::STATUS_COMPLETED,
            'finished_at' => now(),
        ]);

        $this->actingAs($this->user)->postJson(route('peserta.exams.save-answer', $this->schedule->id), [
            'answers' => [$question->id => ''],
        ])->assertStatus(403);
    }
}

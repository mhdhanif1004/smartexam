<?php

namespace Tests\Feature;

use App\Models\ExamSchedule;
use App\Models\Question;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Supervisor;
use App\Models\User;
use Database\Seeders\ClassroomSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->seed(ClassroomSeeder::class);
    }

    public function test_admin_can_view_students_index_and_create_page(): void
    {
        Student::factory()->count(3)->create();

        $this->actingAs($this->admin)->get('/admin/students')->assertOk()->assertSee('Data Siswa');
        $this->actingAs($this->admin)->get('/admin/students/create')->assertOk();
    }

    public function test_admin_can_create_student_with_user_account(): void
    {
        $this->actingAs($this->admin)->post('/admin/students', [
            'name' => 'Budi Santoso',
            'username' => 'budi12345678',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'nisn' => '1234567890',
            'class_name' => 'XI RPL 1',
            'is_active' => '1',
        ])->assertRedirect(route('admin.students.index'));

        $this->assertDatabaseHas('users', ['username' => 'budi12345678', 'role' => 'peserta']);
        $this->assertDatabaseHas('students', ['nisn' => '1234567890', 'class_name' => 'XI RPL 1']);
    }

    public function test_admin_can_create_student_without_username_and_it_is_generated(): void
    {
        $this->actingAs($this->admin)->post('/admin/students', [
            'name' => 'Citra Ayu',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'nisn' => '0987654321',
            'class_name' => 'XI RPL 1',
            'is_active' => '1',
        ])->assertRedirect(route('admin.students.index'));

        $user = User::where('name', 'Citra Ayu')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->username);
        $this->assertNull($user->email);
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]{10,15}$/', $user->username);
    }

    public function test_admin_can_create_student_without_password_and_it_is_generated(): void
    {
        $this->actingAs($this->admin)->post('/admin/students', [
            'name' => 'Dewi Lestari',
            'nisn' => '1122334455',
            'class_name' => 'XI RPL 2',
            'is_active' => '1',
        ])->assertRedirect(route('admin.students.index'));

        $user = User::where('name', 'Dewi Lestari')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->plain_password);
        $this->assertTrue(strlen($user->plain_password) >= 8);
        $this->assertTrue(password_verify($user->plain_password, $user->password));
    }

    public function test_admin_can_update_student_without_changing_password(): void
    {
        $student = Student::factory()->create();
        $beforePassword = $student->user->password;
        $beforePlain = $student->user->plain_password;

        $this->actingAs($this->admin)->put("/admin/students/{$student->id}", [
            'name' => 'Nama Baru',
            'username' => $student->user->username,
            'password' => '',
            'password_confirmation' => '',
            'nisn' => $student->nisn,
            'class_name' => 'XII TKJ 1',
        ])->assertRedirect(route('admin.students.index'));

        $freshUser = $student->fresh()->user;
        $this->assertEquals('Nama Baru', $freshUser->name);
        $this->assertEquals('XII TKJ 1', $student->fresh()->class_name);
        $this->assertEquals($student->user->username, $freshUser->username);
        $this->assertSame($beforePassword, $freshUser->password);
        $this->assertSame($beforePlain, $freshUser->plain_password);
    }

    public function test_admin_can_delete_student_and_related_user(): void
    {
        $student = Student::factory()->create();

        $this->actingAs($this->admin)->delete("/admin/students/{$student->id}")->assertRedirect(route('admin.students.index'));

        $this->assertDatabaseMissing('students', ['id' => $student->id]);
        $this->assertDatabaseMissing('users', ['id' => $student->user_id]);
    }

    public function test_admin_can_create_supervisor_without_room_assignment(): void
    {
        $this->actingAs($this->admin)->post('/admin/supervisors', [
            'name' => 'Pak Guru',
            'email' => 'guru@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_active' => '1',
        ])->assertRedirect(route('admin.supervisors.index'));

        $this->assertDatabaseHas('users', ['email' => 'guru@example.com', 'role' => 'pengawas']);
        $this->assertDatabaseHas('supervisors', ['room_id' => null]);
    }

    public function test_admin_can_update_supervisor_account_without_touching_room_assignment(): void
    {
        $room = Room::factory()->create();
        $supervisor = Supervisor::factory()->create(['room_id' => $room->id]);

        $this->actingAs($this->admin)->put("/admin/supervisors/{$supervisor->id}", [
            'name' => 'Pak Guru Baru',
            'email' => 'guru-baru@example.com',
            'is_active' => '1',
        ])->assertRedirect(route('admin.supervisors.index'));

        $this->assertEquals('Pak Guru Baru', $supervisor->fresh()->user->name);
        $this->assertSame($room->id, $supervisor->fresh()->room_id);
    }

    public function test_admin_can_create_supervisor_without_password_and_it_is_generated(): void
    {
        $this->actingAs($this->admin)->post('/admin/supervisors', [
            'name' => 'Bu Rina',
            'email' => 'rina@example.com',
            'is_active' => '1',
        ])->assertRedirect(route('admin.supervisors.index'));

        $user = User::where('email', 'rina@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->plain_password);
        $this->assertTrue(strlen($user->plain_password) >= 8);
        $this->assertTrue(password_verify($user->plain_password, $user->password));
    }

    public function test_admin_can_crud_subject_and_room(): void
    {
        $this->actingAs($this->admin)->post('/admin/subjects', [
            'code' => 'FIS',
            'name' => 'Fisika',
            'default_duration_minutes' => 90,
        ])->assertRedirect(route('admin.subjects.index'));

        $this->assertDatabaseHas('subjects', ['code' => 'FIS']);

        $this->actingAs($this->admin)->post('/admin/rooms', [
            'name' => 'Ruang 10',
            'capacity' => 40,
        ])->assertRedirect(route('admin.rooms.index'));

        $this->assertDatabaseHas('rooms', ['name' => 'Ruang 10']);
    }

    public function test_admin_can_create_exam_schedule(): void
    {
        $subject = Subject::factory()->create();
        Question::factory()->create(['subject_id' => $subject->id]);
        $room = Room::factory()->create();

        $this->actingAs($this->admin)->post('/admin/exam-schedules', [
            'subject_id' => $subject->id,
            'room_id' => $room->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => '2026-08-10',
            'start_time' => '08:00',
            'duration_minutes' => 90,
            'status' => 'scheduled',
        ])->assertRedirect(route('admin.exam-schedules.index'));

        $schedule = ExamSchedule::first();

        $this->assertEquals('08:00:00', $schedule->start_time);
        $this->assertEquals('09:30:00', $schedule->end_time);
        $this->assertEquals('scheduled', $schedule->status);
    }

    public function test_exam_schedule_cannot_pass_midnight(): void
    {
        $subject = Subject::factory()->create();
        Question::factory()->create(['subject_id' => $subject->id]);
        $room = Room::factory()->create();

        $this->actingAs($this->admin)->from(route('admin.exam-schedules.create'))->post('/admin/exam-schedules', [
            'subject_id' => $subject->id,
            'room_id' => $room->id,
            'class_name' => 'XI RPL 1',
            'exam_date' => '2026-08-10',
            'start_time' => '23:00',
            'duration_minutes' => 120,
            'status' => 'scheduled',
        ])->assertSessionHasErrors('duration_minutes');
    }

    public function test_admin_can_create_single_choice_question(): void
    {
        $subject = Subject::factory()->create();

        $this->actingAs($this->admin)->post('/admin/questions', [
            'subject_id' => $subject->id,
            'type' => 'single_choice',
            'question_text' => 'Berapa hasil 2 + 2?',
            'score_weight' => 10,
            'single_options' => ['A' => '2', 'B' => '4', 'C' => '6', 'D' => '8'],
            'single_answer' => 'B',
        ])->assertRedirect(route('admin.questions.index'));

        $question = Question::first();

        $this->assertEquals('single_choice', $question->type);
        $this->assertEquals(['A' => '2', 'B' => '4', 'C' => '6', 'D' => '8'], $question->options);
        $this->assertEquals('B', $question->answer_key);
    }

    public function test_admin_can_create_multiple_choice_question(): void
    {
        $subject = Subject::factory()->create();

        $this->actingAs($this->admin)->post('/admin/questions', [
            'subject_id' => $subject->id,
            'type' => 'multiple_choice',
            'question_text' => 'Pilih jawaban yang benar?',
            'score_weight' => 10,
            'multiple_options' => ['A' => 'X', 'B' => 'Y', 'C' => 'Z'],
            'multiple_answer' => ['A', 'C'],
        ])->assertRedirect(route('admin.questions.index'));

        $question = Question::first();

        $this->assertEquals(['A' => 'X', 'B' => 'Y', 'C' => 'Z'], $question->options);
        $this->assertEquals(['A', 'C'], $question->answer_key);
    }

    public function test_admin_can_create_matching_question(): void
    {
        $subject = Subject::factory()->create();

        $this->actingAs($this->admin)->post('/admin/questions', [
            'subject_id' => $subject->id,
            'type' => 'matching',
            'question_text' => 'Jodohkan pasangan berikut?',
            'score_weight' => 10,
            'matching_left' => ['Satu', 'Dua', 'Tiga'],
            'matching_right' => ['1', '2', '3'],
        ])->assertRedirect(route('admin.questions.index'));

        $question = Question::first();

        $this->assertEquals(['left' => ['Satu', 'Dua', 'Tiga'], 'right' => ['1', '2', '3']], $question->options);
        $this->assertEquals(['A' => '1', 'B' => '2', 'C' => '3'], $question->answer_key);
    }

    public function test_admin_can_create_true_false_and_essay_question(): void
    {
        $subject = Subject::factory()->create();

        $this->actingAs($this->admin)->post('/admin/questions', [
            'subject_id' => $subject->id,
            'type' => 'true_false',
            'question_text' => 'Bumi itu bulat?',
            'score_weight' => 10,
            'true_false_answer' => '1',
        ])->assertRedirect(route('admin.questions.index'));

        $this->assertTrue(Question::first()->answer_key);

        $this->actingAs($this->admin)->post('/admin/questions', [
            'subject_id' => $subject->id,
            'type' => 'essay',
            'question_text' => 'Jelaskan tentang HTML?',
            'score_weight' => 20,
            'essay_answer' => 'HTML adalah bahasa markup untuk membuat halaman web.',
        ])->assertRedirect(route('admin.questions.index'));

        $this->assertEquals('HTML adalah bahasa markup untuk membuat halaman web.', Question::latest('id')->first()->answer_key);
    }

    public function test_single_choice_question_requires_minimum_two_options(): void
    {
        $subject = Subject::factory()->create();

        $this->actingAs($this->admin)->post('/admin/questions', [
            'subject_id' => $subject->id,
            'type' => 'single_choice',
            'question_text' => 'Pertanyaan?',
            'score_weight' => 10,
            'single_options' => ['A' => 'X', 'B' => '', 'C' => '', 'D' => ''],
            'single_answer' => 'A',
        ])->assertSessionHasErrors('single_options');
    }

    public function test_admin_can_update_and_delete_question(): void
    {
        $question = Question::factory()->create();

        $this->actingAs($this->admin)->put("/admin/questions/{$question->id}", [
            'subject_id' => $question->subject_id,
            'type' => 'essay',
            'question_text' => 'Pertanyaan baru?',
            'score_weight' => 15,
            'essay_answer' => 'Kunci jawaban baru.',
        ])->assertRedirect(route('admin.questions.index'));

        $this->assertEquals('essay', $question->fresh()->type);
        $this->assertEquals('Kunci jawaban baru.', $question->fresh()->answer_key);

        $this->actingAs($this->admin)->delete("/admin/questions/{$question->id}")->assertRedirect(route('admin.questions.index'));

        $this->assertDatabaseMissing('questions', ['id' => $question->id]);
    }

    public function test_peserta_cannot_access_admin_crud_pages(): void
    {
        $peserta = User::factory()->peserta()->create();

        $this->actingAs($peserta)->get('/admin/students')->assertForbidden();
        $this->actingAs($peserta)->get('/admin/questions')->assertForbidden();
    }
}

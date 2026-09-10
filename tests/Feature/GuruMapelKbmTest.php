<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ExamAnswer;
use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\ExamType;
use App\Models\GuruMapel;
use App\Models\Question;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeacherSubjectClassAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GuruMapelKbmTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guru mapel yang mengampu satu mata pelajaran pada satu kelas, lengkap
     * dengan beberapa siswa di kelas itu. Cakupan kelas ditetapkan lewat
     * classroom_id pada penugasan (pivot), sehingga kelas tersebut bisa
     * menjadi target soal dan untuk input Nilai/Absensi.
     *
     * @return array{0: GuruMapel, 1: Subject, 2: Classroom, 3: Collection<int, Student>, 4: User}
     */
    private function makeAmpuGuru(int $studentCount = 2): array
    {
        $guru = GuruMapel::factory()->create();
        $subject = Subject::factory()->create();
        $classroom = Classroom::factory()->create();

        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
        ]);

        $students = Student::factory()->count($studentCount)->create([
            'classroom_id' => $classroom->id,
            'class_name' => $classroom->name,
        ]);

        return [$guru, $subject, $classroom, $students, $guru->user];
    }

    private function singleChoicePayload(int $subjectId, int $classroomId, array $overrides = []): array
    {
        return array_merge([
            'subject_id' => $subjectId,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'question_text' => 'Berapakah hasil dari 2 + 2?',
            'classroom_ids' => [$classroomId],
            'score_weight' => 10,
            'single_options' => ['A' => 'Satu', 'B' => 'Dua', 'C' => 'Tiga', 'D' => 'Empat'],
            'single_answer' => 'D',
        ], $overrides);
    }

    // -----------------------------------------------------------------
    // SOAL
    // -----------------------------------------------------------------

    public function test_guru_can_create_question_for_ampu_subject_and_classroom(): void
    {
        [$guru, $subject, $classroom] = $this->makeAmpuGuru();

        $this->actingAs($guru->user)
            ->post(route('guru_mapel.questions.store'), $this->singleChoicePayload($subject->id, $classroom->id))
            ->assertRedirect(route('guru_mapel.questions.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('questions', [
            'subject_id' => $subject->id,
            'created_by_user_id' => $guru->user->id,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'question_text' => 'Berapakah hasil dari 2 + 2?',
        ]);

        $question = Question::query()
            ->where('created_by_user_id', $guru->user->id)
            ->first();

        $this->assertSame('D', $question->answer_key);
        $this->assertSame(['A', 'B', 'C', 'D'], array_keys($question->options));
        $this->assertDatabaseHas('question_classroom', [
            'question_id' => $question->id,
            'classroom_id' => $classroom->id,
        ]);
    }

    public function test_guru_can_access_question_create_page(): void
    {
        [$guru, $subject] = $this->makeAmpuGuru();

        $this->actingAs($guru->user)
            ->get(route('guru_mapel.questions.create'))
            ->assertOk()
            ->assertSee('Tambah Soal')
            ->assertSee($subject->name)
            ->assertSee('Simpan Soal');
    }

    public function test_create_page_no_longer_has_kelas_target_picker(): void
    {
        [$guru, $subject, $classroom] = $this->makeAmpuGuru();

        // Kelas yang TIDAK diampu ada di master data, tapi sejak picker "Kelas
        // Target" dihapus dari form soal guru, halaman create TIDAK boleh lagi
        // menampilkan section pemilih kelas atau daftar kelas apa pun.
        Classroom::factory()->create(['name' => 'XII IPA 1']);

        $html = $this->actingAs($guru->user)
            ->get(route('guru_mapel.questions.create'))
            ->assertOk()
            ->assertSee('Tambah Soal')
            ->getContent();

        $this->assertStringNotContainsString('Kelas Target', $html);
        $this->assertStringNotContainsString('classroom-picker', $html);
        $this->assertStringNotContainsString('name="classroom_ids', $html);
    }

    public function test_picker_removed_but_snapshot_areas_intact(): void
    {
        [$guru, $subject, $classroom] = $this->makeAmpuGuru();

        // Form create tetap punya input mapel/jenis/pertanyaan (tanpa picker),
        // dan tidak mewajibkan kiriman classroom_ids.
        $this->actingAs($guru->user)
            ->get(route('guru_mapel.questions.create'))
            ->assertOk()
            ->assertSee('Mata Pelajaran')
            ->assertSee('Jenis Soal')
            ->assertSee('Pertanyaan');
    }

    public function test_guru_can_create_question_with_image(): void
    {
        Storage::fake('public');
        [$guru, $subject, $classroom] = $this->makeAmpuGuru();

        $this->actingAs($guru->user)
            ->post(route('guru_mapel.questions.store'), $this->singleChoicePayload($subject->id, $classroom->id, [
                'image' => UploadedFile::fake()->image('guru.png', 400, 300),
            ]))
            ->assertRedirect(route('guru_mapel.questions.index'))
            ->assertSessionHas('success');

        $question = Question::query()
            ->where('created_by_user_id', $guru->user->id)
            ->first();

        $this->assertNotNull($question);
        $this->assertNotNull($question->image_path);
        Storage::disk('public')->assertExists($question->image_path);
    }

    public function test_guru_can_remove_question_image_on_update(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('question-images/guru-lama.png', 'dummy content');

        [$guru, $subject, $classroom] = $this->makeAmpuGuru();

        $question = Question::query()->create([
            'subject_id' => $subject->id,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'question_text' => 'Soal dengan gambar',
            'options' => ['A' => 'Merah', 'B' => 'Biru'],
            'answer_key' => 'A',
            'score_weight' => 10,
            'image_path' => 'question-images/guru-lama.png',
            'created_by_user_id' => $guru->user->id,
        ]);
        $question->classrooms()->attach($classroom->id);

        $this->actingAs($guru->user)
            ->put(route('guru_mapel.questions.update', $question), $this->singleChoicePayload($subject->id, $classroom->id, [
                'question_text' => 'Soal dengan gambar dihapus',
                'remove_image' => '1',
            ]))
            ->assertRedirect(route('guru_mapel.questions.index'))
            ->assertSessionHas('success');

        $this->assertNull($question->fresh()->image_path);
        Storage::disk('public')->assertMissing('question-images/guru-lama.png');
    }

    public function test_guru_can_replace_question_image_on_update(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('question-images/guru-lama.png', 'dummy content');

        [$guru, $subject, $classroom] = $this->makeAmpuGuru();

        $question = Question::query()->create([
            'subject_id' => $subject->id,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'question_text' => 'Soal dengan gambar lama',
            'options' => ['A' => 'Merah', 'B' => 'Biru'],
            'answer_key' => 'A',
            'score_weight' => 10,
            'image_path' => 'question-images/guru-lama.png',
            'created_by_user_id' => $guru->user->id,
        ]);
        $question->classrooms()->attach($classroom->id);

        $this->actingAs($guru->user)
            ->put(route('guru_mapel.questions.update', $question), $this->singleChoicePayload($subject->id, $classroom->id, [
                'question_text' => 'Soal dengan gambar baru',
                'image' => UploadedFile::fake()->image('guru-baru.png', 400, 300),
            ]))
            ->assertRedirect(route('guru_mapel.questions.index'))
            ->assertSessionHas('success');

        $fresh = $question->fresh();
        $this->assertNotSame('question-images/guru-lama.png', $fresh->image_path);
        Storage::disk('public')->assertMissing('question-images/guru-lama.png');
        Storage::disk('public')->assertExists($fresh->image_path);
    }

    public function test_guru_cannot_create_question_for_subject_outside_ampu(): void
    {
        [$guru] = $this->makeAmpuGuru();

        $otherSubject = Subject::factory()->create();
        $otherClassroom = Classroom::factory()->create();

        $this->actingAs($guru->user)
            ->post(route('guru_mapel.questions.store'), $this->singleChoicePayload($otherSubject->id, $otherClassroom->id))
            ->assertForbidden();

        $this->assertDatabaseCount('questions', 0);
    }

    public function test_question_index_only_shows_own_questions(): void
    {
        [$guruA, $subjectA, $classroomA] = $this->makeAmpuGuru();
        [$guruB] = $this->makeAmpuGuru();

        $ownQuestion = Question::query()->create([
            'subject_id' => $subjectA->id,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'question_text' => 'Soal rahasia milik guru A',
            'options' => ['A' => 'a', 'B' => 'b'],
            'answer_key' => 'A',
            'score_weight' => 10,
            'created_by_user_id' => $guruA->user->id,
        ]);
        $ownQuestion->classrooms()->attach($classroomA->id);

        Question::query()->create([
            'subject_id' => $subjectA->id,
            'type' => Question::TYPE_ESSAY,
            'question_text' => 'Soal milik guru B yang sama sekali berbeda',
            'score_weight' => 10,
            'created_by_user_id' => $guruB->user->id,
        ]);

        Question::query()->create([
            'subject_id' => $subjectA->id,
            'type' => Question::TYPE_ESSAY,
            'question_text' => 'Soal buatan admin tanpa penanda pembuat',
            'score_weight' => 10,
        ]);

        $this->actingAs($guruA->user)
            ->get(route('guru_mapel.questions.index'))
            ->assertOk()
            ->assertSee('Soal rahasia milik guru A')
            ->assertDontSee('Soal milik guru B yang sama sekali berbeda')
            ->assertDontSee('Soal buatan admin tanpa penanda pembuat');
    }

    public function test_guru_cannot_edit_or_update_another_gurus_question(): void
    {
        [$guruA, $subjectA, $classroomA] = $this->makeAmpuGuru();
        [$guruB, $subjectB, $classroomB] = $this->makeAmpuGuru();

        $otherQuestion = Question::query()->create([
            'subject_id' => $subjectB->id,
            'type' => Question::TYPE_ESSAY,
            'question_text' => 'Soal milik guru B',
            'answer_key' => 'rubrik',
            'score_weight' => 10,
            'created_by_user_id' => $guruB->user->id,
        ]);
        $otherQuestion->classrooms()->attach($classroomB->id);

        $this->actingAs($guruA->user)
            ->get(route('guru_mapel.questions.edit', $otherQuestion))
            ->assertForbidden();

        $this->actingAs($guruA->user)
            ->put(route('guru_mapel.questions.update', $otherQuestion), $this->singleChoicePayload($subjectA->id, $classroomA->id, [
                'question_text' => 'Soal yang dicoba diubah guru A',
            ]))
            ->assertForbidden();

        $this->assertSame('Soal milik guru B', $otherQuestion->fresh()->question_text);
    }

    public function test_guru_cannot_delete_question_that_has_exam_answers(): void
    {
        [$guru, $subject, $classroom] = $this->makeAmpuGuru();

        $question = Question::query()->create([
            'subject_id' => $subject->id,
            'type' => Question::TYPE_ESSAY,
            'question_text' => 'Soal yang sudah pernah dijawab',
            'answer_key' => 'rubrik',
            'score_weight' => 10,
            'created_by_user_id' => $guru->user->id,
        ]);
        $question->classrooms()->attach($classroom->id);

        $session = ExamSession::factory()->create([
            'exam_schedule_id' => ExamSchedule::factory()->create(['subject_id' => $subject->id])->id,
        ]);

        ExamAnswer::create([
            'exam_session_id' => $session->id,
            'question_id' => $question->id,
            'student_answer' => ['A'],
        ]);

        $this->actingAs($guru->user)
            ->delete(route('guru_mapel.questions.destroy', $question))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('questions', ['id' => $question->id]);
    }

    public function test_guru_can_bulk_delete_own_questions(): void
    {
        [$guru, $subject, $classroom] = $this->makeAmpuGuru();

        $q1 = Question::query()->create([
            'subject_id' => $subject->id,
            'type' => Question::TYPE_ESSAY,
            'question_text' => 'Soal hapus massal 1',
            'answer_key' => 'rubrik',
            'score_weight' => 10,
            'created_by_user_id' => $guru->user->id,
        ]);
        $q1->classrooms()->attach($classroom->id);

        $q2 = Question::query()->create([
            'subject_id' => $subject->id,
            'type' => Question::TYPE_ESSAY,
            'question_text' => 'Soal hapus massal 2',
            'answer_key' => 'rubrik',
            'score_weight' => 10,
            'created_by_user_id' => $guru->user->id,
        ]);
        $q2->classrooms()->attach($classroom->id);

        $this->actingAs($guru->user)
            ->post(route('guru_mapel.questions.bulk-destroy'), ['ids' => [$q1->id, $q2->id]])
            ->assertRedirect(route('guru_mapel.questions.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('questions', ['id' => $q1->id]);
        $this->assertDatabaseMissing('questions', ['id' => $q2->id]);
    }

    public function test_guru_bulk_delete_skips_questions_already_answered(): void
    {
        [$guru, $subject, $classroom] = $this->makeAmpuGuru();

        $answered = Question::query()->create([
            'subject_id' => $subject->id,
            'type' => Question::TYPE_ESSAY,
            'question_text' => 'Soal sudah dijawab peserta',
            'answer_key' => 'rubrik',
            'score_weight' => 10,
            'created_by_user_id' => $guru->user->id,
        ]);
        $answered->classrooms()->attach($classroom->id);

        $clean = Question::query()->create([
            'subject_id' => $subject->id,
            'type' => Question::TYPE_ESSAY,
            'question_text' => 'Soal bersih belum dijawab',
            'answer_key' => 'rubrik',
            'score_weight' => 10,
            'created_by_user_id' => $guru->user->id,
        ]);
        $clean->classrooms()->attach($classroom->id);

        $session = ExamSession::factory()->create([
            'exam_schedule_id' => ExamSchedule::factory()->create(['subject_id' => $subject->id])->id,
        ]);

        ExamAnswer::create([
            'exam_session_id' => $session->id,
            'question_id' => $answered->id,
            'student_answer' => ['A'],
        ]);

        $this->actingAs($guru->user)
            ->post(route('guru_mapel.questions.bulk-destroy'), ['ids' => [$answered->id, $clean->id]])
            ->assertRedirect(route('guru_mapel.questions.index'))
            ->assertSessionHas('success');

        // Soal sudah dijawab dilewati; soal bersih terhapus.
        $this->assertDatabaseHas('questions', ['id' => $answered->id]);
        $this->assertDatabaseMissing('questions', ['id' => $clean->id]);
    }

    public function test_guru_bulk_delete_ignores_other_gurus_questions(): void
    {
        [$guruA, $subjectA, $classroomA] = $this->makeAmpuGuru();
        [$guruB, $subjectB, $classroomB] = $this->makeAmpuGuru();

        $own = Question::query()->create([
            'subject_id' => $subjectA->id,
            'type' => Question::TYPE_ESSAY,
            'question_text' => 'Soal milik guru A',
            'answer_key' => 'rubrik',
            'score_weight' => 10,
            'created_by_user_id' => $guruA->user->id,
        ]);
        $own->classrooms()->attach($classroomA->id);

        $other = Question::query()->create([
            'subject_id' => $subjectB->id,
            'type' => Question::TYPE_ESSAY,
            'question_text' => 'Soal milik guru B',
            'answer_key' => 'rubrik',
            'score_weight' => 10,
            'created_by_user_id' => $guruB->user->id,
        ]);
        $other->classrooms()->attach($classroomB->id);

        $this->actingAs($guruA->user)
            ->post(route('guru_mapel.questions.bulk-destroy'), ['ids' => [$own->id, $other->id]])
            ->assertRedirect(route('guru_mapel.questions.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('questions', ['id' => $own->id]);
        // Soal milik guru B tidak boleh ikut terhapus.
        $this->assertDatabaseHas('questions', ['id' => $other->id]);
    }

    public function test_guru_can_update_own_question_in_ampu_subject(): void
    {
        [$guru, $subject, $classroom] = $this->makeAmpuGuru();

        $question = Question::query()->create([
            'subject_id' => $subject->id,
            'type' => Question::TYPE_ESSAY,
            'question_text' => 'Soal asli',
            'answer_key' => 'rubrik lama',
            'score_weight' => 10,
            'created_by_user_id' => $guru->user->id,
        ]);
        $question->classrooms()->attach($classroom->id);

        $this->actingAs($guru->user)
            ->put(route('guru_mapel.questions.update', $question), [
                'subject_id' => $subject->id,
                'type' => Question::TYPE_ESSAY,
                'question_text' => 'Soal diperbarui',
                'classroom_ids' => [$classroom->id],
                'score_weight' => 12,
                'essay_answer' => 'rubrik baru',
            ])
            ->assertRedirect(route('guru_mapel.questions.index'))
            ->assertSessionHas('success');

        $fresh = $question->fresh();
        $this->assertSame('Soal diperbarui', $fresh->question_text);
        $this->assertSame($subject->id, $fresh->subject_id);
    }

    public function test_guru_cannot_move_own_question_to_another_subject_on_update(): void
    {
        [$guru, $subjectA, $classroomA] = $this->makeAmpuGuru();

        // Guru A ampu mapel kedua, sehingga ia sah mengurus keduanya.
        $otherAmpuSubject = Subject::factory()->create();
        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => $otherAmpuSubject->id,
        ]);

        $question = Question::query()->create([
            'subject_id' => $subjectA->id,
            'type' => Question::TYPE_ESSAY,
            'question_text' => 'Soal di mapel ampu',
            'answer_key' => 'rubrik',
            'score_weight' => 10,
            'created_by_user_id' => $guru->user->id,
        ]);
        $question->classrooms()->attach($classroomA->id);

        $this->actingAs($guru->user)
            ->put(route('guru_mapel.questions.update', $question), [
                'subject_id' => $otherAmpuSubject->id,
                'type' => Question::TYPE_ESSAY,
                'question_text' => 'Soal yang dicoba dipindah mapel',
                'classroom_ids' => [$classroomA->id],
                'score_weight' => 10,
                'essay_answer' => 'rubrik',
            ])
            ->assertSessionHasErrors('subject_id');

        $fresh = $question->fresh();
        $this->assertSame($subjectA->id, $fresh->subject_id);
        $this->assertSame('Soal di mapel ampu', $fresh->question_text);
    }

    // -----------------------------------------------------------------
    // NILAI
    // -----------------------------------------------------------------

    public function test_guru_can_store_grades_for_ampu_class(): void
    {
        [$guru, $subject, $classroom, $students] = $this->makeAmpuGuru(2);

        $semester = Semester::create(['year' => '2024/2025', 'semester' => 1, 'is_active' => true]);

        $this->actingAs($guru->user)
            ->post(route('guru_mapel.grades.store'), [
                'subject_id' => $subject->id,
                'classroom_id' => $classroom->id,
                'semester_id' => $semester->id,
                'entries' => [
                    $students[0]->id => ['uts' => ['score' => '90']],
                    $students[1]->id => ['uas' => ['score' => '75.50']],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('subject_grades', [
            'student_id' => $students[0]->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'exam_type_id' => ExamType::query()->where('code', 'uts')->value('id'),
            'score' => '90.00',
            'is_override' => true,
        ]);
        $this->assertDatabaseHas('subject_grades', [
            'student_id' => $students[1]->id,
            'exam_type_id' => ExamType::query()->where('code', 'uas')->value('id'),
            'score' => '75.50',
        ]);

        // Nilai Akhir tersinkron ke tabel grades (non-override, isi dari kalkulator).
        $this->assertDatabaseHas('grades', [
            'student_id' => $students[0]->id,
            'subject_id' => $subject->id,
            'score' => '90.00',
            'is_override' => false,
        ]);
    }

    public function test_grade_rejects_score_out_of_range_or_invalid_format(): void
    {
        [$guru, $subject, $classroom, $students] = $this->makeAmpuGuru(1);

        $semester = Semester::create(['year' => '2024/2025', 'semester' => 1, 'is_active' => true]);

        $this->actingAs($guru->user)
            ->post(route('guru_mapel.grades.store'), [
                'subject_id' => $subject->id,
                'classroom_id' => $classroom->id,
                'semester_id' => $semester->id,
                'entries' => [
                    $students[0]->id => ['uts' => ['score' => '150']],
                ],
            ])
            ->assertSessionHasErrors('entries.'.$students[0]->id.'.uts.score');

        $this->assertDatabaseCount('subject_grades', 0);
        $this->assertDatabaseCount('grades', 0);
    }

    public function test_grade_rejects_input_for_non_ampu_class(): void
    {
        [$guru, $subject] = $this->makeAmpuGuru(1);
        $otherClassroom = Classroom::factory()->create();

        $semester = Semester::create(['year' => '2024/2025', 'semester' => 1, 'is_active' => true]);

        $this->actingAs($guru->user)
            ->post(route('guru_mapel.grades.store'), [
                'subject_id' => $subject->id,
                'classroom_id' => $otherClassroom->id,
                'semester_id' => $semester->id,
                'entries' => [
                    1 => ['uts' => ['score' => '80']],
                ],
            ])
            ->assertForbidden();
    }

    public function test_grade_rejects_manual_title_reserved_for_system_rows(): void
    {
        [$guru, $subject, $classroom, $students] = $this->makeAmpuGuru(1);

        $semester = Semester::create(['year' => '2024/2025', 'semester' => 1, 'is_active' => true]);

        // Title 'CBT' dan 'Kehadiran' (case-insensitive) dipakai sistem —
        // guru tidak boleh memakainya agar tidak ambigu dengan baris otomatis.
        foreach (['CBT', 'cbt', 'Kehadiran', 'kehadiran'] as $reservedTitle) {
            $this->actingAs($guru->user)
                ->post(route('guru_mapel.grades.store'), [
                    'subject_id' => $subject->id,
                    'classroom_id' => $classroom->id,
                    'semester_id' => $semester->id,
                    'entries' => [
                        $students[0]->id => ['harian' => ['title' => $reservedTitle, 'score' => '80']],
                    ],
                ])
                ->assertSessionHasErrors('entries.'.$students[0]->id.'.harian.title');
        }

        // Tidak ada satupun baris subject_grades yang tersimpan.
        $this->assertDatabaseCount('subject_grades', 0);
    }

    public function test_grade_accepts_normal_manual_title(): void
    {
        [$guru, $subject, $classroom, $students] = $this->makeAmpuGuru(1);

        $semester = Semester::create(['year' => '2024/2025', 'semester' => 1, 'is_active' => true]);

        $this->actingAs($guru->user)
            ->post(route('guru_mapel.grades.store'), [
                'subject_id' => $subject->id,
                'classroom_id' => $classroom->id,
                'semester_id' => $semester->id,
                'entries' => [
                    $students[0]->id => ['harian' => ['title' => 'Quiz 1', 'score' => '85']],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('subject_grades', [
            'student_id' => $students[0]->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'title' => 'Quiz 1',
            'score' => '85.00',
        ]);
    }
}

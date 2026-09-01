<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\GuruMapel;
use App\Models\Subject;
use App\Models\TeacherSubjectClassAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuruMapelModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_can_view_guru_mapel_index(): void
    {
        GuruMapel::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.guru-mapels.index'))
            ->assertOk()
            ->assertSee('Data Guru Mapel');
    }

    public function test_admin_can_create_guru_mapel_with_user(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.guru-mapels.store'), [
                'name' => 'Budi Santoso',
                'nip' => '198501012010011001',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.guru-mapels.index'))
            ->assertSessionHas('success');

        $user = User::query()->where('email', 'budisantoso@gmail.com')->first();
        $this->assertNotNull($user);
        $this->assertSame(User::ROLE_GURU_MAPEL, $user->role);
        $this->assertDatabaseHas('guru_mapels', ['user_id' => $user->id, 'nip' => '198501012010011001']);
    }

    public function test_guru_mapel_email_auto_generated_from_name_in_lowercase_no_spaces(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.guru-mapels.store'), [
                'name' => 'Yanto Sudirman',
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.guru-mapels.index'));

        $this->assertDatabaseHas('users', [
            'name' => 'Yanto Sudirman',
            'email' => 'yantosudirman@gmail.com',
        ]);
    }

    public function test_email_manual_input_is_respected_not_overwritten(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.guru-mapels.store'), [
                'name' => 'Yanto Sudirman',
                'email' => 'yanto.custom@school.ac.id',
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.guru-mapels.index'))
            ->assertSessionHas('success');

        // Email yang diketik admin dipakai apa adanya, bukan hasil generate dari nama.
        $this->assertDatabaseHas('users', [
            'name' => 'Yanto Sudirman',
            'email' => 'yanto.custom@school.ac.id',
        ]);
        $this->assertDatabaseMissing('users', ['email' => 'yantosudirman@gmail.com']);
    }

    public function test_email_manual_duplicate_returns_validation_error(): void
    {
        $existing = GuruMapel::factory()->create()->user;
        $existing->update(['email' => 'yanto.custom@school.ac.id']);

        $this->actingAs($this->admin)
            ->from(route('admin.guru-mapels.create'))
            ->post(route('admin.guru-mapels.store'), [
                'name' => 'Orang Lain',
                'email' => 'yanto.custom@school.ac.id',
                'is_active' => 1,
            ])
            ->assertSessionHasErrors('email');

        // Email manual yang dipakai orang lain tidak di-auto-nomor, hanya error.
        $this->assertSame(1, GuruMapel::count());
        $this->assertDatabaseMissing('users', ['email' => 'oranglain@gmail.com']);
    }

    public function test_guru_mapel_email_appends_number_when_name_yields_duplicate(): void
    {
        $existing = GuruMapel::factory()->create()->user;
        $existing->update(['name' => 'Yanto Sudirman', 'email' => 'yantosudirman@gmail.com']);

        $this->actingAs($this->admin)
            ->post(route('admin.guru-mapels.store'), [
                'name' => 'Yanto Sudirman',
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.guru-mapels.index'))
            ->assertSessionHas('success');

        // Email pertama terpakai, jadi yang baru otomatis dapat angka 2.
        $this->assertDatabaseHas('users', ['email' => 'yantosudirman2@gmail.com']);
        $this->assertSame(2, GuruMapel::count());
    }

    public function test_guru_mapel_created_with_selected_subject_gets_assignment(): void
    {
        $subject = Subject::create(['code' => 'MTK', 'name' => 'Matematika', 'default_duration_minutes' => 90]);

        $this->actingAs($this->admin)
            ->post(route('admin.guru-mapels.store'), [
                'name' => 'Budi Santoso',
                'is_active' => 1,
                'subject_id' => $subject->id,
            ])
            ->assertRedirect(route('admin.guru-mapels.index'));

        $guru = GuruMapel::query()->first();

        $this->assertDatabaseHas('teacher_subject_class_assignments', [
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
        ]);
        $this->assertSame(1, TeacherSubjectClassAssignment::count());
    }

    public function test_guru_mapel_created_without_subject_is_created_without_assignment(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.guru-mapels.store'), [
                'name' => 'Budi Santoso',
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.guru-mapels.index'))
            ->assertSessionHas('success');

        $this->assertSame(1, GuruMapel::count());
        $this->assertSame(0, TeacherSubjectClassAssignment::count());
    }

    public function test_admin_can_update_guru_mapel(): void
    {
        $guru = GuruMapel::factory()->create();

        $this->actingAs($this->admin)
            ->put(route('admin.guru-mapels.update', $guru), [
                'name' => 'Nama Baru',
                'email' => $guru->user->email,
                'nip' => '12345',
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.guru-mapels.index'))
            ->assertSessionHas('success');

        $this->assertSame('Nama Baru', $guru->fresh()->user->name);
        $this->assertSame('12345', $guru->fresh()->nip);
    }

    public function test_admin_can_delete_guru_mapel_with_its_user(): void
    {
        $guru = GuruMapel::factory()->create();
        $userId = $guru->user_id;

        $this->actingAs($this->admin)
            ->delete(route('admin.guru-mapels.destroy', $guru))
            ->assertRedirect(route('admin.guru-mapels.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('guru_mapels', ['id' => $guru->id]);
        $this->assertDatabaseMissing('users', ['id' => $userId]);
    }

    public function test_admin_can_create_assignment_for_guru(): void
    {
        $guru = GuruMapel::factory()->create();
        $subject = Subject::create(['code' => 'MTK', 'name' => 'Matematika', 'default_duration_minutes' => 90]);

        $this->actingAs($this->admin)
            ->post(route('admin.guru-mapels.assignments.store', $guru), [
                'subject_id' => $subject->id,
            ])
            ->assertRedirect(route('admin.guru-mapels.assignments.edit', $guru))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('teacher_subject_class_assignments', [
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
        ]);
        $this->assertSame(1, TeacherSubjectClassAssignment::count());
    }

    public function test_assignment_cannot_be_duplicated(): void
    {
        $guru = GuruMapel::factory()->create();
        $subject = Subject::create(['code' => 'MTK', 'name' => 'Matematika', 'default_duration_minutes' => 90]);

        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.guru-mapels.assignments.store', $guru), [
                'subject_id' => $subject->id,
            ])
            ->assertRedirect(route('admin.guru-mapels.assignments.edit', $guru));

        $this->assertSame(1, TeacherSubjectClassAssignment::count());
    }

    public function test_assignment_resubmit_same_subject_does_not_create_duplicates(): void
    {
        $guru = GuruMapel::factory()->create();
        $subject = Subject::create(['code' => 'MTK', 'name' => 'Matematika', 'default_duration_minutes' => 90]);

        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.guru-mapels.assignments.store', $guru), [
                'subject_id' => $subject->id,
            ])
            ->assertRedirect(route('admin.guru-mapels.assignments.edit', $guru));

        $this->assertSame(1, TeacherSubjectClassAssignment::count());
    }

    public function test_assignment_can_be_removed_from_guru(): void
    {
        $guru = GuruMapel::factory()->create();
        $mtk = Subject::create(['code' => 'MTK', 'name' => 'Matematika', 'default_duration_minutes' => 90]);
        $bindo = Subject::create(['code' => 'BIN', 'name' => 'Bahasa Indonesia', 'default_duration_minutes' => 90]);

        $mtkAssignment = TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => $mtk->id,
        ]);
        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => $bindo->id,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.guru-mapels.assignments.destroy', $mtkAssignment))
            ->assertRedirect(route('admin.guru-mapels.assignments.edit', $guru))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('teacher_subject_class_assignments', ['id' => $mtkAssignment->id]);
        $this->assertSame(1, TeacherSubjectClassAssignment::count());
    }

    public function test_assignment_sync_keeps_other_subjects_intact(): void
    {
        $guru = GuruMapel::factory()->create();
        $mtk = Subject::create(['code' => 'MTK', 'name' => 'Matematika', 'default_duration_minutes' => 90]);
        $bindo = Subject::create(['code' => 'BIN', 'name' => 'Bahasa Indonesia', 'default_duration_minutes' => 90]);

        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => $bindo->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.guru-mapels.assignments.store', $guru), [
                'subject_id' => $mtk->id,
            ])
            ->assertRedirect(route('admin.guru-mapels.assignments.edit', $guru));

        // Assignment mapel lain tidak tersentuh.
        $this->assertSame(2, TeacherSubjectClassAssignment::count());
        $this->assertDatabaseHas('teacher_subject_class_assignments', [
            'guru_mapel_id' => $guru->id,
            'subject_id' => $bindo->id,
        ]);
    }

    public function test_assignment_requires_subject(): void
    {
        $guru = GuruMapel::factory()->create();

        $this->actingAs($this->admin)
            ->from(route('admin.guru-mapels.assignments.edit', $guru))
            ->post(route('admin.guru-mapels.assignments.store', $guru))
            ->assertSessionHasErrors('subject_id');

        $this->assertSame(0, TeacherSubjectClassAssignment::count());
    }

    public function test_assignments_page_shows_assigned_subjects_and_class_scope(): void
    {
        $guru = GuruMapel::factory()->create();
        $subject = Subject::create(['code' => 'MTK', 'name' => 'Matematika', 'default_duration_minutes' => 90]);
        $assigned = Classroom::create(['name' => 'XI RPL 1']);
        Classroom::create(['name' => 'XI TKJ 1']);

        // Cakupan kelas berasal dari classroom_id pada penugasan eksplisit.
        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
            'classroom_id' => $assigned->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.guru-mapels.assignments.edit', $guru))
            ->assertOk()
            ->assertSee((string) $subject->id, false)
            ->assertSee($assigned->name);
    }

    public function test_admin_can_delete_assignment(): void
    {
        $guru = GuruMapel::factory()->create();
        $assignment = TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => Subject::create(['code' => 'MTK', 'name' => 'Matematika', 'default_duration_minutes' => 90])->id,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.guru-mapels.assignments.destroy', $assignment))
            ->assertRedirect(route('admin.guru-mapels.assignments.edit', $guru))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('teacher_subject_class_assignments', ['id' => $assignment->id]);
    }

    public function test_non_admin_cannot_access_guru_mapel_pages(): void
    {
        $peserta = User::factory()->peserta()->create();

        $this->actingAs($peserta)->get(route('admin.guru-mapels.index'))->assertForbidden();
    }

    public function test_admin_can_set_classrooms_for_an_assigned_subject(): void
    {
        $guru = GuruMapel::factory()->create();
        $subject = Subject::create(['code' => 'MTK', 'name' => 'Matematika', 'default_duration_minutes' => 90]);
        $c1 = Classroom::create(['name' => 'X RPL 1']);
        $c2 = Classroom::create(['name' => 'X RPL 2']);
        $c3 = Classroom::create(['name' => 'XI RPL 1']);

        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.guru-mapels.assignments.classrooms', [$guru, $subject]), [
                'classroom_ids' => [$c1->id, $c2->id],
            ])
            ->assertRedirect(route('admin.guru-mapels.assignments.edit', $guru))
            ->assertSessionHas('success');

        $this->assertSame(2, $guru->assignments()->count());
        $this->assertSame([$c1->id, $c2->id], $guru->ampuClassroomIds($subject->id)->sort()->values()->all());
        // Kelas lain tidak ikut masuk.
        $this->assertDatabaseMissing('teacher_subject_class_assignments', [
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
            'classroom_id' => $c3->id,
        ]);
    }

    public function test_clearing_classrooms_keeps_subject_assigned_as_mapel_only(): void
    {
        $guru = GuruMapel::factory()->create();
        $subject = Subject::create(['code' => 'MTK', 'name' => 'Matematika', 'default_duration_minutes' => 90]);
        $c1 = Classroom::create(['name' => 'X RPL 1']);

        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
            'classroom_id' => $c1->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.guru-mapels.assignments.classrooms', [$guru, $subject]), [
                'classroom_ids' => [],
            ])
            ->assertRedirect(route('admin.guru-mapels.assignments.edit', $guru));

        $this->assertSame(1, $guru->assignments()->count());
        $this->assertDatabaseHas('teacher_subject_class_assignments', [
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
            'classroom_id' => null,
        ]);
    }

    public function test_admin_can_remove_entire_subject_assignment(): void
    {
        $guru = GuruMapel::factory()->create();
        $subject = Subject::create(['code' => 'MTK', 'name' => 'Matematika', 'default_duration_minutes' => 90]);
        $c1 = Classroom::create(['name' => 'X RPL 1']);
        $c2 = Classroom::create(['name' => 'X RPL 2']);

        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
            'classroom_id' => $c1->id,
        ]);
        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
            'classroom_id' => $c2->id,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.guru-mapels.assignments.destroy-subject', [$guru, $subject]))
            ->assertRedirect(route('admin.guru-mapels.assignments.edit', $guru))
            ->assertSessionHas('success');

        // Seluruh baris mapel (semua kelas) ikut terhapus.
        $this->assertSame(0, $guru->assignments()->count());
        $this->assertDatabaseMissing('teacher_subject_class_assignments', [
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
        ]);
    }

    public function test_guru_mapel_created_with_subject_and_classrooms_gets_scoped_assignment(): void
    {
        $subject = Subject::create(['code' => 'MTK', 'name' => 'Matematika', 'default_duration_minutes' => 90]);
        $c1 = Classroom::create(['name' => 'X RPL 1']);
        $c2 = Classroom::create(['name' => 'X RPL 2']);

        $this->actingAs($this->admin)
            ->post(route('admin.guru-mapels.store'), [
                'name' => 'Budi Santoso',
                'is_active' => 1,
                'subject_id' => $subject->id,
                'classroom_ids' => [$c1->id, $c2->id],
            ])
            ->assertRedirect(route('admin.guru-mapels.index'));

        $guru = GuruMapel::query()->first();

        $this->assertSame(2, $guru->assignments()->count());
        $this->assertSame([$c1->id, $c2->id], $guru->ampuClassroomIds($subject->id)->sort()->values()->all());
    }

    public function test_detail_page_shows_scoped_classrooms_from_assignment(): void
    {
        $guru = GuruMapel::factory()->create();
        $subject = Subject::create(['code' => 'MTK', 'name' => 'Matematika', 'default_duration_minutes' => 90]);
        $classroom = Classroom::create(['name' => 'XI RPL 1']);
        // Kelas lain sesama tingkat XI sehingga tingkat XI tidak "penuh" — dengan
        // begitu tabel "Cakupan Mengajar" tetap menampilkan nama kelas satuan
        // (bukan ringkasan tingkat), konsisten dengan logika simplify C/D.
        Classroom::create(['name' => 'XI RPL 2']);

        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.guru-mapels.show', $guru))
            ->assertOk()
            ->assertSee($subject->name)
            ->assertSee($classroom->name)
            ->assertSee('1 pasangan');
    }

    public function test_detail_page_does_not_show_edit_button(): void
    {
        $guru = GuruMapel::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.guru-mapels.show', $guru))
            ->assertOk()
            ->assertDontSee(route('admin.guru-mapels.edit', $guru))
            ->assertSee(route('admin.guru-mapels.assignments.edit', $guru));
    }

    public function test_class_can_be_assigned_to_first_guru_for_subject(): void
    {
        $guruA = GuruMapel::factory()->create();
        $subject = Subject::create(['code' => 'BIN', 'name' => 'Bahasa Indonesia', 'default_duration_minutes' => 90]);
        $classX = Classroom::create(['name' => 'X AKL 1']);

        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guruA->id,
            'subject_id' => $subject->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.guru-mapels.assignments.classrooms', [$guruA, $subject]), [
                'classroom_ids' => [$classX->id],
            ])
            ->assertRedirect(route('admin.guru-mapels.assignments.edit', $guruA))
            ->assertSessionHas('success');

        $this->assertSame([$classX->id], $guruA->ampuClassroomIds($subject->id)->sort()->values()->all());
    }

    public function test_second_guru_cannot_take_taken_class_for_same_subject(): void
    {
        $guruA = GuruMapel::factory()->create();
        $guruB = GuruMapel::factory()->create();
        $subject = Subject::create(['code' => 'BIN', 'name' => 'Bahasa Indonesia', 'default_duration_minutes' => 90]);
        $classX = Classroom::create(['name' => 'X AKL 1']);

        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guruA->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classX->id,
        ]);
        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guruB->id,
            'subject_id' => $subject->id,
        ]);

        $this->actingAs($this->admin)
            ->from(route('admin.guru-mapels.assignments.edit', $guruB))
            ->post(route('admin.guru-mapels.assignments.classrooms', [$guruB, $subject]), [
                'classroom_ids' => [$classX->id],
            ])
            ->assertSessionHasErrors('classroom_ids');

        // Kelas X tetap milik Guru A untuk mapel tersebut, tidak diduplikasi.
        $this->assertSame([$classX->id], $guruA->ampuClassroomIds($subject->id)->sort()->values()->all());
        $this->assertSame([], $guruB->ampuClassroomIds($subject->id)->values()->all());
    }

    public function test_class_can_be_taken_for_different_subject_by_another_guru(): void
    {
        $guruA = GuruMapel::factory()->create();
        $guruC = GuruMapel::factory()->create();
        $bindo = Subject::create(['code' => 'BIN', 'name' => 'Bahasa Indonesia', 'default_duration_minutes' => 90]);
        $matematika = Subject::create(['code' => 'MTK', 'name' => 'Matematika', 'default_duration_minutes' => 90]);
        $classX = Classroom::create(['name' => 'X AKL 1']);

        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guruA->id,
            'subject_id' => $bindo->id,
            'classroom_id' => $classX->id,
        ]);
        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guruC->id,
            'subject_id' => $matematika->id,
        ]);

        // Kelas X pada mapel BERBEDA tetap boleh diambil guru lain.
        $this->actingAs($this->admin)
            ->post(route('admin.guru-mapels.assignments.classrooms', [$guruC, $matematika]), [
                'classroom_ids' => [$classX->id],
            ])
            ->assertRedirect(route('admin.guru-mapels.assignments.edit', $guruC))
            ->assertSessionHas('success');

        $this->assertSame([$classX->id], $guruC->ampuClassroomIds($matematika->id)->sort()->values()->all());
    }

    public function test_teacher_can_edit_own_assignment_classroom_still_kept(): void
    {
        $guruA = GuruMapel::factory()->create();
        $subject = Subject::create(['code' => 'BIN', 'name' => 'Bahasa Indonesia', 'default_duration_minutes' => 90]);
        $classX = Classroom::create(['name' => 'X AKL 1']);
        $classY = Classroom::create(['name' => 'X AKL 2']);

        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guruA->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classX->id,
        ]);

        // Guru A menyimpan ulang assignment sendiri (kelas miliknya + tambah kelas Y):
        // kelas miliknya sendiri tidak dianggap "diambil guru lain", jadi tetap diterima.
        $this->actingAs($this->admin)
            ->post(route('admin.guru-mapels.assignments.classrooms', [$guruA, $subject]), [
                'classroom_ids' => [$classX->id, $classY->id],
            ])
            ->assertRedirect(route('admin.guru-mapels.assignments.edit', $guruA))
            ->assertSessionHas('success');

        $this->assertSame([$classX->id, $classY->id], $guruA->ampuClassroomIds($subject->id)->sort()->values()->all());
    }

    public function test_assignments_page_disables_and_notes_class_taken_by_other_teacher(): void
    {
        $guruA = GuruMapel::factory()->create();
        $guruB = GuruMapel::factory()->create();
        $subject = Subject::create(['code' => 'BIN', 'name' => 'Bahasa Indonesia', 'default_duration_minutes' => 90]);
        $taken = Classroom::create(['name' => 'X AKL 1']);
        Classroom::create(['name' => 'X AKL 2']);

        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guruA->id,
            'subject_id' => $subject->id,
            'classroom_id' => $taken->id,
        ]);
        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guruB->id,
            'subject_id' => $subject->id,
        ]);

        $html = $this->actingAs($this->admin)
            ->get(route('admin.guru-mapels.assignments.edit', $guruB))
            ->assertOk()
            ->getContent();

        // Payload exclusion untuk mapel tsb berisi nama guru pemilik kelas yang
        // sudah terambil, dan checkbox-nya dirender disabled oleh komponen.
        $this->assertStringContainsString('notes: '.json_encode([$taken->id => $guruA->user->name]), $html);
        $this->assertStringContainsString('disabled', $html);
    }

    public function test_assignments_page_does_not_exclude_current_teachers_own_class(): void
    {
        $guruA = GuruMapel::factory()->create();
        $subject = Subject::create(['code' => 'BIN', 'name' => 'Bahasa Indonesia', 'default_duration_minutes' => 90]);
        $own = Classroom::create(['name' => 'X AKL 1']);
        $other = Classroom::create(['name' => 'X AKL 2']);

        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guruA->id,
            'subject_id' => $subject->id,
            'classroom_id' => $own->id,
        ]);

        $html = $this->actingAs($this->admin)
            ->get(route('admin.guru-mapels.assignments.edit', $guruA))
            ->assertOk()
            ->getContent();

        // Kelas milik guru yang sedang diedit TIDAK boleh muncul sebagai
        // exclusion milik dirinya sendiri (guru_mapel_id != current guru).
        $this->assertStringNotContainsString('"'.$own->id.'":"'.$guruA->user->name.'"', $html);
        $this->assertStringNotContainsString('"'.$other->id.'":', $html);
    }

    public function test_create_page_preloads_exclusions_payload(): void
    {
        $guruA = GuruMapel::factory()->create();
        $subject = Subject::create(['code' => 'BIN', 'name' => 'Bahasa Indonesia', 'default_duration_minutes' => 90]);
        $taken = Classroom::create(['name' => 'X AKL 1']);
        Classroom::create(['name' => 'X AKL 2']);

        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guruA->id,
            'subject_id' => $subject->id,
            'classroom_id' => $taken->id,
        ]);

        $html = $this->actingAs($this->admin)
            ->get(route('admin.guru-mapels.create'))
            ->assertOk()
            ->getContent();

        // Data preload exclusion (subject => [classroom => nama pemilik]) dikirim
        // ke view sebagai state Alpine, dan binding disable eksklusif terpasang.
        $this->assertStringContainsString($subject->id.':{', $html);
        $this->assertStringContainsString('"'.$taken->id.'":"'.$guruA->user->name.'"', $html);
        $this->assertStringContainsString('excl[subject]', $html);
    }

    public function test_exclusivity_violation_returns_short_one_line_error(): void
    {
        $guruA = GuruMapel::factory()->create();
        $guruB = GuruMapel::factory()->create();
        $subject = Subject::create(['code' => 'BIN', 'name' => 'Bahasa Indonesia', 'default_duration_minutes' => 90]);
        $taken = Classroom::create(['name' => 'X AKL 1']);
        $taken2 = Classroom::create(['name' => 'X AKL 2']);

        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guruA->id,
            'subject_id' => $subject->id,
            'classroom_id' => $taken->id,
        ]);
        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guruA->id,
            'subject_id' => $subject->id,
            'classroom_id' => $taken2->id,
        ]);
        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guruB->id,
            'subject_id' => $subject->id,
        ]);

        $resp = $this->actingAs($this->admin)
            ->from(route('admin.guru-mapels.assignments.edit', $guruB))
            ->post(route('admin.guru-mapels.assignments.classrooms', [$guruB, $subject]), [
                'classroom_ids' => [$taken->id, $taken2->id],
            ])
            ->assertSessionHasErrors('classroom_ids');

        $message = session('errors')->first('classroom_ids');

        // Pesan ringkas 1 baris, bukan daftar panjang tiap kelas yang bentrok.
        $this->assertStringContainsString('Sebagian kelas yang dipilih sudah diampu guru lain', $message);
        $this->assertStringNotContainsString('X AKL 1', $message);
        $this->assertStringNotContainsString('X AKL 2', $message);
    }
}

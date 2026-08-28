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

    public function test_guru_mapel_created_with_selected_subject_and_classes_gets_assignments(): void
    {
        $subject = Subject::create(['code' => 'MTK', 'name' => 'Matematika', 'default_duration_minutes' => 90]);
        $classroomA = Classroom::create(['name' => 'XI RPL 1']);
        $classroomB = Classroom::create(['name' => 'XI RPL 2']);

        $this->actingAs($this->admin)
            ->post(route('admin.guru-mapels.store'), [
                'name' => 'Budi Santoso',
                'is_active' => 1,
                'subject_id' => $subject->id,
                'classroom_ids' => [$classroomA->id, $classroomB->id],
            ])
            ->assertRedirect(route('admin.guru-mapels.index'));

        $guru = GuruMapel::query()->first();

        $this->assertDatabaseHas('teacher_subject_class_assignments', [
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroomA->id,
        ]);
        $this->assertDatabaseHas('teacher_subject_class_assignments', [
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroomB->id,
        ]);
        $this->assertSame(2, TeacherSubjectClassAssignment::count());
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
        $classroomA = Classroom::create(['name' => 'XI RPL 1']);
        $classroomB = Classroom::create(['name' => 'XI RPL 2']);

        $this->actingAs($this->admin)
            ->post(route('admin.guru-mapels.assignments.store', $guru), [
                'subject_id' => $subject->id,
                'classroom_ids' => [$classroomA->id, $classroomB->id],
            ])
            ->assertRedirect(route('admin.guru-mapels.assignments.edit', $guru))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('teacher_subject_class_assignments', [
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroomA->id,
        ]);
        $this->assertDatabaseHas('teacher_subject_class_assignments', [
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroomB->id,
        ]);
        $this->assertSame(2, TeacherSubjectClassAssignment::count());
    }

    public function test_assignment_cannot_be_duplicated(): void
    {
        $guru = GuruMapel::factory()->create();
        $subject = Subject::create(['code' => 'MTK', 'name' => 'Matematika', 'default_duration_minutes' => 90]);
        $classroom = Classroom::create(['name' => 'XI RPL 1']);

        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.guru-mapels.assignments.store', $guru), [
                'subject_id' => $subject->id,
                'classroom_ids' => [$classroom->id],
            ])
            ->assertRedirect(route('admin.guru-mapels.assignments.edit', $guru));

        $this->assertSame(1, TeacherSubjectClassAssignment::count());
    }

    public function test_assignment_resubmit_same_set_does_not_create_duplicates(): void
    {
        $guru = GuruMapel::factory()->create();
        $subject = Subject::create(['code' => 'MTK', 'name' => 'Matematika', 'default_duration_minutes' => 90]);
        $classrooms = [
            Classroom::create(['name' => 'XI RPL 1']),
            Classroom::create(['name' => 'XI RPL 2']),
        ];
        $ids = array_map(fn ($c) => $c->id, $classrooms);

        foreach ($ids as $classroomId) {
            TeacherSubjectClassAssignment::create([
                'guru_mapel_id' => $guru->id,
                'subject_id' => $subject->id,
                'classroom_id' => $classroomId,
            ]);
        }

        $this->actingAs($this->admin)
            ->post(route('admin.guru-mapels.assignments.store', $guru), [
                'subject_id' => $subject->id,
                'classroom_ids' => $ids,
            ])
            ->assertRedirect(route('admin.guru-mapels.assignments.edit', $guru));

        $this->assertSame(2, TeacherSubjectClassAssignment::count());
    }

    public function test_assignment_sync_adds_new_and_removes_unchecked_classes(): void
    {
        $guru = GuruMapel::factory()->create();
        $subject = Subject::create(['code' => 'MTK', 'name' => 'Matematika', 'default_duration_minutes' => 90]);
        $kept = Classroom::create(['name' => 'XI RPL 1']);
        $dropped = Classroom::create(['name' => 'XI RPL 2']);
        $added = Classroom::create(['name' => 'XI TKJ 1']);

        foreach ([$dropped->id, $kept->id] as $classroomId) {
            TeacherSubjectClassAssignment::create([
                'guru_mapel_id' => $guru->id,
                'subject_id' => $subject->id,
                'classroom_id' => $classroomId,
            ]);
        }

        $this->actingAs($this->admin)
            ->post(route('admin.guru-mapels.assignments.store', $guru), [
                'subject_id' => $subject->id,
                'classroom_ids' => [$kept->id, $added->id],
            ])
            ->assertRedirect(route('admin.guru-mapels.assignments.edit', $guru))
            ->assertSessionHas('success');

        // Yang di-uncheck terhapus, yang tetap bertahan, yang baru ditambahkan.
        $this->assertDatabaseMissing('teacher_subject_class_assignments', [
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
            'classroom_id' => $dropped->id,
        ]);
        $this->assertDatabaseHas('teacher_subject_class_assignments', [
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
            'classroom_id' => $kept->id,
        ]);
        $this->assertDatabaseHas('teacher_subject_class_assignments', [
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
            'classroom_id' => $added->id,
        ]);
        $this->assertSame(2, TeacherSubjectClassAssignment::count());
    }

    public function test_assignment_sync_is_scoped_to_the_selected_subject(): void
    {
        $guru = GuruMapel::factory()->create();
        $mtk = Subject::create(['code' => 'MTK', 'name' => 'Matematika', 'default_duration_minutes' => 90]);
        $bindo = Subject::create(['code' => 'BIN', 'name' => 'Bahasa Indonesia', 'default_duration_minutes' => 90]);
        $classroom = Classroom::create(['name' => 'XI RPL 1']);

        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => $bindo->id,
            'classroom_id' => $classroom->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.guru-mapels.assignments.store', $guru), [
                'subject_id' => $mtk->id,
                'classroom_ids' => [$classroom->id],
            ])
            ->assertRedirect(route('admin.guru-mapels.assignments.edit', $guru));

        // Assignment mapel lain tidak tersentuh.
        $this->assertSame(2, TeacherSubjectClassAssignment::count());
        $this->assertDatabaseHas('teacher_subject_class_assignments', [
            'guru_mapel_id' => $guru->id,
            'subject_id' => $bindo->id,
            'classroom_id' => $classroom->id,
        ]);
    }

    public function test_assignment_sync_requires_at_least_one_classroom(): void
    {
        $guru = GuruMapel::factory()->create();
        $subject = Subject::create(['code' => 'MTK', 'name' => 'Matematika', 'default_duration_minutes' => 90]);

        $this->actingAs($this->admin)
            ->from(route('admin.guru-mapels.assignments.edit', $guru))
            ->post(route('admin.guru-mapels.assignments.store', $guru), [
                'subject_id' => $subject->id,
                'classroom_ids' => [],
            ])
            ->assertSessionHasErrors('classroom_ids');

        $this->assertSame(0, TeacherSubjectClassAssignment::count());
    }

    public function test_assignments_page_pre_checks_existing_classrooms_for_subject(): void
    {
        $guru = GuruMapel::factory()->create();
        $subject = Subject::create(['code' => 'MTK', 'name' => 'Matematika', 'default_duration_minutes' => 90]);
        $assigned = Classroom::create(['name' => 'XI RPL 1']);
        $unassigned = Classroom::create(['name' => 'XI TKJ 1']);

        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
            'classroom_id' => $assigned->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.guru-mapels.assignments.edit', $guru))
            ->assertOk()
            // Map peta subject_id => classroom_id termuat di data Alpine.
            ->assertSee((string) $subject->id, false)
            ->assertSee((string) $assigned->id, false)
            ->assertSee((string) $unassigned->id, false);
    }

    public function test_admin_can_delete_assignment(): void
    {
        $guru = GuruMapel::factory()->create();
        $assignment = TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => Subject::create(['code' => 'MTK', 'name' => 'Matematika', 'default_duration_minutes' => 90])->id,
            'classroom_id' => Classroom::create(['name' => 'XI RPL 1'])->id,
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
}

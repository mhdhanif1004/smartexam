<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use App\Models\WaliKelas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaliKelasModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_can_view_wali_kelas_index(): void
    {
        WaliKelas::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.wali-kelas.index'))
            ->assertOk()
            ->assertSee('Data Wali Kelas');
    }

    public function test_admin_can_create_wali_kelas_with_classroom(): void
    {
        $classroom = Classroom::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.wali-kelas.store'), [
                'name' => 'Siti Rahmawati',
                'classroom_id' => $classroom->id,
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.wali-kelas.index'))
            ->assertSessionHas('success');

        $user = User::query()->where('email', 'sitirahmawati@gmail.com')->first();
        $this->assertNotNull($user);
        $this->assertSame(User::ROLE_WALI_KELAS, $user->role);
        $this->assertDatabaseHas('wali_kelas', ['user_id' => $user->id, 'classroom_id' => $classroom->id]);
    }

    public function test_wali_kelas_email_auto_generated_from_name_in_lowercase_no_spaces(): void
    {
        $classroom = Classroom::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.wali-kelas.store'), [
                'name' => 'Yanto Sudirman',
                'classroom_id' => $classroom->id,
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.wali-kelas.index'));

        $this->assertDatabaseHas('users', [
            'name' => 'Yanto Sudirman',
            'email' => 'yantosudirman@gmail.com',
        ]);
    }

    public function test_email_manual_input_is_respected_not_overwritten(): void
    {
        $classroom = Classroom::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.wali-kelas.store'), [
                'name' => 'Yanto Sudirman',
                'email' => 'yanto.custom@school.ac.id',
                'classroom_id' => $classroom->id,
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.wali-kelas.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'name' => 'Yanto Sudirman',
            'email' => 'yanto.custom@school.ac.id',
        ]);
        $this->assertDatabaseMissing('users', ['email' => 'yantosudirman@gmail.com']);
    }

    public function test_classroom_must_be_unique_across_wali_kelas(): void
    {
        $classroom = Classroom::factory()->create();
        WaliKelas::factory()->create(['classroom_id' => $classroom->id]);

        $this->actingAs($this->admin)
            ->from(route('admin.wali-kelas.create'))
            ->post(route('admin.wali-kelas.store'), [
                'name' => 'Orang Lain',
                'classroom_id' => $classroom->id,
                'is_active' => 1,
            ])
            ->assertSessionHasErrors('classroom_id');

        // Wali kelas tidak boleh ditambahkan dua kali untuk kelas yang sama.
        $this->assertSame(1, WaliKelas::count());
    }

    public function test_admin_can_update_wali_kelas_classroom(): void
    {
        $wali = WaliKelas::factory()->create();
        $newClassroom = Classroom::factory()->create();

        $this->actingAs($this->admin)
            ->patch(route('admin.wali-kelas.update', $wali), [
                'name' => $wali->user->name,
                'email' => $wali->user->email,
                'classroom_id' => $newClassroom->id,
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.wali-kelas.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('wali_kelas', ['id' => $wali->id, 'classroom_id' => $newClassroom->id]);
    }

    public function test_admin_can_delete_wali_kelas_and_its_user(): void
    {
        $wali = WaliKelas::factory()->create();
        $userId = $wali->user_id;

        $this->actingAs($this->admin)
            ->delete(route('admin.wali-kelas.destroy', $wali))
            ->assertRedirect(route('admin.wali-kelas.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('wali_kelas', ['id' => $wali->id]);
        $this->assertDatabaseMissing('users', ['id' => $userId]);
    }

    public function test_wali_kelas_can_access_own_dashboard(): void
    {
        $wali = WaliKelas::factory()->create();

        $this->actingAs($wali->user)
            ->get(route('wali_kelas.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard Wali Kelas')
            ->assertSee($wali->classroom->name);
    }

    public function test_dashboard_shows_student_count_only_from_own_classroom(): void
    {
        $wali = WaliKelas::factory()->create();
        $otherClassroom = Classroom::factory()->create();

        Student::factory()->count(3)->create(['classroom_id' => $wali->classroom_id]);
        Student::factory()->count(5)->create(['classroom_id' => $otherClassroom->id]);

        $this->actingAs($wali->user)
            ->get(route('wali_kelas.dashboard'))
            ->assertOk()
            ->assertSee('3');
    }

    public function test_user_without_wali_kelas_profile_cannot_access_dashboard(): void
    {
        $user = User::factory()->waliKelas()->create();

        $this->actingAs($user)
            ->get(route('wali_kelas.dashboard'))
            ->assertForbidden();
    }

    public function test_wali_kelas_cannot_access_admin_dashboard(): void
    {
        $wali = WaliKelas::factory()->create();

        $this->actingAs($wali->user)
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    public function test_admin_cannot_access_wali_kelas_dashboard(): void
    {
        $this->actingAs($this->admin)
            ->get(route('wali_kelas.dashboard'))
            ->assertForbidden();
    }
}

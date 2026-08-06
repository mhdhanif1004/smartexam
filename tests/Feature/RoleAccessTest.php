<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\Supervisor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
    }

    public function test_pengawas_can_access_pengawas_dashboard(): void
    {
        $pengawas = Supervisor::factory()->create()->user;

        $response = $this->actingAs($pengawas)->get('/pengawas/dashboard');

        $response->assertOk();
    }

    public function test_peserta_can_access_peserta_dashboard(): void
    {
        $peserta = Student::factory()->create()->user;

        $response = $this->actingAs($peserta)->get('/peserta/dashboard');

        $response->assertOk();
    }

    public function test_peserta_cannot_access_admin_dashboard(): void
    {
        $peserta = User::factory()->peserta()->create();

        $response = $this->actingAs($peserta)->get('/admin/dashboard');

        $response->assertForbidden();
    }

    public function test_admin_cannot_access_peserta_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/peserta/dashboard');

        $response->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/admin/dashboard');

        $response->assertRedirect(route('login'));
    }
}

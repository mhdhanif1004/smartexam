<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_peserta_can_authenticate_using_username(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->username,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('peserta.dashboard', absolute: false));
    }

    public function test_admin_can_authenticate_using_email(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($admin);
        $response->assertRedirect(route('admin.dashboard', absolute: false));
    }

    public function test_pengawas_can_authenticate_using_email(): void
    {
        $pengawas = User::factory()->pengawas()->create();

        $response = $this->post('/login', [
            'email' => $pengawas->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($pengawas);
        $response->assertRedirect(route('pengawas.dashboard', absolute: false));
    }

    public function test_peserta_cannot_authenticate_using_email_when_they_have_none(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->username,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_login_with_unknown_username_fails_without_server_error(): void
    {
        $this->post('/login', [
            'email' => 'tidakdikenal123',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}

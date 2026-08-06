<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class RootRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_accessing_root_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login', absolute: false));
    }

    public function test_authenticated_user_accessing_root_is_redirected_to_role_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/')
            ->assertRedirect(route('admin.dashboard', absolute: false));
    }

    public function test_authenticated_user_accessing_login_page_is_redirected_to_role_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/login')
            ->assertRedirect(route('admin.dashboard', absolute: false));
    }

    public function test_remember_me_login_then_browser_restart_lands_on_role_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $login = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $recaller = collect($login->headers->getCookies())->first(
            fn (Cookie $cookie) => str_starts_with($cookie->getName(), 'remember_web_')
        );
        $this->assertNotNull($recaller);

        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $this->withUnencryptedCookies([$recaller->getName() => $recaller->getValue()])
            ->get('/')
            ->assertRedirect(route('admin.dashboard', absolute: false));
    }

    public function test_root_redirects_to_login_after_logout(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->username,
            'password' => 'password',
        ]);

        $this->post('/logout');

        $this->get('/')->assertRedirect(route('login', absolute: false));
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class RememberMeTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_sets_recaller_cookie_even_without_the_remember_checkbox(): void
    {
        $user = User::factory()->admin()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($this->recallerCookie($response), 'Remember cookie must be set by default.');
    }

    public function test_user_is_reauthenticated_via_remember_cookie_after_browser_restart(): void
    {
        $user = User::factory()->admin()->create();

        $login = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $recaller = $this->recallerCookie($login);
        $this->assertNotNull($recaller);

        // Simulate closing Chrome: wipe the session, forget resolved guards,
        // and reopen the browser keeping only the stored remember cookie.
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $reopened = $this->withUnencryptedCookies([$recaller->getName() => $recaller->getValue()])
            ->get(route('admin.dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $reopened->assertOk();
    }

    public function test_logout_clears_the_recaller_cookie_and_invalidates_the_token(): void
    {
        $user = User::factory()->admin()->create();

        $login = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $recaller = $this->recallerCookie($login);
        $this->assertNotNull($recaller);

        $tokenBefore = $user->fresh()->remember_token;

        // A real browser sends the remember cookie back with the logout request.
        $response = $this->withUnencryptedCookies([$recaller->getName() => $recaller->getValue()])
            ->post('/logout');

        $this->assertGuest();
        $this->assertNotSame($tokenBefore, $user->fresh()->remember_token);

        $forgotten = $this->recallerCookie($response);
        $this->assertNotNull($forgotten);
        $this->assertLessThan(time(), $forgotten->getExpiresTime());
    }

    private function recallerCookie($response): ?Cookie
    {
        return collect($response->headers->getCookies())->first(
            fn (Cookie $cookie) => str_starts_with($cookie->getName(), 'remember_web_')
        );
    }
}

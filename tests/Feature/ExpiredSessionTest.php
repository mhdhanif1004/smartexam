<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

class ExpiredSessionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Laravel melewatkan verifikasi CSRF saat menjalankan PHPUnit. Untuk
        // mensimulasikan skenario "token basi", verifikasi CSRF dipaksa aktif
        // sehingga TokenMismatchException benar-benar dilempar dan diproses
        // oleh exception handler bawaan aplikasi.
        $this->app->bind(ValidateCsrfToken::class, function ($app) {
            return new class($app, $app['encrypter']) extends ValidateCsrfToken
            {
                protected function runningUnitTests(): bool
                {
                    return false;
                }
            };
        });
    }

    public function test_stale_csrf_on_login_submit_redirects_with_message(): void
    {
        $this->get('/login')->assertOk();

        $response = $this->post('/login', [
            'email' => 'peserta@example.com',
            'password' => 'password',
            '_token' => 'stale-token-123',
        ]);

        $response->assertStatus(302);
        $this->assertStringContainsString('/login', (string) $response->headers->get('Location'));
        $response->assertSessionHas('error', 'Sesi Anda telah kadaluarsa, silakan login kembali.');
    }

    public function test_stale_csrf_json_request_returns_419_with_fresh_token(): void
    {
        $this->get('/login')->assertOk();

        $response = $this->withHeader('Accept', 'application/json')->post('/login', [
            'email' => 'peserta@example.com',
            'password' => 'password',
            '_token' => 'stale-token-123',
        ]);

        $response->assertStatus(419);
        $response->assertJsonStructure(['message', 'csrf_token', 'login_url']);
        $this->assertNotSame('stale-token-123', $response->json('csrf_token'));
        $this->assertStringContainsString('/login', (string) $response->json('login_url'));
    }

    public function test_csrf_token_endpoint_returns_fresh_token(): void
    {
        $this->get('/csrf-token')
            ->assertOk()
            ->assertJsonStructure(['csrf_token']);
    }

    public function test_form_pages_are_not_cached_by_browser(): void
    {
        $response = $this->get('/login')->assertOk();

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('must-revalidate', (string) $response->headers->get('Cache-Control'));
        $this->assertSame('no-cache', (string) $response->headers->get('Pragma'));
    }
}

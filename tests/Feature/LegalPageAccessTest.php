<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPageAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_legal_pages_accessible_without_login(): void
    {
        $this->get('/privacy-policy')->assertOk()->assertSee('Kebijakan Privasi');
        $this->get('/terms-of-service')->assertOk()->assertSee('Syarat');
        $this->get('/cbt-guidelines')->assertOk()->assertSee('Panduan');
    }
}

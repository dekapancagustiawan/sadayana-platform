<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_responses_carry_security_headers(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'")
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_error_and_api_responses_carry_security_headers(): void
    {
        $this->get('/dashboard')->assertStatus(401)->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $this->getJson('/api/health')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_hsts_is_sent_over_https(): void
    {
        $this->get('https://localhost/')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}

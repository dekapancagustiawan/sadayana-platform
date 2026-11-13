<?php

namespace Tests\Feature;

use App\Enums\RoleType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'PermissionSeeder']);
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
        $this->artisan('db:seed', ['--class' => 'MenuAccessPermissionsSeeder']);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleType::Admin->value);

        return $user;
    }

    public function test_web_login_is_blocked_after_five_failed_attempts(): void
    {
        $user = $this->admin();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.submit'), ['login' => $user->email, 'password' => 'wrong-password'])
                ->assertSessionHasErrors('login');
        }

        // Password benar pun ditolak selama masih dibatasi.
        $this->post(route('login.submit'), ['login' => $user->email, 'password' => 'password'])
            ->assertRedirect()
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_api_login_returns_429_after_five_failed_attempts(): void
    {
        $user = $this->admin();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'wrong-password'])
                ->assertStatus(401);
        }

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertStatus(429)
            ->assertJsonPath('status', 'error')
            ->assertHeader('Retry-After');
    }

    public function test_limit_is_per_email(): void
    {
        $user = $this->admin();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'someone@example.com', 'password' => 'wrong-password']);
        }

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk();
    }
}

<?php

namespace Tests\Feature;

use App\Enums\RoleType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokenExpirationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'PermissionSeeder']);
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
    }

    public function test_token_is_rejected_after_expiration(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleType::Admin->value);

        // Token dibuat langsung: login API memakai Auth::attempt (guard web), dan
        // di test session itu terbawa ke request berikutnya sehingga menutupi
        // pengecekan token.
        $token = $user->createToken('api-token')->plainTextToken;

        $this->withToken($token)->getJson('/api/auth/me')->assertOk();

        $this->travel(config('sanctum.expiration') + 1)->minutes();
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
    }
}

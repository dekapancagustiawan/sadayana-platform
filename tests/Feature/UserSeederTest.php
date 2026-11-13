<?php

namespace Tests\Feature;

use App\Enums\RoleType;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'PermissionSeeder']);
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
    }

    public function test_non_production_seeds_both_demo_accounts(): void
    {
        $this->seed(UserSeeder::class);

        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('password', $admin->password));
        $this->assertTrue($admin->hasRole(RoleType::Admin->value));
        $this->assertDatabaseHas('users', ['email' => 'user@example.com']);
    }

    public function test_production_creates_only_admin_with_random_password(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('db:seed', ['--class' => UserSeeder::class, '--force' => true])
            ->expectsOutputToContain('Password (catat sekarang')
            ->assertSuccessful();

        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $this->assertFalse(Hash::check('password', $admin->password));
        $this->assertTrue($admin->hasRole(RoleType::Admin->value));
        $this->assertDatabaseMissing('users', ['email' => 'user@example.com']);
    }

    public function test_production_uses_configured_admin_credentials(): void
    {
        $this->app['env'] = 'production';
        config([
            'app.seed_admin.email' => 'owner@company.test',
            'app.seed_admin.password' => 'S3cure-Passw0rd!',
        ]);

        $this->artisan('db:seed', ['--class' => UserSeeder::class, '--force' => true])
            ->doesntExpectOutputToContain('Password (catat sekarang')
            ->assertSuccessful();

        $admin = User::where('email', 'owner@company.test')->firstOrFail();
        $this->assertTrue(Hash::check('S3cure-Passw0rd!', $admin->password));
        $this->assertDatabaseMissing('users', ['email' => 'admin@example.com']);
    }

    public function test_reseeding_does_not_touch_existing_admin(): void
    {
        $this->app['env'] = 'production';
        $existing = User::factory()->create(['email' => 'admin@example.com', 'password' => Hash::make('kept-password')]);

        $this->artisan('db:seed', ['--class' => UserSeeder::class, '--force' => true])
            ->doesntExpectOutputToContain('Password (catat sekarang')
            ->assertSuccessful();

        $this->assertTrue(Hash::check('kept-password', $existing->fresh()->password));
    }
}

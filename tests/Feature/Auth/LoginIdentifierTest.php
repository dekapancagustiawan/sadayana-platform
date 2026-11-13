<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-AUTH-001: login dengan email atau nomor HP.
 */
class LoginIdentifierTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'PermissionSeeder']);
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
        $this->artisan('db:seed', ['--class' => 'MenuAccessPermissionsSeeder']);
    }

    private function warga(array $attributes = []): User
    {
        $user = User::factory()->create([
            'email' => null,
            'phone' => '0812-3456-7890',
            ...$attributes,
        ]);
        $user->assignRole('User');

        return $user;
    }

    public function test_phone_is_stored_normalized_and_email_may_be_null(): void
    {
        $user = $this->warga();

        $this->assertNull($user->email);
        $this->assertSame('6281234567890', $user->phone);
    }

    public function test_login_with_phone_in_any_format(): void
    {
        $this->warga();

        $this->post(route('login.submit'), ['login' => '+62 812 3456 7890', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    public function test_login_with_email_is_case_insensitive(): void
    {
        $user = User::factory()->create(['email' => 'Bendahara@Example.com']);
        $user->assignRole('User');

        $this->assertSame('bendahara@example.com', $user->email);

        $this->post(route('login.submit'), ['login' => 'BENDAHARA@example.com', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_wrong_password_shows_generic_error(): void
    {
        $this->warga();

        $this->post(route('login.submit'), ['login' => '081234567890', 'password' => 'salah'])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->warga(['status' => UserStatus::Inactive]);

        $this->post(route('login.submit'), ['login' => '081234567890', 'password' => 'password'])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_api_login_accepts_phone_identifier(): void
    {
        $this->warga();

        $this->postJson('/api/auth/login', ['login' => '081234567890', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.user.phone', '6281234567890')
            ->assertJsonPath('data.user.must_change_password', false);
    }

    public function test_admin_can_create_user_with_phone_only(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Warga Tanpa Email',
            'email' => '',
            'phone' => '0857 1111 2222',
            'password' => 'sementara123',
            'password_confirmation' => 'sementara123',
            'status' => 'active',
            'roles' => ['User'],
        ])->assertRedirect(route('users.index'));

        $created = User::where('phone', '6285711112222')->firstOrFail();
        $this->assertNull($created->email);
        $this->assertTrue($created->must_change_password);
    }

    public function test_duplicate_phone_in_other_format_is_rejected(): void
    {
        $this->warga();
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Duplikat',
            'phone' => '+6281234567890',
            'password' => 'sementara123',
            'password_confirmation' => 'sementara123',
            'status' => 'active',
            'roles' => ['User'],
        ])->assertSessionHasErrors('phone');
    }

    public function test_user_needs_email_or_phone(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Tanpa Identitas',
            'password' => 'sementara123',
            'password_confirmation' => 'sementara123',
            'status' => 'active',
            'roles' => ['User'],
        ])->assertSessionHasErrors(['email', 'phone']);
    }
}

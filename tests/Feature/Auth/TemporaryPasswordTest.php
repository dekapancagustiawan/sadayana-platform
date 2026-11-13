<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * FR-AUTH-003: password sementara wajib diganti.
 */
class TemporaryPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'PermissionSeeder']);
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
        $this->artisan('db:seed', ['--class' => 'MenuAccessPermissionsSeeder']);
    }

    private function temporaryUser(): User
    {
        $user = User::factory()->create(['must_change_password' => true]);
        $user->assignRole('User');

        return $user;
    }

    public function test_login_with_temporary_password_goes_to_change_password(): void
    {
        $user = $this->temporaryUser();

        $this->post(route('login.submit'), ['login' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('account.password.edit'));
    }

    public function test_other_pages_are_blocked_until_password_changed(): void
    {
        $this->actingAs($this->temporaryUser())
            ->get(route('dashboard'))
            ->assertRedirect(route('account.password.edit'));

        $this->get(route('account.password.edit'))
            ->assertOk()
            ->assertSee('password sementara', false);
    }

    public function test_logout_is_still_allowed(): void
    {
        $this->actingAs($this->temporaryUser())
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_changing_password_clears_the_flag(): void
    {
        $user = $this->temporaryUser();

        $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => 'password',
            'password' => 'RahasiaBaru123',
            'password_confirmation' => 'RahasiaBaru123',
        ])->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertNotNull($user->password_changed_at);
        $this->assertTrue(Hash::check('RahasiaBaru123', $user->password));

        $this->get(route('dashboard'))->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.password_changed', 'user_id' => $user->id]);
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $this->actingAs($this->temporaryUser())->put(route('account.password.update'), [
            'current_password' => 'salah',
            'password' => 'RahasiaBaru123',
            'password_confirmation' => 'RahasiaBaru123',
        ])->assertSessionHasErrors('current_password');
    }

    public function test_new_password_must_differ_and_be_strong(): void
    {
        $this->actingAs($this->temporaryUser())->put(route('account.password.update'), [
            'current_password' => 'password',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('password');
    }

    public function test_admin_reset_for_other_user_makes_password_temporary(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $user = User::factory()->create();
        $user->assignRole('User');

        $this->actingAs($admin)->put(route('users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'Sementara123',
            'password_confirmation' => 'Sementara123',
            'status' => 'active',
            'roles' => ['User'],
        ])->assertRedirect(route('users.index'));

        $this->assertTrue($user->fresh()->must_change_password);
    }
}

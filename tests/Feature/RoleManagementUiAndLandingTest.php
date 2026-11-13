<?php

namespace Tests\Feature;

use App\Enums\RoleType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Menjaga dua hal:
 *
 * 1. Form Role Management benar-benar ter-render (pengelompokan permission
 *    dibangun di controller, jadi variabel blade yang salah hanya ketahuan di
 *    browser kalau tidak diuji di sini).
 * 2. Halaman tujuan setelah login ditentukan dari izin, bukan nama role —
 *    termasuk untuk role baru yang tidak dikenal kode.
 */
class RoleManagementUiAndLandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'PermissionSeeder']);
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
        $this->artisan('db:seed', ['--class' => 'MenuAccessPermissionsSeeder']);
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->assignRole($roleName);

        return $user;
    }

    public function test_role_create_form_renders_menu_access_group(): void
    {
        $this->actingAs($this->userWithRole(RoleType::Admin->value))
            ->get(route('roles.create'))
            ->assertOk()
            ->assertSee('Menu Access', false)
            // Label ramah-manusia beserta nama teknisnya sama-sama tampil.
            ->assertSee('Audit Logs')
            ->assertSee('access-audit-logs');
    }

    public function test_role_edit_form_renders_with_current_permissions_checked(): void
    {
        $role = Role::where('name', RoleType::User->value)->firstOrFail();

        $this->actingAs($this->userWithRole(RoleType::Admin->value))
            ->get(route('roles.edit', $role))
            ->assertOk()
            ->assertSee('Menu Access', false)
            ->assertSee('access-dashboard');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function landingExpectations(): iterable
    {
        yield 'Admin' => ['Admin', 'dashboard'];
        yield 'User' => ['User', 'dashboard'];
    }

    #[DataProvider('landingExpectations')]
    public function test_login_lands_on_the_expected_page(string $roleName, string $expectedRoute): void
    {
        $user = $this->userWithRole(RoleType::from($roleName)->value);

        $this->post(route('login.submit'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route($expectedRoute));
    }

    public function test_intended_url_is_honoured_after_login(): void
    {
        $admin = $this->userWithRole(RoleType::Admin->value);

        // Tamu tidak dialihkan diam-diam ke login, tapi tujuannya tetap disimpan.
        $this->get(route('audit-logs.index'))->assertUnauthorized();

        $this->post(route('login.submit'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('audit-logs.index'));
    }

    public function test_new_role_lands_on_a_page_it_is_allowed_to_open(): void
    {
        // Role yang tidak dikenal kode sama sekali, hanya diberi izin dashboard.
        $supervisor = Role::create(['name' => 'Supervisor', 'guard_name' => 'web']);
        $supervisor->givePermissionTo(['access-dashboard']);

        $user = $this->userWithRole('Supervisor');

        $this->post(route('login.submit'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Enums\CommunityRole;
use App\Models\User;
use App\Support\Authorization;
use Database\Seeders\CommunityRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CommunityRoleSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_all_community_roles_and_demo_accounts_exist(): void
    {
        foreach (CommunityRole::cases() as $role) {
            $this->assertTrue(Role::where('name', $role->value)->exists(), $role->value);

            $demo = User::where('email', $role->demoEmail())->firstOrFail();
            $this->assertTrue($demo->hasRole($role->value));
            $this->assertFalse($demo->must_change_password);
        }
    }

    public function test_community_roles_reach_dashboard_but_not_user_management(): void
    {
        foreach (CommunityRole::cases() as $role) {
            $user = User::where('email', $role->demoEmail())->firstOrFail();

            $this->assertTrue(Gate::forUser($user)->allows('access-dashboard'), $role->value);
            $this->assertFalse(Gate::forUser($user)->allows('access-user-management'), $role->value);
            $this->assertFalse(Gate::forUser($user)->allows('users.view'), $role->value);
        }
    }

    public function test_admin_never_receives_segregated_permissions_from_matrix(): void
    {
        $admin = Role::findByName('Admin');

        foreach (array_keys(CommunityRoleSeeder::matrix()) as $permission) {
            $this->assertSame(
                ! Authorization::isSegregated($permission),
                $admin->hasPermissionTo($permission),
                $permission,
            );
        }
    }

    public function test_seeder_is_rerunnable_and_keeps_ui_customisation(): void
    {
        $warga = Role::findByName(CommunityRole::Warga->value);
        $warga->givePermissionTo('users.view');

        $this->seed(CommunityRoleSeeder::class);

        $this->assertTrue($warga->fresh()->hasPermissionTo('users.view'));
    }
}

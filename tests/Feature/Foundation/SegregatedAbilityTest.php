<?php

namespace Tests\Feature\Foundation;

use App\Enums\RoleType;
use App\Models\User;
use App\Support\Authorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * SRS ROLE-001: Super Admin tidak otomatis berwenang atas transaksi keuangan.
 */
class SegregatedAbilityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Authorization::SEGREGATED_ABILITIES as $ability) {
            Permission::firstOrCreate(['name' => $ability, 'guard_name' => 'web']);
        }

        $this->artisan('db:seed', ['--class' => 'PermissionSeeder']);
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole(RoleType::Admin->value);
    }

    public function test_admin_is_denied_segregated_abilities_by_default(): void
    {
        foreach (Authorization::SEGREGATED_ABILITIES as $ability) {
            $this->assertFalse(Gate::forUser($this->admin)->allows($ability), $ability);
        }
    }

    public function test_role_seeder_does_not_grant_segregated_permissions_to_admin(): void
    {
        $adminRole = Role::findByName(RoleType::Admin->value);

        foreach (Authorization::SEGREGATED_ABILITIES as $ability) {
            $this->assertFalse($adminRole->hasPermissionTo($ability), $ability);
        }
    }

    public function test_admin_with_explicit_permission_is_allowed(): void
    {
        $this->admin->givePermissionTo('payments.verify');

        $this->assertTrue(Gate::forUser($this->admin)->allows('payments.verify'));
        $this->assertFalse(Gate::forUser($this->admin)->allows('cash.approve'));
    }

    public function test_admin_still_bypasses_other_abilities(): void
    {
        $this->assertTrue(Gate::forUser($this->admin)->allows('some-unknown-ability'));
    }
}

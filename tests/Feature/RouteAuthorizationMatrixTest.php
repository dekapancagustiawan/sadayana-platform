<?php

namespace Tests\Feature;

use App\Enums\RoleType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Menu yang tersembunyi harus ikut menutup URL-nya.
 *
 * Yang diuji hanya lapisan otorisasi, bukan isi halaman: kasus "boleh" cukup
 * memastikan responsnya BUKAN 403, supaya test ini tidak ikut merah kalau
 * sebuah halaman butuh data yang tidak disiapkan di sini.
 */
class RouteAuthorizationMatrixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Route tanpa parameter → daftar role yang berhak membukanya.
     *
     * Admin tidak dicantumkan karena selalu lolos lewat `Gate::before`; ia
     * tetap diuji lewat provider di bawah.
     *
     * @var array<string, list<string>>
     */
    private const MATRIX = [
        'dashboard' => ['User'],
        'users.index' => [],
        'roles.index' => [],
        'permissions.index' => [],
        'audit-logs.index' => [],
        'admin.notifications.index' => [],
        'settings.index' => [],
        'email-logs.index' => [],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'PermissionSeeder']);
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
        $this->artisan('db:seed', ['--class' => 'MenuAccessPermissionsSeeder']);
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function authorizationMatrix(): iterable
    {
        foreach (self::MATRIX as $routeName => $allowedRoles) {
            foreach (RoleType::cases() as $role) {
                $allowed = $role === RoleType::Admin || in_array($role->value, $allowedRoles, true);
                $verdict = $allowed ? 'boleh' : 'tolak';

                yield "{$role->value} {$verdict} {$routeName}" => [$role->value, $routeName, $allowed];
            }
        }
    }

    #[DataProvider('authorizationMatrix')]
    public function test_route_authorization_matches_the_menu(string $roleName, string $routeName, bool $allowed): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleType::from($roleName)->value);

        $response = $this->actingAs($user)->get(route($routeName));

        if ($allowed) {
            $this->assertNotSame(
                403,
                $response->getStatusCode(),
                "Role '{$roleName}' seharusnya boleh membuka '{$routeName}' tapi ditolak 403."
            );

            return;
        }

        $response->assertForbidden();
    }
}

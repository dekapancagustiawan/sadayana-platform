<?php

namespace Tests\Feature;

use App\Enums\RoleType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleMenuAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Baseline perilaku seluruh gate `access-*` per role.
     *
     * Tabel ini adalah KONTRAK dengan MenuAccessPermissionsSeeder: saat menambah
     * menu baru, tambahkan gate-nya di seeder DAN di sini.
     *
     * Admin selalu true lewat `Gate::before` (superuser), bukan karena
     * terdaftar satu per satu.
     *
     * @var array<string, array<string, bool>>
     */
    private const MENU_ACCESS_BASELINE = [
        'access-dashboard' => ['Admin' => true, 'User' => true],
        'access-notifications' => ['Admin' => true, 'User' => false],
        'access-houses' => ['Admin' => true, 'User' => false],
        'access-officer-assignments' => ['Admin' => true, 'User' => false],
        'access-my-house' => ['Admin' => true, 'User' => false],
        'access-water' => ['Admin' => true, 'User' => false],
        'access-meter-recording' => ['Admin' => true, 'User' => false],
        'access-user-management' => ['Admin' => true, 'User' => false],
        'access-audit-logs' => ['Admin' => true, 'User' => false],
        'access-settings' => ['Admin' => true, 'User' => false],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (RoleType::cases() as $role) {
            Role::firstOrCreate(['name' => $role->value, 'guard_name' => 'web']);
        }

        // Sumber kebenaran gate `access-*` adalah permission di database, jadi
        // seeder ini wajib jalan setelah role-nya ada.
        $this->artisan('db:seed', ['--class' => 'MenuAccessPermissionsSeeder']);
    }

    /**
     * Seluruh kombinasi gate × role diuji satu per satu supaya kegagalan
     * langsung menunjuk pasangan yang bergeser.
     *
     * @return iterable<string, array{string, string, bool}>
     */
    public static function menuAccessMatrix(): iterable
    {
        foreach (self::MENU_ACCESS_BASELINE as $gate => $expectations) {
            foreach ($expectations as $roleName => $expected) {
                yield "{$roleName} → {$gate}" => [$roleName, $gate, $expected];
            }
        }
    }

    #[DataProvider('menuAccessMatrix')]
    public function test_menu_gate_matches_baseline(string $roleName, string $gate, bool $expected): void
    {
        // `from()` sekalian memastikan nama role di tabel baseline memang ada
        // di enum — salah ketik langsung gagal, bukan diam-diam lolos.
        $role = RoleType::from($roleName);

        $user = User::factory()->create();
        $user->assignRole($role->value);

        $this->actingAs($user);

        $this->assertSame(
            $expected,
            Gate::allows($gate),
            "Gate '{$gate}' untuk role '{$role->value}' seharusnya ".($expected ? 'true' : 'false').'.'
        );
    }

    /**
     * Setiap gate yang dipakai memagari menu sidebar harus terdaftar di tabel
     * baseline. Menu baru yang lupa didaftarkan akan ketahuan di sini, bukan
     * setelah role baru dibuat dan menunya tidak muncul.
     */
    public function test_every_menu_gate_is_covered_by_the_baseline(): void
    {
        $menuGates = [];

        $collect = function (array $items) use (&$collect, &$menuGates): void {
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                foreach ((array) ($item['can'] ?? []) as $gate) {
                    if (is_string($gate) && str_starts_with($gate, 'access-')) {
                        $menuGates[$gate] = true;
                    }
                }

                if (isset($item['submenu']) && is_array($item['submenu'])) {
                    $collect($item['submenu']);
                }
            }
        };

        $collect(config('adminlte.menu', []));

        $this->assertNotEmpty($menuGates, 'Tidak ada gate access-* terbaca dari config/adminlte.php.');

        $uncovered = array_diff(array_keys($menuGates), array_keys(self::MENU_ACCESS_BASELINE));

        $this->assertSame(
            [],
            array_values($uncovered),
            'Gate menu berikut belum ada di MENU_ACCESS_BASELINE: '.implode(', ', $uncovered)
        );
    }

    public function test_admin_passes_every_gate_even_unknown_ones(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleType::Admin->value);

        $this->actingAs($admin);
        $this->assertTrue(Gate::allows('access-user-management'));
        $this->assertTrue(Gate::allows('access-something-not-seeded'));
    }

    public function test_unknown_gate_is_denied_for_regular_user(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleType::User->value);

        $this->actingAs($user);
        $this->assertFalse(Gate::allows('access-something-not-seeded'));
    }
}

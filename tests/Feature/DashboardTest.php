<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Enums\RoleType;
use App\Models\DashboardNotification;
use App\Models\User;
use App\Services\DashboardNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'PermissionSeeder']);
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
        $this->artisan('db:seed', ['--class' => 'MenuAccessPermissionsSeeder']);
    }

    private function userWithRole(RoleType $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    public function test_admin_sees_stats_activity_and_notification_bell(): void
    {
        $this->actingAs($this->userWithRole(RoleType::Admin))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Total User')
            ->assertSee('Aktivitas Terbaru')
            ->assertSee('notification-bell', false);
    }

    public function test_regular_user_sees_only_the_welcome_card(): void
    {
        $user = $this->userWithRole(RoleType::User);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($user->name)
            ->assertDontSee('Total User')
            ->assertDontSee('Aktivitas Terbaru')
            ->assertDontSee('notification-bell', false);
    }

    public function test_notify_helper_creates_an_unread_notification_with_url(): void
    {
        $target = User::factory()->create();

        $notification = app(DashboardNotificationService::class)->notify(
            NotificationType::INFO,
            'Judul',
            'Pesan',
            'https://example.test/x',
            $target,
        );

        $this->assertFalse($notification->is_read);
        $this->assertSame(User::class, $notification->reference_type);
        $this->assertSame((string) $target->id, (string) $notification->reference_id);

        $this->actingAs($this->userWithRole(RoleType::Admin))
            ->getJson(route('admin.notifications.index'))
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('notifications.0.reference_url', 'https://example.test/x');
    }

    public function test_creating_a_user_pushes_a_notification(): void
    {
        $user = $this->userWithRole(RoleType::User);

        $this->actingAs($this->userWithRole(RoleType::Admin))
            ->post(route('users.store'), [
                'name' => 'Budi',
                'email' => 'budi@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'roles' => [RoleType::User->value],
                'status' => 'active',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('dashboard_notifications', [
            'type' => NotificationType::USER_CREATED->value,
        ]);
        $this->assertSame(1, DashboardNotification::count());
    }

    public function test_health_endpoint_reports_ok(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('db', 'connected');
    }
}

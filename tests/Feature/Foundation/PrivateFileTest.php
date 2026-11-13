<?php

namespace Tests\Feature\Foundation;

use App\Enums\RoleType;
use App\Models\StoredFile;
use App\Models\User;
use App\Services\Files\FileService;
use App\Support\FileAccessRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PrivateFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
    }

    private function storeAs(User $user, string $category = 'meter_photo'): StoredFile
    {
        $this->actingAs($user);

        return app(FileService::class)->store(UploadedFile::fake()->image('meter.jpg'), $category);
    }

    public function test_file_is_stored_privately_with_metadata(): void
    {
        $user = User::factory()->create();
        $file = $this->storeAs($user);

        Storage::disk('private')->assertExists($file->storage_path);
        $this->assertSame($user->id, $file->uploaded_by);
        $this->assertSame('image/jpeg', $file->mime_type);
        $this->assertNotNull($file->community_id);
        $this->assertSame(64, strlen($file->checksum));
    }

    public function test_uploader_can_view_file(): void
    {
        $user = User::factory()->create();
        $file = $this->storeAs($user);

        $this->actingAs($user)->get(route('files.show', $file))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_other_user_cannot_view_file(): void
    {
        $file = $this->storeAs(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->get(route('files.show', $file))
            ->assertForbidden();
    }

    public function test_guest_cannot_view_file(): void
    {
        $file = $this->storeAs(User::factory()->create());
        auth()->logout();

        $response = $this->get(route('files.show', $file));

        $this->assertContains($response->status(), [401, 302]);
    }

    public function test_registered_category_rule_grants_access(): void
    {
        $file = $this->storeAs(User::factory()->create());
        $viewer = User::factory()->create(['name' => 'Bendahara Test']);

        app(FileAccessRegistry::class)->register(
            'meter_photo',
            fn (User $user, StoredFile $f) => $user->name === 'Bendahara Test',
        );

        $this->actingAs($viewer)->get(route('files.show', $file))->assertOk();
    }

    public function test_admin_can_view_any_file(): void
    {
        Role::firstOrCreate(['name' => RoleType::Admin->value, 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole(RoleType::Admin->value);

        $file = $this->storeAs(User::factory()->create());

        $this->actingAs($admin)->get(route('files.show', $file))->assertOk();
    }

    public function test_svg_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());

        $svg = UploadedFile::fake()->createWithContent(
            'evil.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
        );

        $this->expectException(RuntimeException::class);

        app(FileService::class)->store($svg, 'meter_photo');
    }
}

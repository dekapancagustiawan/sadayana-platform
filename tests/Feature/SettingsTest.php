<?php

namespace Tests\Feature;

use App\Enums\RoleType;
use App\Mail\TestMail;
use App\Models\AuditLog;
use App\Models\EmailLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\Mail\MailService;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'PermissionSeeder']);
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
        $this->artisan('db:seed', ['--class' => 'MenuAccessPermissionsSeeder']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole(RoleType::Admin->value);
    }

    /**
     * @return array<string, mixed>
     */
    private function mailPayload(array $overrides = []): array
    {
        return array_merge([
            'mail_mailer' => 'smtp',
            'mail_host' => 'smtp.example.com',
            'mail_port' => 587,
            'mail_scheme' => 'smtp',
            'mail_username' => 'mailer@example.com',
            'mail_password' => 'rahasia-smtp',
            'mail_from_address' => 'noreply@example.com',
            'mail_from_name' => 'Aplikasi Contoh',
        ], $overrides);
    }

    public function test_settings_page_renders_both_tabs(): void
    {
        $this->actingAs($this->admin)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Nama Aplikasi')
            ->assertSee('Host SMTP')
            ->assertSee('Kirim Email Test');
    }

    public function test_general_settings_are_saved_and_override_app_name(): void
    {
        $this->actingAs($this->admin)
            ->put(route('settings.general.update'), [
                'app_name' => 'Toko Maju',
                'contact_email' => 'halo@tokomaju.test',
                'contact_whatsapp' => '6281234567890',
                'social_instagram' => 'https://instagram.com/tokomaju',
            ])
            ->assertRedirect(route('settings.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('Toko Maju', Setting::get('app_name'));
        $this->assertSame('6281234567890', Setting::get('contact_whatsapp'));

        app(SettingsService::class)->applyToConfig();
        $this->assertSame('Toko Maju', config('app.name'));
    }

    public function test_general_settings_validation(): void
    {
        $this->actingAs($this->admin)
            ->put(route('settings.general.update'), [
                'app_name' => '',
                'contact_whatsapp' => '+62 812',
                'social_instagram' => 'http://insecure.test',
            ])
            ->assertSessionHasErrors(['app_name', 'contact_whatsapp', 'social_instagram']);
    }

    public function test_logo_is_stored_on_public_disk_and_replaced(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)->put(route('settings.general.update'), [
            'app_name' => 'App',
            'app_logo' => UploadedFile::fake()->image('logo.png', 200, 60),
        ])->assertSessionHasNoErrors();

        $first = Setting::get('app_logo');
        Storage::disk('public')->assertExists($first);

        $this->actingAs($this->admin)->put(route('settings.general.update'), [
            'app_name' => 'App',
            'app_logo' => UploadedFile::fake()->image('logo2.png', 200, 60),
        ])->assertSessionHasNoErrors();

        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists(Setting::get('app_logo'));

        $this->actingAs($this->admin)->put(route('settings.general.update'), [
            'app_name' => 'App',
            'remove_logo' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertNull(Setting::get('app_logo'));
    }

    public function test_svg_logo_is_rejected(): void
    {
        Storage::fake('public');

        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $this->actingAs($this->admin)
            ->put(route('settings.general.update'), ['app_name' => 'App', 'app_logo' => $svg])
            ->assertSessionHasErrors('app_logo');
    }

    public function test_mail_password_is_encrypted_and_applied_to_config(): void
    {
        $this->actingAs($this->admin)
            ->put(route('settings.mail.update'), $this->mailPayload())
            ->assertSessionHasNoErrors();

        $stored = Setting::get('mail_password');
        $this->assertNotSame('rahasia-smtp', $stored);
        $this->assertSame('rahasia-smtp', Crypt::decryptString($stored));

        app(SettingsService::class)->applyToConfig();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.example.com', config('mail.mailers.smtp.host'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
        $this->assertSame('rahasia-smtp', config('mail.mailers.smtp.password'));
        $this->assertSame('noreply@example.com', config('mail.from.address'));
    }

    public function test_blank_mail_password_keeps_the_previous_one(): void
    {
        $this->actingAs($this->admin)->put(route('settings.mail.update'), $this->mailPayload());
        $this->actingAs($this->admin)->put(route('settings.mail.update'), $this->mailPayload([
            'mail_password' => '',
            'mail_host' => 'smtp2.example.com',
        ]));

        $this->assertSame('rahasia-smtp', Crypt::decryptString(Setting::get('mail_password')));
        $this->assertSame('smtp2.example.com', Setting::get('mail_host'));
    }

    public function test_password_is_never_rendered_back_to_the_form(): void
    {
        $this->actingAs($this->admin)->put(route('settings.mail.update'), $this->mailPayload());

        $this->actingAs($this->admin)
            ->get(route('settings.index', ['tab' => 'mail']))
            ->assertOk()
            ->assertDontSee('rahasia-smtp')
            ->assertSee('(tersimpan)');
    }

    public function test_smtp_host_is_required_only_for_smtp_mailer(): void
    {
        $this->actingAs($this->admin)
            ->put(route('settings.mail.update'), $this->mailPayload(['mail_host' => '']))
            ->assertSessionHasErrors('mail_host');

        $this->actingAs($this->admin)
            ->put(route('settings.mail.update'), $this->mailPayload(['mail_mailer' => 'log', 'mail_host' => '']))
            ->assertSessionHasNoErrors();
    }

    public function test_test_email_is_sent_and_logged(): void
    {
        Mail::fake();

        $this->actingAs($this->admin)
            ->post(route('settings.mail.test'), ['test_recipient' => 'cek@example.com'])
            ->assertRedirect(route('settings.index', ['tab' => 'mail']))
            ->assertSessionHas('success');

        Mail::assertSent(TestMail::class, fn ($mail) => $mail->hasTo('cek@example.com'));

        $this->assertDatabaseHas('email_logs', [
            'recipient_email' => 'cek@example.com',
            'email_type' => 'test',
            'status' => 'sent',
        ]);
    }

    public function test_mail_failure_is_logged_not_thrown(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Connection refused'));

        $log = app(MailService::class)->send('x@example.com', new TestMail('Admin'), 'test');

        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('Connection refused', $log->error_message);
        $this->assertSame(1, EmailLog::where('status', 'failed')->count());
    }

    public function test_email_log_page_filters_by_status(): void
    {
        EmailLog::create(['recipient_email' => 'ok@example.com', 'subject' => 'A', 'email_type' => 'test', 'status' => 'sent']);
        EmailLog::create(['recipient_email' => 'bad@example.com', 'subject' => 'B', 'email_type' => 'test', 'status' => 'failed', 'error_message' => 'boom']);

        $this->actingAs($this->admin)
            ->get(route('email-logs.index', ['status' => 'failed']))
            ->assertOk()
            ->assertSee('bad@example.com')
            ->assertDontSee('ok@example.com');
    }

    public function test_settings_changes_are_audited_without_secrets(): void
    {
        $this->actingAs($this->admin)->put(route('settings.mail.update'), $this->mailPayload());

        $log = AuditLog::where('action', 'settings.mail.update')->firstOrFail();

        $this->assertTrue($log->payload['password_changed']);
        $this->assertStringNotContainsString('rahasia-smtp', json_encode($log->payload));
    }
}

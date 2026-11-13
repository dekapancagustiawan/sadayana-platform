<?php

namespace Tests\Feature;

use Anhskohbo\NoCaptcha\Facades\NoCaptcha;
use App\Enums\RoleType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginCaptchaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'PermissionSeeder']);
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
        $this->artisan('db:seed', ['--class' => 'MenuAccessPermissionsSeeder']);
    }

    private function enableCaptcha(): void
    {
        config([
            'captcha.enabled' => true,
            'captcha.sitekey' => 'test-site-key',
            'captcha.secret' => 'test-secret',
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleType::Admin->value);

        return $user;
    }

    public function test_login_works_without_captcha_when_keys_are_missing(): void
    {
        config(['captcha.sitekey' => null, 'captcha.secret' => null]);

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('recaptcha/api.js', false);

        $this->post(route('login.submit'), [
            'login' => $this->admin()->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));
    }

    public function test_login_form_renders_captcha_when_enabled(): void
    {
        $this->enableCaptcha();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('recaptcha/api.js?render=test-site-key', false);
    }

    /**
     * Celah lama: aturan captcha hanya dipasang kalau field-nya dikirim,
     * sehingga bot cukup tidak mengirim field itu.
     */
    public function test_omitting_the_captcha_field_is_rejected_when_enabled(): void
    {
        $this->enableCaptcha();

        $this->from(route('login'))
            ->post(route('login.submit'), [
                'login' => $this->admin()->email,
                'password' => 'password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('g-recaptcha-response');

        $this->assertGuest();
    }

    public function test_failed_captcha_shows_a_readable_message_on_the_login_form(): void
    {
        $this->enableCaptcha();
        NoCaptcha::shouldReceive('verifyResponse')->once()->andReturn(false);
        NoCaptcha::shouldReceive('renderJs')->andReturn('');

        $this->followingRedirects()
            ->from(route('login'))
            ->post(route('login.submit'), [
                'login' => $this->admin()->email,
                'password' => 'password',
                'g-recaptcha-response' => 'invalid-token',
            ])
            ->assertOk()
            ->assertSee('Captcha verification failed. Please try again.')
            ->assertDontSee('validation.captcha');

        $this->assertGuest();
    }

    public function test_valid_captcha_token_allows_login(): void
    {
        $this->enableCaptcha();
        NoCaptcha::shouldReceive('verifyResponse')->once()->andReturn(true);

        $this->post(route('login.submit'), [
            'login' => $this->admin()->email,
            'password' => 'password',
            'g-recaptcha-response' => 'valid-token',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    public function test_successful_login_records_last_login_at(): void
    {
        $admin = $this->admin();
        $this->assertNull($admin->last_login_at);

        $this->post(route('login.submit'), [
            'login' => $admin->email,
            'password' => 'password',
        ]);

        $this->assertNotNull($admin->fresh()->last_login_at);
    }

    public function test_login_path_follows_config(): void
    {
        $this->assertSame(url(config('app.login_path')), route('login'));
    }
}

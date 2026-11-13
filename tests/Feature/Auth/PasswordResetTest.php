<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * FR-AUTH-003: reset password lewat verifikasi email.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_reset_routes_live_under_the_login_path(): void
    {
        $prefix = url(config('app.login_path'));

        $this->assertStringStartsWith($prefix.'/', route('password.request'));
        $this->assertStringStartsWith($prefix.'/', route('password.reset', 'token'));
    }

    public function test_forgot_password_page_renders(): void
    {
        $this->get(route('password.request'))->assertOk()->assertSee('Lupa password');
    }

    public function test_reset_link_is_emailed_and_logged(): void
    {
        $user = User::factory()->create(['email' => 'warga@example.com']);

        $this->post(route('password.email'), ['email' => 'WARGA@example.com'])
            ->assertSessionHas('status');

        Mail::assertSent(ResetPasswordMail::class, fn (ResetPasswordMail $mail) => $mail->hasTo('warga@example.com')
            && str_contains($mail->resetUrl, '/reset-password/'));
        $this->assertDatabaseHas('email_logs', ['recipient_email' => 'warga@example.com', 'email_type' => 'password_reset', 'status' => 'sent']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.password_reset_requested', 'user_id' => $user->id]);
    }

    public function test_unknown_or_inactive_email_gets_same_response_without_mail(): void
    {
        User::factory()->create(['email' => 'nonaktif@example.com', 'status' => UserStatus::Inactive]);

        foreach (['tidakada@example.com', 'nonaktif@example.com'] as $email) {
            $this->post(route('password.email'), ['email' => $email])->assertSessionHas('status');
        }

        Mail::assertNothingSent();
    }

    public function test_valid_token_resets_password_and_clears_temporary_flag(): void
    {
        $user = User::factory()->create(['email' => 'warga@example.com', 'must_change_password' => true]);
        $token = Password::broker()->createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'warga@example.com',
            'password' => 'PasswordBaru123',
            'password_confirmation' => 'PasswordBaru123',
        ])->assertRedirect(route('login'));

        $user->refresh();
        $this->assertTrue(Hash::check('PasswordBaru123', $user->password));
        $this->assertFalse($user->must_change_password);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.password_reset', 'user_id' => $user->id]);
    }

    public function test_invalid_token_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'warga@example.com']);

        $this->post(route('password.update'), [
            'token' => 'token-palsu',
            'email' => 'warga@example.com',
            'password' => 'PasswordBaru123',
            'password_confirmation' => 'PasswordBaru123',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}

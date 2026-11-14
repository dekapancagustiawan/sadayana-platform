<?php

namespace Tests\Feature;

use App\Mail\ContactMessageMail;
use App\Models\DashboardNotification;
use App\Models\EmailLog;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function contactPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Andi',
            'email' => 'andi@example.com',
            'phone' => '0812-3456-7890',
            'message' => 'Halo, saya ingin bertanya soal layanan Anda.',
        ], $overrides);
    }

    public function test_sadayana_brand_is_the_default_without_uploaded_logo(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(asset('images/sadayana-mark.png'), false)
            ->assertSee(asset('images/sadayana-logo.png'), false)
            ->assertSee('Silih Asih, Silih Asah, Silih Asuh');
    }

    public function test_landing_renders_all_default_sections(): void
    {
        $response = $this->get('/')->assertOk();

        foreach (['features', 'workflow', 'about', 'roles', 'faq', 'contact'] as $section) {
            $response->assertSee('id="'.$section.'"', false);
        }
    }

    public function test_landing_uses_identity_from_settings(): void
    {
        Setting::set('app_name', 'Toko Maju');
        Setting::set('contact_whatsapp', '6281234567890');
        Setting::set('contact_address', 'Jl. Merdeka 1, Jakarta');
        Setting::set('social_instagram', 'https://instagram.com/tokomaju');

        $this->get('/')
            ->assertOk()
            ->assertSee('<title>Toko Maju', false)
            ->assertSee('https://wa.me/6281234567890', false)
            ->assertSee('Jl. Merdeka 1, Jakarta')
            ->assertSee('https://instagram.com/tokomaju', false);
    }

    public function test_landing_never_links_to_the_staff_login(): void
    {
        $this->get('/')->assertOk()->assertDontSee(route('login'), false);
    }

    public function test_disabled_and_unknown_sections_are_skipped(): void
    {
        config(['landing.sections' => ['hero', 'faq', 'does-not-exist']]);

        $this->get('/')
            ->assertOk()
            ->assertSee('id="faq"', false)
            ->assertDontSee('id="features"', false)
            ->assertDontSee('id="contact"', false)
            // Tanpa section kontak, tombol CTA ke #contact ikut hilang.
            ->assertDontSee('href="#contact"', false);
    }

    public function test_landing_switches_to_english(): void
    {
        $this->get(route('lang.switch', 'en'));

        $this->get('/')
            ->assertOk()
            ->assertSee('Frequently asked questions')
            ->assertSee('<html lang="en"', false);
    }

    public function test_contact_message_is_emailed_to_the_contact_address_and_logged(): void
    {
        Mail::fake();
        Setting::set('contact_email', 'owner@example.com');

        $this->post(route('public.contact.store'), $this->contactPayload())
            ->assertRedirect(route('public.home').'#contact')
            ->assertSessionHas('contact_status', 'sent');

        Mail::assertSent(ContactMessageMail::class, function (ContactMessageMail $mail) {
            return $mail->hasTo('owner@example.com') && $mail->hasReplyTo('andi@example.com');
        });

        $this->assertDatabaseHas('email_logs', [
            'recipient_email' => 'owner@example.com',
            'email_type' => 'contact_form',
            'status' => 'sent',
        ]);
        $this->assertSame(1, DashboardNotification::count());
    }

    public function test_contact_falls_back_to_mail_from_address(): void
    {
        Mail::fake();
        config(['mail.from.address' => 'hello@example.com']);

        $this->post(route('public.contact.store'), $this->contactPayload());

        Mail::assertSent(ContactMessageMail::class, fn ($mail) => $mail->hasTo('hello@example.com'));
    }

    public function test_contact_validation_redirects_back_to_the_form(): void
    {
        $this->post(route('public.contact.store'), $this->contactPayload(['email' => 'bukan-email', 'message' => 'pendek']))
            ->assertRedirect(route('public.home').'#contact')
            ->assertSessionHasErrors(['email', 'message']);
    }

    public function test_honeypot_submission_is_silently_dropped(): void
    {
        Mail::fake();

        $this->post(route('public.contact.store'), $this->contactPayload(['website' => 'http://spam.test']))
            ->assertSessionHas('contact_status', 'sent');

        Mail::assertNothingSent();
        $this->assertSame(0, EmailLog::count());
        $this->assertSame(0, DashboardNotification::count());
    }

    public function test_contact_requires_captcha_when_enabled(): void
    {
        config([
            'captcha.enabled' => true,
            'captcha.sitekey' => 'site-key',
            'captcha.secret' => 'secret',
        ]);

        $this->get('/')->assertSee('recaptcha/api.js?render=site-key', false);

        $this->post(route('public.contact.store'), $this->contactPayload())
            ->assertSessionHasErrors('g-recaptcha-response');
    }

    public function test_failed_email_keeps_input_and_still_notifies_admin(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));

        $this->post(route('public.contact.store'), $this->contactPayload())
            ->assertSessionHas('contact_status', 'failed')
            ->assertSessionHasInput('email', 'andi@example.com');

        $this->assertDatabaseHas('email_logs', ['status' => 'failed', 'email_type' => 'contact_form']);
        $this->assertSame(1, DashboardNotification::count());
    }

    public function test_contact_form_is_rate_limited(): void
    {
        Mail::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('public.contact.store'), $this->contactPayload());
        }

        $this->post(route('public.contact.store'), $this->contactPayload())->assertTooManyRequests();
    }
}

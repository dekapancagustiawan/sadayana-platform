<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateGeneralSettingsRequest;
use App\Http\Requests\Settings\UpdateMailSettingsRequest;
use App\Mail\TestMail;
use App\Services\Mail\MailService;
use App\Services\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function __construct(
        protected SettingsService $settings,
        protected MailService $mail,
    ) {}

    /**
     * GET /settings
     */
    public function index(): View
    {
        return view('settings.index', [
            'settings' => $this->settings->values(),
            'mailers' => SettingsService::MAILERS,
        ]);
    }

    /**
     * PUT /settings/general
     */
    public function updateGeneral(UpdateGeneralSettingsRequest $request): RedirectResponse
    {
        try {
            $this->settings->updateGeneral(
                $request->safe()->except(['app_logo', 'remove_logo']),
                $request->file('app_logo'),
                $request->boolean('remove_logo'),
            );
        } catch (\RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('settings.index')->with('success', 'Pengaturan umum disimpan.');
    }

    /**
     * PUT /settings/mail
     */
    public function updateMail(UpdateMailSettingsRequest $request): RedirectResponse
    {
        $this->settings->updateMail($request->validated());

        return redirect()->route('settings.index', ['tab' => 'mail'])->with('success', 'Pengaturan email disimpan.');
    }

    /**
     * POST /settings/mail/test
     */
    public function sendTestEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'test_recipient' => ['required', 'email', 'max:255'],
        ]);

        $log = $this->mail->send(
            $validated['test_recipient'],
            new TestMail($request->user()->name),
            'test',
        );

        return redirect()->route('settings.index', ['tab' => 'mail'])->with(
            $log->status === 'sent' ? 'success' : 'error',
            $log->status === 'sent'
                ? "Email test dikirim ke {$log->recipient_email}."
                : 'Email test gagal dikirim: '.$log->error_message,
        );
    }
}

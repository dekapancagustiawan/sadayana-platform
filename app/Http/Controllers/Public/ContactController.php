<?php

namespace App\Http\Controllers\Public;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactMessageRequest;
use App\Mail\ContactMessageMail;
use App\Services\DashboardNotificationService;
use App\Services\Mail\MailService;
use App\Services\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class ContactController extends Controller
{
    public function __construct(
        protected MailService $mail,
        protected SettingsService $settings,
        protected DashboardNotificationService $notifications,
    ) {}

    /**
     * POST /contact
     */
    public function store(StoreContactMessageRequest $request): RedirectResponse
    {
        $back = redirect()->to(route('public.home').'#contact');

        // Bot yang mengisi honeypot mendapat respons sukses yang sama persis,
        // supaya tidak tahu pesannya dibuang.
        if ($request->isSpam()) {
            return $back->with('contact_status', 'sent');
        }

        $contact = $request->safe()->only(['name', 'email', 'phone', 'message']);
        $contact['phone'] ??= null;

        $recipient = $this->settings->contactRecipient();
        $log = $recipient
            ? $this->mail->send($recipient, new ContactMessageMail($contact), 'contact_form')
            : null;

        // Notifikasi tetap dibuat meski email gagal, supaya pesan tidak hilang
        // tanpa jejak sama sekali.
        $this->notifications->notify(
            NotificationType::INFO,
            'Pesan kontak baru',
            "{$contact['name']} ({$contact['email']}): ".Str::limit($contact['message'], 120),
            $recipient ? 'mailto:'.$contact['email'] : null,
        );

        if (! $log || $log->status !== 'sent') {
            return $back->withInput()->with('contact_status', 'failed');
        }

        return $back->with('contact_status', 'sent');
    }
}

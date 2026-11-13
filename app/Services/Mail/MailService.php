<?php

namespace App\Services\Mail;

use App\Models\EmailLog;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Satu pintu untuk mengirim email aplikasi.
 *
 * Setiap pengiriman — berhasil maupun gagal — dicatat di `email_logs`, jadi
 * admin bisa melihat email mana yang tidak sampai tanpa membuka log server.
 * Kegagalan SMTP tidak dilempar ke pemanggil; cek nilai kembaliannya.
 *
 *   app(MailService::class)->send('a@b.com', new SomeMail(...), 'contact_form');
 */
class MailService
{
    public function send(string $to, Mailable $mailable, string $type): EmailLog
    {
        $subject = $this->subjectOf($mailable);

        try {
            Mail::to($to)->send($mailable);
        } catch (\Throwable $e) {
            Log::warning('Email gagal dikirim', [
                'to' => $to,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return $this->log($to, $subject, $type, 'failed', $e->getMessage());
        }

        return $this->log($to, $subject, $type, 'sent');
    }

    private function log(string $to, string $subject, string $type, string $status, ?string $error = null): EmailLog
    {
        return EmailLog::create([
            'recipient_email' => $to,
            'subject' => $subject,
            'email_type' => $type,
            'status' => $status,
            // Pesan error SMTP bisa sangat panjang (transkrip percakapan server).
            'error_message' => $error ? mb_substr($error, 0, 1000) : null,
        ]);
    }

    private function subjectOf(Mailable $mailable): string
    {
        if ($mailable->subject) {
            return $mailable->subject;
        }

        if (method_exists($mailable, 'envelope')) {
            return (string) ($mailable->envelope()->subject ?? class_basename($mailable));
        }

        return class_basename($mailable);
    }
}

<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ResetPasswordMail extends Mailable
{
    public function __construct(
        public User $user,
        public string $resetUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reset password — '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reset-password',
            with: [
                'expireMinutes' => config('auth.passwords.users.expire', 60),
            ],
        );
    }
}

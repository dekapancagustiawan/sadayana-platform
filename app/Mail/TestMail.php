<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class TestMail extends Mailable
{
    public function __construct(
        public string $sentBy,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Email test — '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.test',
        );
    }
}

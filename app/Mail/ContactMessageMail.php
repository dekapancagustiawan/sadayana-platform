<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ContactMessageMail extends Mailable
{
    /**
     * @param  array{name: string, email: string, phone: ?string, message: string}  $contact
     */
    public function __construct(
        public array $contact,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pesan baru dari '.$this->contact['name'].' — '.config('app.name'),
            // Balas langsung ke pengirim dari klien email admin.
            replyTo: [new Address($this->contact['email'], $this->contact['name'])],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-message',
        );
    }
}

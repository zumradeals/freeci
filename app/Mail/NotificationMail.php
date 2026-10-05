<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Courriel transactionnel générique : une invitation à consulter l'espace connecté, sans contenu privé. */
class NotificationMail extends Mailable
{
    public function __construct(public string $title) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'FreeCI : '.$this->title);
    }

    public function content(): Content
    {
        return new Content(text: 'emails.notification', with: ['title' => $this->title, 'url' => route('notifications.index')]);
    }
}

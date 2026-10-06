<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Lien de vérification d'adresse (signé, 60 minutes). */
class VerifyEmailMail extends Mailable
{
    public function __construct(public string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'FreeCI : confirmez votre adresse e-mail');
    }

    public function content(): Content
    {
        return new Content(text: 'emails.verify');
    }
}

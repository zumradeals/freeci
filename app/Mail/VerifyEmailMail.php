<?php

namespace App\Mail;

use App\Modules\Admin\Settings\AppSettings;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Lien de vérification d'adresse (signé, 60 minutes). */
class VerifyEmailMail extends Mailable
{
    public function __construct(public string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: AppSettings::text('mailtpl.subject_prefix').' : confirmez votre adresse e-mail');
    }

    public function content(): Content
    {
        return new Content(text: 'emails.verify');
    }
}

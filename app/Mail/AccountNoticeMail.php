<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Avis de sécurité sur le compte (changement d'adresse, fermeture) : texte seul, jamais de mot de passe ni de secret. */
class AccountNoticeMail extends Mailable
{
    /** @param list<string> $lines */
    public function __construct(public string $subjectLine, public array $lines, public ?string $url = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'FreeCI : '.$this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(text: 'emails.account-notice');
    }
}

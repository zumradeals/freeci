<?php

namespace App\Modules\Notifications\Jobs;

use App\Mail\NotificationMail;
use App\Modules\Notifications\Models\AppNotification;
use App\Modules\Notifications\Support\MailStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envoi du courriel d'une notification, en file, avec reprises espacées. L'état est tenu sur la notification : « envoyé » signifie
 * UNIQUEMENT que le serveur de courrier configuré a accepté le message ; « échec » après les reprises ; « indisponible » si le courrier
 * n'est pas configuré. Le courriel ne contient jamais le texte d'un message ni de pièce jointe. Aucun contenu ni secret n'est journalisé.
 */
class SendNotificationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public int $notificationId)
    {
        $this->afterCommit = true;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(): void
    {
        $n = AppNotification::query()->with('user')->find($this->notificationId);
        if ($n === null || $n->email_state === 'sent') {
            return;
        }
        if (! MailStatus::deliverable()) {
            $n->forceFill(['email_state' => 'unavailable', 'updated_at' => now()])->save();      // jamais présenté comme envoyé

            return;
        }
        if ($n->read_at !== null) {
            $n->forceFill(['email_state' => 'none', 'updated_at' => now()])->save();            // déjà lue dans l'application : rien à envoyer

            return;
        }
        $n->forceFill(['email_attempts' => $n->email_attempts + 1])->save();
        Mail::to($n->user->email)->send(new NotificationMail($n->title));
        $n->forceFill(['email_state' => 'sent', 'email_sent_at' => now(), 'email_error' => null, 'updated_at' => now()])->save();
    }

    public function failed(Throwable $e): void
    {
        AppNotification::query()->whereKey($this->notificationId)->update(['email_state' => 'failed', 'email_error' => 'transport_error', 'updated_at' => now()]);
    }
}

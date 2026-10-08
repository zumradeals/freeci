<?php

namespace App\Console\Commands;

use App\Modules\Accounts\Support\AccountMail;
use App\Modules\Notifications\Support\MailStatus;
use Illuminate\Console\Command;

class MailTest extends Command
{
    protected $signature = 'freeci:mail:test {to : Adresse qui doit recevoir le message d\'essai}';

    protected $description = 'Envoie un courriel d\'essai avec la configuration réelle (refuse les pilotes « log » et « array », qui n\'envoient rien) et affiche l\'état du courrier.';

    public function handle(): int
    {
        $to = (string) $this->argument('to');
        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('Adresse invalide.');

            return self::INVALID;
        }
        $this->line('Pilote : '.config('mail.default').' · expéditeur : '.config('mail.from.address').' · notifications par courriel : '.(config('freeci.notifications.emails') ? 'activées' : 'désactivées'));
        if (! MailStatus::configured()) {
            $this->error('Aucun pilote réel n\'est configuré (MAIL_MAILER = log ou array) : rien n\'a été envoyé. Voir docs/37-courrier.md.');

            return self::FAILURE;
        }
        if (! AccountMail::send($to, 'Essai d\'envoi FreeCI', ['Ceci est un message d\'essai : si vous le lisez, le courrier de FreeCI fonctionne.', 'Aucune action n\'est requise.'])) {
            $this->error('Le serveur de courrier a refusé le message ou ne répond pas (détail dans storage/logs). Aucun courriel envoyé.');

            return self::FAILURE;
        }
        $this->info('Message accepté par le serveur de courrier. Vérifiez la réception (et les courriers indésirables) : « accepté » ne garantit pas la remise en boîte.');

        return self::SUCCESS;
    }
}

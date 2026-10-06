<?php

namespace App\Console\Commands;

use App\Modules\Accounts\Models\User;
use App\Modules\Accounts\Security\EmailVerification;
use Illuminate\Console\Command;

/**
 * Attestation console de l'adresse d'un administrateur, pour un serveur SANS courrier configuré. C'est une déclaration explicite et
 * journalisée de la personne qui a l'accès serveur (canal « console »), distincte d'un lien ouvert depuis la boîte e-mail. Elle ne
 * s'applique qu'à un compte portant une habilitation administrateur en vigueur. Aucun courriel écrit dans un journal ne vaut vérification.
 */
class AdminVerifyEmail extends Command
{
    protected $signature = 'freeci:admin:verify-email {email : adresse du compte administrateur}
        {--attest : je certifie que cette adresse est bien celle du porteur du compte (obligatoire)}
        {--reason=Attestation console (SMTP non configuré) : motif enregistré}
        {--yes : ne pas demander de confirmation}';

    protected $description = 'Atteste, depuis le serveur, l\'adresse d\'un administrateur (sans SMTP). Journalisé comme attestation console.';

    public function handle(EmailVerification $verification): int
    {
        $user = User::where('email', mb_strtolower(trim((string) $this->argument('email'))))->first();
        if ($user === null || ! $user->isAdministrator()) {
            $this->error('Refusé : le compte doit exister et porter une habilitation administrateur en vigueur.');

            return self::FAILURE;
        }
        if ($user->emailVerified()) {
            $this->info('Adresse déjà vérifiée : rien à faire.');

            return self::SUCCESS;
        }
        if (! $this->option('attest')) {
            $this->error('Ajoutez --attest : vous certifiez que '.$user->email.' est bien l\'adresse du porteur du compte (cette attestation est journalisée).');

            return self::FAILURE;
        }
        if (! $this->option('yes') && ! $this->confirm("Attester l'adresse « {$user->email} » depuis la console ?")) {
            $this->warn('Annulé.');

            return self::FAILURE;
        }
        $verification->attestByConsole($user, (string) $this->option('reason'));
        $this->info('Adresse attestée par la console (journalisée). Étape suivante : double authentification dans le navigateur (/admin/activation).');

        return self::SUCCESS;
    }
}

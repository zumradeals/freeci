<?php

namespace App\Console\Commands;

use App\Modules\Accounts\Models\User;
use App\Modules\Accounts\Security\TwoFactor;
use Illuminate\Console\Command;

/**
 * Récupération console : application d'authentification ET codes de récupération perdus. Désactive la double authentification, supprime
 * les codes, ferme TOUTES les sessions du compte et journalise l'opération. L'administration reste fermée jusqu'à une nouvelle activation.
 */
class AdminMfaReset extends Command
{
    protected $signature = 'freeci:admin:mfa-reset {email : adresse du compte}
        {--yes : ne pas demander de confirmation}';

    protected $description = 'Réinitialise la double authentification d\'un compte (récupération depuis le serveur, journalisée) ; ferme ses sessions.';

    public function handle(TwoFactor $mfa): int
    {
        $user = User::where('email', mb_strtolower(trim((string) $this->argument('email'))))->first();
        if ($user === null) {
            $this->error('Compte introuvable.');

            return self::FAILURE;
        }
        if (! $user->hasTwoFactor() && $user->two_factor_secret === null) {
            $this->info('Aucune double authentification à réinitialiser.');

            return self::SUCCESS;
        }
        if (! $this->option('yes') && ! $this->confirm("Réinitialiser la double authentification de « {$user->email} » et fermer toutes ses sessions ?")) {
            $this->warn('Annulé.');

            return self::FAILURE;
        }
        $mfa->reset($user, 'console');
        $this->info('Double authentification réinitialisée (journalisée), sessions fermées. Le titulaire doit se reconnecter et la réactiver (/admin/activation) ; l\'administration reste fermée d\'ici là.');

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Modules\Accounts\Models\User;
use App\Modules\Notifications\Support\MailStatus;
use Illuminate\Console\Command;

class AdminStatus extends Command
{
    protected $signature = 'freeci:admin:status {email : adresse du compte}';

    protected $description = 'Montre où en est l\'activation de l\'administration d\'un compte (habilitation, adresse vérifiée, double authentification, courrier), sans secret.';

    public function handle(): int
    {
        $user = User::where('email', mb_strtolower(trim((string) $this->argument('email'))))->first();
        if ($user === null) {
            $this->error('Compte introuvable.');

            return self::FAILURE;
        }
        $yes = fn (bool $b) => $b ? 'OUI' : 'NON';
        $this->table(['Prérequis', 'État'], [
            ['Habilitation administrateur en vigueur', $yes($user->isAdministrator())],
            ['Adresse e-mail vérifiée', $yes($user->emailVerified())],
            ['Double authentification activée', $yes($user->hasTwoFactor())],
            ['Compte suspendu', $yes($user->isSuspended())],
            ['Courrier configuré (SMTP réel)', $yes(MailStatus::configured())],
        ]);
        $ready = $user->isAdministrator() && $user->emailVerified() && $user->hasTwoFactor();
        $this->line($ready ? 'Prérequis satisfaits : l\'administration s\'ouvre après le code de double authentification de chaque session.' : 'Administration FERMÉE : au moins un prérequis manque.');
        if (! $user->emailVerified()) {
            $this->line(MailStatus::configured() ? '→ Connectez-vous, ouvrez /admin et demandez le lien de vérification.' : '→ Le courrier n\'est pas configuré : configurez MAIL_* (docs/15), ou attestez l\'adresse : php artisan freeci:admin:verify-email '.$user->email);
        }
        if (! $user->hasTwoFactor()) {
            $this->line('→ L\'activation de la double authentification se fait dans le navigateur (/admin/activation) une fois l\'adresse vérifiée.');
        }

        return self::SUCCESS;
    }
}

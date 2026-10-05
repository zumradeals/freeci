<?php

namespace App\Console\Commands;

use App\Modules\Accounts\Models\User;
use Illuminate\Console\Command;

/** Autorise (ou retire) un COMPTE DE DÉMONSTRATION à utiliser le paiement simulé. Jamais un compte réel. */
class SandboxAuthorize extends Command
{
    protected $signature = 'freeci:sandbox:authorize {email} {--revoke : retire l\'autorisation}';

    protected $description = 'Autorise un compte de démonstration (is_demo) à utiliser le paiement simulé ; refuse tout compte réel.';

    public function handle(): int
    {
        $user = User::where('email', mb_strtolower(trim((string) $this->argument('email'))))->first();
        if ($user === null) {
            $this->error('Compte introuvable.');

            return self::FAILURE;
        }
        if ($this->option('revoke')) {
            $user->forceFill(['sandbox_payments' => false])->save();
            $this->info('Autorisation retirée.');

            return self::SUCCESS;
        }
        if (! $user->is_demo) {
            $this->error('Refusé : ce compte n\'est pas un compte de démonstration. Le paiement simulé est réservé aux comptes de recette.');

            return self::FAILURE;
        }
        $user->forceFill(['sandbox_payments' => true])->save();
        $this->info('Compte autorisé pour le paiement simulé (si FREECI_PAYMENT_SANDBOX=true, et pour les commandes de démonstration seulement).');

        return self::SUCCESS;
    }
}

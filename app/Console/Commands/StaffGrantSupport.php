<?php

namespace App\Console\Commands;

use App\Modules\Accounts\Actions\GrantSupport;
use App\Modules\Accounts\Models\User;
use Illuminate\Console\Command;

class StaffGrantSupport extends Command
{
    protected $signature = 'freeci:staff:grant {email : adresse d\'un compte EXISTANT}
        {--reason=Désignation par le porteur (console) : motif enregistré}
        {--expires= : date d\'expiration (AAAA-MM-JJ), facultatif}
        {--yes : ne pas demander de confirmation}';

    protected $description = 'Accorde l\'habilitation « support » (traitement des dossiers d\'assistance qui lui sont affectés) à un compte existant.';

    public function handle(GrantSupport $grant): int
    {
        $user = User::where('email', mb_strtolower(trim((string) $this->argument('email'))))->first();
        if ($user === null) {
            $this->error('Compte introuvable : la personne doit d\'abord s\'inscrire.');

            return self::FAILURE;
        }
        $expires = null;
        if ($this->option('expires')) {
            try {
                $expires = now()->parse((string) $this->option('expires'))->endOfDay();
            } catch (\Throwable) {
                $this->error('Date d\'expiration invalide (AAAA-MM-JJ).');

                return self::FAILURE;
            }
        }
        if (! $this->option('yes') && ! $this->confirm("Accorder l'habilitation support à « {$user->email} » ?")) {
            $this->warn('Annulé.');

            return self::FAILURE;
        }
        $g = $grant($user, (string) $this->option('reason'), $expires);
        $this->info("Habilitation support {$g->id} accordée à {$user->email}. L'accès reste fermé tant que l'adresse n'est pas vérifiée et la double authentification activée (/admin/activation).");

        return self::SUCCESS;
    }
}

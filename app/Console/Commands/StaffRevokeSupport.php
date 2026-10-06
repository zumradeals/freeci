<?php

namespace App\Console\Commands;

use App\Modules\Accounts\Actions\GrantSupport;
use App\Modules\Accounts\Models\User;
use Illuminate\Console\Command;

class StaffRevokeSupport extends Command
{
    protected $signature = 'freeci:staff:revoke {email} {--reason=Révocation par le porteur (console)}';

    protected $description = 'Révoque l\'habilitation « support » ; les affectations en cours prennent fin (le dossier redevient non affecté).';

    public function handle(GrantSupport $grant): int
    {
        $user = User::where('email', mb_strtolower(trim((string) $this->argument('email'))))->first();
        if ($user === null) {
            $this->error('Compte introuvable.');

            return self::FAILURE;
        }
        $n = $grant->revoke($user, (string) $this->option('reason'));
        $n > 0 ? $this->info("{$n} habilitation(s) support révoquée(s) ; affectations terminées.") : $this->warn('Aucune habilitation support active.');

        return self::SUCCESS;
    }
}

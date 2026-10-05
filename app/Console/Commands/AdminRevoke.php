<?php

namespace App\Console\Commands;

use App\Modules\Accounts\Actions\RevokeAdministrator;
use App\Modules\Accounts\Models\User;
use Illuminate\Console\Command;

class AdminRevoke extends Command
{
    protected $signature = 'freeci:admin:revoke {email} {--reason=Révocation par le porteur (console)}';

    protected $description = 'Révoque l\'habilitation administrateur d\'un compte (ses rôles client et freelance sont conservés).';

    public function handle(RevokeAdministrator $revoke): int
    {
        $user = User::where('email', mb_strtolower(trim((string) $this->argument('email'))))->first();
        if ($user === null) {
            $this->error('Compte introuvable.');

            return self::FAILURE;
        }
        $n = $revoke($user, (string) $this->option('reason'));
        $n > 0 ? $this->info("{$n} habilitation(s) révoquée(s).") : $this->warn('Aucune habilitation active.');

        return self::SUCCESS;
    }
}

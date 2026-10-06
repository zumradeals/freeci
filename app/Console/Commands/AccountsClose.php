<?php

namespace App\Console\Commands;

use App\Modules\Accounts\Actions\AccountClosure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AccountsClose extends Command
{
    protected $signature = 'freeci:accounts:close';

    protected $description = 'Traite les demandes de fermeture de compte échues : anonymise si aucune obligation n\'est en cours, sinon laisse la demande en attente (obstacles listés au titulaire).';

    public function handle(AccountClosure $closure): int
    {
        $n = ['closed' => 0, 'blocked' => 0, 'none' => 0];
        foreach (DB::table('account_closure_requests')->where('state', 'requested')->where('due_at', '<=', now())->orderBy('due_at')->limit(100)->get() as $r) {
            $n[$closure->process($r)]++;
        }
        $this->line("Comptes fermés : {$n['closed']} · en attente d'obligations : {$n['blocked']}");

        return self::SUCCESS;
    }
}

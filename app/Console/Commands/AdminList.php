<?php

namespace App\Console\Commands;

use App\Modules\Accounts\Models\StaffGrant;
use Illuminate\Console\Command;

class AdminList extends Command
{
    protected $signature = 'freeci:admin:list';

    protected $description = 'Liste les habilitations administrateur (actives et passées), sans aucune donnée secrète.';

    public function handle(): int
    {
        $rows = StaffGrant::with('user:id,email')->orderBy('granted_at')->get()->map(fn ($g) => [
            $g->user->email.' ['.$g->capability.']', $g->granted_at->format('Y-m-d H:i'), $g->expires_at?->format('Y-m-d') ?? '—',
            $g->revoked_at ? 'révoquée '.$g->revoked_at->format('Y-m-d') : ($g->expires_at && $g->expires_at->isPast() ? 'expirée' : 'ACTIVE'), $g->granted_by,
        ])->all();
        $this->table(['Compte', 'Accordée', 'Expire', 'État', 'Par'], $rows);
        $this->line('Prérequis d\'accès (adresse vérifiée, double authentification) : freeci:admin:status <courriel>.');

        return self::SUCCESS;
    }
}

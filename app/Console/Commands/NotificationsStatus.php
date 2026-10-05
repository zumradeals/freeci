<?php

namespace App\Console\Commands;

use App\Modules\Notifications\Support\MailStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NotificationsStatus extends Command
{
    protected $signature = 'freeci:notifications:status';

    protected $description = 'État des courriels de notification et de la file d\'attente (lecture seule ; aucun contenu ni secret affiché).';

    public function handle(): int
    {
        $this->line('Courrier réel configuré : '.(MailStatus::deliverable() ? 'oui (pilote « '.config('mail.default').' »)' : 'NON : les notifications restent dans l\'application, aucun courriel n\'est présenté comme envoyé'));
        $this->line('File d\'attente : '.config('queue.default').(config('queue.default') === 'sync' ? ' (synchrone : aucun traitement différé ni reprise)' : ''));
        $rows = DB::table('app_notifications')->selectRaw('email_state, count(*) as n')->groupBy('email_state')->pluck('n', 'email_state');
        $this->table(['État du courriel', 'Notifications'], collect(['none' => 'aucun courriel prévu', 'unavailable' => 'indisponible (courrier non configuré)', 'pending' => 'en attente d\'envoi', 'sent' => 'accepté par le serveur de courrier', 'failed' => 'échec après reprises'])->map(fn ($label, $k) => [$label, (int) ($rows[$k] ?? 0)])->values()->all());
        $stale = DB::table('app_notifications')->where('email_state', 'pending')->where('updated_at', '<', now()->subMinutes(15))->count();
        $this->line("En attente depuis plus de 15 minutes : {$stale} (relance : freeci:notifications:retry --stale)");
        $this->line('Tâches en file : '.DB::table('jobs')->count().' · tâches échouées : '.DB::table('failed_jobs')->count());

        return self::SUCCESS;
    }
}

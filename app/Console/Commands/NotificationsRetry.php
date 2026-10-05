<?php

namespace App\Console\Commands;

use App\Modules\Notifications\Jobs\SendNotificationEmail;
use App\Modules\Notifications\Models\AppNotification;
use App\Modules\Notifications\Support\MailStatus;
use Illuminate\Console\Command;

class NotificationsRetry extends Command
{
    protected $signature = 'freeci:notifications:retry {--stale : relance les courriels « en attente » depuis plus de 15 minutes (planifié)} {--failed : relance aussi les courriels en échec}';

    protected $description = 'Remet en file les courriels de notification restés en attente ou en échec. Sans effet si le courrier n\'est pas configuré.';

    public function handle(): int
    {
        if (! MailStatus::deliverable()) {
            $this->warn('Courrier non configuré : rien n\'est relancé (les notifications dans l\'application ne sont pas concernées).');

            return self::SUCCESS;
        }
        $q = AppNotification::query()->whereNull('read_at')->where(function ($w) {
            $w->where(fn ($p) => $p->where('email_state', 'pending')->where('updated_at', '<', now()->subMinutes(15)));
            if ($this->option('failed')) {
                $w->orWhere('email_state', 'failed');
            }
        });
        $n = 0;
        foreach ($q->get() as $row) {
            $row->forceFill(['email_state' => 'pending', 'email_error' => null, 'updated_at' => now()])->save();
            SendNotificationEmail::dispatch($row->getKey());
            $n++;
        }
        $this->info("{$n} courriel(s) remis en file.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Shared;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Dernier passage réussi / échoué de chaque tâche planifiée (visible de l'administrateur). N'enregistre ni contenu ni détail technique. */
final class TaskHeartbeat
{
    public static function watch(Event $event, string $task): Event
    {
        return $event->onSuccess(fn () => self::mark($task, true))->onFailure(fn () => self::mark($task, false));
    }

    public static function mark(string $task, bool $ok): void
    {
        try {
            $col = $ok ? 'last_ok_at' : 'last_failed_at';
            DB::statement("INSERT INTO system_task_runs (task, {$col}, updated_at) VALUES (?, now(), now()) ON CONFLICT (task) DO UPDATE SET {$col} = now(), updated_at = now()", [$task]);
        } catch (Throwable) {
            // la supervision ne doit jamais faire échouer la tâche
        }
    }
}

<?php

namespace App\Modules\Finance\Jobs;

use App\Modules\Finance\Actions\ProcessProviderEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Traitement asynchrone d'un événement de paiement déjà ENREGISTRÉ. Idempotent ; les résultats incertains sont repris par `freeci:payments:reconcile`. */
class ProcessPaymentEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(public int $eventId) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(ProcessProviderEvent $process): void
    {
        $process->process($this->eventId);
    }
}

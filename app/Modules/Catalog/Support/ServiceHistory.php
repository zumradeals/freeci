<?php

namespace App\Modules\Catalog\Support;

use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Models\ServiceEvent;

/** Écrit l'historique d'un service (ajout seul). */
final class ServiceHistory
{
    public static function log(string $serviceId, ?string $versionId, string $type, ?User $actor, string $label, ?string $note = null, ?array $meta = null): void
    {
        ServiceEvent::create(['service_id' => $serviceId, 'version_id' => $versionId, 'type' => $type, 'actor_id' => $actor?->getKey(), 'actor_label' => $label, 'note' => $note, 'meta' => $meta]);
    }
}

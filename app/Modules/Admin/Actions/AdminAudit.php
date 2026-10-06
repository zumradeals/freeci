<?php

namespace App\Modules\Admin\Actions;

use App\Modules\Accounts\Exceptions\AccountRestricted;
use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Exceptions\ModerationDenied;
use App\Modules\Catalog\Exceptions\ServiceStateConflict;
use App\Modules\Missions\Exceptions\MissionConflict;
use Illuminate\Support\Facades\DB;

/**
 * Journal des actions administratives (ajout seul) : auteur, date, cible, motif, résultat. Aucun contenu privé, aucun secret :
 * seul le motif saisi par l'administrateur et le message d'une règle métier sont conservés.
 * `run()` revérifie à CHAQUE action que l'acteur dispose d'une habilitation en vigueur, d'une adresse vérifiée et de la double authentification.
 */
final class AdminAudit
{
    public function assertActor(User $actor): void
    {
        $actor->refresh();
        if (! $actor->isAdministrator() || ! $actor->emailVerified() || ! $actor->hasTwoFactor()) {
            throw new ModerationDenied('Habilitation administrateur en vigueur, adresse vérifiée et double authentification requises.');
        }
    }

    /** Personnel d'assistance (ou administrateur) : habilitation en vigueur + adresse vérifiée + double authentification. */
    public function assertStaff(User $actor): void
    {
        $actor->refresh();
        if (! $actor->isStaff() || ! $actor->emailVerified() || ! $actor->hasTwoFactor()) {
            throw new ModerationDenied('Habilitation du personnel en vigueur, adresse vérifiée et double authentification requises.');
        }
    }

    /** Comme `run()`, pour une action du personnel d'assistance. */
    public function runStaff(User $actor, string $action, string $targetType, ?string $targetId, ?string $label, ?string $reason, callable $do): mixed
    {
        try {
            $this->assertStaff($actor);
            $result = $do();
        } catch (ModerationDenied|ServiceStateConflict|MissionConflict|AccountRestricted|\DomainException $e) {
            $this->record($actor, $action, $targetType, $targetId, $label, $reason, 'refused', $e->getMessage());
            throw $e;
        }
        $this->record($actor, $action, $targetType, $targetId, $label, $reason, 'done');

        return $result;
    }

    /** @template T @param callable(): T $do @return T */
    public function run(User $actor, string $action, string $targetType, ?string $targetId, ?string $label, ?string $reason, callable $do): mixed
    {
        try {
            $this->assertActor($actor);
            $result = $do();
        } catch (ModerationDenied|ServiceStateConflict|MissionConflict|AccountRestricted|\DomainException $e) {
            $this->record($actor, $action, $targetType, $targetId, $label, $reason, 'refused', $e->getMessage());
            throw $e;
        }
        $this->record($actor, $action, $targetType, $targetId, $label, $reason, 'done');

        return $result;
    }

    public function record(User $actor, string $action, string $targetType, ?string $targetId, ?string $label, ?string $reason, string $result, ?string $detail = null): void
    {
        DB::table('admin_actions')->insert([
            'actor_id' => $actor->getKey(), 'action' => $action, 'target_type' => $targetType, 'target_id' => $targetId,
            'target_label' => $label === null ? null : mb_substr($label, 0, 200), 'reason' => $reason === null ? null : mb_substr($reason, 0, 1000),
            'result' => $result, 'detail' => $detail === null ? null : mb_substr($detail, 0, 300), 'created_at' => now(),
        ]);
    }
}

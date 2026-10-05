<?php

namespace App\Modules\Missions\Queries;

use App\Modules\Missions\Models\Mission;
use App\Modules\Missions\Models\MissionVersion;

/** Libellé, ton et icône d'une mission pour son propriétaire. */
final class MissionPresenter
{
    /** @return array{0: string, 1: string, 2: string, 3: ?string} libellé, ton, icône, explication */
    public static function status(Mission $m, ?MissionVersion $working, ?MissionVersion $live): array
    {
        return match (true) {
            $working?->state === 'changes_requested' => ['À corriger', 'warning', 'warn', $working->decision_note],
            $working?->state === 'in_review' => [$live ? 'Modification en contrôle' : 'En contrôle', 'info', 'clock', $live ? 'La version publiée (v'.$live->number.') reste en ligne pendant le contrôle.' : 'Invisible du public jusqu’à l’approbation.'],
            $m->status === 'selection_ended' => ['Sélection terminée', 'warning', 'warn', 'La commande a été annulée ou a expiré avant paiement. La mission n’est pas rouverte automatiquement : rouvrez-la ou fermez-la.'],
            $m->status === 'reserved' => ['Réservée', 'info', 'clock', 'Une proposition est retenue ; la mission sera attribuée à la confirmation du paiement.'],
            $m->status === 'awarded' => ['Attribuée', 'success', 'check-circle', 'Le paiement est confirmé : la commande suit son cours.'],
            $m->status === 'closed' => ['Fermée', 'neutral', 'minus-circle', null],
            $m->status === 'cancelled' => ['Annulée', 'neutral', 'minus-circle', null],
            $m->status === 'expired' => ['Expirée', 'neutral', 'minus-circle', 'Aucune proposition n’a été retenue avant la fin de la période de sélection.'],
            $working?->state === 'draft' && $live !== null => ['Ouverte · modification en brouillon', 'success', 'check-circle', 'Votre brouillon n’est pas visible : la version publiée (v'.$live->number.') reste en ligne.'],
            $m->status === 'open' => ['Ouverte', 'success', 'check-circle', null],
            default => ['Brouillon', 'neutral', 'pencil', null],
        };
    }
}

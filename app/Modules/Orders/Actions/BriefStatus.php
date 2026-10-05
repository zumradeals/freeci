<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Files\Enums\FileState;
use App\Modules\Files\Models\FileAsset;
use App\Modules\Orders\Models\Order;

/**
 * « Brief complet » = toutes les réponses renseignées ET, si l'ACCORD l'exigeait au moment de la demande, au moins une
 * pièce jointe ayant passé le contrôle de sécurité. L'exigence vient de l'accord figé, jamais du service actuel :
 * compléter le brief ne peut donc pas modifier le périmètre commercial accepté.
 */
final class BriefStatus
{
    /** @return array{complete: bool, missing: int, filesRequired: bool, hasCleanFile: bool} */
    public static function of(Order $order): array
    {
        $order->loadMissing(['brief', 'agreement']);
        $missing = collect($order->brief->answers)->filter(fn ($i) => trim((string) ($i['answer'] ?? '')) === '')->count();
        $required = (bool) $order->agreement->brief_requires_files;
        $hasClean = FileAsset::query()->where('order_id', $order->getKey())->where('state', FileState::Clean->value)->exists();
        $missingFiles = $required && ! $hasClean ? 1 : 0;

        return ['complete' => $missing === 0 && $missingFiles === 0, 'missing' => $missing + $missingFiles, 'filesRequired' => $required, 'hasCleanFile' => $hasClean];
    }
}

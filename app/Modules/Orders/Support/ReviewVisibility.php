<?php

namespace App\Modules\Orders\Support;

use Illuminate\Database\Query\Builder;

/**
 * Règle UNIQUE de ce qui est PUBLIC. Un avis compte (liste publique, moyenne, nombre) seulement si : commande RÉELLE (`counts_public`), date de publication atteinte,
 * non masqué par la modération. Les commandes de test (sandbox) et antérieures à l'environnement explicite n'ont jamais d'effet sur la réputation.
 * Toute agrégation passe par ici : après un masquage ou un rétablissement, les chiffres suivent immédiatement (aucun cache à purger).
 */
final class ReviewVisibility
{
    public static function published(Builder $q, string $alias = 'reviews'): Builder
    {
        return $q->where("{$alias}.counts_public", true)->whereNull("{$alias}.hidden_at")->where("{$alias}.visible_at", '<=', now());
    }

    /** Catégories de masquage. Une note négative seule n'en fait PAS partie : elle n'est jamais un motif de retrait. */
    public const MODERATION_CATEGORIES = [
        'abuse' => 'Propos injurieux, diffamatoires ou harcèlement',
        'personal_data' => 'Données personnelles ou coordonnées',
        'illegal' => 'Contenu illicite',
        'off_topic' => 'Sans rapport avec la prestation commandée',
    ];
}

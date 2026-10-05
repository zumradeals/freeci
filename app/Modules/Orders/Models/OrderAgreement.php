<?php

namespace App\Modules\Orders\Models;

use Illuminate\Database\Eloquent\Model;

/** Accord figé : ajout seul (déclencheur PostgreSQL). Ne se recalcule jamais depuis le service. */
class OrderAgreement extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'order_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /**
     * Mode de livraison figé : « files » (≥ 1 fichier contrôlé exigé), « message » (aucun fichier exigé, précisé dans l'accord) ou
     * « unspecified » (accord antérieur à la règle : l'ancien indicateur n'était pas un choix de l'auteur ; il n'est ni réécrit ni appliqué
     * comme une renonciation aux fichiers, mais l'obligation n'est pas contrôlée automatiquement).
     */
    public function deliveryMode(): string
    {
        return match ($this->delivery_mode) {
            'files', 'message' => $this->delivery_mode,
            default => $this->delivery_requires_files ? 'files' : 'unspecified',
        };
    }

    protected function casts(): array
    {
        return [
            'price_xof' => 'integer', 'delivery_days' => 'integer', 'revisions_included' => 'integer',
            'brief_requires_files' => 'boolean', 'delivery_requires_files' => 'boolean', 'deliverables' => 'array', 'exclusions' => 'array', 'client_inputs' => 'array',
            'conditions_accepted_at' => 'datetime', 'created_at' => 'datetime',
        ];
    }
}

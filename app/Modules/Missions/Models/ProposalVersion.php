<?php

namespace App\Modules\Missions\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Version soumise d'une proposition : ajout seul (déclencheur) ; le client sélectionne UNE version précise. */
class ProposalVersion extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['price_xof' => 'integer', 'delivery_days' => 'integer', 'revisions_included' => 'integer', 'number' => 'integer', 'deliverables' => 'array', 'milestones' => 'array', 'valid_until' => 'datetime', 'submitted_at' => 'datetime'];
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    public function missionVersion(): BelongsTo
    {
        return $this->belongsTo(MissionVersion::class);
    }
}

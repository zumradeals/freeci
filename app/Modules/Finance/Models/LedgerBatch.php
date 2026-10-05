<?php

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Lot du registre : ajout seul, équilibré (somme nulle, vérifiée par contrainte différée). */
class LedgerBatch extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_simulated' => 'boolean', 'occurred_at' => 'datetime'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(LedgerLine::class, 'batch_id');
    }
}

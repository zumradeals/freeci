<?php

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Cas à examiner par le support : paiement tardif, doublon, vérification discordante. Aucune réouverture automatique. */
class ReconciliationCase extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['details' => 'array', 'created_at' => 'datetime', 'resolved_at' => 'datetime'];
    }
}

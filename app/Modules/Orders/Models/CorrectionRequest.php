<?php

namespace App\Modules\Orders\Models;

use Illuminate\Database\Eloquent\Model;

/** Demande de correction : ajout seul, liée à UNE version de livraison (unique), numérotée dans la limite de l'accord figé. */
class CorrectionRequest extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['number' => 'integer', 'created_at' => 'datetime'];
    }
}

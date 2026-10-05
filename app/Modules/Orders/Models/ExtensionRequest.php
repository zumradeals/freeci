<?php

namespace App\Modules\Orders\Models;

use Illuminate\Database\Eloquent\Model;

/** Proposition de report : décidée une seule fois (déclencheur) ; l'échéance d'origine est conservée dans `previous_due_at`. */
class ExtensionRequest extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['previous_due_at' => 'datetime', 'proposed_due_at' => 'datetime', 'decided_at' => 'datetime', 'created_at' => 'datetime'];
    }
}

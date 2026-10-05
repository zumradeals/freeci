<?php

namespace App\Modules\Missions\Models;

use Illuminate\Database\Eloquent\Model;

/** Historique d'une mission : ajout seul. */
class MissionEvent extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['meta' => 'array', 'occurred_at' => 'datetime'];
    }
}

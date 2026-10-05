<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Image d'un service : fichiers RÉENCODÉS stockés sur le disque privé ; jamais d'accès direct, seulement la route contrôlée. */
class ServiceMedia extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $guarded = [];

    protected $hidden = ['key_large', 'key_card'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}

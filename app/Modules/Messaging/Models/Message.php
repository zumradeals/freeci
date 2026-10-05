<?php

namespace App\Modules\Messaging\Models;

use App\Modules\Accounts\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Message : ajout seul (déclencheur PostgreSQL). Un message n'est jamais une livraison, une modification d'accord, une acceptation de report ni une validation. */
class Message extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}

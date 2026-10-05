<?php

namespace App\Modules\Notifications\Models;

use App\Modules\Accounts\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Notification dans l'application. Le contenu est volontairement court et ne reprend jamais le texte d'un message. */
class AppNotification extends Model
{
    protected $table = 'app_notifications';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['route_params' => 'array', 'read_at' => 'datetime', 'email_sent_at' => 'datetime', 'count' => 'integer', 'email_attempts' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Modules\Accounts\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffGrant extends Model
{
    use HasUuids;

    public const ADMINISTRATOR = 'administrator';

    /** Personnel d'assistance : traite les dossiers qui lui sont affectés ; aucun droit de modération, de gestion de comptes ni d'audit. */
    public const SUPPORT = 'support';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['granted_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    /** Habilitation en vigueur : ni révoquée, ni expirée. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

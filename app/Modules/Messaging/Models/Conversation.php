<?php

namespace App\Modules\Messaging\Models;

use App\Modules\Accounts\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Fil privé entre DEUX participants (le client et le freelance du contexte). Aucun accès pour un tiers, administrateur compris. */
class Conversation extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function freelancer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'freelancer_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('id');
    }

    public function isParticipant(User $u): bool
    {
        return $u->getKey() === $this->client_id || $u->getKey() === $this->freelancer_id;
    }

    public function counterpartId(User $u): string
    {
        return $u->getKey() === $this->client_id ? $this->freelancer_id : $this->client_id;
    }

    public function readColumn(User $u): string
    {
        return $u->getKey() === $this->client_id ? 'client_read_id' : 'freelancer_read_id';
    }
}

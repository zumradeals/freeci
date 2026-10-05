<?php

namespace App\Modules\Missions\Models;

use App\Modules\Accounts\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Besoin publié par un client. Contenu rédigé : `MissionVersion` ; ici l'identité, l'état et la sélection. */
class Mission extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'closed_at' => 'datetime', 'is_demo' => 'boolean', 'row_version' => 'integer'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(MissionVersion::class)->orderBy('number');
    }

    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(MissionVersion::class, 'published_version_id');
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }
}

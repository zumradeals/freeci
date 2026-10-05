<?php

namespace App\Modules\Missions\Models;

use App\Modules\Accounts\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Candidature d'un freelance à une mission ; chaque révision est une `ProposalVersion` conservée. */
class Proposal extends Model
{
    use HasUuids;

    protected $guarded = [];

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function freelancer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'freelancer_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ProposalVersion::class)->orderBy('number');
    }
}

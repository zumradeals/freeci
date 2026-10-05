<?php

namespace App\Modules\Missions\Models;

use App\Modules\Catalog\Models\Category;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Contenu d'une mission. Brouillon / à corriger : modifiable ; soumise, publiée, remplacée : immuable (déclencheur). */
class MissionVersion extends Model
{
    use HasUuids;

    public const OPEN = ['draft', 'in_review', 'changes_requested'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'budget_xof' => 'integer', 'number' => 'integer', 'revision_no' => 'integer', 'client_inputs' => 'array', 'brief_requires_files' => 'boolean',
            'application_deadline' => 'datetime', 'submitted_at' => 'datetime', 'decided_at' => 'datetime', 'published_at' => 'datetime',
        ];
    }

    public function isEditable(): bool
    {
        return in_array($this->state, ['draft', 'changes_requested'], true);
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}

<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Accounts\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Contenu rédigé d'un service. Brouillon et « à corriger » : modifiables par le propriétaire. Soumise, publiée ou remplacée :
 * immuable (déclencheur PostgreSQL). Le public ne voit que la version publiée, copiée dans `services` à l'approbation.
 */
class ServiceVersion extends Model
{
    use HasUuids;

    public const OPEN = ['draft', 'in_review', 'changes_requested'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price_xof' => 'integer', 'delivery_days' => 'integer', 'revisions_included' => 'integer', 'revision_no' => 'integer', 'number' => 'integer',
            'deliverables' => 'array', 'exclusions' => 'array', 'client_inputs' => 'array', 'images' => 'array',
            'brief_requires_files' => 'boolean', 'delivery_requires_files' => 'boolean',
            'submitted_at' => 'datetime', 'decided_at' => 'datetime', 'published_at' => 'datetime',
        ];
    }

    public function isEditable(): bool
    {
        return in_array($this->state, ['draft', 'changes_requested'], true);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}

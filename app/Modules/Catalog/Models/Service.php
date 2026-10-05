<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Enums\ServiceStatus;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Service extends Model
{
    use HasFactory, HasUuids;

    protected static function newFactory(): ServiceFactory
    {
        return ServiceFactory::new();
    }

    protected $guarded = [];

    protected $hidden = ['search_document'];

    protected function casts(): array
    {
        return [
            'status' => ServiceStatus::class,
            'price_xof' => 'integer',
            'delivery_days' => 'integer',
            'revisions_included' => 'integer',
            'deliverables' => 'array',
            'exclusions' => 'array',
            'client_inputs' => 'array',
            'images' => 'array',
            'published_at' => 'datetime',
            'is_demo' => 'boolean',
        ];
    }

    /** Seuls les services publiés sont visibles du public (docs/04 §2.6). */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('services.status', ServiceStatus::Published->value)
            ->whereNotNull('services.published_at')
            ->where('services.published_at', '<=', now());
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function freelanceProfile(): BelongsTo
    {
        return $this->belongsTo(FreelanceProfile::class);
    }
}

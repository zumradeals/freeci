<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Enums\ServiceStatus;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

class Service extends Model
{
    use HasFactory, HasUuids;

    protected static function newFactory(): ServiceFactory
    {
        return ServiceFactory::new();
    }

    protected $guarded = [];

    protected $hidden = ['search_document'];

    /** Champs qui constituent les conditions commerciales : toute modification crée une nouvelle « version » du service. */
    public const COMMERCIAL_FIELDS = ['title', 'summary', 'scope', 'price_xof', 'delivery_days', 'revisions_included', 'deliverables', 'exclusions', 'client_inputs', 'status'];

    protected static function booted(): void
    {
        // Incrément atomique en base (pas de lecture-puis-écriture) : deux modifications concurrentes ne partagent jamais une version.
        static::updating(function (self $service): void {
            if (! $service->isDirty('row_version') && $service->isDirty(self::COMMERCIAL_FIELDS)) {
                $service->row_version = DB::raw('row_version + 1');
            }
        });
        static::updated(function (self $service): void {
            if ($service->row_version instanceof Expression) {
                $service->setRawAttributes(array_merge($service->getAttributes(), [
                    'row_version' => (int) static::query()->whereKey($service->getKey())->value('row_version'),
                ]), true);
            }
        });
    }

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
            'accepts_requests' => 'boolean',
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

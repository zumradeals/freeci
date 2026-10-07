<?php

namespace App\Modules\Catalog\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory, HasUuids;

    protected static function newFactory(): CategoryFactory
    {
        return CategoryFactory::new();
    }

    protected $guarded = [];

    protected $casts = ['archived_at' => 'datetime'];

    /** Catégories proposées : les archivées restent attachées à leur historique mais ne sont plus offertes. */
    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}

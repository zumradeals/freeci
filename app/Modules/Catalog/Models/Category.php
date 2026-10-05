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

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}

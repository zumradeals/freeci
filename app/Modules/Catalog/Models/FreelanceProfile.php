<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Accounts\Models\User;
use Database\Factories\FreelanceProfileFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FreelanceProfile extends Model
{
    use HasFactory, HasUuids;

    protected static function newFactory(): FreelanceProfileFactory
    {
        return FreelanceProfileFactory::new();
    }

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_demo' => 'boolean'];
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

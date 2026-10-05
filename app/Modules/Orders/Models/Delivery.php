<?php

namespace App\Modules\Orders\Models;

use App\Modules\Accounts\Models\User;
use App\Modules\Files\Models\FileAsset;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Livraison : brouillon modifiable par le freelance, puis VERSION soumise, immuable (déclencheur PostgreSQL) et conservée. */
class Delivery extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['version' => 'integer', 'submitted_at' => 'datetime', 'review_deadline_at' => 'datetime'];
    }

    public function isSubmitted(): bool
    {
        return $this->state === 'submitted';
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(FileAsset::class, 'delivery_id')->orderBy('created_at');
    }

    public function correction(): HasOne
    {
        return $this->hasOne(CorrectionRequest::class);
    }

    /** La demande de correction à laquelle cette version répond. */
    public function answers(): BelongsTo
    {
        return $this->belongsTo(CorrectionRequest::class, 'correction_request_id');
    }
}

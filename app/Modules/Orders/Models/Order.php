<?php

namespace App\Modules\Orders\Models;

use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Models\Service;
use App\Modules\Files\Models\FileAsset;
use App\Modules\Finance\Models\Payment;
use App\Modules\Orders\Enums\ClosureReason;
use App\Modules\Orders\Enums\OrderState;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'state' => OrderState::class,
            'closure_reason' => ClosureReason::class,
            'requested_at' => 'datetime',
            'response_deadline_at' => 'datetime',
            'accepted_at' => 'datetime',
            'payment_deadline_at' => 'datetime',
            'closed_at' => 'datetime',
            'started_at' => 'datetime',
            'due_at' => 'datetime',
            'is_demo' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function freelancer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'freelancer_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function agreement(): HasOne
    {
        return $this->hasOne(OrderAgreement::class);
    }

    public function brief(): HasOne
    {
        return $this->hasOne(OrderBrief::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(FileAsset::class)->orderBy('created_at');
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->orderBy('id');
    }

    public function isParty(User $user): bool
    {
        return $user->getKey() === $this->client_id || $user->getKey() === $this->freelancer_id;
    }
}

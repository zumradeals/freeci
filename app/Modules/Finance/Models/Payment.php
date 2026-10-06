<?php

namespace App\Modules\Finance\Models;

use App\Modules\Finance\Enums\PaymentState;
use App\Modules\Orders\Models\Order;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'state' => PaymentState::class, 'amount_xof' => 'integer', 'is_simulated' => 'boolean',
            'pending_at' => 'datetime', 'confirmed_at' => 'datetime', 'failed_at' => 'datetime', 'last_checked_at' => 'datetime', 'provider_expires_at' => 'datetime', 'binding_verified_at' => 'datetime',
        ];
    }

    /** Référence à interroger chez le prestataire : celle qu'il a attribuée (Genius Pay), sinon la nôtre (simulateur). */
    public function verificationReference(): string
    {
        return $this->provider_transaction_reference ?? $this->provider_reference;
    }

    public function isSandboxProvider(): bool
    {
        return $this->provider === 'genius_pay';
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

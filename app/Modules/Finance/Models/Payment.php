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
            'pending_at' => 'datetime', 'confirmed_at' => 'datetime', 'failed_at' => 'datetime', 'last_checked_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

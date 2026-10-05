<?php

namespace App\Modules\Orders\Models;

use Illuminate\Database\Eloquent\Model;

/** Accord figé : ajout seul (déclencheur PostgreSQL). Ne se recalcule jamais depuis le service. */
class OrderAgreement extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'order_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price_xof' => 'integer', 'delivery_days' => 'integer', 'revisions_included' => 'integer',
            'deliverables' => 'array', 'exclusions' => 'array', 'client_inputs' => 'array',
            'conditions_accepted_at' => 'datetime', 'created_at' => 'datetime',
        ];
    }
}

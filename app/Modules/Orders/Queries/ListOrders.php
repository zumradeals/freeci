<?php

namespace App\Modules\Orders\Queries;

use App\Modules\Accounts\Models\User;
use App\Modules\Orders\Actions\ExpireOverdueOrders;
use App\Modules\Orders\Data\OrderCard;
use App\Modules\Orders\Models\Order;

/** `Orders\ListOrders` : liste TOUJOURS bornée par relation (`client_id` ou `freelancer_id` = utilisateur), jamais « tout puis filtrer ». */
final class ListOrders
{
    public function __construct(private ExpireOverdueOrders $expire) {}

    /** @return list<OrderCard> */
    public function __invoke(User $user, string $as): array
    {
        $asFreelancer = $as === 'freelancer';
        $this->expire->__invoke($user->getKey());

        return Order::query()->with(['agreement', 'client', 'freelancer', 'latestDelivery'])
            ->where($asFreelancer ? 'freelancer_id' : 'client_id', $user->getKey())
            ->orderByRaw("case when state in ('awaiting_acceptance','awaiting_payment','awaiting_brief','delivered','revision_requested') then 0 else 1 end")
            ->orderByDesc('requested_at')->orderByDesc('id')
            ->get()->map(fn (Order $o) => OrderCards::make($o, $asFreelancer))->all();
    }
}

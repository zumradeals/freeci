<?php

namespace App\Modules\Finance\Queries;

use App\Integrations\Payments\GeniusPayConfig;
use App\Integrations\Payments\PaymentProviders;
use App\Modules\Accounts\Models\User;
use App\Modules\Finance\Data\PaymentPage;
use App\Modules\Finance\Enums\PaymentState;
use App\Modules\Finance\SandboxGate;
use App\Modules\Orders\Actions\BriefStatus;
use App\Modules\Orders\Actions\ExpireOverdueOrders;
use App\Modules\Orders\Enums\OrderState;
use App\Modules\Orders\Exceptions\OrderForbidden;
use App\Modules\Orders\Models\Order;
use App\Shared\Money;

/** `Finance\GetPaymentSummary` + `GetPaymentStatus` : montant lu dans l'ACCORD, état lu en base, borné au client de la commande. */
final class GetPaymentPage
{
    public function __construct(private SandboxGate $gate, private ExpireOverdueOrders $expire, private PaymentProviders $providers) {}

    public function __invoke(User $client, string $reference): PaymentPage
    {
        $order = Order::query()->where('reference', $reference)->where('client_id', $client->getKey())->first();
        if ($order === null) {
            throw new OrderForbidden;
        }
        $this->expire->forOrder($order->getKey());
        $order = Order::with(['agreement', 'brief', 'freelancer'])->findOrFail($order->getKey());
        $payment = $order->payments()->orderByDesc('id')->first();
        $brief = BriefStatus::of($order);
        $active = $this->providers->active();
        $ready = $active->environment() !== 'sandbox' || GeniusPayConfig::ready();
        $allowed = $this->gate->allows($order);
        $open = $payment?->state->isOpen() ?? false;
        $confirmed = $payment?->state === PaymentState::Confirmed;
        [$tone, $icon] = $payment?->state->tone() ?? [null, null];

        return new PaymentPage(
            reference: $order->reference, title: $order->agreement->service_title, sellerName: $order->freelancer->name,
            amount: Money::xof($order->agreement->price_xof), deliveryDays: $order->agreement->delivery_days, revisionsIncluded: $order->agreement->revisions_included,
            orderState: $order->state->value, orderStateLabel: $order->state->label(), sandboxAllowed: $allowed, deadline: $order->payment_deadline_at,
            paymentState: $payment?->state->value, paymentLabel: $payment?->state->label(), paymentTone: $tone, paymentIcon: $icon,
            providerReference: $payment?->provider_reference,
            paymentChangedAt: $payment?->confirmed_at ?? $payment?->failed_at ?? $payment?->pending_at ?? $payment?->created_at,
            lastCheckedAt: $payment?->last_checked_at,
            // Le bouton « Payer » est ABSENT dès qu'une tentative est ouverte ou confirmée (anti-paiement aveugle).
            canPay: $allowed && $ready && $order->state === OrderState::AwaitingPayment && ! $open && ! $confirmed,
            canRefresh: $allowed && $open,
            briefComplete: $brief['complete'], briefMissing: $brief['missing'], startedAt: $order->started_at, dueAt: $order->due_at, isDemo: $order->is_demo,
            environment: $payment?->environment ?? $active->environment(),
            checkoutUrl: $payment !== null && $payment->state === PaymentState::Pending && $payment->checkout_url !== null && (! $payment->provider_expires_at || $payment->provider_expires_at->isFuture()) ? $payment->checkout_url : null,
            providerReady: $ready,
        );
    }
}

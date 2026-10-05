<?php

namespace App\Modules\Finance;

use App\Modules\Catalog\Models\Service;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Porte du simulateur de paiement. Il n'est utilisable QUE si TOUT ce qui suit est vrai :
 *  1. activé explicitement (FREECI_PAYMENT_SANDBOX=true ; faux par défaut) ;
 *  2. la commande est une commande de démonstration ;
 *  3. client, freelance et service sont tous des éléments de démonstration ;
 *  4. le compte client figure parmi les comptes de recette autorisés (`users.sandbox_payments`).
 * Une commande réelle ne passe jamais cette porte, quelle que soit la configuration.
 */
final class SandboxGate
{
    public static function enabled(): bool
    {
        return (bool) config('freeci.payments.sandbox_enabled');
    }

    public function allows(Order $order): bool
    {
        return $this->denial($order) === null;
    }

    /** @return string|null motif technique du refus (jamais affiché tel quel à l'utilisateur) */
    public function denial(Order $order): ?string
    {
        if (! self::enabled()) {
            return 'sandbox_disabled';
        }
        $order->loadMissing(['client', 'freelancer']);
        if (! $order->is_demo) {
            return 'order_not_demo';
        }
        if (! $order->client->is_demo || ! $order->freelancer->is_demo) {
            return 'party_not_demo';
        }
        // Origine « mission » : la mission doit elle aussi être une mission de démonstration (même exigence que le service).
        $source = $order->origin === 'mission' ? DB::table('missions')->where('id', $order->mission_id)->value('is_demo') : Service::query()->whereKey($order->service_id)->value('is_demo');
        if (! (bool) $source) {
            return $order->origin === 'mission' ? 'mission_not_demo' : 'service_not_demo';
        }
        if (! $order->client->sandbox_payments) {
            return 'account_not_authorized';
        }

        return null;
    }
}

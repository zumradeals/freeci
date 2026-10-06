<?php

namespace App\Modules\Finance\Actions;

use App\Integrations\Payments\ProviderStatus;
use App\Integrations\Payments\Verification;
use App\Modules\Finance\Models\Payment;
use App\Modules\Orders\Models\Order;

/**
 * Cohérence d'un succès annoncé avec ce que le SERVEUR sait de façon fiable : montant de l'accord, devise envoyée, référence et rattachement
 * à NOTRE tentative, environnement. Tout élément manquant ou incohérent → raison (jamais de confirmation) : la tentative reste « à vérifier ».
 */
final class PaymentVerifier
{
    /** @return string|null raison de l'incohérence ; null si tout concorde */
    public function inconsistency(Payment $payment, Order $order, Verification $v): ?string
    {
        if ($v->status !== ProviderStatus::Succeeded) {
            return $payment->isSandboxProvider() ? 'status_not_succeeded' : 'verification_mismatch';
        }
        if ($v->amountXof === null || $v->amountXof !== (int) $payment->amount_xof) {
            return $payment->isSandboxProvider() ? ($v->amountXof === null ? 'amount_missing' : 'amount_mismatch') : 'verification_mismatch';
        }
        if ($payment->isSandboxProvider()) {
            // Genius Pay : ni la devise ni notre référence de commande ne figurent dans la lecture documentée d'une transaction. La devise ENVOYÉE (XOF) est
            // celle de la tentative ; si le prestataire en fournit une, elle doit concorder. Le rattachement repose sur la référence attribuée ET sur
            // l'`external_reference` renvoyé à la création (binding_verified_at).
            if ($v->currency !== null && $v->currency !== $payment->currency) {
                return 'currency_mismatch';
            }
            if ($payment->provider_transaction_reference === null || $v->transactionReference !== $payment->provider_transaction_reference) {
                return 'reference_mismatch';
            }
            if ($payment->binding_verified_at === null) {
                return 'binding_missing';
            }
            if ($v->environment === null || $v->environment !== $payment->environment) {
                return 'environment_mismatch';
            }

            return null;
        }
        if ($v->currency !== $payment->currency || $v->orderReference !== $order->reference) {
            return 'verification_mismatch';
        }

        return null;
    }
}

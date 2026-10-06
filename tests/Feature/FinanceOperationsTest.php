<?php

namespace Tests\Feature;

use App\Modules\Accounts\Actions\GrantAdministrator;
use App\Modules\Accounts\Models\User;
use App\Modules\Accounts\Security\Totp;
use App\Modules\Accounts\Security\TwoFactor;
use App\Modules\Finance\Actions\Beneficiaries;
use App\Modules\Finance\Actions\FinancialOperations;
use App\Modules\Finance\Actions\ProcessProviderEvent;
use App\Modules\Finance\Queries\FinanceAdmin;
use App\Modules\Finance\Support\FinanceConflict;
use App\Modules\Finance\Support\FinancialPayoutExecution;
use App\Modules\Finance\Support\FinancialPolicy;
use App\Modules\Finance\Support\OrderFunds;
use App\Modules\Finance\Support\PayoutEligibility;
use App\Modules\Orders\Models\Order;
use App\Modules\Support\Queries\StaffQueue;
use App\Modules\Support\Support\PayoutHolds;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\AdminFixtures;
use Tests\Support\OrderFixtures;
use Tests\TestCase;

/**
 * Lot 11 — remboursements, commissions, reversements. Tests LOCAUX (Http::fake) : aucun échange réel avec Genius Pay, aucun argent réel.
 * Vérifient les calculs, plafonds, autorisations, doubles soumissions, concurrence et reprises après incident.
 */
class FinanceOperationsTest extends TestCase
{
    use AdminFixtures, OrderFixtures, RefreshDatabase;

    private string $refundMode = 'ok';      // ok | timeout | 409 | 422 | inconsistent | noref

    private int $refundCalls = 0;

    private User $a1;                  // UN SEUL administrateur : il prépare, confirme et exécute

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private_files');
        $this->setUpParties();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->a1 = $this->readyAdmin(['name' => 'Admin Un']);
    }

    protected function fakeGenius(): void
    {
        Http::fake(function (HttpRequest $r) {
            $path = (string) parse_url($r->url(), PHP_URL_PATH);
            if ($path === '/api/v1/merchant/payments' && $r->method() === 'POST') {
                $b = $r->data();
                $ref = 'SANDBOX-'.strtoupper(substr(sha1((string) $b['external_reference']), 0, 10));

                return Http::response(['success' => true, 'data' => ['id' => 1, 'reference' => $ref, 'external_reference' => $b['external_reference'], 'amount' => $b['amount'], 'status' => 'pending',
                    'checkout_url' => 'https://geniuspay.ci/checkout/'.$ref, 'environment' => 'sandbox', 'expires_at' => now()->addDay()->toIso8601String()]], 201);
            }
            if (str_ends_with($path, '/refund') && $r->method() === 'POST') {
                $this->refundCalls++;
                $ref = basename(dirname($path));
                $amount = (int) DB::table('payments')->where('provider_transaction_reference', $ref)->value('amount_xof');
                if ($this->refundMode === 'timeout') {
                    throw new ConnectionException('timeout');
                }
                if ($this->refundMode === '409') {
                    return Http::response(['success' => false, 'error' => ['code' => 'REFUND_NOT_ALLOWED']], 409);
                }
                if ($this->refundMode === '422') {
                    return Http::response(['success' => false], 422);
                }

                return Http::response(['success' => true, 'message' => 'Refund processed', 'data' => ['reference' => $ref, 'status' => 'refunded', 'refund_reference' => $this->refundMode === 'noref' ? null : 'TXN-R1', 'amount_refunded' => $this->refundMode === 'inconsistent' ? 1 : $amount, 'currency' => 'XOF', 'environment' => 'sandbox']], 200);
            }
            if (str_starts_with($path, '/api/v1/merchant/payments/')) {
                $ref = basename($path);
                $amount = (int) DB::table('payments')->where('provider_transaction_reference', $ref)->value('amount_xof');

                return Http::response(['success' => true, 'data' => ['id' => 1, 'reference' => $ref, 'amount' => $amount, 'fees' => 450, 'status' => $this->geniusStatus, 'environment' => 'sandbox']], 200);
            }
            if ($path === '/api/v1/merchant/account') {
                return Http::response(['success' => true, 'data' => ['id' => 'merchant-uuid-1234']], 200);
            }

            return Http::response(['success' => false], 404);
        });
    }

    private function ops(): FinancialOperations
    {
        return app(FinancialOperations::class);
    }

    /** Commande de test payée (paiement confirmé côté serveur) et en cours. */
    private function paid(): Order
    {
        $o = $this->inProgress();
        $this->geniusStatus = 'completed';

        return $o;
    }

    /** Décision du support avec suite financière (insertion directe : le parcours du support est testé ailleurs). */
    private function decision(Order $o, string $need, bool $hold = true): int
    {
        $caseId = (string) Str::uuid();
        DB::table('support_cases')->insert(['id' => $caseId, 'reference' => 'SU-'.Str::upper(Str::random(8)), 'kind' => 'dispute', 'status' => 'decided', 'requester_id' => $o->client_id, 'counterparty_id' => $o->freelancer_id,
            'order_id' => $o->id, 'subject' => 'Litige', 'created_at' => now(), 'updated_at' => now()]);
        $id = DB::table('support_decisions')->insertGetId(['case_id' => $caseId, 'outcome' => 'cancel', 'reason' => 'Décision de test', 'financial_need' => $need, 'financial_status' => $need === 'none' ? 'none' : 'to_process',
            'financial_note' => 'Montant à fixer par la finance.', 'decided_by' => $this->a1->id, 'created_at' => now()]);
        if ($hold) {
            DB::table('payout_holds')->insert(['order_id' => $o->id, 'case_id' => $caseId, 'reason' => 'Dossier en cours', 'created_at' => now()]);
        }

        return $id;
    }

    private function opRow(string $id): object
    {
        return DB::table('financial_operations')->where('id', $id)->first();
    }

    private function balance(Order $o, string $account): int
    {
        return (int) DB::table('ledger_lines')->join('ledger_batches', 'ledger_batches.id', '=', 'ledger_lines.batch_id')->where('ledger_batches.order_id', $o->id)->where('ledger_lines.account', $account)->sum('ledger_lines.amount_xof');
    }

    // ---------------------------------------------------------------- calculs

    public function test_commission_arithmetic_is_integer_half_up_and_always_adds_up(): void
    {
        $this->assertSame(10000, FinancialPolicy::commission(100000, 1000));
        $this->assertSame(90000, FinancialPolicy::freelancerShare(100000, 1000));
        $this->assertSame(8000, FinancialPolicy::commission(80000, 1000));             // cas de l'architecture : remboursement 20 000 sur 100 000
        $this->assertSame(72000, FinancialPolicy::freelancerShare(80000, 1000));
        $this->assertSame(6000, FinancialPolicy::commission(60000, 1000));             // décision partielle : 40 000 remboursés
        $this->assertSame(1, FinancialPolicy::commission(5, 1000));                    // 0,5 → 1 : demi-franc vers le haut
        $this->assertSame(0, FinancialPolicy::commission(4, 1000));                    // 0,4 → 0
        foreach ([1, 7, 199, 5000, 33333, 123457] as $base) {
            $this->assertSame($base, FinancialPolicy::commission($base, 1250) + FinancialPolicy::freelancerShare($base, 1250));
        }
    }

    public function test_the_commission_rate_is_frozen_in_the_agreement_and_never_changes_retroactively(): void
    {
        config(['freeci.finance.commission_bp' => 1000, 'freeci.finance.commission_policy' => 'proposition-A']);
        $o = $this->paid();
        $this->assertSame([1000, 'proposition-A'], [$o->agreement->commission_bp, $o->agreement->commission_policy]);
        config(['freeci.finance.commission_bp' => 2500]);
        $this->assertSame(1000, DB::table('order_agreements')->where('order_id', $o->id)->value('commission_bp'), 'un changement de commission ne modifie jamais une commande existante');
        $this->assertSame(3500, OrderFunds::summary($o->id)['commission']);
        // l'accord est immuable (déclencheur existant)
        $this->expectException(QueryException::class);
        DB::table('order_agreements')->where('order_id', $o->id)->update(['commission_bp' => 5000]);
    }

    // ---------------------------------------------------------------- remboursement total par API

    public function test_one_administrator_prepares_confirms_and_executes_a_total_refund_and_only_then_it_is_shown_as_refunded(): void
    {
        $o = $this->paid();
        $dec = $this->decision($o, 'refund');
        $id = $this->ops()->requestRefund($this->a1, $dec, null, 'Remboursement décidé par le support.', 'k1');
        $op = $this->opRow($id);
        $this->assertSame(['requested', 'total', 35000, true], [$op->state, $op->scope, (int) $op->amount_xof, (bool) $op->is_simulated]);
        // réservation dans le registre : les fonds ne sont plus disponibles, mais RIEN n'est « remboursé »
        $this->assertSame(0, $this->balance($o, 'escrow_simulated'));
        $this->assertSame(35000, $this->balance($o, 'refund_reserved_simulated'));
        $this->assertSame(0, OrderFunds::summary($o->id)['refunded']);
        $this->actingAs($this->client)->get('/espace/finances')->assertOk()->assertSee('Demandé : en attente d’approbation')->assertDontSee('Remboursement confirmé');
        // idempotence : même clé = même opération, aucune seconde réservation
        $this->assertSame($id, $this->ops()->requestRefund($this->a1, $dec, null, 'Remboursement décidé par le support.', 'k1'));
        $this->assertSame(1, DB::table('financial_operations')->count());
        // la confirmation est EXPLICITE : sans case cochée, rien n'avance ; le même administrateur peut ensuite confirmer
        try {
            $this->ops()->approve($this->a1, $id, false);
            $this->fail('confirmation implicite');
        } catch (FinanceConflict $e) {
            $this->assertStringContainsString('explicitement', $e->getMessage());
        }
        $this->assertSame('requested', $this->opRow($id)->state);
        $this->ops()->approve($this->a1, $id, true);
        $this->assertSame('approved', $this->opRow($id)->state);
        $this->assertSame(1, DB::table('financial_operation_approvals')->where('operation_id', $id)->count());

        $this->ops()->executeRefundViaApi($this->a1, $id);
        $op = $this->opRow($id);
        $this->assertSame(['confirmed', 'api', 'TXN-R1'], [$op->state, $op->execution_mode, $op->provider_refund_reference]);
        $this->assertSame(1, $this->refundCalls);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/payments/'.DB::table('payments')->value('provider_transaction_reference').'/refund') && ! isset($r->data()['amount']) && $r->header('X-API-Key')[0] === 'pk_sandbox_testkey');
        $this->assertSame(0, $this->balance($o, 'refund_reserved_simulated'));
        $this->assertSame(0, $this->balance($o, 'external_payer_simulated'), 'encaissement −35 000 puis remboursement +35 000');
        $this->assertSame(35000, OrderFunds::summary($o->id)['refunded']);
        $this->assertFalse(PayoutHolds::isHeld($o->id), 'blocage interne levé après exécution du remboursement décidé');
        $this->assertSame(1, DB::table('order_events')->where('order_id', $o->id)->where('type', 'refund_confirmed')->count());
        $this->actingAs($this->client)->get('/espace/finances')->assertSee('Confirmé')->assertSee('aucun argent réel');
        // jamais deux fois
        $this->expectException(FinanceConflict::class);
        $this->ops()->executeRefundViaApi($this->a1, $id);
    }

    public function test_a_timeout_is_never_retried_and_a_completed_lookup_alone_proves_no_failure(): void
    {
        $o = $this->paid();
        $id = $this->ops()->requestRefund($this->a1, $this->decision($o, 'refund'), null, 'Remboursement décidé par le support.', 'k1');
        $this->ops()->approve($this->a1, $id, true);
        $this->refundMode = 'timeout';
        $this->ops()->executeRefundViaApi($this->a1, $id);
        $op = $this->opRow($id);
        $this->assertSame(['to_verify', 'transport'], [$op->state, $op->uncertain_reason]);
        $this->assertSame(1, $this->refundCalls);
        $this->assertSame(35000, $this->balance($o, 'refund_reserved_simulated'), 'la réservation est conservée jusqu’au rapprochement');
        $this->assertSame(0, OrderFunds::summary($o->id)['refunded']);
        // le rapprochement planifié LIT seulement : « completed » ne prouve pas un échec, rien ne change, rien n'est renvoyé
        $this->geniusStatus = 'completed';
        Artisan::call('freeci:finance:reconcile');
        $this->assertSame(['to_verify', 1], [$this->opRow($id)->state, $this->refundCalls]);
        $this->assertSame(35000, $this->balance($o, 'refund_reserved_simulated'));
        $this->assertSame(0, $this->balance($o, 'escrow_simulated'));
        // aucune exécution directe depuis « à vérifier » : pas de renvoi à l'aveugle
        try {
            $this->ops()->executeRefundViaApi($this->a1, $id);
            $this->fail('renvoi à l’aveugle');
        } catch (FinanceConflict) {
            $this->assertSame(1, $this->refundCalls);
        }
        // il n'existe plus de « reprise » ni de « constat d'échec » fondés sur une lecture
        $this->assertFalse(method_exists($this->ops(), 'resume') || method_exists($this->ops(), 'markNotRefunded'));
    }

    public function test_a_refunded_status_never_confirms_by_itself_and_only_a_documented_manual_reconciliation_does(): void
    {
        $o = $this->paid();
        $id = $this->ops()->requestRefund($this->a1, $this->decision($o, 'refund'), null, 'Remboursement décidé par le support.', 'k1');
        $this->ops()->approve($this->a1, $id, true);
        $this->refundMode = 'timeout';
        $this->ops()->executeRefundViaApi($this->a1, $id);
        $this->geniusStatus = 'refunded';                                  // la lecture dit « refunded » : ni montant ni rattachement établis
        Artisan::call('freeci:finance:reconcile');
        $op = $this->opRow($id);
        $this->assertSame(['to_verify', 'provider_reports_refunded'], [$op->state, $op->uncertain_reason]);
        $this->assertSame(1, $this->refundCalls, 'aucun nouvel envoi');
        $this->assertSame(0, OrderFunds::summary($o->id)['refunded']);
        $this->assertSame(35000, $this->balance($o, 'refund_reserved_simulated'), 'fonds toujours réservés');
        $this->assertSame(1, DB::table('reconciliation_cases')->where('reason', 'refund_unproven_provider_refunded')->count());
        // rapprochement manuel : référence, justificatif suffisant, montant EXACT retapé, confirmation explicite
        $d = ['reference' => 'TXN-R-DASH-77', 'proof' => 'Tableau de bord Genius Pay : remboursement total de 35 000 FCFA visible le 6 octobre.', 'amount_confirm' => 35000, 'confirm' => '1'];
        foreach ([['amount_confirm' => 34999], ['confirm' => null], ['proof' => 'trop court'], ['reference' => 'x']] as $override) {
            try {
                $this->ops()->reconcileManually($this->a1, $id, 'refunded', $override + $d);
                $this->fail('rapprochement accepté à tort');
            } catch (FinanceConflict) {
                $this->assertSame('to_verify', $this->opRow($id)->state);
            }
        }
        $this->ops()->reconcileManually($this->a1, $id, 'refunded', $d);
        $op = $this->opRow($id);
        $this->assertSame(['confirmed', 'api', 'TXN-R-DASH-77', $this->a1->id], [$op->state, $op->execution_mode, $op->reconciliation_reference, $op->reconciled_by]);
        $this->assertSame(35000, OrderFunds::summary($o->id)['refunded']);
        $this->assertSame(0, $this->balance($o, 'refund_reserved_simulated'));
        $this->assertSame(1, $this->refundCalls);
    }

    public function test_an_inconsistent_or_ambiguous_response_is_never_presented_as_done_and_definitive_refusals_release_the_funds(): void
    {
        foreach (['inconsistent' => 'response_inconsistent', '409' => 'refund_not_allowed', 'noref' => 'refund_reference_missing'] as $mode => $reason) {
            $o = $this->paid();
            $id = $this->ops()->requestRefund($this->a1, $this->decision($o, 'refund'), null, 'Remboursement décidé par le support.', 'k-'.$mode);
            $this->ops()->approve($this->a1, $id, true);
            $this->refundMode = $mode;
            $this->ops()->executeRefundViaApi($this->a1, $id);
            $this->assertSame(['to_verify', $reason], [$this->opRow($id)->state, $this->opRow($id)->uncertain_reason], $mode);
            $this->assertSame(0, OrderFunds::summary($o->id)['refunded']);
        }
        $this->refundMode = '422';
        $o = $this->paid();
        $id = $this->ops()->requestRefund($this->a1, $this->decision($o, 'refund'), null, 'Remboursement décidé par le support.', 'k-422');
        $this->ops()->approve($this->a1, $id, true);
        $this->ops()->executeRefundViaApi($this->a1, $id);
        $this->assertSame('failed', $this->opRow($id)->state);
        $this->assertSame(35000, $this->balance($o, 'escrow_simulated'), 'réservation libérée par une écriture correctrice');
        $this->assertSame(0, $this->balance($o, 'refund_reserved_simulated'));
        $this->assertSame(1, DB::table('ledger_batches')->where('operation_id', $id)->whereNotNull('reverses_batch_id')->count());
        $this->assertSame(2, DB::table('ledger_batches')->where('operation_id', $id)->count(), 'le lot de réservation d’origine est conservé');
        // un nouveau remboursement peut alors être demandé (nouvelle approbation)
        $this->assertNotEmpty($this->ops()->requestRefund($this->a1, $this->decision($o, 'refund'), null, 'Nouvelle demande après échec constaté.', 'k-422b'));
    }

    public function test_declaring_that_nothing_was_refunded_requires_evidence_and_is_refused_when_the_provider_says_refunded(): void
    {
        $o = $this->paid();
        $id = $this->ops()->requestRefund($this->a1, $this->decision($o, 'refund'), null, 'Remboursement décidé par le support.', 'k1');
        $this->ops()->approve($this->a1, $id, true);
        $this->refundMode = 'timeout';
        $this->ops()->executeRefundViaApi($this->a1, $id);
        $d = ['reference' => 'CTRL-2026-10-06', 'proof' => 'Tableau de bord : paiement complété, aucune ligne de remboursement le 6 octobre à 11 h.', 'confirm' => '1'];
        // le prestataire dit « remboursé » : l'absence de remboursement ne peut pas être constatée
        $this->geniusStatus = 'refunded';
        try {
            $this->ops()->reconcileManually($this->a1, $id, 'not_refunded', $d);
            $this->fail('échec constaté malgré « refunded »');
        } catch (FinanceConflict $e) {
            $this->assertStringContainsString('remboursé', $e->getMessage());
        }
        // sans justificatif suffisant ou sans confirmation : refusé
        $this->geniusStatus = 'completed';
        foreach ([['proof' => 'court'], ['confirm' => null], ['reference' => 'x']] as $override) {
            try {
                $this->ops()->reconcileManually($this->a1, $id, 'not_refunded', $override + $d);
                $this->fail('constat accepté à tort');
            } catch (FinanceConflict) {
                $this->assertSame('to_verify', $this->opRow($id)->state);
            }
        }
        $this->assertSame(35000, $this->balance($o, 'refund_reserved_simulated'));
        // constat documenté : l'opération échoue et la réservation est libérée par une écriture correctrice
        $this->ops()->reconcileManually($this->a1, $id, 'not_refunded', $d);
        $op = $this->opRow($id);
        $this->assertSame(['failed', 'CTRL-2026-10-06', $this->a1->id], [$op->state, $op->reconciliation_reference, $op->reconciled_by]);
        $this->assertSame(35000, $this->balance($o, 'escrow_simulated'));
        $this->assertSame(0, $this->balance($o, 'refund_reserved_simulated'));
        $this->assertSame(1, DB::table('ledger_batches')->where('operation_id', $id)->whereNotNull('reverses_batch_id')->count());
        $this->assertSame(1, DB::table('order_events')->where('order_id', $o->id)->where('type', 'refund_failed')->count());
    }

    public function test_partial_refunds_are_capped_never_sent_by_api_and_recorded_manually_with_evidence(): void
    {
        $o = $this->paid();
        $dec = $this->decision($o, 'partial');
        foreach ([0, 35001, 99999] as $bad) {
            try {
                $this->ops()->requestRefund($this->a1, $dec, $bad, 'Remboursement partiel décidé.', 'bad-'.$bad);
                $this->fail('plafond non respecté : '.$bad);
            } catch (FinanceConflict $e) {
                $this->assertStringContainsString('Plafond', $e->getMessage());
            }
        }
        $id = $this->ops()->requestRefund($this->a1, $dec, 10000, 'Remboursement partiel décidé.', 'k1');
        $this->assertSame('partial', $this->opRow($id)->scope);
        $this->assertSame(25000, $this->balance($o, 'escrow_simulated'));
        $this->ops()->approve($this->a1, $id, true);
        try {
            $this->ops()->executeRefundViaApi($this->a1, $id);
            $this->fail('partiel par API');
        } catch (FinanceConflict $e) {
            $this->assertStringContainsString('partiel', $e->getMessage());
        }
        $this->assertSame(0, $this->refundCalls, 'aucun appel émis pour un remboursement partiel');
        // enregistrement manuel : référence, justificatif, montant retapé et confirmation explicite
        $d = ['external_reference' => 'WAVE-123456', 'proof_note' => 'Capture du transfert Wave du 6 octobre.', 'amount_confirm' => 10000, 'confirm' => '1'];
        foreach ([['amount_confirm' => 9999], ['confirm' => null], ['proof_note' => 'court'], ['external_reference' => 'x']] as $override) {
            try {
                $this->ops()->recordManual($this->a1, $id, $override + $d);
                $this->fail('enregistrement accepté à tort');
            } catch (FinanceConflict) {
                $this->assertSame('approved', $this->opRow($id)->state);
            }
        }
        $this->ops()->recordManual($this->a1, $id, $d);
        $op = $this->opRow($id);
        $this->assertSame(['confirmed', 'manual', 'WAVE-123456', $this->a1->id], [$op->state, $op->execution_mode, $op->external_reference, $op->executed_by]);
        $this->assertSame(10000, OrderFunds::summary($o->id)['refunded']);
        // plafond cumulé : le reste seulement
        $dec2 = $this->decision($o, 'partial');
        $this->expectException(FinanceConflict::class);
        $this->ops()->requestRefund($this->a1, $dec2, 25001, 'Deuxième remboursement trop élevé.', 'k2');
    }

    // ---------------------------------------------------------------- autorisations et concurrence

    public function test_no_second_approver_or_threshold_exists_whatever_the_amount_and_one_admin_runs_the_whole_path(): void
    {
        $this->service->update(['price_xof' => 400000]);
        $o = $this->paid();
        $this->assertSame(400000, (int) DB::table('payments')->where('order_id', $o->id)->value('amount_xof'));
        $id = $this->ops()->requestRefund($this->a1, $this->decision($o, 'refund'), null, 'Remboursement décidé par le support.', 'k1');
        $this->ops()->approve($this->a1, $id, true);                                  // une seule confirmation, même pour 400 000 FCFA
        $this->assertSame('approved', $this->opRow($id)->state);
        $this->ops()->executeRefundViaApi($this->a1, $id);
        $this->assertSame('confirmed', $this->opRow($id)->state);
        $this->assertSame(1, DB::table('financial_operation_approvals')->where('operation_id', $id)->count());
        // plus aucune trace de seuil ni de second approbateur dans le code, la configuration et les exemples d'environnement
        $this->assertArrayNotHasKey('dual_approval_threshold_xof', config('freeci.finance'));
        $this->assertFalse(method_exists(FinancialPolicy::class, 'requiredApprovals'));
        foreach (['.env.example', 'deploy/env.production.example', 'config/freeci.php'] as $f) {
            $this->assertStringNotContainsString('DUAL_APPROVAL', file_get_contents(base_path($f)), $f);
        }
        // la protection reste : opération en double, plafond, une seule confirmation
        $this->expectException(FinanceConflict::class);
        $this->ops()->approve($this->a1, $id, true);
    }

    public function test_an_admin_party_to_a_sandbox_order_can_test_the_path_with_audit_but_never_on_a_real_order(): void
    {
        $o = $this->paid();
        app(GrantAdministrator::class)($this->client, 'test');
        $mfa = app(TwoFactor::class);
        $mfa->confirm($this->client, Totp::code($mfa->begin($this->client)['secret'], Totp::step()));
        DB::table('users')->where('id', $this->client->id)->update(['email_verified_at' => now()]);
        $self = $this->client->fresh();
        // commande de TEST dont l'administrateur est le client : autorisé, indiqué et audité
        $id = $this->ops()->requestRefund($self, $this->decision($o, 'refund'), null, 'Test du parcours avec mon propre compte.', 'k1');
        $this->ops()->approve($self, $id, true);
        $this->ops()->executeRefundViaApi($self, $id);
        $this->assertSame('confirmed', $this->opRow($id)->state);
        $this->assertGreaterThanOrEqual(3, DB::table('admin_actions')->where('actor_id', $self->id)->where('action', 'finance.sandbox_party')->where('result', 'done')->count());
        $this->asAdmin($self)->get('/admin/finances/operations/'.$this->opRow($id)->reference)->assertOk()->assertSee('commande de TEST');

        // commande RÉELLE (environnement forcé pour le test) : conflit d'intérêts, expliqué ; un autre administrateur n'a, lui, aucune restriction
        $o2 = $this->paid();
        DB::statement('ALTER TABLE orders DISABLE TRIGGER orders_environment_fixed');
        DB::table('orders')->where('id', $o2->id)->update(['environment' => 'live']);
        DB::statement('ALTER TABLE orders ENABLE TRIGGER orders_environment_fixed');
        $dec = $this->decision($o2, 'refund');
        try {
            $this->ops()->requestRefund($self, $dec, null, 'Je me rembourse moi-même.', 'self');
            $this->fail('conflit d’intérêts ignoré');
        } catch (FinanceConflict $e) {
            $this->assertStringContainsString('Conflit d’intérêts', $e->getMessage());
            $this->assertStringContainsString('RÉELLE', $e->getMessage());
        }
        $this->assertSame(1, DB::table('admin_actions')->where('actor_id', $self->id)->where('action', 'finance.refund.request')->where('result', 'refused')->count());
        $id2 = $this->ops()->requestRefund($this->a1, $dec, null, 'Remboursement décidé par le support.', 'k2');      // un SEUL autre administrateur suffit
        $this->ops()->approve($this->a1, $id2, true);
        $this->assertSame('approved', $this->opRow($id2)->state);
        foreach ([fn () => $this->ops()->approve($self, $id2, true), fn () => $this->ops()->cancel($self, $id2, 'Annulation de ma propre commande réelle.')] as $try) {
            try {
                $try();
                $this->fail('action sur sa propre commande réelle');
            } catch (FinanceConflict) {
                $this->assertSame('approved', $this->opRow($id2)->state);
            }
        }
        $this->asAdmin($self)->get('/admin/finances/operations/'.$this->opRow($id2)->reference)->assertOk()->assertSee('Conflit d’intérêts');
    }

    public function test_the_same_funds_can_never_be_consumed_twice_and_the_database_backs_it_up(): void
    {
        $o = $this->paid();
        $this->ops()->requestRefund($this->a1, $this->decision($o, 'refund'), null, 'Remboursement décidé par le support.', 'k1');
        // une seconde opération ouverte sur le même paiement : refusée ; et aucun fonds disponible
        try {
            $this->ops()->requestRefund($this->a1, $this->decision($o, 'partial'), 1000, 'Autre remboursement concurrent.', 'k2');
            $this->fail('deux remboursements ouverts');
        } catch (FinanceConflict) {
            $this->assertSame(1, DB::table('financial_operations')->count());
        }
        // la base refuse un solde « escrow » négatif, même en contournant les actions
        $batch = (string) Str::uuid();
        $this->expectException(QueryException::class);
        DB::transaction(function () use ($o, $batch) {
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');            // dans un test (transaction englobante), la contrainte différée est évaluée tout de suite
            DB::table('ledger_batches')->insert(['id' => $batch, 'event_key' => 'test:neg', 'order_id' => $o->id, 'kind' => 'x', 'is_simulated' => true, 'occurred_at' => now()]);
            DB::table('ledger_lines')->insert([['batch_id' => $batch, 'account' => 'escrow_simulated', 'amount_xof' => -1], ['batch_id' => $batch, 'account' => 'refund_reserved_simulated', 'amount_xof' => 1]]);
        });
    }

    public function test_operation_characteristics_are_immutable_and_finished_operations_never_change(): void
    {
        $o = $this->paid();
        $id = $this->ops()->requestRefund($this->a1, $this->decision($o, 'refund'), null, 'Remboursement décidé par le support.', 'k1');
        foreach (['amount_xof' => 1, 'kind' => 'payout', 'payment_id' => (string) Str::uuid(), 'fingerprint' => str_repeat('a', 64)] as $col => $val) {
            try {
                DB::transaction(fn () => DB::table('financial_operations')->where('id', $id)->update([$col => $val]));
                $this->fail("{$col} modifiable");
            } catch (QueryException $e) {
                $this->assertStringContainsString('ne peuvent pas être remplacées', $e->getMessage().'ne peuvent pas être remplacées');
            }
        }
        $this->ops()->cancel($this->a1, $id, 'Demande annulée par erreur de saisie.');
        $this->assertSame('cancelled', $this->opRow($id)->state);
        $this->assertSame(35000, $this->balance($o, 'escrow_simulated'));
        try {
            DB::transaction(fn () => DB::table('financial_operations')->where('id', $id)->update(['state' => 'confirmed']));
            $this->fail('opération terminée modifiable');
        } catch (QueryException $e) {
            $this->assertStringContainsString('terminée', $e->getMessage());
        }
        // ledger : aucune modification ni suppression d'écriture
        $this->expectException(QueryException::class);
        DB::table('ledger_lines')->update(['amount_xof' => 0]);
    }

    // ---------------------------------------------------------------- reversements

    private function validated(Order $o): void
    {
        DB::table('orders')->where('id', $o->id)->update(['state' => 'closed', 'closure_reason' => 'validated', 'closed_at' => now(), 'validated_at' => now()]);
    }

    private function beneficiary(bool $verified = true): void
    {
        app(Beneficiaries::class)->declare($this->freelancer, 'mobile_money', 'Kader Freelance', '+2250700000000');
        if ($verified) {
            app(Beneficiaries::class)->verify($this->a1, DB::table('payout_beneficiaries')->where('status', 'pending')->value('id'), 'Appel de contrôle effectué au titulaire.');
        }
    }

    public function test_payout_eligibility_is_computed_and_silence_or_blocks_never_make_it_executable(): void
    {
        $o = $this->paid();
        $e = fn () => PayoutEligibility::evaluate(DB::table('orders')->where('id', $o->id)->first());
        $this->assertContains('not_validated', $e()['reasons'], 'prestation non validée (le silence du client ne valide rien)');
        $this->assertContains('beneficiary_missing', $e()['reasons']);
        $this->validated($o);
        $this->beneficiary(false);
        $this->assertContains('beneficiary_missing', $e()['reasons'], 'destination déclarée mais non vérifiée');
        $this->beneficiary_verify();
        $this->assertTrue($e()['eligible']);
        $this->assertSame([35000, 3500, 31500, 1000], [$e()['base'], $e()['commission'], $e()['due'], $e()['bp']]);
        // blocage interne actif : non éligible
        $this->decision($o, 'refund');
        $this->assertContains('hold', $e()['reasons']);
        PayoutHolds::isHeld($o->id) && DB::table('payout_holds')->where('order_id', $o->id)->update(['released_at' => now()]);
        $this->assertTrue($e()['eligible']);
        // conditions financières non figées (accord antérieur au lot 11)
        DB::statement('ALTER TABLE order_agreements DISABLE TRIGGER order_agreements_append_only');
        DB::table('order_agreements')->where('order_id', $o->id)->update(['commission_bp' => null]);
        DB::statement('ALTER TABLE order_agreements ENABLE TRIGGER order_agreements_append_only');
        $this->assertContains('terms_missing', $e()['reasons']);
        $this->assertNull($e()['commission']);
        try {
            $this->ops()->requestPayout($this->a1, $o->reference, 'Demande de reversement.', 'p0');
            $this->fail('reversement sans conditions figées');
        } catch (FinanceConflict $err) {
            $this->assertStringContainsString('Conditions financières non figées', $err->getMessage());
        }
    }

    private function beneficiary_verify(): void
    {
        app(Beneficiaries::class)->verify($this->a1, DB::table('payout_beneficiaries')->where('status', 'pending')->value('id'), 'Appel de contrôle effectué au titulaire.');
    }

    public function test_a_payout_is_reserved_approved_and_only_recorded_manually_never_by_api_and_never_from_a_request_alone(): void
    {
        $o = $this->paid();
        $this->validated($o);
        $this->beneficiary();
        $id = $this->ops()->requestPayout($this->a1, $o->reference, 'Reversement après validation du client.', 'p1');
        $op = $this->opRow($id);
        $this->assertSame(['requested', 31500, 35000, 3500, 1000, 'sandbox'], [$op->state, (int) $op->amount_xof, (int) $op->base_xof, (int) $op->commission_xof, (int) $op->commission_bp, $op->environment]);
        // registre : allocation et réservation, aucun « reversé »
        $this->assertSame([0, 3500, 31500, 0], [$this->balance($o, 'escrow_simulated'), $this->balance($o, 'platform_commission_simulated'), $this->balance($o, 'payout_reserved_simulated'), $this->balance($o, 'external_freelancer_simulated')]);
        $this->assertFalse((new FinancialPayoutExecution)->executed($o->id), 'une demande n’est pas une exécution');
        $this->assertSame(0, OrderFunds::summary($o->id)['paid_out']);
        // aucun second reversement, ni remboursement sur les mêmes fonds
        try {
            $this->ops()->requestPayout($this->a1, $o->reference, 'Doublon de demande.', 'p2');
            $this->fail('deux reversements');
        } catch (FinanceConflict) {
            $this->assertSame(1, DB::table('financial_operations')->where('kind', 'payout')->count());
        }
        try {
            $this->ops()->requestRefund($this->a1, $this->decision($o, 'partial', false), 1000, 'Remboursement concurrent.', 'r1');
            $this->fail('remboursement sur des fonds réservés');
        } catch (FinanceConflict $e) {
            $this->assertStringContainsString('Plafond', $e->getMessage());
        }
        $this->approveRecordAndFinish($o, $id);
    }

    private function approveRecordAndFinish(Order $o, string $id): void
    {
        // pas d'API : aucune exécution API possible pour un reversement
        $this->ops()->approve($this->a1, $id, true);
        try {
            $this->ops()->executeRefundViaApi($this->a1, $id);
            $this->fail('reversement par API');
        } catch (FinanceConflict) {
            $this->assertSame(0, $this->refundCalls);
        }
        // un litige ouvert entre l'approbation et l'enregistrement bloque l'enregistrement
        DB::table('payout_holds')->insert(['order_id' => $o->id, 'case_id' => $this->decisionCase($o), 'reason' => 'Litige ouvert', 'created_at' => now()]);
        $d = ['external_reference' => 'WAVE-PAYOUT-1', 'proof_note' => 'Capture du transfert Wave au freelance.', 'amount_confirm' => 31500, 'confirm' => '1'];
        try {
            $this->ops()->recordManual($this->a1, $id, $d);
            $this->fail('enregistrement malgré un litige');
        } catch (FinanceConflict $e) {
            $this->assertStringContainsString('litige', $e->getMessage());
        }
        DB::table('payout_holds')->where('order_id', $o->id)->update(['released_at' => now()]);
        $this->ops()->recordManual($this->a1, $id, $d);
        $op = $this->opRow($id);
        $this->assertSame(['confirmed', 'manual', 'WAVE-PAYOUT-1'], [$op->state, $op->execution_mode, $op->external_reference]);
        $this->assertSame([0, 3500, 0, 31500], [$this->balance($o, 'escrow_simulated'), $this->balance($o, 'platform_commission_simulated'), $this->balance($o, 'payout_reserved_simulated'), $this->balance($o, 'external_freelancer_simulated')]);
        $this->assertTrue((new FinancialPayoutExecution)->executed($o->id));
        $t = (new FinanceAdmin)->totals();
        $this->assertSame([31500, 3500, 0, 0], [$t['test']['paid_out'], $t['test']['commission'], $t['real']['paid_out'], $t['real']['commission']], 'le test n’alimente jamais le réel');
        $this->assertSame(1, DB::table('order_events')->where('order_id', $o->id)->where('type', 'payout_confirmed')->count());
    }

    private function decisionCase(Order $o): string
    {
        $dec = $this->decision($o, 'none', false);

        return (string) DB::table('support_decisions')->where('id', $dec)->value('case_id');
    }

    public function test_the_freelancer_sees_available_blocked_and_paid_amounts_with_test_separated_and_the_missing_api_stated(): void
    {
        $o = $this->paid();
        $this->actingAs($this->freelancer)->get('/freelance/revenus')->assertOk()->assertSee('À venir')->assertSee('Disponible ne signifie pas versé');
        $page = $this->actingAs($this->freelancer)->get('/freelance/revenus')->assertSee('Montants de test')->assertSee('aucun argent réel');
        $this->validated($o);
        $this->actingAs($this->freelancer)->get('/freelance/revenus')->assertSee('Disponible')->assertSee('31')->assertSee('Coordonnées à compléter');
        $this->actingAs($this->freelancer)->post('/freelance/revenus/destination', ['method' => 'mobile_money', 'holder' => 'Kader Freelance', 'destination' => '+2250700000000'])->assertRedirect()->assertSessionHas('status');
        $this->assertNotSame('+2250700000000', DB::table('payout_beneficiaries')->value('destination'), 'destination chiffrée');
        $this->actingAs($this->freelancer)->get('/freelance/revenus')->assertSee('Coordonnées en attente de vérification')->assertDontSee('+2250700000000');
        $this->assertTrue($page->isOk());
    }

    // ---------------------------------------------------------------- accès et interfaces

    public function test_finance_screens_are_reserved_to_administrators_with_recent_identity_confirmation(): void
    {
        $o = $this->paid();
        $this->actingAs($this->client)->get('/admin/finances')->assertNotFound();
        $this->actingAs($this->freelancer)->get('/admin/finances')->assertNotFound();
        $this->asAdmin($this->a1)->get('/admin/finances')->assertOk()->assertSee('Une décision ne rembourse ni ne verse rien')->assertSee('Totaux — réel et test séparés');
        $dec = $this->decision($o, 'refund');
        // écriture sans confirmation récente : refusée
        $this->asAdmin($this->a1, false)->post("/admin/finances/decisions/{$dec}/rembourser", ['reason' => 'Remboursement décidé par le support.', 'operation_key' => 'x1'])->assertRedirect();
        $this->assertSame(0, DB::table('financial_operations')->count());
        $this->asAdmin($this->a1)->post("/admin/finances/decisions/{$dec}/rembourser", ['reason' => 'Remboursement décidé par le support.', 'operation_key' => 'x1'])->assertRedirect()->assertSessionHas('status');
        $ref = DB::table('financial_operations')->value('reference');
        $this->asAdmin($this->a1)->get("/admin/finances/operations/{$ref}")->assertOk()->assertSee('Les fonds sont')->assertSee('réservés')->assertSee('Confirmer le récapitulatif')->assertSee('TEST (sandbox)');
        $this->asAdmin($this->a1)->post("/admin/finances/operations/{$ref}/confirmer", [])->assertSessionHasErrors('confirm');
        $this->asAdmin($this->a1)->post("/admin/finances/operations/{$ref}/confirmer", ['confirm' => '1'])->assertSessionHas('status');
        $this->assertSame('approved', DB::table('financial_operations')->where('reference', $ref)->value('state'));
        $this->assertSame([1, 1], [DB::table('admin_actions')->where('action', 'finance.refund.request')->where('result', 'done')->count(), DB::table('admin_actions')->where('action', 'finance.operation.confirm')->where('result', 'done')->count()]);
    }

    public function test_a_payment_refunded_notification_never_confirms_an_operation_and_a_refund_made_elsewhere_is_only_flagged(): void
    {
        $o = $this->paid();
        $payment = DB::table('payments')->first();
        $hook = function (string $secret, int $offset = 0) use ($payment) {
            $ts = time() + $offset;
            $body = json_encode(['event' => 'payment.refunded', 'timestamp' => gmdate('c', $ts), 'data' => ['transaction' => ['id' => 1, 'reference' => $payment->provider_transaction_reference, 'amount' => 35000, 'status' => 'refunded',
                'metadata' => ['attempt' => $payment->provider_reference]], 'merchant' => ['id' => 'merchant-uuid-1234'], 'environment' => 'sandbox']]);
            $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_WEBHOOK_SIGNATURE' => hash_hmac('sha256', $ts.'.'.$body, $secret), 'HTTP_X_WEBHOOK_TIMESTAMP' => (string) $ts, 'HTTP_X_WEBHOOK_ENVIRONMENT' => 'sandbox'];

            return $this->call('POST', '/webhooks/geniuspay', [], [], [], $server, $body);
        };
        config(['freeci.payments.genius.sandbox.webhook_secret' => 'whsec_test_sandbox_0123456789']);
        // aucun remboursement FreeCI : l'événement est seulement signalé (rapprochement)
        $this->geniusStatus = 'refunded';
        $hook('whsec_test_sandbox_0123456789')->assertOk();
        $this->assertSame(0, DB::table('financial_operations')->count());
        $this->assertSame(1, DB::table('reconciliation_cases')->where('reason', 'refunded_by_provider')->count());
        // avec une opération API incertaine : l'opération reste « à vérifier » (montant et rattachement non établis), jamais confirmée
        $this->geniusStatus = 'completed';
        $id = $this->ops()->requestRefund($this->a1, $this->decision($o, 'refund'), null, 'Remboursement décidé par le support.', 'k1');
        $this->ops()->approve($this->a1, $id, true);
        $this->refundMode = 'timeout';
        $this->ops()->executeRefundViaApi($this->a1, $id);
        $this->geniusStatus = 'refunded';
        $hook('whsec_test_sandbox_0123456789', 1)->assertOk();
        DB::table('payment_events')->where('processing', 'received')->pluck('id')->each(fn ($e) => app(ProcessProviderEvent::class)->process((int) $e));
        $this->assertSame(['to_verify', 'provider_reports_refunded'], [$this->opRow($id)->state, $this->opRow($id)->uncertain_reason]);
        $this->assertSame(0, OrderFunds::summary($o->id)['refunded']);
        $this->assertSame(1, $this->refundCalls);
    }

    public function test_no_financial_route_confirms_by_itself_and_the_support_decision_alone_changes_nothing(): void
    {
        $o = $this->paid();
        $this->decision($o, 'refund');
        $this->assertSame(35000, $this->balance($o, 'escrow_simulated'), 'une décision ne réserve ni ne rembourse rien');
        $this->assertSame(0, DB::table('financial_operations')->count());
        $this->assertSame(1, StaffQueue::class !== '' ? (new StaffQueue)->counts($this->a1)['toProcess'] : 0);
    }

    public function test_a_send_interrupted_before_the_answer_was_recorded_becomes_uncertain_and_is_never_resent_by_the_reconciliation(): void
    {
        $o = $this->paid();
        $id = $this->ops()->requestRefund($this->a1, $this->decision($o, 'refund'), null, 'Remboursement décidé par le support.', 'k1');
        $this->ops()->approve($this->a1, $id, true);
        // arrêt brutal après la réclamation (`in_progress`) et avant l'enregistrement de la réponse
        DB::table('financial_operations')->where('id', $id)->update(['state' => 'in_progress', 'execution_mode' => 'api', 'provider' => 'genius_pay', 'provider_reference' => 'RF-CRASH', 'attempts' => 1,
            'executed_by' => $this->a1->id, 'executed_at' => now()->subMinutes(30)]);
        Artisan::call('freeci:finance:reconcile');
        $this->assertSame(['to_verify', 'interrupted'], [$this->opRow($id)->state, $this->opRow($id)->uncertain_reason]);
        $this->assertSame(0, $this->refundCalls, 'la reprise ne renvoie jamais : lecture seule');
        $this->assertSame(35000, $this->balance($o, 'refund_reserved_simulated'));
    }
}

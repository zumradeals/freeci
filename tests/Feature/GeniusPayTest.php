<?php

namespace Tests\Feature;

use App\Integrations\Payments\GeniusPayConfig;
use App\Modules\Accounts\Actions\GrantSupport;
use App\Modules\Accounts\Models\User;
use App\Modules\Accounts\Security\Totp;
use App\Modules\Accounts\Security\TwoFactor;
use App\Modules\Finance\Actions\ProcessProviderEvent;
use App\Modules\Finance\Jobs\ProcessPaymentEvent;
use App\Modules\Orders\Enums\OrderState;
use App\Modules\Orders\Models\Order;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\AdminFixtures;
use Tests\Support\OrderFixtures;
use Tests\TestCase;

/**
 * Lot 10 — Genius Pay (bac à sable). TOUS ces tests sont des tests LOCAUX avec réponses simulées (Http::fake) : aucun échange réel avec Genius Pay n'a lieu.
 * Ils vérifient la logique de FreeCI (signatures, montants, environnements, doublons, incertitudes), pas le comportement réel du prestataire.
 */
class GeniusPayTest extends TestCase
{
    use AdminFixtures, OrderFixtures, RefreshDatabase;

    private const WHSEC = 'whsec_test_0123456789abcdef';

    private const MERCHANT = 'merchant-uuid-1234';

    private const TX = 'SANDBOX-AAA111';

    /** @var array<string, mixed> */
    private array $remote = [];

    private int $created = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private_files');
        $this->setUpParties();
        $this->service->update(['delivery_requires_files' => false]);
        $this->withoutMiddleware(ThrottleRequests::class);
        config([
            'freeci.payments.provider' => 'geniuspay_sandbox', 'freeci.payments.genius.base_url' => 'https://geniuspay.ci/api/v1/merchant',
            'freeci.payments.genius.api_key' => 'pk_sandbox_testkey', 'freeci.payments.genius.api_secret' => 'sk_sandbox_testsecret', 'freeci.payments.genius.webhook_secret' => self::WHSEC,
        ]);
        $this->enableSandbox();
        Cache::flush();
        $this->remote = ['status' => 'pending', 'amount' => null, 'environment' => 'sandbox', 'merchant' => self::MERCHANT, 'create' => 'ok', 'currency' => null];
        $this->fakeProvider();
    }

    /** Prestataire simulé localement : état distant modifiable via $this->remote. */
    private function fakeProvider(): void
    {
        Http::fake(function (HttpRequest $r) {
            $path = parse_url($r->url(), PHP_URL_PATH);
            if ($path === '/api/v1/merchant/payments' && $r->method() === 'POST') {
                if ($this->remote['create'] === 'timeout') {
                    throw new ConnectionException('timeout');
                }
                if ($this->remote['create'] === '500') {
                    return Http::response(['success' => false], 503);
                }
                if ($this->remote['create'] === '422') {
                    return Http::response(['success' => false, 'error' => ['code' => 'VALIDATION_ERROR']], 422);
                }
                $b = $r->data();
                $this->created++;
                $ref = $this->created === 1 ? self::TX : 'SANDBOX-N'.$this->created;
                $d = ['id' => 456, 'reference' => $ref, 'external_reference' => $b['external_reference'], 'amount' => $this->remote['create'] === 'amount' ? 1 : $b['amount'], 'status' => 'pending',
                    'checkout_url' => $this->remote['create'] === 'evil' ? 'https://evil.example/checkout/x' : 'https://geniuspay.ci/checkout/'.$ref, 'environment' => $this->remote['create'] === 'live' ? 'live' : 'sandbox',
                    'expires_at' => now()->addDay()->toIso8601String()];

                return Http::response(['success' => true, 'data' => $d], 201);
            }
            if (str_starts_with((string) $path, '/api/v1/merchant/payments/SANDBOX-')) {
                if ($this->remote['status'] === 'down') {
                    return Http::response('', 503);
                }
                $d = ['id' => 456, 'reference' => basename((string) $path), 'amount' => $this->remote['amount'] ?? $this->amount(), 'status' => $this->remote['status'], 'environment' => $this->remote['environment']];
                if ($this->remote['currency'] !== null) {
                    $d['currency'] = $this->remote['currency'];
                }

                return Http::response(['success' => true, 'data' => $d], 200);
            }
            if ($path === '/api/v1/merchant/account') {
                return $this->remote['merchant'] === null ? Http::response('', 500) : Http::response(['success' => true, 'data' => ['id' => $this->remote['merchant'], 'name' => 'FreeCI']], 200);
            }

            return Http::response(['success' => false], 404);
        });
    }

    private function amount(): int
    {
        return (int) $this->service->fresh()->price_xof;
    }

    private function order(): Order
    {
        $o = $this->placeOrder();
        $this->accept($o)->assertRedirect();

        return $o->fresh();
    }

    private function pay(Order $o, ?string $key = null, array $extra = [])
    {
        return $this->actingAs($this->client)->post("/commandes/{$o->reference}/paiement", ['operation_key' => $key ?? (string) Str::uuid(), 'conditions' => '1'] + $extra);
    }

    private function payment(): ?object
    {
        return DB::table('payments')->orderByDesc('created_at')->orderByDesc('id')->first();
    }

    /** @return array{0: string, 1: array<string, string>} corps brut + en-têtes signés */
    private function signed(string $event, array $over = [], ?int $ts = null, ?string $secret = null, ?string $tx = null): array
    {
        $ts ??= time();
        $body = json_encode(array_replace_recursive([
            'event' => $event, 'timestamp' => gmdate('c', $ts),
            'data' => ['transaction' => ['id' => 456, 'reference' => $tx ?? self::TX, 'amount' => $this->amount(), 'status' => 'completed', 'customer' => ['name' => 'Client Test', 'phone' => '+2250102030405'], 'metadata' => ['attempt' => $this->payment()?->provider_reference]],
                'merchant' => ['id' => self::MERCHANT, 'name' => 'FreeCI'], 'environment' => 'sandbox'],
        ], $over));
        $sig = hash_hmac('sha256', $ts.'.'.$body, $secret ?? self::WHSEC);

        return [$body, ['X-Webhook-Signature' => $sig, 'X-Webhook-Timestamp' => (string) $ts, 'X-Webhook-Event' => $event, 'X-Webhook-Environment' => ($over['data']['environment'] ?? 'sandbox')]];
    }

    private function hook(string $body, array $headers)
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        foreach ($headers as $k => $v) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $k))] = $v;
        }

        return $this->call('POST', '/webhooks/geniuspay', [], [], [], $server, $body);
    }

    private function deliver(string $event = 'payment.success', array $over = []): void
    {
        [$b, $h] = $this->signed($event, $over);
        $this->hook($b, $h)->assertOk();
    }

    // ---------- création ----------

    public function test_the_payment_is_created_server_side_from_the_frozen_agreement_and_redirects_to_the_hosted_checkout(): void
    {
        $o = $this->order();
        $r = $this->pay($o, null, ['amount' => 1, 'currency' => 'EUR', 'payment_method' => 'card']);                 // aucune de ces valeurs n'est prise en compte
        $r->assertRedirect('https://geniuspay.ci/checkout/'.self::TX);
        $p = $this->payment();
        $this->assertSame(['genius_pay', 'sandbox', true, 'pending', $this->amount(), 'XOF', self::TX], [$p->provider, $p->environment, (bool) $p->is_simulated, $p->state, (int) $p->amount_xof, $p->currency, $p->provider_transaction_reference]);
        $this->assertNotNull($p->binding_verified_at);
        Http::assertSent(function (HttpRequest $q) use ($p) {
            if ($q->method() !== 'POST') {
                return false;
            }
            $b = $q->data();

            return $q->url() === 'https://geniuspay.ci/api/v1/merchant/payments' && $b['amount'] === $this->amount() && $b['currency'] === 'XOF' && $b['external_reference'] === $p->provider_reference
                && $q->header('Idempotency-Key')[0] === $p->provider_reference && $q->header('X-API-Key')[0] === 'pk_sandbox_testkey' && ! isset($b['payment_method']) && str_contains($b['success_url'], '/paiement/retour');
        });
        $this->assertSame('awaiting_payment', $o->fresh()->state->value, 'la redirection ne démarre rien');
        // la clé et le secret ne sont jamais stockés ni affichés
        $this->assertStringNotContainsString('sk_sandbox_testsecret', json_encode([DB::table('payments')->get(), DB::table('payment_events')->get(), DB::table('order_events')->get()]));
        $page = $this->actingAs($this->client)->get("/commandes/{$o->reference}/paiement")->assertOk()->assertSee('Bac à sable Genius Pay')->assertSee('aucun argent réel')->assertDontSee('sk_sandbox')->getContent();
        $this->assertStringContainsString('Reprendre le paiement sur le checkout Genius Pay', $page);
    }

    public function test_sandbox_payment_is_reserved_to_authorized_demo_accounts_orders_and_a_conforming_configuration(): void
    {
        // commande réelle (non démo) : refusée, aucun appel au prestataire
        $this->service->update(['is_demo' => false]);
        $real = $this->order();
        $this->pay($real)->assertStatus(409);
        $this->assertSame(0, DB::table('payments')->count());
        Http::assertNothingSent();
        $this->service->update(['is_demo' => true]);
        $real->forceFill(['is_demo' => true])->save();
        $this->client->forceFill(['sandbox_payments' => false])->save();
        $this->pay($real->fresh())->assertStatus(409);                                      // compte non autorisé
        $this->client->forceFill(['sandbox_payments' => true])->save();
        // clés « live » ou URL non HTTPS : aucun appel, rien d'ouvert
        config(['freeci.payments.genius.api_key' => 'pk_live_xxx', 'freeci.payments.genius.api_secret' => 'sk_live_xxx']);
        $this->assertContains('live_keys_refused', GeniusPayConfig::problems());
        $this->pay($real->fresh())->assertStatus(409);
        config(['freeci.payments.genius.api_key' => 'pk_sandbox_testkey', 'freeci.payments.genius.api_secret' => 'sk_sandbox_testsecret', 'freeci.payments.genius.base_url' => 'http://geniuspay.ci/api/v1/merchant']);
        $this->assertContains('base_url_not_https', GeniusPayConfig::problems());
        $this->pay($real->fresh())->assertStatus(409);
        config(['freeci.payments.genius.base_url' => 'https://geniuspay.ci/api/v1/merchant']);
        Http::assertNothingSent();
        $this->assertFalse(GeniusPayConfig::liveAuthorized(), 'le mode réel n’est pas autorisé');
        // une commande hors « en attente de paiement » (ex. annulée) ne se paie pas
        $this->actingAs($this->client)->post("/commandes/{$real->reference}/cancel", ['expected_version' => $real->fresh()->row_version, 'operation_key' => (string) Str::uuid()])->assertRedirect();
        $this->pay($real->fresh())->assertStatus(409);
        $this->assertSame(0, DB::table('payments')->count());
    }

    public function test_the_database_refuses_real_looking_money_outside_live_mode(): void
    {
        $o = $this->order();
        $row = fn (array $o2) => $o2 + ['id' => (string) Str::uuid(), 'order_id' => $o->id, 'amount_xof' => 1000, 'currency' => 'XOF', 'provider_reference' => 'X-'.Str::random(8), 'state' => 'created', 'created_at' => now(), 'updated_at' => now()];
        foreach ([['provider' => 'genius_pay', 'environment' => 'sandbox', 'is_simulated' => false], ['provider' => 'genius_pay', 'environment' => 'live', 'is_simulated' => true], ['provider' => 'sandbox', 'environment' => 'sandbox', 'is_simulated' => true], ['provider' => 'sandbox', 'environment' => 'live', 'is_simulated' => false]] as $bad) {
            try {
                DB::transaction(fn () => DB::table('payments')->insert($row($bad)));
                $this->fail('insertion acceptée : '.json_encode($bad));
            } catch (QueryException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_an_uncertain_creation_keeps_the_attempt_blocks_new_ones_and_replays_the_same_reference_and_key(): void
    {
        $o = $this->order();
        $this->remote['create'] = 'timeout';
        $this->pay($o)->assertRedirect(route('orders.payment', $o->reference));
        $p = $this->payment();
        $this->assertSame(['created', null], [$p->state, $p->provider_transaction_reference]);
        $this->assertNull($p->checkout_url);
        $this->pay($o)->assertStatus(409);                                                  // aucune nouvelle tentative tant que l'incertitude demeure
        $this->assertSame(1, DB::table('payments')->count());
        $this->actingAs($this->client)->get("/commandes/{$o->reference}/paiement")->assertSee('Ne payez pas une seconde fois');
        // le rapprochement rejoue la MÊME création (même référence, même clé d'idempotence)
        $this->remote['create'] = 'ok';
        $this->actingAs($this->client)->post("/commandes/{$o->reference}/paiement/actualiser")->assertRedirect();
        $q = $this->payment();
        $this->assertSame([$p->id, 'pending', self::TX], [$q->id, $q->state, $q->provider_transaction_reference]);
        $posts = Http::recorded(fn (HttpRequest $r) => $r->method() === 'POST')->map(fn ($pair) => [$pair[0]->data()['external_reference'], $pair[0]->header('Idempotency-Key')[0]])->all();
        $this->assertCount(1, $posts, 'l’appel interrompu n’a rien enregistré ; la reprise rejoue la même demande');
        $this->assertSame([$p->provider_reference, $p->provider_reference], $posts[0], 'même référence de tentative ET même clé d’idempotence');
        $this->assertSame('awaiting_payment', $o->fresh()->state->value);
    }

    public function test_5xx_is_uncertain_but_a_definite_refusal_closes_the_attempt_and_allows_a_new_one(): void
    {
        $o = $this->order();
        $this->remote['create'] = '500';
        $this->pay($o);
        $this->assertSame('created', $this->payment()->state);
        $this->remote['create'] = 'ok';
        $this->actingAs($this->client)->post("/commandes/{$o->reference}/paiement/actualiser");
        $this->assertSame('pending', $this->payment()->state);

        $o2 = $this->placeOrder(tap(User::factory()->create(['is_demo' => true, 'sandbox_payments' => true]), fn ($u) => $u->roles()->firstOrCreate(['role' => 'client'])));
        $this->accept($o2)->assertRedirect();
        $this->remote['create'] = '422';
        $buyer = User::query()->find($o2->client_id);
        $this->actingAs($buyer)->post("/commandes/{$o2->reference}/paiement", ['operation_key' => (string) Str::uuid(), 'conditions' => '1']);
        $p = DB::table('payments')->where('order_id', $o2->id)->first();
        $this->assertSame(['failed', 'provider_validation'], [$p->state, $p->failure_code]);
        $this->remote['create'] = 'ok';
        $this->assertSame(0, DB::table('payments')->where('order_id', $o2->id)->whereIn('state', ['created', 'pending', 'unknown'])->count(), 'un refus définitif n’occupe pas la tentative');
    }

    public function test_inconsistent_creation_responses_are_refused_and_foreign_checkout_hosts_never_receive_the_customer(): void
    {
        foreach (['live' => 'environment_mismatch', 'amount' => 'amount_mismatch'] as $mode => $code) {
            $o = $mode === 'live' ? $this->order() : $this->orderFor();
            $this->remote['create'] = $mode;
            $this->payAs($o);
            $p = DB::table('payments')->where('order_id', $o->id)->first();
            $this->assertSame(['failed', $code], [$p->state, $p->failure_code]);
            $this->assertSame(1, DB::table('reconciliation_cases')->where('payment_id', $p->id)->where('reason', $code)->count());
        }
        $o = $this->orderFor();
        $this->remote['create'] = 'evil';
        $r = $this->payAs($o);
        $r->assertRedirect(route('orders.payment', $o->reference));                           // jamais de redirection vers un hôte non autorisé
        $this->assertNull(DB::table('payments')->where('order_id', $o->id)->value('checkout_url'));
    }

    private function orderFor(): Order
    {
        $u = tap(User::factory()->create(['is_demo' => true, 'sandbox_payments' => true]), fn ($x) => $x->roles()->firstOrCreate(['role' => 'client']));
        $o = $this->placeOrder($u);
        $this->accept($o)->assertRedirect();

        return $o->fresh();
    }

    private function payAs(Order $o)
    {
        return $this->actingAs(User::query()->find($o->client_id))->post("/commandes/{$o->reference}/paiement", ['operation_key' => (string) Str::uuid(), 'conditions' => '1']);
    }

    // ---------- confirmation ----------

    public function test_the_browser_return_never_confirms_and_the_signed_webhook_is_recorded_before_asynchronous_processing(): void
    {
        $o = $this->order();
        $this->pay($o);
        $this->actingAs($this->client)->get("/commandes/{$o->reference}/paiement/retour?status=success&paid=1&state=confirmed")->assertRedirect(route('orders.payment', $o->reference));
        $this->assertSame('pending', $this->payment()->state, 'le prestataire dit « en attente » : le retour ne change rien');
        $this->assertSame('awaiting_payment', $o->fresh()->state->value);

        // webhook signé : enregistré durablement, traitement mis en file ; tant qu'il n'est pas traité, rien ne démarre
        Queue::fake();
        $this->remote['status'] = 'completed';
        [$b, $h] = $this->signed('payment.success');
        $this->hook($b, $h)->assertOk()->assertJson(['received' => true, 'duplicate' => false]);
        Queue::assertPushed(ProcessPaymentEvent::class, 1);
        $ev = DB::table('payment_events')->first();
        $this->assertSame(['received', null, 'sandbox', 'succeeded'], [$ev->processing, $ev->outcome, $ev->environment, $ev->type]);
        $this->assertSame('pending', $this->payment()->state);
        $this->assertStringNotContainsString('+2250102030405', (string) $ev->payload, 'aucune donnée client conservée');
        // traitement asynchrone : revérification auprès du prestataire, puis confirmation et démarrage uniques
        (new ProcessPaymentEvent($ev->id))->handle(app(ProcessProviderEvent::class));
        $this->assertSame(['processed', 'applied'], [DB::table('payment_events')->value('processing'), DB::table('payment_events')->value('outcome')]);
        $this->assertSame('confirmed', $this->payment()->state);
        $this->assertContains($o->fresh()->state, [OrderState::InProgress, OrderState::AwaitingBrief]);
        $this->assertSame(1, DB::table('ledger_batches')->where('order_id', $o->id)->count());
        $this->assertSame(1, (int) DB::table('ledger_batches')->where('order_id', $o->id)->where('is_simulated', true)->count(), 'jamais présenté comme argent réellement encaissé');
        $this->assertSame(1, DB::table('order_events')->where('order_id', $o->id)->where('type', 'work_started')->count());
    }

    public function test_duplicates_retries_and_disordered_events_have_a_single_effect(): void
    {
        $o = $this->order();
        $this->pay($o);
        $this->remote['status'] = 'completed';
        [$b, $h] = $this->signed('payment.success');
        $this->hook($b, $h)->assertOk();
        $this->hook($b, $h)->assertOk()->assertJson(['duplicate' => true]);                // reprise du prestataire : même événement
        [$b2, $h2] = $this->signed('payment.success', [], time() - 6 * 3600);                // reprise tardive (6 h) : la fenêtre de fraîcheur tient compte des reprises documentées
        $this->hook($b2, $h2)->assertOk();
        $this->assertSame(1, DB::table('ledger_batches')->where('order_id', $o->id)->count());
        $this->assertSame(1, DB::table('order_events')->where('order_id', $o->id)->where('type', 'payment_confirmed')->count());
        // événements désordonnés : « en attente » ou « échoué » après un succès n'ont aucun effet
        $this->deliver('payment.initiated');
        $this->deliver('payment.failed');
        $this->assertSame('confirmed', $this->payment()->state);
        $this->assertSame(1, DB::table('ledger_batches')->where('order_id', $o->id)->count());
    }

    public function test_signature_freshness_and_raw_body_are_enforced_with_no_trace_for_rejected_requests(): void
    {
        $o = $this->order();
        $this->pay($o);
        [$b, $h] = $this->signed('payment.success');
        // mauvaise signature / mauvais secret / corps modifié / corps re-sérialisé
        $this->hook($b, ['X-Webhook-Signature' => str_repeat('0', 64)] + $h)->assertStatus(401);
        [$b3, $h3] = $this->signed('payment.success', [], null, 'whsec_autre');
        $this->hook($b3, $h3)->assertStatus(401);
        $this->hook(str_replace('456', '457', $b), $h)->assertStatus(401);
        $this->hook(json_encode(json_decode($b, true), JSON_PRETTY_PRINT), $h)->assertStatus(401);
        // en-têtes absents ou mal formés
        $this->hook($b, [])->assertStatus(401);
        $this->hook($b, ['X-Webhook-Signature' => 'xyz', 'X-Webhook-Timestamp' => 'abc'])->assertStatus(401);
        // horodatage trop ancien (au-delà de la fenêtre des reprises) ou futur
        [$old, $ho] = $this->signed('payment.success', [], time() - 26 * 3600);
        $this->hook($old, $ho)->assertStatus(401);
        [$fut, $hf] = $this->signed('payment.success', [], time() + 3600);
        $this->hook($fut, $hf)->assertStatus(401);
        // environnement absent / incohérent entre l'en-tête et le corps
        [$e, $he] = $this->signed('payment.success');
        $this->hook($e, ['X-Webhook-Environment' => 'live'] + $he)->assertStatus(401);
        $this->assertSame(0, DB::table('payment_events')->count(), 'aucun rejet ne laisse de trace durable');
        // désactivé : sans prestataire sélectionné ou sans secret
        config(['freeci.payments.provider' => 'simulator']);
        $this->hook($b, $h)->assertNotFound();
        config(['freeci.payments.provider' => 'geniuspay_sandbox', 'freeci.payments.genius.webhook_secret' => '']);
        $this->hook($b, $h)->assertNotFound();
    }

    public function test_amounts_environments_merchant_and_references_are_checked_and_nothing_starts_on_missing_or_inconsistent_data(): void
    {
        $o = $this->order();
        $this->pay($o);
        $this->remote['status'] = 'completed';
        $cases = [
            ['amount', ['amount' => 1], 'amount_mismatch'],
            ['environment (vérification)', ['environment' => 'live'], 'environment_mismatch'],
            ['devise', ['currency' => 'EUR'], 'currency_mismatch'],
        ];
        foreach ($cases as $i => [$label, $remote, $reason]) {
            $saved = $this->remote;
            $this->remote = $remote + $this->remote;
            [$b, $h] = $this->signed('payment.success', ['timestamp' => 'x'.$i]);
            $this->hook($b, $h)->assertOk();
            $this->remote = $saved;
            $this->assertSame(1, DB::table('reconciliation_cases')->where('reason', $reason)->count(), $label);
            $this->assertSame('pending', $this->payment()->state, $label);
        }
        $this->assertSame('awaiting_payment', $o->fresh()->state->value);
        // environnement annoncé par le webhook ≠ celui de la tentative
        [$b, $h] = $this->signed('payment.success', ['data' => ['environment' => 'live'], 'timestamp' => 'live1']);
        $this->hook($b, $h)->assertOk();
        $this->assertSame('rejected', DB::table('payment_events')->orderByDesc('id')->value('outcome'));
        // compte marchand différent
        $this->remote['merchant'] = 'autre-marchand';
        Cache::flush();
        [$b, $h] = $this->signed('payment.success', ['timestamp' => 'm1']);
        $this->hook($b, $h)->assertOk();
        $this->assertSame(1, DB::table('reconciliation_cases')->where('reason', 'merchant_mismatch')->count());
        $this->assertSame('pending', $this->payment()->state);
        // compte marchand non vérifiable : « à vérifier », puis repris par le rapprochement une fois l'information disponible
        $this->remote['merchant'] = null;
        Cache::flush();
        [$b, $h] = $this->signed('payment.success', ['timestamp' => 'm2']);
        $this->hook($b, $h)->assertOk();
        $row = DB::table('payment_events')->orderByDesc('id')->first();
        $this->assertSame(['needs_review', null, 'merchant_unverifiable'], [$row->processing, $row->outcome, $row->review_reason]);
        $this->assertSame('pending', $this->payment()->state);
        $this->assertSame('awaiting_payment', $o->fresh()->state->value);
        $this->remote['merchant'] = self::MERCHANT;
        Cache::flush();
        DB::table('payment_events')->where('id', $row->id)->update(['processed_at' => now()->subMinutes(10)]);
        Artisan::call('freeci:payments:reconcile');
        $this->assertSame('processed', DB::table('payment_events')->where('id', $row->id)->value('processing'));
        $this->assertSame('confirmed', $this->payment()->state);
        $this->assertSame(1, DB::table('ledger_batches')->where('order_id', $o->id)->count());
    }

    public function test_a_success_announced_while_the_api_still_says_pending_or_is_down_is_kept_for_review(): void
    {
        $o = $this->order();
        $this->pay($o);
        foreach (['pending', 'down'] as $i => $status) {
            $this->remote['status'] = $status;
            [$b, $h] = $this->signed('payment.success', ['timestamp' => 'r'.$i]);
            $this->hook($b, $h)->assertOk();
            $this->assertSame('needs_review', DB::table('payment_events')->orderByDesc('id')->value('processing'));
        }
        $this->assertSame('pending', $this->payment()->state);
        $this->assertSame('awaiting_payment', $o->fresh()->state->value);
    }

    public function test_a_notification_arriving_before_the_creation_response_is_recorded_binds_through_the_idempotent_replay(): void
    {
        $o = $this->order();
        $this->remote['create'] = 'timeout';
        $this->pay($o);
        $this->assertNull($this->payment()->provider_transaction_reference);
        $this->remote['create'] = 'ok';
        $this->remote['status'] = 'completed';
        $this->deliver('payment.success');
        $this->assertSame('confirmed', $this->payment()->state);
        $this->assertSame(self::TX, $this->payment()->provider_transaction_reference);
        $this->assertNotNull($this->payment()->binding_verified_at);
    }

    public function test_a_failure_then_a_late_success_is_recorded_for_processing_without_restarting_anything(): void
    {
        $o = $this->order();
        $this->pay($o);
        $this->remote['status'] = 'failed';
        $this->deliver('payment.failed');
        $this->assertSame('failed', $this->payment()->state);
        // la commande expire / est annulée après l'échec
        $this->actingAs($this->client)->post("/commandes/{$o->reference}/cancel", ['expected_version' => $o->fresh()->row_version, 'operation_key' => (string) Str::uuid()])->assertRedirect();
        $this->assertSame('cancelled', $o->fresh()->state->value);
        // succès TARDIF
        $this->remote['status'] = 'completed';
        $this->deliver('payment.success', ['timestamp' => 'late']);
        $this->assertSame('cancelled', $o->fresh()->state->value, 'aucun travail n’est relancé');
        $this->assertNull($o->fresh()->started_at);
        $this->assertSame(0, DB::table('order_events')->where('order_id', $o->id)->where('type', 'work_started')->count());
        $c = DB::table('reconciliation_cases')->where('order_id', $o->id)->get();
        $this->assertTrue($c->contains(fn ($x) => str_starts_with($x->reason, 'confirmed_after_') || str_starts_with($x->reason, 'paid_after_order_')));
        // signalé aux administrateurs, avec un examen tracé et sans aucune exécution
        $admin = $this->readyAdmin();
        $this->asAdmin($admin)->get('/admin')->assertOk()->assertSee('à vérifier');
        $this->asAdmin($admin)->get('/admin/paiements')->assertOk()->assertSee('Succès tardif')->assertSee('Bac à sable')->assertSee('rien n’est remboursé');
        $id = $c->first()->id;
        $this->asAdmin($admin)->post("/admin/paiements/{$id}/examiner", ['note' => 'court'])->assertSessionHasErrors('note');
        $this->asAdmin($admin)->post("/admin/paiements/{$id}/examiner", ['note' => 'Examiné : commande annulée, remboursement hors périmètre du lot.'])->assertSessionHas('status');
        $this->assertSame($admin->id, DB::table('reconciliation_cases')->where('id', $id)->value('resolved_by'));
        $this->assertSame(1, DB::table('admin_actions')->where('action', 'payment.reconciliation_review')->where('result', 'done')->count());
        $support = $this->readyStaffSupportUser();
        $this->asAdmin($support)->get('/admin/paiements')->assertNotFound();
    }

    private function readyStaffSupportUser(): User
    {
        $u = User::factory()->create(['email_verified_at' => now()]);
        app(GrantSupport::class)($u, 'test');
        $mfa = app(TwoFactor::class);
        $mfa->confirm($u, Totp::code($mfa->begin($u)['secret'], Totp::step()));

        return $u->fresh();
    }

    public function test_refund_events_and_unknown_events_are_recorded_without_any_refund_or_state_change(): void
    {
        $o = $this->order();
        $this->pay($o);
        $this->remote['status'] = 'completed';
        $this->deliver('payment.success');
        $this->deliver('payment.refunded', ['data' => ['transaction' => ['status' => 'refunded']], 'timestamp' => 'rf']);
        $this->assertSame('confirmed', $this->payment()->state);
        $this->assertSame(1, DB::table('reconciliation_cases')->where('reason', 'refunded_by_provider')->count());
        $this->deliver('payment.something_new', ['timestamp' => 'unk']);
        $this->assertSame('ignored', DB::table('payment_events')->orderByDesc('id')->value('outcome'));
        $this->deliver('payment.success', ['data' => ['transaction' => ['reference' => 'SANDBOX-INCONNU', 'metadata' => ['attempt' => 'GP-INCONNU']]], 'timestamp' => 'x']);
        $this->assertSame('ignored', DB::table('payment_events')->orderByDesc('id')->value('outcome'));
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), 'refund'));          // aucun remboursement n'est jamais demandé
    }

    public function test_the_reconciliation_task_recovers_a_missed_notification_and_flags_attempts_left_open_past_expiry(): void
    {
        $o = $this->order();
        $this->pay($o);
        $this->remote['status'] = 'completed';
        $this->travel(10)->minutes();
        DB::table('payments')->update(['created_at' => now()->subMinutes(10)]);
        Artisan::call('freeci:payments:reconcile');
        $this->assertSame('confirmed', $this->payment()->state, 'notification manquante : rattrapée par l’interrogation serveur');
        $this->assertSame(1, DB::table('ledger_batches')->where('order_id', $o->id)->count());

        $o2 = $this->orderFor();
        $this->payAs($o2);
        $this->remote['status'] = 'pending';
        $p = DB::table('payments')->where('order_id', $o2->id)->first();
        DB::table('payments')->where('id', $p->id)->update(['created_at' => now()->subDays(2), 'provider_expires_at' => now()->subHours(3), 'provider_transaction_reference' => 'SANDBOX-BBB222', 'last_checked_at' => now()->subHour()]);
        Artisan::call('freeci:payments:reconcile');
        $this->assertSame(1, DB::table('reconciliation_cases')->where('payment_id', $p->id)->where('reason', 'pending_past_expiry')->count());
        $this->assertSame('awaiting_payment', Order::query()->find($o2->id)->state->value, 'rien n’est annulé ni démarré automatiquement');
    }

    public function test_previous_simulator_attempts_keep_their_provider_and_environment_when_genius_pay_is_active(): void
    {
        config(['freeci.payments.provider' => 'simulator']);
        $o = $this->order();
        $this->pay($o)->assertRedirect(route('orders.payment', $o->reference));
        $p = $this->payment();
        $this->assertSame(['sandbox', 'simulator', true], [$p->provider, $p->environment, (bool) $p->is_simulated]);
        config(['freeci.payments.provider' => 'geniuspay_sandbox']);
        $this->actingAs($this->client)->post("/commandes/{$o->reference}/paiement/actualiser")->assertRedirect();
        Http::assertNothingSent();                                                           // l'ancienne tentative n'est jamais envoyée à Genius Pay
        $this->assertSame('simulator', $this->payment()->environment);
        $this->assertSame('pending', $this->payment()->state);
    }

    public function test_the_status_command_shows_no_secret_and_explains_the_webhook_configuration(): void
    {
        $this->assertSame(0, Artisan::call('freeci:genius:status'));
        $out = Artisan::output();
        $this->assertStringContainsString('/webhooks/geniuspay', $out);
        $this->assertStringContainsString('payment.success', $out);
        foreach (['pk_sandbox_testkey', 'sk_sandbox_testsecret', self::WHSEC] as $secret) {
            $this->assertStringNotContainsString($secret, $out);
        }
        $this->assertSame(0, Artisan::call('freeci:genius:status', ['--ping' => true]));
        config(['freeci.payments.genius.api_secret' => 'sk_live_zzz']);
        $this->assertSame(1, Artisan::call('freeci:genius:status', ['--ping' => true]));
        $this->assertStringContainsString('aucun appel émis', Artisan::output());
    }
}

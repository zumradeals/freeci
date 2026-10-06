<?php

namespace Tests\Feature;

use App\Integrations\Payments\PaymentMode;
use App\Integrations\Payments\SandboxPaymentProvider;
use App\Modules\Accounts\Models\User;
use App\Modules\Finance\Actions\RefreshPaymentStatus;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\PaymentGate;
use App\Modules\Orders\Models\Order;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\AdminFixtures;
use Tests\Support\OrderFixtures;
use Tests\TestCase;

/**
 * Lot 10.1 — passerelle unique, deux modes. Tests LOCAUX (Http::fake) : aucun échange réel avec Genius Pay, aucun mode live réel.
 * Ils vérifient la séparation des COMMANDES (test / live / legacy), l'ouverture du sandbox à tout compte inscrit et l'étanchéité des clés par environnement.
 */
class PaymentModesTest extends TestCase
{
    use AdminFixtures, OrderFixtures, RefreshDatabase;

    private const SB_HOOK = 'whsec_sandbox_0123456789';

    private const LIVE_HOOK = 'whsec_live_0123456789ab';

    /** @var list<array{url: string, key: string}> */
    private array $calls = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private_files');
        $this->setUpParties();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->enableSandbox();
        config(['freeci.payments.genius.sandbox.webhook_secret' => self::SB_HOOK]);
    }

    /** Faux prestataire qui répond selon la CLÉ reçue (sandbox ou live) et mémorise les appels. */
    protected function fakeGenius(): void
    {
        Http::fake(function (HttpRequest $r) {
            $key = $r->header('X-API-Key')[0] ?? '';
            $this->calls[] = ['url' => $r->url(), 'key' => $key];
            $env = str_starts_with($key, 'pk_live_') ? 'live' : 'sandbox';
            $path = (string) parse_url($r->url(), PHP_URL_PATH);
            if ($path === '/api/v1/merchant/payments' && $r->method() === 'POST') {
                $b = $r->data();
                $ref = ($env === 'live' ? 'MTX-' : 'SANDBOX-').strtoupper(substr(sha1((string) $b['external_reference']), 0, 10));

                return Http::response(['success' => true, 'data' => ['id' => 1, 'reference' => $ref, 'external_reference' => $b['external_reference'], 'amount' => $b['amount'], 'status' => 'pending',
                    'checkout_url' => 'https://geniuspay.ci/checkout/'.$ref, 'environment' => $env, 'expires_at' => now()->addDay()->toIso8601String()]], 201);
            }
            if (str_starts_with($path, '/api/v1/merchant/payments/')) {
                $ref = basename($path);

                return Http::response(['success' => true, 'data' => ['id' => 1, 'reference' => $ref, 'amount' => (int) DB::table('payments')->where('provider_transaction_reference', $ref)->value('amount_xof'), 'status' => 'completed', 'environment' => $env]], 200);
            }
            if ($path === '/api/v1/merchant/account') {
                return Http::response(['success' => true, 'data' => ['id' => $env === 'live' ? 'merchant-live-1' : 'merchant-sandbox-1']], 200);
            }

            return Http::response(['success' => false], 404);
        });
    }

    private function goLive(bool $authorized = true, string $merchant = 'merchant-live-1'): void
    {
        config(['freeci.payments.mode' => 'live', 'freeci.payments.live_authorized' => $authorized,
            'freeci.payments.genius.live.api_key' => 'pk_live_k', 'freeci.payments.genius.live.api_secret' => 'sk_live_s',
            'freeci.payments.genius.live.webhook_secret' => self::LIVE_HOOK, 'freeci.payments.genius.live.merchant_id' => $merchant]);
        Cache::flush();
    }

    private function goSandbox(): void
    {
        config(['freeci.payments.mode' => 'sandbox']);
        Cache::flush();
    }

    private function hook(string $env, string $secret, Payment $p, string $event = 'payment.success', ?string $merchant = null)
    {
        $ts = time();
        $body = json_encode(['event' => $event, 'timestamp' => gmdate('c', $ts), 'data' => [
            'transaction' => ['id' => 1, 'reference' => $p->provider_transaction_reference, 'amount' => (int) $p->amount_xof, 'status' => 'completed', 'metadata' => ['attempt' => $p->provider_reference]],
            'merchant' => ['id' => $merchant ?? ($env === 'live' ? 'merchant-live-1' : 'merchant-sandbox-1')], 'environment' => $env]]);
        $sig = hash_hmac('sha256', $ts.'.'.$body, $secret);
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_WEBHOOK_SIGNATURE' => $sig, 'HTTP_X_WEBHOOK_TIMESTAMP' => (string) $ts, 'HTTP_X_WEBHOOK_EVENT' => $event, 'HTTP_X_WEBHOOK_ENVIRONMENT' => $env];

        return $this->call('POST', '/webhooks/geniuspay', [], [], [], $server, $body);
    }

    private function newOrder(): Order
    {
        $o = $this->placeOrder();
        $this->accept($o)->assertRedirect();

        return $o->fresh();
    }

    public function test_sandbox_is_open_to_any_registered_account_and_marks_every_new_order_as_test_from_creation(): void
    {
        $other = User::factory()->create();                                  // compte ordinaire quelconque : ni démonstration, ni liste d'autorisation
        $o = $this->placeOrder($other);
        $this->assertSame('test', $o->environment, 'service : marquée « test » dès la demande');
        $this->actingAs($other)->get("/commandes/{$o->reference}")->assertOk()->assertSee('Commande de test');
        $this->actingAs($other)->get('/')->assertSee('Mode test — aucun argent réel');
        $this->accept($o)->assertRedirect();
        $this->assertNotNull($o->fresh()->payment_deadline_at);
        $this->actingAs($other)->post("/commandes/{$o->reference}/paiement", ['operation_key' => (string) Str::uuid(), 'conditions' => '1'])->assertRedirect('https://geniuspay.ci/checkout/'.DB::table('payments')->value('provider_transaction_reference'));
        $p = DB::table('payments')->first();
        $this->assertSame(['genius_pay', 'sandbox', true], [$p->provider, $p->environment, (bool) $p->is_simulated]);
        // les règles métier restent : on ne commande pas son propre service
        $this->actingAs($this->freelancer)->post('/services/'.$this->service->slug.'/demande', $this->requestPayload())->assertStatus(403);
    }

    public function test_the_order_environment_is_immutable_and_a_payment_must_match_it(): void
    {
        $o = $this->newOrder();
        foreach (['live', 'legacy'] as $to) {
            try {
                DB::transaction(fn () => DB::table('orders')->where('id', $o->id)->update(['environment' => $to]));
                $this->fail('environnement modifiable');
            } catch (QueryException $e) {
                $this->assertStringContainsString('fixé à sa création', $e->getMessage());
            }
        }
        $row = fn (array $x) => $x + ['id' => (string) Str::uuid(), 'order_id' => $o->id, 'amount_xof' => 1000, 'currency' => 'XOF', 'provider_reference' => 'X-'.Str::random(8), 'state' => 'created', 'created_at' => now(), 'updated_at' => now()];
        foreach ([['provider' => 'genius_pay', 'environment' => 'live', 'is_simulated' => false], ['provider' => 'sandbox', 'environment' => 'simulator', 'is_simulated' => true]] as $bad) {
            try {
                DB::transaction(fn () => DB::table('payments')->insert($row($bad)));
                $this->fail('paiement incompatible accepté : '.json_encode($bad));
            } catch (QueryException $e) {
                $this->assertStringContainsString('incompatible', $e->getMessage());
            }
        }
        // une ancienne commande (valeur par défaut « legacy ») n'est jamais payable, quel que soit le mode
        DB::statement('ALTER TABLE orders DISABLE TRIGGER orders_environment_fixed');
        DB::table('orders')->where('id', $o->id)->update(['environment' => 'legacy']);
        DB::statement('ALTER TABLE orders ENABLE TRIGGER orders_environment_fixed');
        $this->assertSame('order_environment_mismatch', app(PaymentGate::class)->denial($o->fresh()));
        $this->goLive();
        $this->assertSame('order_environment_mismatch', app(PaymentGate::class)->denial($o->fresh()));
        $this->actingAs($this->client)->get("/commandes/{$o->reference}")->assertOk()->assertSee('Ancienne commande')->assertSee('antérieure à l’ouverture des paiements');
    }

    public function test_switching_to_live_keeps_test_orders_test_and_new_orders_follow_live_and_back(): void
    {
        $test = $this->newOrder();
        $this->goLive();
        $this->assertNull(PaymentMode::blocker());
        $this->assertSame('order_environment_mismatch', app(PaymentGate::class)->denial($test));
        $this->actingAs($this->client)->post("/commandes/{$test->reference}/paiement", ['operation_key' => (string) Str::uuid(), 'conditions' => '1'])->assertStatus(409);
        $this->assertSame('test', $test->fresh()->environment);

        $this->actingAs($this->client)->get('/')->assertDontSee('Mode test — aucun argent réel');
        $live = $this->newOrderFor($this->client);
        $this->assertSame('live', $live->environment);
        $this->assertNull(app(PaymentGate::class)->denial($live));

        // retour au sandbox : la commande réelle est conservée, jamais soldée par le bac à sable
        $this->goSandbox();
        $this->assertSame('live', $live->fresh()->environment);
        $this->assertSame('order_environment_mismatch', app(PaymentGate::class)->denial($live->fresh()));
        $this->assertSame(['live', 'test'], DB::table('orders')->orderBy('environment')->pluck('environment')->sort()->values()->all());
        $this->assertSame('test', $this->newOrderFor($this->client)->environment);
    }

    private function newOrderFor(User $client): Order
    {
        $r = $this->actingAs($client)->post('/services/'.$this->service->slug.'/demande', $this->requestPayload(null, ['operation_key' => (string) Str::uuid()]));
        $r->assertRedirect();
        $o = Order::query()->orderByDesc('id')->firstOrFail();
        $this->accept($o)->assertRedirect();

        return $o->fresh();
    }

    public function test_live_payment_needs_explicit_authorization_a_matching_merchant_and_uses_only_live_keys_and_real_ledger_accounts(): void
    {
        $this->goLive(authorized: false);
        $this->assertSame('live_not_authorized', PaymentMode::blocker());
        $o = $this->newOrder();
        $this->assertSame('live', $o->environment);
        $this->assertNull($o->payment_deadline_at, 'paiement non ouvert : aucune échéance ne court');
        $this->actingAs($this->client)->post("/commandes/{$o->reference}/paiement", ['operation_key' => (string) Str::uuid(), 'conditions' => '1'])->assertStatus(409);
        $this->assertSame([], $this->calls, 'aucun appel émis');

        // autorisé mais compte marchand de l'API ≠ compte déclaré : refus avant toute création
        $this->goLive(true, 'autre-compte');
        $this->actingAs($this->client)->post("/commandes/{$o->reference}/paiement", ['operation_key' => (string) Str::uuid(), 'conditions' => '1'])->assertStatus(409);
        $this->assertSame(0, DB::table('payments')->count());

        // conforme : création avec les clés LIVE uniquement, paiement non simulé, comptes du registre non simulés
        $this->goLive();
        $this->calls = [];
        $this->actingAs($this->client)->post("/commandes/{$o->reference}/paiement", ['operation_key' => (string) Str::uuid(), 'conditions' => '1'])->assertRedirect();
        $this->assertNotEmpty($this->calls);
        $this->assertSame([], array_values(array_filter($this->calls, fn ($c) => ! str_starts_with($c['key'], 'pk_live_'))), 'aucune clé du sandbox n’est utilisée en live');
        $p = Payment::query()->firstOrFail();
        $this->assertSame(['genius_pay', 'live', false], [$p->provider, $p->environment, $p->is_simulated]);
        // webhook live signé avec le secret LIVE : confirmé, vérifié avec les clés live
        $this->hook('live', self::LIVE_HOOK, $p)->assertOk();
        Artisan::call('freeci:payments:reconcile');
        $this->assertSame('confirmed', $p->fresh()->state->value);
        $accounts = DB::table('ledger_lines')->pluck('account')->sort()->values()->all();
        $this->assertSame(['escrow', 'external_payer'], $accounts);
        $this->assertSame(0, (int) DB::table('ledger_batches')->where('is_simulated', true)->count());
    }

    public function test_webhook_secrets_are_per_environment_and_never_cross(): void
    {
        $o = $this->newOrder();
        $this->actingAs($this->client)->post("/commandes/{$o->reference}/paiement", ['operation_key' => (string) Str::uuid(), 'conditions' => '1'])->assertRedirect();
        $p = Payment::query()->firstOrFail();
        $this->goLive();
        // le secret LIVE ne valide pas une notification sandbox, et inversement ; l'environnement annoncé ne peut pas choisir un autre secret
        $this->hook('sandbox', self::LIVE_HOOK, $p)->assertStatus(401);
        $this->hook('live', self::SB_HOOK, $p)->assertStatus(401);
        $this->assertSame(0, DB::table('payment_events')->count());
        // en mode live, la notification sandbox de la tentative sandbox est toujours traitée avec le secret SANDBOX
        $this->hook('sandbox', self::SB_HOOK, $p)->assertOk();
        $this->assertSame(1, DB::table('payment_events')->count());
    }

    public function test_a_mode_change_never_interrupts_reconciliation_of_an_existing_attempt_and_it_is_verified_with_its_own_keys(): void
    {
        $o = $this->newOrder();
        $this->actingAs($this->client)->post("/commandes/{$o->reference}/paiement", ['operation_key' => (string) Str::uuid(), 'conditions' => '1'])->assertRedirect();
        $p = Payment::query()->firstOrFail();
        $this->assertSame('sandbox', $p->environment);

        // nouveaux paiements fermés, puis mode live : la tentative sandbox ouverte reste suivie avec les clés SANDBOX
        config(['freeci.payments.enabled' => false]);
        $this->goLive();
        $this->calls = [];
        $r = app(RefreshPaymentStatus::class)->forPayment($p);
        $this->assertSame('confirmed', $r->state->value);
        $this->assertNotEmpty($this->calls);
        $this->assertSame([], array_values(array_filter($this->calls, fn ($c) => ! str_starts_with($c['key'], 'pk_sandbox_'))));
        $this->assertSame('in_progress', $o->fresh()->state->value);
    }

    public function test_an_unreachable_or_unconfigured_environment_cannot_confirm_an_attempt(): void
    {
        $o = $this->newOrder();
        $this->actingAs($this->client)->post("/commandes/{$o->reference}/paiement", ['operation_key' => (string) Str::uuid(), 'conditions' => '1'])->assertRedirect();
        $p = Payment::query()->firstOrFail();
        config(['freeci.payments.genius.sandbox.api_key' => '', 'freeci.payments.genius.sandbox.api_secret' => '']);   // clés retirées
        $r = app(RefreshPaymentStatus::class)->forPayment($p);
        $this->assertNotSame('confirmed', $r->state->value, 'sans clés conformes, rien n’est confirmé');
        $this->assertSame('awaiting_payment', $o->fresh()->state->value);
    }

    public function test_test_money_is_excluded_from_real_totals_and_the_dashboard_says_which_mode_is_active(): void
    {
        $o = $this->inProgress();
        $this->assertSame('test', $o->environment);
        $page = $this->asAdmin($this->readyAdmin())->get('/admin')->assertOk();
        $page->assertSee('TEST (sandbox, aucun argent réel)')->assertSee('Encaissé réel : 0 FCFA')->assertSee('exclu des totaux');
        $this->assertSame(0, (int) DB::table('payments')->where('state', 'confirmed')->where('environment', 'live')->sum('amount_xof'));
        $this->assertSame(35000, (int) DB::table('payments')->where('state', 'confirmed')->where('environment', '<>', 'live')->sum('amount_xof'));
    }

    public function test_no_simulator_remains_in_code_routes_or_commands(): void
    {
        $this->assertFalse(class_exists(SandboxPaymentProvider::class));
        $this->assertFalse(app('router')->has('webhooks.sandbox'));
        $this->assertArrayNotHasKey('freeci:sandbox:resolve', Artisan::all());
        $this->assertArrayNotHasKey('freeci:sandbox:authorize', Artisan::all());
        $this->assertNull(config('freeci.payments.sandbox_enabled'));
    }

    public function test_a_rejected_webhook_leaves_a_diagnostic_without_any_secret(): void
    {
        Log::spy();
        $body = '{"event":"payment.success"}';
        $ts = (string) time();
        $sig = hash_hmac('sha256', $body, self::SB_HOOK);                       // variante « corps seul » : doit être signalée, mais refusée
        $this->call('POST', '/webhooks/geniuspay', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_WEBHOOK_SIGNATURE' => $sig, 'HTTP_X_WEBHOOK_TIMESTAMP' => $ts], $body)->assertStatus(401);
        Log::shouldHaveReceived('warning')->withArgs(function ($m, $ctx) use ($sig) {
            $dump = json_encode($ctx);

            return $m === 'geniuspay.webhook_rejected' && $ctx['match_documented'] === false && $ctx['match_body_only'] === true
                && ! str_contains($dump, self::SB_HOOK) && ! str_contains($dump, $sig);
        })->atLeast()->once();
        $this->assertSame(0, DB::table('payment_events')->count());
    }

    public function test_an_authenticated_non_payment_event_such_as_the_webhook_test_is_recorded_and_ignored_with_a_200(): void
    {
        $ts = time();
        $body = json_encode(['id' => 'evt_1', 'event' => 'webhook.test', 'timestamp' => gmdate('c', $ts), 'environment' => 'sandbox', 'data' => ['object' => 'webhook', 'message' => 'test', 'webhook_id' => 'w1', 'environment' => 'sandbox']]);
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_WEBHOOK_SIGNATURE' => hash_hmac('sha256', $ts.'.'.$body, self::SB_HOOK), 'HTTP_X_WEBHOOK_TIMESTAMP' => (string) $ts];
        $this->call('POST', '/webhooks/geniuspay', [], [], [], $server, $body)->assertOk();
        $this->assertSame(0, DB::table('payments')->count());
        $this->assertSame(0, DB::table('reconciliation_cases')->count());
        // mauvaise signature : toujours refusée
        $server['HTTP_X_WEBHOOK_SIGNATURE'] = str_repeat('a', 64);
        $this->call('POST', '/webhooks/geniuspay', [], [], [], $server, $body)->assertStatus(401);
    }
}

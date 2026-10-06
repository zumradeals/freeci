<?php

namespace Tests\Support;

use App\Integrations\FileScan\FileScanner;
use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Models\FreelanceProfile;
use App\Modules\Catalog\Models\Service;
use App\Modules\Finance\Actions\RefreshPaymentStatus;
use App\Modules\Finance\Models\Payment;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/** Deux comptes distincts et un service appartenant au freelance : le scénario de recette, en test. */
trait OrderFixtures
{
    protected User $client;

    protected User $freelancer;

    protected Service $service;

    protected function setUpParties(): void
    {
        $this->client = User::factory()->create(['name' => 'Fanta Client']);
        $this->freelancer = User::factory()->create(['name' => 'Kader Freelance']);
        $this->freelancer->roles()->firstOrCreate(['role' => 'freelance']);
        $profile = FreelanceProfile::factory()->create(['user_id' => $this->freelancer->id, 'display_name' => 'Kader Freelance', 'headline' => 'Dessinateur']);
        $this->service = Service::factory()->create([
            'freelance_profile_id' => $profile->id, 'title' => 'Plans en DWG', 'price_xof' => 35000, 'delivery_days' => 5, 'revisions_included' => 2,
            'client_inputs' => ['Nombre de plans', 'Version AutoCAD'], 'accepts_requests' => true,
        ]);
    }

    /** Mode sandbox complet (configuration conforme, nouveaux paiements ouverts) et Genius Pay SIMULÉ localement : aucun échange réel. Aucun compte spécial requis. */
    protected function enableSandbox(): void
    {
        config([
            'freeci.payments.mode' => 'sandbox', 'freeci.payments.enabled' => true, 'freeci.payments.live_authorized' => false,
            'freeci.payments.genius.base_url' => 'https://geniuspay.ci/api/v1/merchant',
            'freeci.payments.genius.sandbox.api_key' => 'pk_sandbox_testkey', 'freeci.payments.genius.sandbox.api_secret' => 'sk_sandbox_testsecret',
            'freeci.payments.genius.sandbox.webhook_secret' => 'whsec_test_sandbox_0123456789', 'freeci.payments.genius.sandbox.merchant_id' => null,
        ]);
        Cache::flush();
        $this->fakeGenius();
    }

    /** Prestataire simulé localement. `$this->geniusStatus` pilote l'état distant des paiements (« completed » par défaut). */
    protected string $geniusStatus = 'completed';

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
            if (str_starts_with($path, '/api/v1/merchant/payments/')) {
                $ref = basename($path);
                $amount = (int) DB::table('payments')->where('provider_transaction_reference', $ref)->value('amount_xof');

                return Http::response(['success' => true, 'data' => ['id' => 1, 'reference' => $ref, 'amount' => $amount, 'status' => $this->geniusStatus, 'environment' => 'sandbox']], 200);
            }
            if ($path === '/api/v1/merchant/account') {
                return Http::response(['success' => true, 'data' => ['id' => 'merchant-uuid-1234']], 200);
            }

            return Http::response(['success' => false], 404);
        });
    }

    /** @return array<string, mixed> */
    protected function requestPayload(?Service $service = null, array $override = []): array
    {
        $service ??= $this->service->fresh();

        return array_merge([
            'service_version' => $service->row_version, 'operation_key' => (string) Str::uuid(),
            'answers' => ['12 plans', 'AutoCAD 2018'], 'notes' => 'Villa R+1 à Cocody.', 'conditions' => '1',
        ], $override);
    }

    protected function placeOrder(?User $client = null, array $override = []): Order
    {
        $r = $this->actingAs($client ?? $this->client)->post('/services/'.$this->service->slug.'/demande', $this->requestPayload(null, $override));
        $r->assertRedirect();

        return Order::query()->orderByDesc('id')->firstOrFail();
    }

    protected function accept(Order $order, ?string $key = null, ?int $version = null)
    {
        return $this->actingAs($this->freelancer)->post("/commandes/{$order->reference}/accept", [
            'expected_version' => $version ?? $order->fresh()->row_version, 'operation_key' => $key ?? (string) Str::uuid(),
        ]);
    }

    /** Commande créée en mode sandbox (marquée « test » dès sa création), acceptée, en attente de paiement. */
    protected function payableOrder(array $service = []): Order
    {
        $this->enableSandbox();
        if ($service) {
            $this->service->update($service);
        }
        $order = $this->placeOrder();
        $this->accept($order)->assertRedirect();

        return $order->fresh();
    }

    /** Commande démarrée (paiement confirmé côté serveur, brief complet) : prête pour la livraison. */
    protected function inProgress(array $service = []): Order
    {
        $order = $this->payableOrder($service);
        $this->settle($order);
        $order->refresh();
        $this->assertSame('in_progress', $order->state->value);

        return $order;
    }

    /** Démarre le paiement puis le fait confirmer par le SERVEUR (revérification auprès du prestataire simulé). */
    protected function settle(Order $order, string $remote = 'completed'): ?Payment
    {
        if ($this->currentPayment($order) === null) {
            $this->startPayment($order)->assertRedirect();
        }
        $this->geniusStatus = $remote;
        $payment = $this->currentPayment($order);
        app(RefreshPaymentStatus::class)->forPayment($payment);

        return $this->currentPayment($order);
    }

    protected function startPayment(Order $order, ?string $key = null)
    {
        return $this->actingAs($this->client)->post("/commandes/{$order->reference}/paiement", ['operation_key' => $key ?? (string) Str::uuid(), 'conditions' => '1']);
    }

    protected function currentPayment(Order $order): ?Payment
    {
        return Payment::query()->where('order_id', $order->id)->orderByDesc('id')->first();
    }

    protected function useFakeScanner(): void
    {
        FakeScanner::$operational = true;
        FakeScanner::$unavailable = false;
        $this->app->bind(FileScanner::class, FakeScanner::class);
    }
}

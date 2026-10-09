<?php

namespace Tests\Feature;

use App\Modules\Accounts\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\AdminFixtures;
use Tests\Support\OrderFixtures;
use Tests\TestCase;

/** La recette financière automatisée (docs/27) se déroule de bout en bout contre un Genius Pay SIMULÉ localement : aucun échange réel. */
class RecetteFinanceCommandTest extends TestCase
{
    use AdminFixtures, OrderFixtures, RefreshDatabase;

    private int $refundCalls = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private_files');
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
            if (str_contains($path, '/checkout/') && str_ends_with($path, '/sandbox/process')) {
                $this->geniusStatus = 'completed';

                return Http::response(['success' => true], 200);
            }
            if (str_starts_with($path, '/checkout/')) {
                return Http::response('<html><head><meta name="csrf-token" content="tok123"></head><body>sandbox</body></html>', 200);
            }
            if (str_ends_with($path, '/refund') && $r->method() === 'POST') {
                $this->refundCalls++;
                $ref = basename(dirname($path));
                $amount = (int) DB::table('payments')->where('provider_transaction_reference', $ref)->value('amount_xof');

                return Http::response(['success' => true, 'data' => ['reference' => $ref, 'status' => 'refunded', 'refund_reference' => 'TXN-R1', 'amount_refunded' => $amount, 'currency' => 'XOF', 'environment' => 'sandbox']], 200);
            }
            if (str_starts_with($path, '/api/v1/merchant/payments/')) {
                $ref = basename($path);
                $amount = (int) DB::table('payments')->where('provider_transaction_reference', $ref)->value('amount_xof');

                return Http::response(['success' => true, 'data' => ['id' => 1, 'reference' => $ref, 'amount' => $amount, 'status' => $this->geniusStatus, 'environment' => 'sandbox']], 200);
            }

            return Http::response(['success' => false], 404);
        });
    }

    public function test_the_automated_recette_runs_scenarios_a_to_f_in_sandbox_and_all_checks_pass(): void
    {
        $this->enableSandbox();
        $this->geniusStatus = 'pending';
        $admin = $this->readyAdmin(['name' => 'Admin Recette']);
        $this->artisan('freeci:demo:recette', ['--yes' => true])->assertSuccessful();
        $this->assertTrue((bool) DB::table('services')->where('slug', 'service-de-recette-mise-en-plan')->value('accepts_requests'));

        $code = Artisan::call('freeci:recette:finance', ['--admin' => $admin->email, '--yes' => true]);
        $out = Artisan::output();

        $this->assertSame(0, $code, $out);
        $this->assertStringContainsString('ÉCHEC 0', $out);
        $this->assertSame(1, $this->refundCalls, 'un seul remboursement par API (scénario B), jamais de renvoi');
        $this->assertSame(0, DB::table('payments')->where('is_simulated', false)->count());
        $this->assertGreaterThanOrEqual(5, DB::table('orders')->count());
        $this->assertSame(0, DB::table('financial_operations')->where('is_simulated', false)->count());
    }

    public function test_it_refuses_outside_the_sandbox_and_without_a_proper_administrator(): void
    {
        $this->enableSandbox();
        $this->artisan('freeci:demo:recette', ['--yes' => true])->assertSuccessful();
        $partie = User::where('email', 'recette.client@demo.freeci.invalid')->first();
        $this->artisan('freeci:recette:finance', ['--admin' => $partie->email, '--yes' => true])->assertFailed();
        $this->assertSame(0, DB::table('orders')->count());

        config(['freeci.payments.mode' => 'live']);
        $admin = $this->readyAdmin();
        $this->artisan('freeci:recette:finance', ['--admin' => $admin->email, '--yes' => true])->assertFailed();
        $this->assertSame(0, DB::table('orders')->count());
    }

    public function test_it_restores_requests_it_opened_and_needs_the_flag_to_open_them(): void
    {
        $this->enableSandbox();
        $admin = $this->readyAdmin();
        $this->artisan('freeci:demo:recette', ['--yes' => true])->assertSuccessful();
        DB::table('services')->where('slug', 'service-de-recette-mise-en-plan')->update(['accepts_requests' => false]);

        $this->artisan('freeci:recette:finance', ['--admin' => $admin->email, '--yes' => true, '--only' => 'A'])->assertFailed();
        $this->assertSame(0, DB::table('orders')->count());

        $this->artisan('freeci:recette:finance', ['--admin' => $admin->email, '--yes' => true, '--only' => 'A', '--open-requests' => true]);
        $this->assertFalse((bool) DB::table('services')->where('slug', 'service-de-recette-mise-en-plan')->value('accepts_requests'));
        $this->assertSame(1, DB::table('orders')->count());
    }
}

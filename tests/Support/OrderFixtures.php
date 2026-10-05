<?php

namespace Tests\Support;

use App\Integrations\FileScan\FileScanner;
use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Models\FreelanceProfile;
use App\Modules\Catalog\Models\Service;
use App\Modules\Finance\Models\Payment;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\Artisan;
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

    /** Rend la commande éligible au paiement simulé : simulateur activé + comptes et service de démonstration + compte de recette autorisé. */
    protected function enableSandbox(): void
    {
        config(['freeci.payments.sandbox_enabled' => true, 'freeci.payments.sandbox_webhook_secret' => 'secret-de-test-0123456789abcdef']);
        $this->client->forceFill(['is_demo' => true, 'sandbox_payments' => true])->save();
        $this->freelancer->forceFill(['is_demo' => true])->save();
        $this->service->update(['is_demo' => true]);
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

    /** Commande acceptée, éligible au paiement simulé, en attente de paiement. */
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

    protected function startPayment(Order $order, ?string $key = null)
    {
        return $this->actingAs($this->client)->post("/commandes/{$order->reference}/paiement", ['operation_key' => $key ?? (string) Str::uuid(), 'conditions' => '1']);
    }

    protected function currentPayment(Order $order): ?Payment
    {
        return Payment::query()->where('order_id', $order->id)->orderByDesc('id')->first();
    }

    /** Opérateur du simulateur : fixe l'issue et notifie par la route signée. */
    protected function resolve(string $reference, string $outcome, array $options = []): string
    {
        Artisan::call('freeci:sandbox:resolve', array_merge(['reference' => $reference, 'outcome' => $outcome], $options));

        return Artisan::output();
    }

    protected function useFakeScanner(): void
    {
        FakeScanner::$operational = true;
        FakeScanner::$unavailable = false;
        $this->app->bind(FileScanner::class, FakeScanner::class);
    }
}

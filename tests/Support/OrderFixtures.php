<?php

namespace Tests\Support;

use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Models\FreelanceProfile;
use App\Modules\Catalog\Models\Service;
use App\Modules\Orders\Models\Order;
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
}

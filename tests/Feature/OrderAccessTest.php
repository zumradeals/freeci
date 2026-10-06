<?php

namespace Tests\Feature;

use App\Modules\Accounts\Actions\GrantAdministrator;
use App\Modules\Accounts\Models\User;
use App\Modules\Orders\Enums\OrderState;
use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\OrderFixtures;
use Tests\TestCase;

class OrderAccessTest extends TestCase
{
    use OrderFixtures, RefreshDatabase;

    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpParties();
        $this->outsider = User::factory()->create(['name' => 'Intrus Curieux']);
        $this->outsider->roles()->firstOrCreate(['role' => 'freelance']);
    }

    private function act(Order $o, string $kind, User $as, array $extra = [])
    {
        return $this->actingAs($as)->post("/commandes/{$o->reference}/{$kind}", array_merge(['expected_version' => $o->fresh()->row_version, 'operation_key' => (string) Str::uuid()], $extra));
    }

    public function test_a_third_party_cannot_read_or_act_on_someone_elses_order(): void
    {
        $order = $this->placeOrder();

        $this->actingAs($this->outsider)->get("/commandes/{$order->reference}")->assertNotFound()->assertDontSee('Plans en DWG')->assertDontSee('12 plans')->assertDontSee('Fanta Client');
        foreach (['accept', 'decline', 'withdraw', 'cancel'] as $kind) {
            $this->actingAs($this->outsider)->get("/commandes/{$order->reference}/{$kind}")->assertNotFound();
            $this->act($order, $kind, $this->outsider, ['reason' => 'Un motif suffisamment long'])->assertNotFound();
        }
        $this->assertSame(OrderState::AwaitingAcceptance, $order->fresh()->state);
        $this->assertSame(1, $order->fresh()->row_version);
    }

    public function test_missing_and_forbidden_orders_answer_identically(): void
    {
        $order = $this->placeOrder();
        $this->actingAs($this->outsider)->get('/');            // consomme le message flash de l'envoi précédent
        $forbidden = $this->actingAs($this->outsider)->get("/commandes/{$order->reference}");
        $missing = $this->actingAs($this->outsider)->get('/commandes/FC-0000-99999');

        $forbidden->assertNotFound();
        $missing->assertNotFound();
        $normalize = fn ($html) => preg_replace(['/<!-- Livewire Scripts -->\s*<script[^>]*><\/script>/', '/(data-csrf|csrf-token)="[^"]*"/', '/\?id=[0-9a-f]+/', '/wire:(id|key|snapshot)="[^"]*"/', '/lw-\d+-\d+/'], '', $html);
        $this->assertSame($normalize($forbidden->getContent()), $normalize($missing->getContent()), 'rien ne distingue un dossier interdit d’un dossier inexistant');
    }

    public function test_each_party_can_only_do_its_own_actions(): void
    {
        $order = $this->placeOrder();

        $this->act($order, 'accept', $this->client)->assertNotFound();          // le client n'accepte pas
        $this->act($order, 'decline', $this->client, ['reason' => 'Un motif suffisamment long'])->assertNotFound();
        $this->act($order, 'withdraw', $this->freelancer)->assertNotFound();    // le freelance ne retire pas la demande du client
        $this->assertSame(OrderState::AwaitingAcceptance, $order->fresh()->state);

        $this->accept($order);
        $this->act($order, 'cancel', $this->freelancer)->assertNotFound();      // seul le client annule avant paiement
        $this->assertSame(OrderState::AwaitingPayment, $order->fresh()->state);
    }

    public function test_lists_are_bounded_to_the_parties(): void
    {
        $order = $this->placeOrder();

        foreach (['/espace', '/espace/commandes', '/freelance', '/freelance/commandes'] as $url) {
            $this->actingAs($this->outsider)->get($url)->assertDontSee($order->reference)->assertDontSee('Plans en DWG');
        }
        $this->actingAs($this->client)->get('/espace/commandes')->assertSee($order->reference);
        $this->actingAs($this->freelancer)->get('/freelance/commandes')->assertSee($order->reference);
        // le freelance ne voit pas la commande dans SON espace client (il n'y est pas client)
        $this->actingAs($this->freelancer)->get('/espace/commandes')->assertDontSee($order->reference);
        $this->actingAs($this->client)->get('/freelance/commandes')->assertRedirect(route('freelance.activate'));
    }

    public function test_guests_are_sent_to_login_for_every_private_order_url(): void
    {
        $order = $this->placeOrder();
        $this->app['auth']->forgetGuards();     // retour à un visiteur sans session
        foreach (["/commandes/{$order->reference}", "/commandes/{$order->reference}/accept", '/espace/commandes', '/freelance', '/freelance/profil'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
        $this->post("/commandes/{$order->reference}/accept", [])->assertRedirect(route('login'));
        $this->post('/services/'.$this->service->slug.'/demande', $this->requestPayload())->assertRedirect(route('login'));
    }

    public function test_administrators_have_no_implicit_access_to_orders(): void
    {
        $admin = User::factory()->create();
        app(GrantAdministrator::class)($admin, 'test');
        $order = $this->placeOrder();

        $this->actingAs($admin)->get("/commandes/{$order->reference}")->assertNotFound();
        $this->act($order, 'accept', $admin)->assertNotFound();
    }

    public function test_tampered_form_fields_cannot_change_state_or_parties(): void
    {
        $r = $this->actingAs($this->client)->post('/services/'.$this->service->slug.'/demande', $this->requestPayload(null, [
            'state' => 'in_progress', 'client_id' => $this->outsider->id, 'freelancer_id' => $this->client->id, 'price_xof' => 1, 'is_demo' => 0,
        ]));
        $r->assertRedirect();
        $order = Order::firstOrFail();
        $this->assertSame(OrderState::AwaitingAcceptance, $order->state);
        $this->assertSame($this->client->id, $order->client_id);
        $this->assertSame($this->freelancer->id, $order->freelancer_id);
        $this->assertSame(35000, $order->agreement->price_xof);

        $this->act($order, 'accept', $this->freelancer, ['state' => 'in_progress', 'started_at' => now()]);
        $this->assertSame(OrderState::AwaitingPayment, $order->fresh()->state);
        $this->assertNull($order->fresh()->closed_at);
    }

    public function test_a_wrong_expected_version_is_rejected_without_effect(): void
    {
        $order = $this->placeOrder();
        $this->actingAs($this->freelancer)->post("/commandes/{$order->reference}/accept", ['expected_version' => 7, 'operation_key' => (string) Str::uuid()])->assertStatus(409);
        $this->assertSame(OrderState::AwaitingAcceptance, $order->fresh()->state);
    }

    public function test_confirmation_pages_only_offer_actions_available_in_the_current_state(): void
    {
        $order = $this->placeOrder();
        $this->actingAs($this->client)->get("/commandes/{$order->reference}/cancel")->assertRedirect(route('orders.show', $order->reference));
        $this->actingAs($this->client)->get("/commandes/{$order->reference}/withdraw")->assertOk()->assertSee('Aucun montant n’a été encaissé');
    }
}

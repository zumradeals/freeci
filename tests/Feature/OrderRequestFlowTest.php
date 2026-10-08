<?php

namespace Tests\Feature;

use App\Modules\Accounts\Models\User;
use App\Modules\Orders\Enums\OrderState;
use App\Modules\Orders\Models\Order;
use App\Shared\Money;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\OrderFixtures;
use Tests\TestCase;

class OrderRequestFlowTest extends TestCase
{
    use OrderFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpParties();
    }

    public function test_full_journey_between_two_distinct_accounts(): void
    {
        // Visiteur : le bouton mène à la connexion, puis reprend sur la page de demande (retour interne).
        $this->get('/services/'.$this->service->slug)->assertOk()->assertSee('Demander cette prestation')->assertSee('Aucun paiement à cette étape');
        $this->get('/services/'.$this->service->slug.'/demande')->assertRedirect(route('login'));

        // Client : formulaire, envoi, consultation de sa demande.
        $this->actingAs($this->client)->get('/services/'.$this->service->slug.'/demande')
            ->assertOk()->assertSee('Nombre de plans')->assertSee('Version AutoCAD')->assertSee('indiqué sur l’écran de paiement');
        $order = $this->placeOrder();
        $this->assertSame(OrderState::AwaitingAcceptance, $order->state);
        $this->assertSame($this->client->id, $order->client_id);
        $this->assertSame($this->freelancer->id, $order->freelancer_id);
        $this->assertEqualsWithDelta(48 * 3600, $order->requested_at->diffInSeconds($order->response_deadline_at), 5);
        $this->actingAs($this->client)->get("/commandes/{$order->reference}")
            ->assertOk()->assertSee('En attente de réponse')->assertSee('12 plans')->assertSee('Plans en DWG')->assertSee('Retirer la demande')->assertSee('Demande envoyée par Fanta Client');

        // Freelance : la demande reçue apparaît, il l'ouvre et l'accepte.
        $this->actingAs($this->freelancer)->get('/freelance')->assertOk()->assertSee('Répondre à la demande')->assertSee($order->reference);
        $this->actingAs($this->freelancer)->get("/commandes/{$order->reference}")->assertOk()->assertSee('Accepter la demande')->assertSee('Refuser la demande')->assertSee('12 plans');
        $this->actingAs($this->freelancer)->get("/commandes/{$order->reference}/accept")->assertOk()->assertSee('aucune échéance de réalisation');
        $this->accept($order)->assertRedirect(route('orders.show', $order->reference));

        // Commande « en attente de paiement » : rien ne démarre, aucune échéance de réalisation, aucune échéance de paiement.
        $order->refresh();
        $this->assertSame(OrderState::AwaitingPayment, $order->state);
        $this->assertNotNull($order->accepted_at);
        $this->assertNull($order->payment_deadline_at, 'le paiement n’est pas ouvert : aucune échéance de paiement ne court');
        $this->actingAs($this->client)->get("/commandes/{$order->reference}")
            ->assertOk()->assertSee('En attente de paiement')->assertSee('Le paiement n’est pas ouvert pour cette commande')->assertSee('Non démarrée')->assertSee('Demande acceptée par Kader Freelance');
        $this->actingAs($this->client)->get('/espace')->assertOk()->assertSee($order->reference)->assertSee('Le paiement n’est pas ouvert pour cette commande')->assertDontSee('Payer 35');
        $this->actingAs($this->freelancer)->get('/freelance')->assertOk()->assertSee('En attente du client')->assertSee($order->reference);
    }

    public function test_agreement_is_frozen_and_independent_from_later_service_changes(): void
    {
        $order = $this->placeOrder();
        $a = $order->agreement;
        $this->assertSame(35000, $a->price_xof);
        $this->assertSame(5, $a->delivery_days);
        $this->assertSame(2, $a->revisions_included);
        $this->assertSame(['Nombre de plans', 'Version AutoCAD'], $a->client_inputs);
        $this->assertSame(config('freeci.orders.conditions_version'), $a->conditions_version);

        $this->service->update(['price_xof' => 99000, 'delivery_days' => 20, 'title' => 'Titre modifié', 'scope' => 'Autre périmètre']);

        $this->actingAs($this->client)->get("/commandes/{$order->reference}")->assertSee('Plans en DWG')->assertSee(Money::xof(35000)->formatted())->assertDontSee(Money::xof(99000)->formatted())->assertDontSee('Titre modifié');
        $this->accept($order->fresh());
        $order->refresh();
        $this->assertSame(35000, $order->agreement->fresh()->price_xof, 'l’acceptation ne recalcule rien');
        $this->assertSame('Plans en DWG', $order->agreement->fresh()->service_title);
    }

    public function test_a_modified_service_forces_the_client_to_review_the_new_conditions(): void
    {
        $payload = $this->requestPayload();
        $this->service->update(['price_xof' => 50000]);          // modifié entre la consultation et l'envoi

        $r = $this->actingAs($this->client)->post('/services/'.$this->service->slug.'/demande', $payload);
        $r->assertStatus(409)->assertSee('Ce service a été modifié')->assertSee(Money::xof(50000)->formatted())->assertSee('12 plans');
        $this->assertSame(0, Order::count(), 'aucune acceptation silencieuse');

        // avec la version à jour, la demande passe et fige le nouveau prix
        $this->actingAs($this->client)->post('/services/'.$this->service->slug.'/demande', $this->requestPayload())->assertRedirect();
        $this->assertSame(50000, Order::firstOrFail()->agreement->price_xof);
    }

    public function test_nobody_can_order_their_own_service(): void
    {
        $this->actingAs($this->freelancer)->get('/services/'.$this->service->slug.'/demande')->assertForbidden();
        $this->actingAs($this->freelancer)->post('/services/'.$this->service->slug.'/demande', $this->requestPayload())->assertForbidden();
        $this->assertSame(0, Order::count());
        $this->actingAs($this->freelancer)->get('/services/'.$this->service->slug)->assertSee('C’est votre service')->assertDontSee('/demande"', false);

        // rempart de base de données
        $this->expectException(QueryException::class);
        DB::table('orders')->insert([
            'id' => (string) Str::uuid(), 'reference' => 'FC-X-1', 'client_id' => $this->freelancer->id, 'freelancer_id' => $this->freelancer->id,
            'service_id' => $this->service->id, 'state' => 'awaiting_acceptance', 'requested_at' => now(), 'response_deadline_at' => now(),
        ]);
    }

    public function test_demo_catalog_services_refuse_requests_and_unavailable_services_are_gone(): void
    {
        $this->service->update(['accepts_requests' => false]);
        $this->actingAs($this->client)->post('/services/'.$this->service->slug.'/demande', $this->requestPayload())->assertStatus(409);
        $this->assertSame(0, Order::count());
        $this->actingAs($this->client)->get('/services/'.$this->service->slug)->assertSee('Demandes fermées')->assertDontSee('/demande"', false);

        $this->service->update(['accepts_requests' => true, 'status' => 'suspended']);
        $this->actingAs($this->client)->post('/services/'.$this->service->slug.'/demande', $this->requestPayload(null, ['service_version' => $this->service->fresh()->row_version]))->assertStatus(410);
        $this->assertSame(0, Order::count());
    }

    public function test_brief_validation_blocks_incomplete_requests(): void
    {
        $base = '/services/'.$this->service->slug.'/demande';
        $this->actingAs($this->client)->post($base, $this->requestPayload(null, ['answers' => ['12 plans', '']]))->assertSessionHasErrors('answers.1');
        $this->actingAs($this->client)->post($base, $this->requestPayload(null, ['answers' => ['12 plans']]))->assertSessionHasErrors('answers.1');
        $this->actingAs($this->client)->post($base, $this->requestPayload(null, ['conditions' => null]))->assertSessionHasErrors('conditions');
        $this->actingAs($this->client)->post($base, $this->requestPayload(null, ['notes' => str_repeat('x', 3001)]))->assertSessionHasErrors('notes');
        $this->actingAs($this->client)->post($base, $this->requestPayload(null, ['answers' => [str_repeat('x', 1001), 'a']]))->assertSessionHasErrors();
        $this->assertSame(0, Order::count());
    }

    public function test_double_submission_creates_one_order(): void
    {
        $payload = $this->requestPayload();
        $first = $this->actingAs($this->client)->post('/services/'.$this->service->slug.'/demande', $payload);
        $second = $this->actingAs($this->client)->post('/services/'.$this->service->slug.'/demande', $payload);   // même clé : double clic

        $this->assertSame(1, Order::count());
        $this->assertSame($first->headers->get('Location'), $second->headers->get('Location'));
        $this->assertSame(1, DB::table('order_events')->count());

        // autre clé mais demande déjà en attente : refusée proprement
        $this->actingAs($this->client)->post('/services/'.$this->service->slug.'/demande', $this->requestPayload())->assertStatus(409)->assertSee('Demande déjà envoyée');
        $this->assertSame(1, Order::count());

        // même clé avec un contenu différent : refusée
        $this->actingAs($this->client)->post('/services/'.$this->service->slug.'/demande', array_merge($payload, ['notes' => 'autre']))->assertStatus(409);
        $this->assertSame(1, Order::count());
    }

    public function test_double_acceptance_is_idempotent_and_a_stale_second_acceptance_is_a_conflict(): void
    {
        $order = $this->placeOrder();
        $version = $order->row_version;
        $key = (string) Str::uuid();
        $this->accept($order, $key, $version)->assertRedirect();
        $this->accept($order, $key, $version)->assertRedirect();            // même clé : rejouée, un seul effet
        $this->assertSame(1, DB::table('order_events')->where('type', 'accepted')->count());

        $this->accept($order, (string) Str::uuid(), $version)->assertStatus(409)->assertSee('La commande a changé');   // autre clé, version périmée
        $this->assertSame(OrderState::AwaitingPayment, $order->fresh()->state);
        $this->assertSame(2, $order->fresh()->row_version);
    }

    public function test_decline_needs_a_reason_and_closes_the_request_visibly_for_both_parties(): void
    {
        $order = $this->placeOrder();
        $decline = fn (string $reason) => $this->actingAs($this->freelancer)->post("/commandes/{$order->reference}/decline", [
            'expected_version' => $order->fresh()->row_version, 'operation_key' => (string) Str::uuid(), 'reason' => $reason,
        ]);

        $decline('court')->assertSessionHasErrors('reason');
        $this->assertSame(OrderState::AwaitingAcceptance, $order->fresh()->state);

        $decline('Je ne propose pas ce type de prestation actuellement.')->assertRedirect();
        $order->refresh();
        $this->assertSame(OrderState::Cancelled, $order->state);
        $this->assertSame('declined', $order->closure_reason->value);
        $this->actingAs($this->client)->get("/commandes/{$order->reference}")->assertSee('Demande refusée par le freelance')->assertSee('Je ne propose pas ce type de prestation actuellement.')->assertSee('Aucun montant n’a été encaissé');
        $this->accept($order, null, $order->row_version)->assertStatus(409);       // plus rien à accepter
        $this->assertSame(OrderState::Cancelled, $order->fresh()->state);
    }

    public function test_client_can_withdraw_before_answer_or_cancel_before_payment_but_not_the_other_way_round(): void
    {
        $order = $this->placeOrder();
        $act = fn (string $kind, Order $o) => $this->actingAs($this->client)->post("/commandes/{$o->reference}/{$kind}", ['expected_version' => $o->fresh()->row_version, 'operation_key' => (string) Str::uuid()]);

        $act('cancel', $order)->assertStatus(409);                  // « annuler » n'existe qu'après acceptation
        $act('withdraw', $order)->assertRedirect();
        $this->assertSame('withdrawn', $order->fresh()->closure_reason->value);

        $order2 = $this->placeOrder();
        $this->accept($order2);
        $act('withdraw', $order2)->assertStatus(409);               // « retirer » n'existe qu'avant la réponse
        $act('cancel', $order2)->assertRedirect();
        $this->assertSame('cancelled_before_payment', $order2->fresh()->closure_reason->value);
        $this->assertSame(OrderState::Cancelled, $order2->fresh()->state);
    }

    public function test_requests_expire_after_the_response_deadline_and_cannot_be_accepted(): void
    {
        $order = $this->placeOrder();
        $this->travel(49)->hours();

        $this->accept($order, null, $order->row_version)->assertStatus(409)->assertSee('Demande expirée');
        $order->refresh();
        $this->assertSame(OrderState::Expired, $order->state);
        $this->assertSame('expired_acceptance', $order->closure_reason->value);
        $this->assertSame(1, DB::table('order_events')->where(['type' => 'expired', 'actor_id' => null])->count());
        $this->actingAs($this->client)->get("/commandes/{$order->reference}")->assertSee('Délai de réponse dépassé');
    }

    public function test_orders_whose_payment_was_never_opened_never_expire_for_non_payment(): void
    {
        $order = $this->placeOrder();
        $this->accept($order);
        $this->assertNull($order->fresh()->payment_deadline_at, 'paiement jamais ouvert : aucune échéance');
        $this->travel(30)->days();
        $this->artisan('freeci:orders:expire')->assertSuccessful();
        $this->assertSame(OrderState::AwaitingPayment, $order->fresh()->state);
    }

    public function test_payment_deadline_only_runs_when_payment_is_opened_for_that_order_and_never_during_a_payment(): void
    {
        $this->enableSandbox();
        $order = $this->placeOrder();
        $this->accept($order);
        $this->assertNotNull($order->fresh()->payment_deadline_at, 'paiement ouvert pour cette commande : l’échéance court');

        // un paiement en cours bloque l'expiration
        $this->travel(2)->hours();
        $this->actingAs($this->client)->post("/commandes/{$order->reference}/paiement", ['operation_key' => 'k-expire', 'conditions' => '1'])->assertRedirect();
        $this->travel(30)->hours();
        $this->artisan('freeci:orders:expire')->assertSuccessful();
        $this->assertSame(OrderState::AwaitingPayment, $order->fresh()->state, 'jamais d’expiration pendant un paiement ouvert');
    }

    public function test_unpaid_expired_deadline_closes_an_opened_order(): void
    {
        $this->enableSandbox();
        $order = $this->placeOrder();
        $this->accept($order);
        $this->travel(25)->hours();
        $this->artisan('freeci:orders:expire')->assertSuccessful();
        $this->assertSame('expired_payment', $order->fresh()->closure_reason->value);
    }

    public function test_state_machine_has_no_path_to_work_without_confirmed_payment(): void
    {
        $this->assertFalse(OrderState::AwaitingAcceptance->canTransitionTo(OrderState::InProgress));
        $this->assertFalse(OrderState::Cancelled->canTransitionTo(OrderState::AwaitingPayment));
        $this->assertFalse(OrderState::Expired->canTransitionTo(OrderState::AwaitingAcceptance));
        $this->assertSame([OrderState::Delivered], OrderState::InProgress->allowedNext(), 'depuis le travail : seule la livraison explicite');
        $this->assertFalse(OrderState::Closed->canTransitionTo(OrderState::InProgress));

        // aucune route publique ne permet de « marquer payé » ; aucune colonne de paiement dans la table des commandes
        // Seules les routes de paiement Genius Pay prévues existent ; aucune ne « confirme » ni ne « marque payé ».
        // Retour du navigateur (informatif : il déclenche seulement une revérification serveur), webhook Genius Pay (signature + revérification), rapprochement administrateur (trace, aucune exécution).
        $allowed = ['commandes/{reference}/paiement', 'commandes/{reference}/paiement/actualiser', 'commandes/{reference}/paiement/retour', 'webhooks/geniuspay', 'admin/paiements', 'admin/paiements/{id}/examiner'];
        foreach (app('router')->getRoutes() as $route) {
            $line = $route->uri().' '.($route->getName() ?? '');
            $this->assertDoesNotMatchRegularExpression('/paid|mark|confirm-?pay|confirmer/i', $line, $route->uri());
            if (preg_match('/pay|paiement/i', $route->uri())) {
                $this->assertContains($route->uri(), $allowed, $route->uri());
            }
        }
        $columns = DB::getSchemaBuilder()->getColumnListing('orders');
        $this->assertSame([], array_values(array_filter($columns, fn ($c) => preg_match('/paid|payment_status|payment_ref/', $c))));
    }

    public function test_agreement_and_history_are_append_only_in_the_database(): void
    {
        $order = $this->placeOrder();
        $this->accept($order);

        foreach ([
            fn () => DB::table('order_agreements')->where('order_id', $order->id)->update(['price_xof' => 1]),
            fn () => DB::table('order_agreements')->where('order_id', $order->id)->delete(),
            fn () => DB::table('order_events')->where('order_id', $order->id)->update(['note' => 'altéré']),
            fn () => DB::table('order_events')->where('order_id', $order->id)->delete(),
        ] as $i => $attempt) {
            try {
                DB::transaction($attempt);
                $this->fail("modification $i acceptée");
            } catch (QueryException $e) {
                $this->assertStringContainsString('en ajout seul', $e->getMessage());
            }
        }
        $this->assertSame(35000, $order->agreement->fresh()->price_xof);
    }

    public function test_demo_flag_follows_the_parties(): void
    {
        $this->service->update(['is_demo' => false]);
        $this->service->freelanceProfile->update(['is_demo' => false]);
        $this->assertFalse($this->placeOrder()->is_demo);

        $demoClient = User::factory()->create(['is_demo' => true]);
        $order = $this->placeOrder($demoClient);
        $this->assertTrue($order->is_demo);
        $this->actingAs($demoClient)->get("/commandes/{$order->reference}")->assertDontSee('Démonstration');
    }

    public function test_request_page_shows_the_track_the_figed_summary_and_what_happens_next(): void
    {
        $this->actingAs($this->client)->get('/services/'.$this->service->slug.'/demande')->assertOk()->assertSee('Vous ne payez rien à cette étape')->assertSee('Votre demande')->assertSee('Réponse du freelance')
            ->assertSee('Ce qui se passera')->assertSee('figées')->assertSee('Envoyer la demande')->assertSee('name="operation_key"', false)->assertSee('name="conditions"', false);
    }
}

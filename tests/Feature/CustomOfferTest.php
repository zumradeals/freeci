<?php

namespace Tests\Feature;

use App\Modules\Accounts\Models\User;
use App\Modules\Messaging\Actions\Conversations;
use App\Modules\Orders\Actions\CustomOffers;
use App\Modules\Orders\Actions\SubmitReview;
use App\Modules\Orders\Models\Order;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\OrderFixtures;
use Tests\TestCase;

/** F-09 — offre personnalisée : envoi, retrait, refus, acceptation (commande normale d'origine « offre »), expiration, garde-fous. */
class CustomOfferTest extends TestCase
{
    use OrderFixtures, RefreshDatabase;

    private string $conversation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->setUpParties();
        $this->conversation = (string) app(Conversations::class)->forService($this->client, $this->service->slug)[0]->getKey();
    }

    /** @return array<string, mixed> */
    private function payload(array $o = []): array
    {
        return $o + ['operation_key' => (string) Str::uuid(), 'title' => 'Plans d’une villa R+1 à Cocody', 'scope' => 'Mise en plan complète d’une villa R+1 de 180 m² à partir de vos croquis, avec une reprise de la cotation après vos retours.',
            'deliverables' => "Plan de masse coté (PDF)\nPlans d’étage R+0 et R+1 (PDF)\nFichier source DWG", 'client_inputs' => "Croquis ou plans existants\nSurface du terrain", 'price_xof' => '120000',
            'delivery_days' => '10', 'revisions_included' => '2', 'delivery_files' => '1', 'valid_days' => '7'];
    }

    private function send(array $o = [])
    {
        return $this->actingAs($this->freelancer)->post("/espace/messages/{$this->conversation}/offre", $this->payload($o));
    }

    private function offerId(): string
    {
        return (string) DB::table('custom_offers')->orderByDesc('created_at')->value('id');
    }

    private function accept(string $id, array $o = [])
    {
        return $this->actingAs($this->client)->post("/espace/offres/{$id}/accepter", $o + ['operation_key' => (string) Str::uuid(), 'answers' => ['Croquis joints dans la conversation', '180 m²'], 'conditions' => '1']);
    }

    public function test_the_freelancer_sends_an_offer_and_the_client_sees_it_in_the_thread(): void
    {
        $this->actingAs($this->freelancer)->get("/espace/messages/{$this->conversation}")->assertOk()->assertSee('Proposer une offre personnalisée');
        $this->actingAs($this->freelancer)->get("/espace/messages/{$this->conversation}/offre")->assertOk()->assertSee('Nouvelle offre personnalisée');
        $this->send()->assertSessionHasNoErrors()->assertRedirect();
        $o = DB::table('custom_offers')->first();
        $this->assertSame(['pending', 120000, 10], [$o->state, (int) $o->price_xof, (int) $o->delivery_days]);
        $this->assertSame(1, DB::table('app_notifications')->where('user_id', $this->client->id)->where('type', 'offer_received')->count());

        $this->actingAs($this->client)->get("/espace/messages/{$this->conversation}")->assertOk()->assertSee('Offre personnalisée')->assertSee('Examiner l’offre')->assertSee('Plans d’une villa');
        $this->actingAs($this->freelancer)->get("/espace/messages/{$this->conversation}")->assertSee('Retirer l’offre')->assertDontSee('Proposer une offre personnalisée');
        $this->actingAs($this->client)->get('/espace/offres/'.$o->id)->assertOk()->assertSee('Accepter l’offre')->assertSee('Croquis ou plans existants');
    }

    public function test_content_rules_and_private_contacts_are_refused_and_nothing_is_created(): void
    {
        foreach ([['title' => 'ab'], ['scope' => 'trop court'], ['deliverables' => ''], ['price_xof' => '100'], ['price_xof' => '9999999'], ['delivery_days' => '0'], ['delivery_days' => '999'], ['revisions_included' => '11'], ['valid_days' => '31']] as $bad) {
            $this->send($bad)->assertSessionHasErrors();
        }
        $this->send(['scope' => str_repeat('Écrivez-moi sur ma-boite@exemple.ci pour discuter du projet en détail. ', 2)])->assertSessionHasErrors('scope');
        $this->send(['title' => 'Appelez le +225 07 00 00 00 00'])->assertSessionHasErrors('title');
        $this->assertSame(0, DB::table('custom_offers')->count());
    }

    public function test_only_one_pending_offer_per_conversation_and_withdrawing_allows_a_new_one(): void
    {
        $this->send();
        $this->send(['title' => 'Seconde offre'])->assertSessionHas('error');
        $this->assertSame(1, DB::table('custom_offers')->count());

        $id = $this->offerId();
        $this->actingAs($this->client)->post("/espace/offres/{$id}/retirer")->assertStatus(302);
        $this->assertSame('pending', DB::table('custom_offers')->where('id', $id)->value('state'), 'le client ne retire pas l’offre du freelance');
        $this->actingAs($this->freelancer)->post("/espace/offres/{$id}/retirer")->assertSessionHas('status');
        $this->assertSame('withdrawn', DB::table('custom_offers')->where('id', $id)->value('state'));
        $this->send(['title' => 'Offre corrigée'])->assertSessionHasNoErrors();
        $this->assertSame(2, DB::table('custom_offers')->count());
        $this->actingAs($this->client)->post("/espace/offres/{$id}/accepter", ['operation_key' => 'x', 'conditions' => '1', 'answers' => ['a', 'b']])->assertSessionHas('error');
        $this->assertSame(0, DB::table('orders')->count());
    }

    public function test_the_content_of_an_offer_can_never_be_changed_in_the_database(): void
    {
        $this->send();
        $id = $this->offerId();
        $this->expectException(QueryException::class);
        DB::table('custom_offers')->where('id', $id)->update(['price_xof' => 1000]);
    }

    public function test_declining_records_the_note_and_notifies_the_freelancer(): void
    {
        $this->send();
        $id = $this->offerId();
        $this->actingAs($this->client)->post("/espace/offres/{$id}/refuser", ['note' => 'Délai trop long pour moi.'])->assertSessionHas('status');
        $o = DB::table('custom_offers')->where('id', $id)->first();
        $this->assertSame(['declined', 'Délai trop long pour moi.'], [$o->state, $o->decline_note]);
        $this->assertSame(1, DB::table('app_notifications')->where('user_id', $this->freelancer->id)->where('dedupe_key', 'offer_declined:'.$id)->count());
        $this->actingAs($this->client)->post("/espace/offres/{$id}/refuser", ['note' => 'Écrivez-moi à moi@exemple.ci'])->assertSessionHas('error');
        $this->actingAs($this->client)->get("/espace/messages/{$this->conversation}")->assertSee('Refusée')->assertSee('Délai trop long pour moi.');
    }

    public function test_accepting_creates_a_normal_order_awaiting_payment_with_a_frozen_agreement(): void
    {
        $this->enableSandbox();
        $this->send();
        $id = $this->offerId();
        $this->accept($id, ['answers' => ['', 'x']])->assertSessionHasErrors('answers.0');
        $this->actingAs($this->client)->post("/espace/offres/{$id}/accepter", ['operation_key' => (string) Str::uuid(), 'answers' => ['a', 'b']])->assertSessionHasErrors('conditions');
        $this->assertSame(0, DB::table('orders')->count());

        $key = (string) Str::uuid();
        $r = $this->accept($id, ['operation_key' => $key]);
        $order = DB::table('orders')->first();
        $r->assertRedirect('/commandes/'.$order->reference);
        $this->assertSame(['offer', 'awaiting_payment', null, $id], [$order->origin, $order->state, $order->service_id, $order->offer_id]);
        $this->assertSame($this->client->id, $order->client_id);
        $this->assertSame($this->freelancer->id, $order->freelancer_id);
        $this->assertNotNull($order->payment_deadline_at);

        $a = DB::table('order_agreements')->where('order_id', $order->id)->first();
        $this->assertSame(['offer', 120000, 10, 2, 'files', 'Offre personnalisée'], [$a->origin, (int) $a->price_xof, (int) $a->delivery_days, (int) $a->revisions_included, $a->delivery_mode, $a->category_name]);
        $this->assertSame(['Plan de masse coté (PDF)', 'Plans d’étage R+0 et R+1 (PDF)', 'Fichier source DWG'], json_decode($a->deliverables, true));
        $this->assertNotNull($a->commission_bp);
        $brief = DB::table('order_briefs')->where('order_id', $order->id)->first();
        $this->assertSame('Croquis ou plans existants', json_decode($brief->answers, true)[0]['label']);

        $this->assertSame(['accepted', $order->id], [DB::table('custom_offers')->where('id', $id)->value('state'), DB::table('custom_offers')->where('id', $id)->value('order_id')]);
        $this->assertSame($order->id, DB::table('conversations')->where('id', $this->conversation)->value('order_id'), 'la conversation devient celle de la commande');
        $this->assertSame(1, DB::table('app_notifications')->where('user_id', $this->freelancer->id)->where('dedupe_key', 'offer_accepted:'.$id)->count());

        // rejouer la même opération : même commande, aucun doublon
        $this->accept($id, ['operation_key' => $key])->assertRedirect('/commandes/'.$order->reference);
        $this->assertSame(1, DB::table('orders')->count());
        // une autre opération sur une offre déjà acceptée : refusée
        $this->accept($id)->assertSessionHas('error');
        $this->assertSame(1, DB::table('orders')->count());

        // la commande s'affiche comme une offre, et la suite du parcours est celle d'une commande normale
        $this->actingAs($this->client)->get('/commandes/'.$order->reference)->assertOk()->assertSee('Offre personnalisée')->assertSee('Plans d’une villa R+1 à Cocody');
        $this->actingAs($this->freelancer)->get('/commandes/'.$order->reference)->assertOk();
        // plus d'offre possible depuis une conversation rattachée à une commande
        $this->actingAs($this->freelancer)->get("/espace/messages/{$this->conversation}/offre")->assertRedirect();
    }

    public function test_the_paid_order_started_from_an_offer_follows_the_normal_journey(): void
    {
        $this->enableSandbox();
        $this->send();
        $id = $this->offerId();
        $this->accept($id);
        $order = Order::query()->firstOrFail();
        $this->settle($order);
        $this->assertSame('in_progress', $order->fresh()->state->value);
        $this->assertSame($order->agreement->delivery_days, (int) $order->fresh()->started_at->diffInDays($order->fresh()->due_at));
    }

    public function test_an_expired_offer_cannot_be_accepted_and_the_task_notifies_both_once(): void
    {
        $this->send();
        $id = $this->offerId();
        DB::statement('ALTER TABLE custom_offers DISABLE TRIGGER custom_offers_guard');
        DB::table('custom_offers')->where('id', $id)->update(['valid_until' => now()->subHour()]);
        DB::statement('ALTER TABLE custom_offers ENABLE TRIGGER custom_offers_guard');

        $this->actingAs($this->client)->get("/espace/messages/{$this->conversation}")->assertSee('Expirée');
        $this->accept($id)->assertSessionHas('error');
        $this->assertSame(0, DB::table('orders')->count());
        $this->assertSame(1, app(CustomOffers::class)->expireDue() + (DB::table('custom_offers')->where('state', 'expired')->count() === 1 ? 0 : 1));
        $this->assertSame('expired', DB::table('custom_offers')->where('id', $id)->value('state'));
        $this->assertSame(2, DB::table('app_notifications')->where('type', 'offer_update')->where('dedupe_key', 'like', 'offer_expired:'.$id.'%')->count());
        $this->assertSame(0, app(CustomOffers::class)->expireDue());
        $this->send(['title' => 'Nouvelle offre après expiration'])->assertSessionHasNoErrors();
    }

    public function test_guard_rails_strangers_clients_blocked_or_suspended_accounts_and_other_conversation_kinds(): void
    {
        $this->actingAs($this->client)->get("/espace/messages/{$this->conversation}/offre")->assertStatus(404);
        $this->actingAs($this->client)->post("/espace/messages/{$this->conversation}/offre", $this->payload())->assertSessionHas('error');
        $this->assertSame(0, DB::table('custom_offers')->count());
        $this->send();
        $id = $this->offerId();
        $stranger = User::factory()->create();
        $this->actingAs($stranger)->get('/espace/offres/'.$id)->assertStatus(404);
        $this->actingAs($stranger)->post("/espace/offres/{$id}/accepter", ['operation_key' => 'z', 'conditions' => '1', 'answers' => ['a', 'b']])->assertSessionHas('error');
        $this->assertSame(0, DB::table('orders')->count());

        // le freelance ne peut pas accepter sa propre offre
        $this->actingAs($this->freelancer)->post("/espace/offres/{$id}/accepter", ['operation_key' => 'y', 'conditions' => '1', 'answers' => ['a', 'b']])->assertSessionHas('error');

        // client suspendu : aucune acceptation
        DB::table('users')->where('id', $this->client->id)->update(['suspended_at' => now()]);
        $this->accept($id)->assertStatus(302);
        $this->assertSame(0, DB::table('orders')->count());
        DB::table('users')->where('id', $this->client->id)->update(['suspended_at' => null]);

        // contact bloqué : aucune nouvelle offre
        DB::table('custom_offers')->where('id', $id)->update(['state' => 'withdrawn']);
        DB::table('contact_blocks')->insert(['blocker_id' => $this->client->id, 'blocked_id' => $this->freelancer->id, 'created_at' => now()]);
        $this->send()->assertSessionHas('error');
        DB::table('contact_blocks')->delete();
        $this->send()->assertSessionHasNoErrors();
    }

    public function test_the_review_of_an_order_from_an_offer_has_the_offer_origin_and_counts_for_the_freelancer(): void
    {
        $this->enableSandbox();
        $this->send();
        $this->accept($this->offerId());
        $order = Order::query()->firstOrFail();
        $this->assertNull($order->service_id);
        DB::table('orders')->where('id', $order->id)->update(['state' => 'closed', 'closure_reason' => 'validated', 'closed_at' => now(), 'started_at' => now()->subDays(5), 'due_at' => now()->addDay()]);
        DB::table('order_events')->insert(['order_id' => $order->id, 'type' => 'validated', 'actor_id' => $this->client->id, 'occurred_at' => now()]);

        app(SubmitReview::class)($this->client, $order->reference, 5, 'Très bon travail, plans clairs et livrés dans les temps.', (string) Str::uuid());
        $r = DB::table('reviews')->first();
        $this->assertSame(['offer', null, null, $this->freelancer->id], [$r->origin, $r->service_id, $r->mission_id, $r->subject_id]);
    }
}

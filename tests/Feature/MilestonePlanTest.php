<?php

namespace Tests\Feature;

use App\Modules\Accounts\Actions\GrantAdministrator;
use App\Modules\Accounts\Models\User;
use App\Modules\Missions\Actions\MissionModeration;
use App\Modules\Missions\Actions\MissionPlans;
use App\Modules\Missions\Models\Mission;
use App\Modules\Orders\Actions\ExpireOverdueOrders;
use App\Modules\Orders\Models\Delivery;
use App\Modules\Orders\Models\Order;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\OrderFixtures;
use Tests\TestCase;

/** F-13 — jalons de paiement : un jalon = une commande ordinaire, enchaînée ; le client ne paie qu'un jalon à la fois. */
class MilestonePlanTest extends TestCase
{
    use OrderFixtures, RefreshDatabase;

    private User $admin;

    private Mission $mission;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private_files');
        $this->setUpParties();
        $this->useFakeScanner();
        $this->enableSandbox();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->admin = User::factory()->create();
        app(GrantAdministrator::class)($this->admin, 'test');
        $this->mission = $this->publishMission();
    }

    private function publishMission(): Mission
    {
        $cat = $this->service->category_id;
        $this->actingAs($this->client)->post('/espace/missions', ['title' => 'Plans d’une villa R+1 à Bingerville', 'category_id' => $cat])->assertRedirect();
        $m = Mission::firstOrFail();
        $v = $m->versions()->first();
        $this->actingAs($this->client)->post("/espace/missions/{$m->id}/modifier", ['title' => $v->title, 'category_id' => $cat, 'description' => str_repeat('Villa R+1 de quatre chambres, plans complets à produire en plusieurs étapes. ', 3),
            'budget_xof' => '350000', 'application_deadline' => now()->addDays(5)->format('Y-m-d'), 'client_inputs' => "Surface du terrain\nVersion AutoCAD", 'revision_no' => $v->revision_no])->assertRedirect();
        $this->actingAs($this->client)->post("/espace/missions/{$m->id}/soumettre", ['revision_no' => $v->fresh()->revision_no])->assertRedirect();
        app(MissionModeration::class)->approve($this->admin, $v->id);

        return $m->refresh();
    }

    /** @return array<string, mixed> */
    private function form(array $o = []): array
    {
        return array_merge(['price_xof' => '350 000', 'delivery_days' => '1', 'revisions_included' => '2', 'validity_days' => '7', 'delivery_mode' => 'message',
            'scope' => 'Plans complets de la villa, de l’esquisse au dossier final, en trois étapes distinctes et payées séparément.', 'deliverables' => "Esquisse\nPlans d’exécution\nDossier final", 'expected_number' => 0,
            'use_milestones' => '1', 'milestones' => [
                ['title' => 'Esquisse et plans de principe', 'price' => '100 000', 'days' => '10', 'scope' => 'Plans de principe et implantation, au format PDF, avec une note de synthèse.'],
                ['title' => 'Plans d’exécution', 'price' => '150 000', 'days' => '12', 'scope' => 'Plans d’exécution cotés : fondations, niveaux, coupes, au format DWG et PDF.'],
                ['title' => 'Dossier final et suivi', 'price' => '100 000', 'days' => '8', 'scope' => 'Dossier complet DWG et PDF, notices, et une visite de contrôle sur le terrain.'],
            ]], $o);
    }

    private function propose(array $o = [])
    {
        return $this->actingAs($this->freelancer)->post("/missions/{$this->mission->fresh()->slug}/proposition", $this->form($o));
    }

    private function select(): Order
    {
        $pv = $this->mission->proposals()->firstOrFail()->versions()->get()->last();
        $this->actingAs($this->client)->get("/espace/missions/{$this->mission->id}/propositions/{$pv->id}/choisir")->assertOk()->assertSee('Plan de paiement par jalons');
        $this->actingAs($this->client)->post("/espace/missions/{$this->mission->id}/propositions/{$pv->id}/choisir", ['expected_version' => $this->mission->fresh()->row_version, 'operation_key' => (string) Str::uuid(),
            'answers' => ['450 m²', 'AutoCAD'], 'conditions' => '1'])->assertRedirect()->assertSessionHas('status');

        return Order::query()->orderBy('created_at')->orderBy('id')->firstOrFail();
    }

    private function validateOrder(Order $order): void
    {
        $order->refresh();
        $this->settle($order);
        $order->refresh();
        $this->assertSame('in_progress', $order->state->value);
        $this->actingAs($this->freelancer)->post("/commandes/{$order->reference}/livraison/message", ['message' => 'Voici la livraison complète de ce jalon, formats DWG et PDF.'])->assertRedirect();
        $draft = Delivery::query()->where('order_id', $order->id)->where('state', 'draft')->firstOrFail();
        $this->actingAs($this->freelancer)->post("/commandes/{$order->reference}/livraison/soumettre", ['delivery_id' => $draft->id, 'expected_version' => $order->fresh()->row_version, 'operation_key' => (string) Str::uuid()])->assertSessionHas('status');
        $d = Delivery::query()->where('order_id', $order->id)->where('state', 'submitted')->firstOrFail();
        $this->actingAs($this->client)->post("/commandes/{$order->reference}/validation", ['delivery_id' => $d->id, 'expected_version' => $order->fresh()->row_version, 'operation_key' => (string) Str::uuid(), 'confirm' => '1'])->assertSessionHas('status');
        $this->assertSame('closed', $order->fresh()->state->value);
    }

    private function latestOrder(): Order
    {
        return Order::query()->orderByDesc('created_at')->orderByDesc('id')->firstOrFail();
    }

    private function plan(): object
    {
        return DB::table('mission_plans')->where('mission_id', $this->mission->id)->orderByDesc('created_at')->first();
    }

    // -------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_proposal_milestone_rules(): void
    {
        $bad = fn (array $o) => $this->propose($o)->assertSessionHasErrors();
        $bad(['price_xof' => '340 000']);                                                                                                  // somme ≠ prix total
        $bad(['milestones' => [['title' => 'Un seul', 'price' => '350 000', 'days' => '5', 'scope' => str_repeat('x', 40)]]]);              // moins de 2 jalons
        $f = $this->form();
        $f['milestones'][0]['price'] = '9 000';
        $f['milestones'][1]['price'] = '241 000';
        $this->propose($f)->assertSessionHasErrors('milestones.0.price');                                                                  // moins de 10 000 FCFA
        $f = $this->form();
        $f['milestones'][2]['scope'] = 'Écrivez-moi à pro@exemple.ci pour la suite.';
        $f['milestones'][2]['scope'] .= str_repeat(' pour le détail', 3);
        $this->propose($f)->assertSessionHasErrors('milestones.2.scope');                                                                  // coordonnées privées
        $this->propose(['milestones' => [['title' => '', 'price' => '', 'days' => '', 'scope' => '']]])->assertSessionHasErrors('milestones');   // case cochée mais vide
        $six = array_fill(0, 6, ['title' => 'Jalon', 'price' => '10 000', 'days' => '1', 'scope' => str_repeat('x', 40)]);
        $this->propose(['price_xof' => '60 000', 'milestones' => $six])->assertSessionHasErrors('milestones');                             // plus de 5 jalons
        $this->assertSame(0, DB::table('proposal_versions')->count());

        $this->propose()->assertSessionHasNoErrors()->assertRedirect();
        $pv = DB::table('proposal_versions')->first();
        $this->assertSame(30, (int) $pv->delivery_days, 'le délai total est la somme des délais des jalons');
        $this->assertCount(3, json_decode($pv->milestones, true));
        $this->actingAs($this->freelancer)->get("/missions/{$this->mission->slug}/proposition")->assertOk()->assertSee('Esquisse et plans de principe');
        $this->actingAs($this->client)->get("/espace/missions/{$this->mission->id}/propositions")->assertOk()->assertSee('Paiement en 3 jalons');
        // une proposition SANS jalon reste inchangée
        $this->propose(['use_milestones' => null, 'milestones' => null, 'delivery_days' => '6', 'expected_number' => 1])->assertSessionHasNoErrors();
        $this->assertNull(DB::table('proposal_versions')->orderByDesc('number')->first()->milestones);
    }

    public function test_selection_creates_plan_and_only_the_first_milestone_order(): void
    {
        $this->propose();
        $order = $this->select();

        $this->assertSame(1, Order::count());
        $plan = $this->plan();
        $this->assertSame('active', $plan->state);
        $this->assertSame(350000, (int) $plan->total_xof);
        $items = DB::table('mission_plan_items')->where('plan_id', $plan->id)->orderBy('rank')->get();
        $this->assertSame(['open', 'upcoming', 'upcoming'], $items->pluck('state')->all());
        $this->assertSame($order->id, $items[0]->order_id);
        $this->assertSame($items[0]->id, $order->milestone_item_id);
        $a = $order->agreement;
        $this->assertSame(100000, $a->price_xof);
        $this->assertSame(10, $a->delivery_days);
        $this->assertSame('Esquisse et plans de principe', $a->service_title);
        $this->assertSame(2, $a->revisions_included);
        $this->actingAs($this->client)->get("/commandes/{$order->reference}")->assertOk()->assertSee('Jalon 1 sur 3');
        $this->actingAs($this->client)->get("/espace/jalons/{$this->mission->id}")->assertOk()->assertSee('Plan de paiement par jalons')->assertSee('Plans d’exécution');
        $this->actingAs($this->freelancer)->get("/espace/jalons/{$this->mission->id}")->assertOk();
        $other = User::factory()->create();
        $this->actingAs($other)->get("/espace/jalons/{$this->mission->id}")->assertNotFound();
        $this->actingAs($this->client)->get("/espace/missions/{$this->mission->id}")->assertOk()->assertSee('Plan de jalons');
        $this->actingAs($this->freelancer)->get('/freelance/propositions')->assertOk()->assertSee('Plan de jalons');

        // le plan est figé : un jalon ne se modifie pas
        $this->expectException(QueryException::class);
        DB::transaction(fn () => DB::table('mission_plan_items')->where('id', $items[1]->id)->update(['price_xof' => 1]));
    }

    public function test_milestones_chain_one_at_a_time_and_the_review_is_unique(): void
    {
        $this->propose();
        $o1 = $this->select();
        $this->validateOrder($o1);

        $this->assertSame(2, Order::count());
        $o2 = $this->latestOrder();
        $this->assertSame('awaiting_payment', $o2->state->value);
        $this->assertSame(150000, $o2->agreement->price_xof);
        $this->assertSame($o1->agreement->commission_bp, $o2->agreement->commission_bp, 'conditions financières reprises de l’accord');
        $this->assertSame($o1->brief->answers, $o2->brief->answers, 'le brief n’est saisi qu’une fois');
        $this->assertSame('awarded', $this->mission->fresh()->status);
        $this->assertSame(1, DB::table('app_notifications')->where('user_id', $this->client->id)->where('type', 'milestone_opened')->count());
        $this->actingAs($this->client)->get("/commandes/{$o2->reference}")->assertOk()->assertSee('Jalon 2 sur 3');

        // avis : refusé tant que le plan n'est pas terminé (jalon 1)
        $this->actingAs($this->client)->post("/commandes/{$o1->reference}/avis", ['rating' => 5, 'comment' => str_repeat('Très bon travail. ', 4), 'operation_key' => (string) Str::uuid()])->assertSessionHas('error');
        $this->assertSame(0, DB::table('reviews')->count());

        $this->validateOrder($o2);
        $o3 = $this->latestOrder();
        $this->assertSame(100000, $o3->agreement->price_xof);
        $this->validateOrder($o3);

        $this->assertSame('completed', $this->plan()->state);
        $this->assertSame(3, Order::count());
        $this->assertSame('awarded', $this->mission->fresh()->status);
        $this->actingAs($this->client)->get("/espace/jalons/{$this->mission->id}")->assertOk()->assertSee('Terminé');
        $this->actingAs($this->client)->post("/commandes/{$o2->reference}/avis", ['rating' => 5, 'comment' => str_repeat('Très bon travail. ', 4), 'operation_key' => (string) Str::uuid()])->assertSessionHas('error');
        $this->actingAs($this->client)->post("/commandes/{$o3->reference}/avis", ['rating' => 5, 'comment' => str_repeat('Très bon travail. ', 4), 'operation_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('reviews')->count());
    }

    public function test_unpaid_milestone_pauses_the_plan_then_client_can_reopen_it_once(): void
    {
        $this->propose();
        $this->validateOrder($this->select());
        $o2 = $this->latestOrder();
        DB::table('orders')->where('id', $o2->id)->update(['payment_deadline_at' => now()->subMinute()]);
        $this->assertSame(1, app(ExpireOverdueOrders::class)());

        $this->assertSame('paused', $this->plan()->state);
        $this->assertSame(2, DB::table('app_notifications')->where('type', 'milestone_update')->where('title', 'Plan de jalons en pause')->count());
        $this->assertSame('paused', DB::table('mission_plans')->value('state'));
        $page = $this->actingAs($this->client)->get("/espace/jalons/{$this->mission->id}")->assertOk();
        $page->assertSee('Rouvrir le paiement');
        $this->actingAs($this->freelancer)->get("/espace/jalons/{$this->mission->id}")->assertOk()->assertDontSee('Rouvrir le paiement');
        $this->actingAs($this->freelancer)->post("/espace/jalons/{$this->mission->id}/rouvrir")->assertNotFound();

        $this->actingAs($this->client)->post("/espace/jalons/{$this->mission->id}/rouvrir")->assertRedirect();
        $this->assertSame('active', $this->plan()->state);
        $o2b = $this->latestOrder();
        $this->assertNotSame($o2->id, $o2b->id);
        $this->assertSame('awaiting_payment', $o2b->state->value);
        $this->assertSame($o2b->id, DB::table('mission_plan_items')->where('rank', 2)->value('order_id'));
        $this->actingAs($this->client)->post("/espace/jalons/{$this->mission->id}/rouvrir")->assertSessionHas('error');                      // plus en pause
        $this->validateOrder($o2b);                                                                                                       // la reprise se poursuit normalement
        $this->assertSame(4, Order::count());
    }

    public function test_paused_plan_stops_after_the_reopening_delay_and_remaining_milestones_are_never_due(): void
    {
        $this->propose();
        $this->validateOrder($this->select());
        DB::table('orders')->where('id', $this->latestOrder()->id)->update(['payment_deadline_at' => now()->subMinute()]);
        app(ExpireOverdueOrders::class)();

        DB::table('mission_plans')->update(['paused_at' => now()->subDays(15)]);
        $this->actingAs($this->client)->post("/espace/jalons/{$this->mission->id}/rouvrir")->assertSessionHas('error');
        $this->assertSame('paused', $this->plan()->state);
        $this->assertSame(1, app(MissionPlans::class)->expireDue());
        $this->assertSame(0, app(MissionPlans::class)->expireDue(), 'idempotent');
        $this->assertSame('stopped', $this->plan()->state);
        $this->assertSame('unpaid', $this->plan()->stop_reason);
        $this->assertSame(['validated', 'cancelled', 'cancelled'], DB::table('mission_plan_items')->orderBy('rank')->pluck('state')->all());
        $this->artisan('freeci:milestones:expire')->assertSuccessful();
        // l'avis unique reste possible sur le dernier jalon validé
        $o1 = Order::query()->orderBy('created_at')->orderBy('id')->first();
        $this->actingAs($this->client)->post("/commandes/{$o1->reference}/avis", ['rating' => 4, 'comment' => str_repeat('Premier jalon très correct. ', 3), 'operation_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
    }

    public function test_client_stops_the_plan_only_after_a_validated_milestone_and_never_during_work(): void
    {
        $this->propose();
        $o1 = $this->select();
        $this->actingAs($this->client)->get("/espace/jalons/{$this->mission->id}/arreter")->assertRedirect();                           // aucun jalon validé
        $this->actingAs($this->client)->post("/espace/jalons/{$this->mission->id}/arreter", ['confirm' => '1'])->assertSessionHas('error');
        $this->assertSame('active', $this->plan()->state);

        $this->validateOrder($o1);
        $o2 = $this->latestOrder();
        $this->actingAs($this->freelancer)->get("/espace/jalons/{$this->mission->id}/arreter")->assertNotFound();
        $this->actingAs($this->client)->get("/espace/jalons/{$this->mission->id}/arreter")->assertOk()->assertSee('Arrêter le plan');
        $this->actingAs($this->client)->post("/espace/jalons/{$this->mission->id}/arreter", [])->assertSessionHasErrors('confirm');
        $this->actingAs($this->client)->post("/espace/jalons/{$this->mission->id}/arreter", ['confirm' => '1'])->assertRedirect()->assertSessionHas('status');

        $this->assertSame('stopped', $this->plan()->state);
        $this->assertSame('client', $this->plan()->stop_reason);
        $this->assertSame('cancelled', $o2->fresh()->state->value);
        $this->assertSame(['validated', 'cancelled', 'cancelled'], DB::table('mission_plan_items')->orderBy('rank')->pluck('state')->all());
        $this->assertSame(1, DB::table('app_notifications')->where('user_id', $this->freelancer->id)->where('type', 'milestone_update')->where('title', 'Plan de jalons arrêté')->count());
        $this->actingAs($this->client)->post("/espace/jalons/{$this->mission->id}/arreter", ['confirm' => '1'])->assertSessionHas('error');   // déjà arrêté
        $this->assertSame(2, Order::count(), 'aucun nouveau jalon n’est ouvert');
    }

    public function test_plan_cannot_be_stopped_while_a_paid_milestone_is_in_progress(): void
    {
        $this->propose();
        $this->validateOrder($this->select());
        $o2 = $this->latestOrder();
        $this->settle($o2);
        $this->assertSame('in_progress', $o2->fresh()->state->value);
        $this->actingAs($this->client)->post("/espace/jalons/{$this->mission->id}/arreter", ['confirm' => '1'])->assertSessionHas('error');
        $this->assertSame('active', $this->plan()->state);
        $this->assertSame('in_progress', $o2->fresh()->state->value);
    }

    public function test_first_milestone_never_paid_discards_the_plan_and_mission_can_be_selected_again(): void
    {
        $this->propose();
        $o1 = $this->select();
        DB::table('orders')->where('id', $o1->id)->update(['payment_deadline_at' => now()->subMinute()]);
        app(ExpireOverdueOrders::class)();

        $this->assertSame('discarded', $this->plan()->state);
        $this->assertSame('selection_ended', $this->mission->fresh()->status);
        $this->assertSame(['cancelled', 'cancelled', 'cancelled'], DB::table('mission_plan_items')->pluck('state')->all());
        $this->actingAs($this->client)->get("/espace/jalons/{$this->mission->id}")->assertNotFound();
    }
}

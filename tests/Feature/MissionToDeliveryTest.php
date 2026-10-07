<?php

namespace Tests\Feature;

use App\Modules\Accounts\Actions\GrantAdministrator;
use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Models\FreelanceProfile;
use App\Modules\Missions\Actions\MissionModeration;
use App\Modules\Missions\Models\Mission;
use App\Modules\Missions\Models\Proposal;
use App\Modules\Orders\Models\Delivery;
use App\Modules\Orders\Models\Order;
use App\Shared\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\OrderFixtures;
use Tests\TestCase;

/**
 * Parcours « mission » d'un seul tenant, en mode test (sandbox) : publication, deux propositions, comparaison, sélection, paiement confirmé par le
 * serveur, livraison, validation, clôture. Chaque écran traversé doit répondre 200. Complète les tests par segment (messagerie, livraison).
 */
class MissionToDeliveryTest extends TestCase
{
    use OrderFixtures, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private_files');
        $this->setUpParties();
        $this->useFakeScanner();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->admin = User::factory()->create();
        app(GrantAdministrator::class)($this->admin, 'test');
    }

    private function publishMission(): Mission
    {
        $cat = $this->service->category_id;
        $this->actingAs($this->client)->post('/espace/missions', ['title' => 'Conversion de douze plans PDF en fichiers DWG', 'category_id' => $cat])->assertRedirect();
        $m = Mission::firstOrFail();
        $v = $m->versions()->first();
        $this->actingAs($this->client)->get("/espace/missions/{$m->id}/modifier")->assertOk();
        $this->actingAs($this->client)->post("/espace/missions/{$m->id}/modifier", ['title' => $v->title, 'category_id' => $cat, 'description' => str_repeat('Douze plans d’architecture en PDF à convertir en DWG, calques conservés. ', 3),
            'budget_xof' => '120000', 'application_deadline' => now()->addDays(5)->format('Y-m-d'), 'client_inputs' => "Nombre de plans\nVersion AutoCAD", 'revision_no' => $v->revision_no])->assertRedirect();
        $this->actingAs($this->client)->post("/espace/missions/{$m->id}/soumettre", ['revision_no' => $v->fresh()->revision_no])->assertRedirect();
        app(MissionModeration::class)->approve($this->admin, $v->id);

        return $m->refresh();
    }

    private function propose(Mission $mission, User $who, int $price): Proposal
    {
        $slug = $mission->fresh()->slug;
        $this->actingAs($who)->get("/missions/{$slug}")->assertOk();
        $this->actingAs($who)->get("/missions/{$slug}/proposition")->assertOk();
        $this->actingAs($who)->post("/missions/{$slug}/proposition", ['price_xof' => (string) $price, 'delivery_days' => '6', 'revisions_included' => '2', 'validity_days' => '7', 'delivery_mode' => 'message',
            'scope' => 'Conversion des douze plans en DWG AutoCAD 2018, calques conservés, un fichier par plan et un PDF de contrôle.', 'deliverables' => 'Douze fichiers DWG', 'expected_number' => 0])->assertRedirect();

        return Proposal::where('mission_id', $mission->id)->where('freelancer_id', $who->id)->firstOrFail();
    }

    public function test_a_mission_leads_to_a_complete_order_up_to_validated_delivery(): void
    {
        $this->enableSandbox();
        $other = User::factory()->create(['name' => 'Concurrent Freelance']);
        $other->roles()->firstOrCreate(['role' => 'freelance']);
        FreelanceProfile::factory()->create(['user_id' => $other->id, 'display_name' => 'Concurrent Freelance']);

        // 1. Mission publiée, visible du public.
        $mission = $this->publishMission();
        $this->get("/missions/{$mission->slug}")->assertOk()->assertSee('Conversion de douze plans');
        $this->get('/missions')->assertOk()->assertSee('Conversion de douze plans');
        $this->actingAs($this->client)->get("/espace/missions/{$mission->id}")->assertOk();

        // 2. Deux propositions ; chacune est visible et révisable par son auteur.
        $pa = $this->propose($mission, $this->freelancer, 95000);
        $pb = $this->propose($mission, $other, 80000);
        $this->actingAs($this->freelancer)->get('/freelance/propositions')->assertOk();

        // 3. Comparaison côté client, puis page de confirmation de la sélection.
        $this->actingAs($this->client)->get("/espace/missions/{$mission->id}/propositions")->assertOk()->assertSee('Concurrent Freelance')->assertSee('Kader Freelance');
        $pv = $pa->versions()->first();
        $this->actingAs($this->client)->get("/espace/missions/{$mission->id}/propositions/{$pv->id}/choisir")->assertOk();

        // 4. Sélection : une commande de test, accord figé sur la proposition retenue.
        $this->actingAs($this->client)->post("/espace/missions/{$mission->id}/propositions/{$pv->id}/choisir", ['expected_version' => $mission->fresh()->row_version, 'operation_key' => (string) Str::uuid(), 'answers' => ['12', 'AutoCAD'], 'conditions' => '1'])
            ->assertRedirect()->assertSessionHas('status');
        $order = Order::firstOrFail();
        $this->assertSame('test', $order->environment);
        $this->assertSame($this->freelancer->id, $order->freelancer_id);
        $this->actingAs($this->client)->get("/commandes/{$order->reference}")->assertOk()->assertSee(Money::xof(95000)->formatted(), false);
        $this->actingAs($this->freelancer)->get("/commandes/{$order->reference}")->assertOk();
        $this->actingAs($other)->get("/commandes/{$order->reference}")->assertNotFound();
        $this->assertSame(1, Order::count(), 'la proposition non retenue ne crée aucune commande');
        $this->assertNotNull($pb->fresh());

        // 5. Paiement sandbox confirmé par le serveur : la commande démarre (brief déjà complet).
        $this->actingAs($this->client)->get("/commandes/{$order->reference}/paiement")->assertOk();
        $this->settle($order);
        $order->refresh();
        $this->assertSame('in_progress', $order->state->value);

        // 6. Livraison par message, puis validation par le client : clôture commerciale.
        $this->actingAs($this->freelancer)->get("/commandes/{$order->reference}/livraison")->assertOk();
        $this->actingAs($this->freelancer)->post("/commandes/{$order->reference}/livraison/message", ['message' => 'Voici la livraison complète des douze plans, formats DWG et PDF.'])->assertRedirect();
        $draft = Delivery::query()->where('order_id', $order->id)->where('state', 'draft')->firstOrFail();
        $this->actingAs($this->freelancer)->post("/commandes/{$order->reference}/livraison/soumettre", ['delivery_id' => $draft->id, 'expected_version' => $order->fresh()->row_version, 'operation_key' => (string) Str::uuid()])
            ->assertRedirect()->assertSessionHas('status');
        $delivered = Delivery::query()->where('order_id', $order->id)->where('state', 'submitted')->firstOrFail();
        $this->actingAs($this->client)->get("/commandes/{$order->reference}")->assertOk()->assertSee('Livraison v1');
        $this->actingAs($this->client)->post("/commandes/{$order->reference}/validation", ['delivery_id' => $delivered->id, 'expected_version' => $order->fresh()->row_version, 'operation_key' => (string) Str::uuid(), 'confirm' => '1'])
            ->assertRedirect()->assertSessionHas('status');
        $order->refresh();
        $this->assertSame('closed', $order->state->value);
        $this->assertSame('validated', $order->closure_reason->value);

        // 7. Les écrans de fin répondent : avis (client), revenus du freelance, finances du client, messages.
        $this->actingAs($this->client)->get("/commandes/{$order->reference}/avis")->assertOk();
        $this->actingAs($this->freelancer)->get('/freelance/revenus')->assertOk();
        $this->actingAs($this->client)->get('/espace/finances')->assertOk();
        $this->actingAs($this->client)->get('/espace/messages')->assertOk();
    }
}

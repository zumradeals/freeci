<?php

namespace Tests\Feature;

use App\Integrations\Payments\PaymentMode;
use App\Modules\Accounts\Actions\GrantAdministrator;
use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Exceptions\ModerationDenied;
use App\Modules\Catalog\Models\FreelanceProfile;
use App\Modules\Finance\PaymentGate;
use App\Modules\Missions\Actions\MissionModeration;
use App\Modules\Missions\Models\Mission;
use App\Modules\Missions\Models\MissionVersion;
use App\Modules\Missions\Models\Proposal;
use App\Modules\Missions\Models\ProposalVersion;
use App\Modules\Orders\Enums\OrderState;
use App\Modules\Orders\Models\Delivery;
use App\Modules\Orders\Models\Order;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\OrderFixtures;
use Tests\TestCase;

/** Lot 6 : missions, propositions versionnées, sélection exclusive, raccordement au cycle de commande. */
class MissionFlowTest extends TestCase
{
    use OrderFixtures, RefreshDatabase;

    private User $admin;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private_files');
        $this->setUpParties();
        $this->admin = User::factory()->create();
        app(GrantAdministrator::class)($this->admin, 'test');
        $this->other = User::factory()->create(['name' => 'Concurrent Freelance']);
        $this->other->roles()->firstOrCreate(['role' => 'freelance']);
        FreelanceProfile::factory()->create(['user_id' => $this->other->id, 'display_name' => 'Concurrent Freelance']);
    }

    private function missionForm(array $o = []): array
    {
        return array_merge([
            'title' => 'Conversion de douze plans PDF en fichiers DWG', 'category_id' => $this->service->category_id,
            'description' => str_repeat('Douze plans d’architecture en PDF à convertir en DWG AutoCAD 2018, calques conservés. ', 3),
            'budget_xof' => '120 000', 'application_deadline' => now()->addDays(5)->format('Y-m-d'), 'client_inputs' => "Nombre de plans\nVersion AutoCAD", 'intent' => 'save',
        ], $o);
    }

    /** Mission publiée (créée par $client, approuvée par l'administrateur). */
    private function openMission(?User $client = null, array $o = []): Mission
    {
        $client ??= $this->client;
        $this->actingAs($client)->post('/espace/missions', ['title' => $o['title'] ?? 'Conversion de douze plans PDF en fichiers DWG', 'category_id' => $this->service->category_id])->assertRedirect();
        $m = Mission::query()->orderByDesc('created_at')->orderByDesc('id')->firstOrFail();
        $v = $m->versions()->first();
        $this->actingAs($client)->post("/espace/missions/{$m->id}/modifier", $this->missionForm($o) + ['revision_no' => $v->revision_no])->assertRedirect()->assertSessionHas('status');
        $this->actingAs($client)->post("/espace/missions/{$m->id}/soumettre", ['revision_no' => $v->fresh()->revision_no])->assertRedirect()->assertSessionHas('status');
        app(MissionModeration::class)->approve($this->admin, $v->id);

        return $m->refresh();
    }

    private function proposalForm(array $o = []): array
    {
        return array_merge(['price_xof' => '95 000', 'delivery_days' => '6', 'revisions_included' => '2', 'validity_days' => '7', 'delivery_mode' => 'message',
            'scope' => 'Conversion des douze plans en DWG AutoCAD 2018, calques conservés, un fichier par plan et un PDF de contrôle.', 'deliverables' => "Douze fichiers DWG\nUn PDF de contrôle", 'message' => 'Disponible tout de suite.'], $o);
    }

    private function propose(Mission $m, ?User $who = null, array $o = [], ?int $expected = null)
    {
        $who ??= $this->freelancer;
        $current = Proposal::query()->where('mission_id', $m->id)->where('freelancer_id', $who->id)->first();
        $n = $expected ?? ($current ? (int) $current->versions()->max('number') : 0);

        return $this->actingAs($who)->post("/missions/{$m->fresh()->slug}/proposition", $this->proposalForm($o) + ['expected_number' => $n]);
    }

    private function latest(Mission $m, User $who): ProposalVersion
    {
        return Proposal::query()->where('mission_id', $m->id)->where('freelancer_id', $who->id)->firstOrFail()->versions()->get()->last();
    }

    private function select(Mission $m, ProposalVersion $pv, array $o = [], ?string $key = null)
    {
        return $this->actingAs($this->client)->post("/espace/missions/{$m->id}/propositions/{$pv->id}/choisir", array_merge([
            'expected_version' => $m->fresh()->row_version, 'operation_key' => $key ?? (string) Str::uuid(), 'answers' => ['12', 'AutoCAD 2018'], 'notes' => '', 'conditions' => '1',
        ], $o));
    }

    private function dbRefuses(callable $attempt, string $contains = ''): void
    {
        try {
            DB::transaction($attempt);
        } catch (QueryException $e) {
            $this->assertStringContainsString($contains, $e->getMessage());

            return;
        }
        $this->fail('La base aurait dû refuser.');
    }

    // ---------- missions côté client ----------

    public function test_a_client_drafts_submits_and_a_moderator_publishes_a_mission_through_the_existing_mechanism(): void
    {
        $this->actingAs($this->client)->post('/espace/missions', ['title' => 'Conversion de douze plans PDF en fichiers DWG', 'category_id' => $this->service->category_id])->assertRedirect();
        $m = Mission::firstOrFail();
        $v = $m->versions()->first();
        $this->assertSame('draft', $m->status);
        $this->get("/missions/{$m->slug}")->assertNotFound();

        // erreurs compréhensibles, saisies conservées, rien d'écrit
        $bad = $this->missionForm(['budget_xof' => '100', 'description' => 'trop court', 'application_deadline' => now()->subDay()->format('Y-m-d')]);
        $this->actingAs($this->client)->post("/espace/missions/{$m->id}/modifier", $bad + ['revision_no' => $v->revision_no])->assertRedirect()->assertSessionHas('status');       // brouillon : incomplet admis
        $this->actingAs($this->client)->post("/espace/missions/{$m->id}/soumettre", ['revision_no' => $v->fresh()->revision_no])->assertSessionHasErrors(['budget_xof', 'description', 'application_deadline']);
        $this->assertSame('draft', $v->fresh()->state);
        $this->actingAs($this->client)->post("/espace/missions/{$m->id}/modifier", $this->missionForm(['description' => 'Contact : moi@exemple.ci ou 07 08 09 10 11']) + ['revision_no' => $v->fresh()->revision_no])->assertSessionHasErrors('description');
        $this->actingAs($this->client)->get("/espace/missions/{$m->id}/soumettre")->assertOk()->assertSee('pas encore')->assertDontSee('submit-ready');

        $this->actingAs($this->client)->post("/espace/missions/{$m->id}/modifier", $this->missionForm() + ['revision_no' => $v->fresh()->revision_no])->assertRedirect()->assertSessionHas('status');
        $this->actingAs($this->client)->post("/espace/missions/{$m->id}/soumettre", ['revision_no' => $v->fresh()->revision_no])->assertRedirect()->assertSessionHas('status');
        $this->assertSame('in_review', $m->fresh()->status);
        $this->get('/missions')->assertOk()->assertDontSee('Conversion de douze plans');
        $this->actingAs($this->client)->post("/espace/missions/{$m->id}/modifier", $this->missionForm(['title' => 'Un autre titre suffisamment long']) + ['revision_no' => $v->fresh()->revision_no])->assertRedirect()->assertSessionHas('error');

        // modération : réservée aux administrateurs, jamais sur sa propre mission, refus motivé
        $mod = app(MissionModeration::class);
        foreach ([$this->client, $this->freelancer] as $who) {
            try {
                $mod->approve($who, $v->id);
                $this->fail('modération sans habilitation');
            } catch (ModerationDenied) {
                $this->assertSame('in_review', $v->fresh()->state);
            }
        }
        app(GrantAdministrator::class)($this->client, 'test');
        try {
            $mod->approve($this->client, $v->id);
            $this->fail('auto-modération');
        } catch (ModerationDenied $e) {
            $this->assertStringContainsString('propre mission', $e->getMessage());
        }
        $this->assertSame(1, Artisan::call('freeci:moderation:mission-refuse', ['version' => $v->id, '--by' => $this->admin->email, '--reason' => 'non', '--yes' => true]));
        $this->assertSame(0, Artisan::call('freeci:moderation:mission-refuse', ['version' => $v->id, '--by' => $this->admin->email, '--reason' => 'Précisez les formats de fichiers attendus.', '--yes' => true]));
        $this->assertSame('changes_requested', $v->fresh()->state);
        $this->actingAs($this->client)->get('/espace/missions')->assertOk()->assertSee('À corriger')->assertSee('Précisez les formats de fichiers attendus.');
        $this->actingAs($this->client)->post("/espace/missions/{$m->id}/modifier", $this->missionForm() + ['revision_no' => $v->fresh()->revision_no])->assertRedirect();
        $this->actingAs($this->client)->post("/espace/missions/{$m->id}/soumettre", ['revision_no' => $v->fresh()->revision_no])->assertRedirect();
        $this->assertSame(0, Artisan::call('freeci:moderation:mission-queue'));
        $this->assertStringContainsString($v->id, Artisan::output());
        $this->assertSame(0, Artisan::call('freeci:moderation:mission-approve', ['version' => $v->id, '--by' => $this->admin->email, '--yes' => true]));
        $m->refresh();
        $this->assertSame(['open', 'conversion-de-douze-plans-pdf-en-fichiers-dwg'], [$m->status, $m->slug]);

        // découverte publique : recherche, filtre, fiche sans donnée privée
        $this->get('/missions')->assertOk()->assertSee('Conversion de douze plans');
        $this->get('/missions?q=plans+dwg')->assertOk()->assertSee('Conversion de douze plans');
        $this->get('/missions?q=zzzintrouvable')->assertOk()->assertDontSee('Conversion de douze plans');
        $this->get('/missions?categorie='.$this->service->category->slug)->assertOk()->assertSee('Conversion de douze plans');
        $this->get('/missions?categorie=autre-categorie')->assertOk()->assertDontSee('Conversion de douze plans');
        auth()->forgetGuards();
        $page = $this->get("/missions/{$m->slug}")->assertOk();
        $page->assertSee('Nombre de plans')->assertDontSee($this->client->email)->assertDontSee($this->client->name)->assertSee('Aucune pièce jointe');
        $this->assertSame(['created', 'submitted', 'changes_requested', 'submitted', 'approved'], array_values(array_filter(DB::table('mission_events')->where('mission_id', $m->id)->orderBy('id')->pluck('type')->all(), fn ($t) => $t !== 'submission_withdrawn')));
    }

    public function test_only_the_owner_manages_a_mission_and_nobody_but_the_two_parties_sees_proposals(): void
    {
        $m = $this->openMission();
        $this->propose($m)->assertRedirect()->assertSessionHas('status');
        $this->propose($m, $this->other, ['price_xof' => '80000'])->assertRedirect()->assertSessionHas('status');

        foreach ([$this->other, $this->freelancer, $this->admin] as $who) {
            foreach (["/espace/missions/{$m->id}", "/espace/missions/{$m->id}/modifier", "/espace/missions/{$m->id}/propositions", "/espace/missions/{$m->id}/apercu", "/espace/missions/{$m->id}/fermer"] as $url) {
                $this->actingAs($who)->get($url)->assertNotFound();
            }
            $this->actingAs($who)->post("/espace/missions/{$m->id}/fermer")->assertNotFound();
            $this->actingAs($who)->post("/espace/missions/{$m->id}/modifier", $this->missionForm() + ['revision_no' => 1])->assertNotFound();
        }
        // un candidat ne voit que SA proposition, jamais celle du concurrent ni leur nombre
        $this->actingAs($this->freelancer)->get("/missions/{$m->slug}")->assertOk()->assertSee('95')->assertDontSee('80 000')->assertDontSee('Concurrent Freelance');
        $this->actingAs($this->other)->get("/missions/{$m->slug}")->assertOk()->assertSee('80')->assertDontSee('95 000');
        $this->actingAs($this->freelancer)->get('/freelance/propositions')->assertOk()->assertSee('95')->assertDontSee('80 000');
        $this->actingAs($this->client)->get("/espace/missions/{$m->id}/propositions")->assertOk()->assertSee('Kader Freelance')->assertSee('Concurrent Freelance');
        $this->actingAs($this->client)->get('/espace/missions')->assertOk()->assertSee('2 propositions');
        $pv = $this->latest($m, $this->other);
        foreach ([$this->freelancer, $this->other] as $who) {
            $this->actingAs($who)->get("/espace/missions/{$m->id}/propositions/{$this->latest($m, $this->freelancer)->id}/choisir")->assertNotFound();
        }
        $this->assertSame('open', $m->fresh()->status);
        $this->assertNotNull($pv);
        // une mission non publiée n'a pas de page publique
        $this->actingAs($this->client)->post('/espace/missions', ['title' => 'Mission encore en brouillon, non publiée', 'category_id' => $this->service->category_id])->assertRedirect();
        $draft = Mission::query()->where('status', 'draft')->firstOrFail();
        $this->get("/missions/{$draft->slug}")->assertNotFound();
        $this->actingAs($this->freelancer)->get("/missions/{$draft->slug}/proposition")->assertNotFound();
    }

    // ---------- propositions ----------

    public function test_proposals_are_versioned_revisable_withdrawable_and_never_on_ones_own_mission(): void
    {
        $m = $this->openMission();
        $this->propose($m)->assertRedirect()->assertSessionHas('status');
        $v1 = $this->latest($m, $this->freelancer);
        $this->propose($m, null, ['price_xof' => '90000'])->assertRedirect()->assertSessionHas('status');
        $v2 = $this->latest($m, $this->freelancer);
        $this->assertSame([1, 2], [$v1->number, $v2->number]);
        $this->assertSame(95000, $v1->fresh()->price_xof, 'la version 1 est conservée telle quelle');
        $this->assertSame(1, Proposal::where('mission_id', $m->id)->count());
        $this->dbRefuses(fn () => DB::table('proposal_versions')->where('id', $v1->id)->update(['price_xof' => 1]), 'ajout seul');
        $this->dbRefuses(fn () => DB::table('proposal_versions')->where('id', $v2->id)->delete(), 'ajout seul');

        // formulaire périmé (autre onglet) : refus sans écrasement
        $this->propose($m, null, ['price_xof' => '70000'], expected: 1)->assertRedirect()->assertSessionHas('error');
        $this->assertSame(2, $this->latest($m, $this->freelancer)->number);
        // validations lisibles
        $this->propose($m, null, ['price_xof' => '10', 'scope' => 'court', 'deliverables' => ''])->assertSessionHasErrors(['price_xof', 'scope', 'deliverables']);
        $this->propose($m, null, ['scope' => str_repeat('Appelez le 07 08 09 10 11. ', 4)])->assertSessionHasErrors('scope');

        // retrait puis nouvelle version qui réactive
        $p = Proposal::firstOrFail();
        $this->actingAs($this->freelancer)->post("/freelance/propositions/{$p->id}/retirer")->assertRedirect()->assertSessionHas('status');
        $this->assertSame('withdrawn', $p->fresh()->state);
        $this->actingAs($this->client)->get("/espace/missions/{$m->id}/propositions")->assertOk()->assertDontSee('Kader Freelance');
        $this->propose($m)->assertRedirect()->assertSessionHas('status');
        $this->assertSame(['active', 3], [$p->fresh()->state, $this->latest($m, $this->freelancer)->number]);
        $this->assertSame(1, Proposal::where('mission_id', $m->id)->count());

        // jamais sur sa propre mission : action et base
        $own = $this->openMission($this->other, ['title' => 'Besoin de mon propre concurrent freelance']);
        $this->propose($own, $this->other)->assertRedirect()->assertSessionHas('error');
        $this->assertSame(0, Proposal::where('mission_id', $own->id)->count());
        $this->dbRefuses(fn () => DB::table('proposals')->insert(['id' => (string) Str::uuid(), 'mission_id' => $own->id, 'freelancer_id' => $this->other->id, 'state' => 'active', 'created_at' => now(), 'updated_at' => now()]), 'propre mission');
        // un seul « actif » par couple mission/freelance (base)
        $this->dbRefuses(fn () => DB::table('proposals')->insert(['id' => (string) Str::uuid(), 'mission_id' => $m->id, 'freelancer_id' => $this->freelancer->id, 'state' => 'active', 'created_at' => now(), 'updated_at' => now()]));

        // date limite dépassée : propositions closes
        $this->travel(6)->days();
        $this->propose($m, $this->other)->assertRedirect()->assertSessionHas('error');
    }

    public function test_changing_a_mission_after_proposals_versions_it_and_requires_reconfirmation_before_selection(): void
    {
        $m = $this->openMission();
        $this->propose($m)->assertRedirect();
        $pv1 = $this->latest($m, $this->freelancer);
        $v1 = $m->versions()->where('state', 'published')->firstOrFail();

        $this->actingAs($this->client)->post("/espace/missions/{$m->id}/nouvelle-version")->assertRedirect()->assertSessionHas('status');
        $v2 = $m->versions()->where('state', 'draft')->firstOrFail();
        $this->actingAs($this->client)->post("/espace/missions/{$m->id}/modifier", $this->missionForm(['budget_xof' => '200000', 'title' => 'Conversion de quinze plans PDF en fichiers DWG', 'client_inputs' => 'Nombre de plans']) + ['revision_no' => $v2->revision_no])->assertRedirect()->assertSessionHas('status');
        $this->actingAs($this->client)->post("/espace/missions/{$m->id}/soumettre", ['revision_no' => $v2->fresh()->revision_no])->assertRedirect();
        $this->get("/missions/{$m->fresh()->slug}")->assertOk()->assertSee('Conversion de douze plans')->assertDontSee('quinze plans');       // la version publiée reste visible
        $this->dbRefuses(fn () => DB::table('mission_versions')->where('id', $v2->id)->update(['title' => 'modif silencieuse']), 'ne se modifie pas');
        $this->dbRefuses(fn () => DB::table('mission_versions')->where('id', $v1->id)->update(['budget_xof' => 1]), 'ne se modifie pas');
        // pendant le contrôle de la nouvelle version, l'ancienne proposition reste sélectionnable (besoin publié inchangé)
        $this->actingAs($this->client)->get("/espace/missions/{$m->id}/propositions/{$pv1->id}/choisir")->assertOk();

        app(MissionModeration::class)->approve($this->admin, $v2->id);
        $this->get("/missions/{$m->fresh()->slug}")->assertOk()->assertSee('quinze plans');
        $this->assertSame(['superseded', 'published'], $m->versions()->orderBy('number')->pluck('state')->all());
        $this->actingAs($this->client)->get("/espace/missions/{$m->id}/propositions")->assertOk()->assertSee('Besoin modifié depuis cette proposition');
        $this->actingAs($this->client)->get("/espace/missions/{$m->id}/propositions/{$pv1->id}/choisir")->assertRedirect()->assertSessionHas('error');
        $this->select($m, $pv1)->assertRedirect()->assertSessionHas('error');
        $this->assertSame(0, Order::count());
        $this->actingAs($this->freelancer)->get("/missions/{$m->fresh()->slug}")->assertOk()->assertSee('Besoin modifié depuis votre proposition');
        $this->actingAs($this->freelancer)->get('/freelance/propositions')->assertOk()->assertSee('Besoin modifié : à reconfirmer');

        // reconfirmation = nouvelle version liée à la nouvelle version du besoin ; l'ancienne reste conservée
        $this->propose($m)->assertRedirect()->assertSessionHas('status');
        $pv2 = $this->latest($m, $this->freelancer);
        $this->assertSame([$v2->id, $v1->id], [$pv2->mission_version_id, $pv1->fresh()->mission_version_id]);
        $this->actingAs($this->client)->get("/espace/missions/{$m->id}/propositions/{$pv2->id}/choisir")->assertOk();
    }

    // ---------- sélection ----------

    public function test_selection_reserves_the_mission_creates_one_order_with_a_frozen_agreement_and_is_exclusive(): void
    {
        $m = $this->openMission();
        $this->propose($m)->assertRedirect();
        $this->propose($m, $this->other, ['price_xof' => '80000', 'delivery_days' => '9'])->assertRedirect();
        $a = $this->latest($m, $this->freelancer);
        $b = $this->latest($m, $this->other);

        // comparaison lisible
        $this->actingAs($this->client)->get("/espace/missions/{$m->id}/propositions?comparer[]={$a->proposal_id}&comparer[]={$b->proposal_id}")->assertOk()->assertSee('Comparaison')->assertSee('Prix ferme')->assertSee('Valable jusqu’au');
        // la page de choix expose les conditions ; sans confirmation ni brief rien n'est retenu
        $this->actingAs($this->client)->get("/espace/missions/{$m->id}/propositions/{$a->id}/choisir")->assertOk()->assertSee('Conditions que vous acceptez')->assertSee('rien n’est rouvert automatiquement', false);
        $this->select($m, $a, ['conditions' => null, 'answers' => ['', '']])->assertSessionHasErrors(['conditions', 'answers.0', 'answers.1']);
        $this->assertSame(0, Order::count());
        $this->assertSame('open', $m->fresh()->status);

        $key = (string) Str::uuid();
        $version = $m->fresh()->row_version;
        $this->select($m, $a, ['expected_version' => $version], $key)->assertRedirect()->assertSessionHas('status');
        $order = Order::firstOrFail();
        $this->assertSame(['mission', null, $m->id, $a->id, OrderState::AwaitingPayment], [$order->origin, $order->service_id, $order->mission_id, $order->proposal_version_id, $order->state]);
        $ag = $order->agreement;
        $this->assertSame(['mission', 95000, 6, 2, 'message', 'Conversion de douze plans PDF en fichiers DWG'], [$ag->origin, $ag->price_xof, $ag->delivery_days, $ag->revisions_included, $ag->delivery_mode, $ag->service_title]);
        $this->assertSame(['Douze fichiers DWG', 'Un PDF de contrôle'], $ag->deliverables);
        $this->assertSame(['Nombre de plans', 'Version AutoCAD'], $ag->client_inputs);
        $this->assertSame('12', $order->brief->answers[0]['answer']);
        $m->refresh();
        $this->assertSame(['reserved', $a->id], [$m->status, $m->selected_proposal_version_id]);
        $this->assertSame('selected', Proposal::find($a->proposal_id)->state);
        $this->assertNull($order->started_at, 'aucun travail sans paiement confirmé');
        $this->assertNull($order->payment_deadline_at, 'paiement non ouvert : aucune échéance (commande réelle)');

        // exclusivité : même clé = rejouée ; autre clé / autre proposition = refusée ; la base elle-même refuse deux commandes vivantes
        $this->select($m, $a, ['expected_version' => $version], $key)->assertRedirect()->assertSessionHas('status', 'Cette sélection avait déjà été enregistrée.');
        $this->select($m, $b)->assertRedirect()->assertSessionHas('error');
        $this->select($m, $a, [], (string) Str::uuid())->assertRedirect()->assertSessionHas('error');
        $this->assertSame(1, Order::count());
        $this->dbRefuses(fn () => DB::table('orders')->where('id', $order->id)->get()->each(fn ($o) => DB::table('orders')->insert(['id' => (string) Str::uuid(), 'reference' => 'FC-X-1', 'client_id' => $o->client_id, 'freelancer_id' => $this->other->id, 'service_id' => null, 'origin' => 'mission', 'mission_id' => $m->id,
            'proposal_version_id' => $b->id, 'state' => 'awaiting_payment', 'requested_at' => now(), 'response_deadline_at' => now(), 'created_at' => now(), 'updated_at' => now()])), 'order_mission_live_uq');
        $this->actingAs($this->client)->get("/espace/missions/{$m->id}/propositions/{$b->id}/choisir")->assertRedirect()->assertSessionHas('error');
        // une mission réservée ne se modifie ni ne se ferme : il faut d'abord annuler la commande
        $this->actingAs($this->client)->post("/espace/missions/{$m->id}/nouvelle-version")->assertRedirect()->assertSessionHas('error');
        $this->actingAs($this->client)->post("/espace/missions/{$m->id}/fermer")->assertRedirect()->assertSessionHas('error');
        $this->get('/missions')->assertDontSee('Conversion de douze plans');
        // le dossier de commande montre l'origine
        $this->actingAs($this->client)->get("/commandes/{$order->reference}")->assertOk()->assertSee('Mission')->assertSee('proposition v1 retenue')->assertSee('La proposition vaut acceptation');
        $this->actingAs($this->other)->get("/commandes/{$order->reference}")->assertNotFound();
    }

    public function test_an_old_version_or_an_expired_proposal_cannot_be_selected(): void
    {
        $m = $this->openMission();
        $this->propose($m)->assertRedirect();
        $v1 = $this->latest($m, $this->freelancer);
        $this->propose($m, null, ['price_xof' => '90000'])->assertRedirect();
        $this->select($m, $v1)->assertRedirect()->assertSessionHas('error');
        $this->assertSame(0, Order::count());
        $this->travel(8)->days();                                     // validité (7 j) dépassée ; la sélection reste ouverte (14 j après la date limite)
        $v2 = $this->latest($m, $this->freelancer);
        $this->select($m, $v2)->assertRedirect()->assertSessionHas('error');
        $this->assertSame(0, Order::count());
        $this->actingAs($this->client)->get("/espace/missions/{$m->id}/propositions")->assertOk()->assertSee('Validité dépassée');
    }

    // ---------- raccordement au cycle de commande ----------

    public function test_mission_orders_follow_the_same_payment_brief_start_delivery_and_validation_rules(): void
    {
        $this->enableSandbox();
        $m = $this->openMission();
        $this->propose($m)->assertRedirect();
        $pv = $this->latest($m, $this->freelancer);
        $this->select($m, $pv)->assertRedirect()->assertSessionHas('status');
        $order = Order::firstOrFail();
        $this->assertNotNull($order->payment_deadline_at, 'paiement ouvert : commande de test créée en mode sandbox');
        $this->assertSame('reserved', $m->fresh()->status, 'attribuée seulement après paiement confirmé');

        $this->assertSame('test', $order->environment, 'marquée « test » dès la création (origine mission)');
        $this->startPayment($order)->assertRedirect();
        $this->assertSame('reserved', $m->fresh()->status);
        $this->settle($order);
        $order->refresh();
        $this->assertSame(OrderState::InProgress, $order->state, 'brief complet + paiement confirmé : une seule fois');
        $this->assertNotNull($order->started_at);
        $this->assertSame('awarded', $m->fresh()->status);
        $this->actingAs($this->client)->get("/espace/missions/{$m->id}")->assertOk()->assertSee('Attribuée');

        // livraison, validation : mêmes règles que pour une commande issue d'un service
        $this->actingAs($this->freelancer)->post("/commandes/{$order->reference}/livraison/message", ['message' => 'Voici les douze plans convertis.'])->assertRedirect();
        $draft = Delivery::where('order_id', $order->id)->where('state', 'draft')->firstOrFail();
        $this->actingAs($this->freelancer)->post("/commandes/{$order->reference}/livraison/soumettre", ['delivery_id' => $draft->id, 'expected_version' => $order->row_version, 'operation_key' => 'k1'])->assertRedirect()->assertSessionHas('status');
        $v = $order->fresh();
        $this->actingAs($this->client)->post("/commandes/{$order->reference}/validation", ['delivery_id' => $draft->id, 'expected_version' => $v->row_version, 'operation_key' => 'k2', 'confirm' => '1'])->assertRedirect()->assertSessionHas('status');
        $this->assertSame(OrderState::Closed, $order->fresh()->state);
        $this->assertSame(1, DB::table('order_events')->where('order_id', $order->id)->where('type', 'work_started')->count());
    }

    public function test_a_test_mission_order_never_becomes_payable_in_live_mode_and_an_unopened_payment_starts_no_deadline(): void
    {
        $this->enableSandbox();
        $m = $this->openMission();                                                           // comptes ordinaires : aucune restriction de personne
        $this->propose($m)->assertRedirect();
        $this->select($m, $this->latest($m, $this->freelancer))->assertRedirect()->assertSessionHas('status');
        $order = Order::firstOrFail();
        $this->assertSame('test', $order->environment);
        $this->assertNotNull($order->payment_deadline_at);

        // bascule explicite vers le live (configuration complète et autorisée) : la commande de test reste de test et n'est jamais payable en argent réel
        config(['freeci.payments.mode' => 'live', 'freeci.payments.live_authorized' => true,
            'freeci.payments.genius.live.api_key' => 'pk_live_k', 'freeci.payments.genius.live.api_secret' => 'sk_live_s',
            'freeci.payments.genius.live.webhook_secret' => 'whsec_live_0123456789', 'freeci.payments.genius.live.merchant_id' => 'merchant-uuid-1234']);
        $this->assertTrue(app(PaymentMode::class)::creationOpen());
        $this->assertSame('order_environment_mismatch', app(PaymentGate::class)->denial($order->fresh()));
        $this->startPayment($order)->assertStatus(409);
        $this->assertSame(0, DB::table('payments')->count());
        $this->assertSame('test', $order->fresh()->environment);
        $this->actingAs($this->client)->get("/commandes/{$order->reference}")->assertOk()->assertSee('Commande de test')->assertSee('ne peut jamais être payée en argent réel');
    }

    public function test_cancelling_or_expiring_before_payment_frees_the_proposal_and_never_reopens_the_mission_implicitly(): void
    {
        $this->enableSandbox();
        $m = $this->openMission();
        $this->propose($m)->assertRedirect();
        $this->propose($m, $this->other, ['price_xof' => '80000'])->assertRedirect();
        $a = $this->latest($m, $this->freelancer);
        $this->select($m, $a)->assertRedirect()->assertSessionHas('status');
        $order = Order::firstOrFail();

        // annulation avant paiement : explicitée dans la confirmation, puis « sélection terminée » (pas « ouverte »)
        $this->actingAs($this->client)->get("/commandes/{$order->reference}/cancel")->assertOk()->assertSee('n’est PAS rouverte automatiquement', false);
        $this->actingAs($this->client)->post("/commandes/{$order->reference}/cancel", ['expected_version' => $order->fresh()->row_version, 'operation_key' => (string) Str::uuid()])->assertRedirect();
        $m->refresh();
        $this->assertSame(['selection_ended', null], [$m->status, $m->selected_proposal_version_id]);
        $this->assertSame('released', Proposal::find($a->proposal_id)->state);
        $this->assertSame(OrderState::Cancelled, $order->fresh()->state);
        $this->get('/missions')->assertDontSee('Conversion de douze plans');               // ni visible ni ouverte tant que le client n'a pas décidé
        $this->actingAs($this->client)->get('/espace/missions')->assertOk()->assertSee('Sélection terminée')->assertSee('Action attendue');
        $this->actingAs($this->client)->get("/espace/missions/{$m->id}")->assertOk()->assertSee('n’a pas été rouverte automatiquement');
        $this->propose($m, $this->other, ['price_xof' => '79000'])->assertRedirect()->assertSessionHas('error');       // pas de nouvelle candidature tant que non rouverte
        $this->select($m, $this->latest($m, $this->other))->assertRedirect()->assertSessionHas('error');             // ni de sélection

        // réouverture EXPLICITE : l'autre proposition redevient sélectionnable, la proposition libérée doit être reconfirmée
        $this->actingAs($this->client)->post("/espace/missions/{$m->id}/rouvrir")->assertRedirect()->assertSessionHas('status');
        $this->assertSame('open', $m->fresh()->status);
        $this->actingAs($this->client)->get("/espace/missions/{$m->id}/propositions/{$this->latest($m, $this->other)->id}/choisir")->assertOk();
        $this->assertSame('released', Proposal::find($a->proposal_id)->state);
        $this->actingAs($this->client)->get("/espace/missions/{$m->id}/propositions")->assertOk()->assertDontSee('Kader Freelance');
        $this->propose($m)->assertRedirect()->assertSessionHas('status');                                              // reconfirmation par son auteur
        $this->assertSame('active', Proposal::find($a->proposal_id)->state);

        // expiration du paiement (24 h) sur une nouvelle sélection : même conséquence
        $this->select($m, $this->latest($m, $this->freelancer))->assertRedirect()->assertSessionHas('status');
        $order2 = Order::where('state', 'awaiting_payment')->firstOrFail();
        $this->travel(25)->hours();
        $this->artisan('freeci:orders:expire')->assertSuccessful();
        $this->assertSame(OrderState::Expired, $order2->fresh()->state);
        $this->assertSame('selection_ended', $m->fresh()->status);
        $this->assertSame(1, DB::table('mission_events')->where('mission_id', $m->id)->where('type', 'reservation_ended')->count() - 1);
        // fermer est l'autre choix explicite
        $this->actingAs($this->client)->post("/espace/missions/{$m->id}/fermer")->assertRedirect()->assertSessionHas('status');
        $this->assertSame('closed', $m->fresh()->status);
        $this->assertSame(0, Proposal::where('mission_id', $m->id)->where('state', 'active')->count());
    }

    public function test_reopening_is_refused_once_the_selection_period_is_over_and_idle_missions_expire(): void
    {
        $this->enableSandbox();
        $this->client->forceFill(['is_demo' => true])->save();
        $m = $this->openMission();
        $this->propose($m)->assertRedirect();
        $this->select($m, $this->latest($m, $this->freelancer))->assertRedirect();
        $order = Order::firstOrFail();
        $this->actingAs($this->client)->post("/commandes/{$order->reference}/cancel", ['expected_version' => $order->fresh()->row_version, 'operation_key' => (string) Str::uuid()])->assertRedirect();
        $this->assertSame('selection_ended', $m->fresh()->status);
        $this->travel(20)->days();                                                          // date limite (5 j) + 14 j de sélection dépassés
        $this->actingAs($this->client)->post("/espace/missions/{$m->id}/rouvrir")->assertRedirect()->assertSessionHas('error');
        $this->actingAs($this->client)->get("/espace/missions/{$m->id}")->assertOk();
        $this->assertSame('expired', $m->fresh()->status, 'expirée à la lecture : jamais rouverte');

        $m2 = $this->openMission($this->client, ['title' => 'Autre besoin sans aucune proposition reçue']);
        $this->propose($m2, $this->other)->assertRedirect();
        $this->travel(30)->days();
        $this->artisan('freeci:orders:expire')->assertSuccessful();
        $this->assertSame('expired', $m2->fresh()->status);
        $this->assertSame(0, Proposal::where('mission_id', $m2->id)->where('state', 'active')->count());
        $this->get("/missions/{$m2->slug}")->assertOk()->assertSee('n’accepte plus de propositions');
    }

    public function test_existing_service_orders_and_agreements_are_unchanged_by_the_new_origin(): void
    {
        $order = $this->placeOrder();
        $this->assertSame(['service', $this->service->id], [$order->origin, $order->service_id]);
        $this->assertSame('service', $order->agreement->origin);
        $this->assertNotNull($order->agreement->service_row_version);
        $this->actingAs($this->client)->get("/commandes/{$order->reference}")->assertOk()->assertSee('Prestation')->assertSee('du service, au moment de la demande')->assertDontSee('proposition v');
        $this->dbRefuses(fn () => DB::table('orders')->where('id', $order->id)->update(['service_id' => null]), 'orders_origin_chk');
        $this->dbRefuses(fn () => DB::table('orders')->where('id', $order->id)->update(['origin' => 'mission']), 'orders_origin_chk');
    }

    public function test_pending_requests_cannot_be_forced_through_the_mission_origin(): void
    {
        $m = $this->openMission();
        $this->propose($m)->assertRedirect();
        $pv = $this->latest($m, $this->freelancer);
        // un tiers (même administrateur) ne peut pas sélectionner pour le compte du client
        foreach ([$this->other, $this->freelancer, $this->admin] as $who) {
            $this->actingAs($who)->post("/espace/missions/{$m->id}/propositions/{$pv->id}/choisir", ['expected_version' => 1, 'operation_key' => 'x', 'conditions' => '1', 'answers' => ['1', '2']])->assertNotFound();
        }
        $this->assertSame(0, Order::count());
        $this->assertNotNull(MissionVersion::query()->where('mission_id', $m->id)->where('state', 'published')->first());
    }

    public function test_dashboards_surface_the_real_mission_actions(): void
    {
        $m = $this->openMission();
        $this->propose($m)->assertRedirect();
        $this->actingAs($this->client)->get('/espace')->assertOk()->assertSee('1 proposition à examiner')->assertSee('Comparer les propositions');
        $v = $m->versions()->first();
        $this->actingAs($this->client)->post("/espace/missions/{$m->id}/nouvelle-version");
        $w = $m->versions()->where('state', 'draft')->firstOrFail();
        $this->actingAs($this->client)->post("/espace/missions/{$m->id}/modifier", $this->missionForm(['budget_xof' => '150000']) + ['revision_no' => $w->revision_no]);
        $this->actingAs($this->client)->post("/espace/missions/{$m->id}/soumettre", ['revision_no' => $w->fresh()->revision_no]);
        app(MissionModeration::class)->approve($this->admin, $w->id);
        $this->actingAs($this->freelancer)->get('/freelance')->assertOk()->assertSee('Reconfirmer votre proposition');
        $this->assertNotNull($v);
    }

    public function test_mission_editor_shows_steps_counters_checklist_and_the_action_bar(): void
    {
        $this->actingAs($this->client)->post('/espace/missions', ['title' => 'Conversion de douze plans PDF en fichiers DWG', 'category_id' => $this->service->category_id])->assertRedirect();
        $m = Mission::query()->orderByDesc('created_at')->orderByDesc('id')->firstOrFail();
        $v = $m->versions()->first();
        $this->actingAs($this->client)->post("/espace/missions/{$m->id}/modifier", $this->missionForm() + ['revision_no' => $v->revision_no])->assertRedirect();

        $page = $this->actingAs($this->client)->get("/espace/missions/{$m->id}/modifier")->assertOk()->assertSee('Avant de publier')->assertSee('Après la publication')->assertSee('Enregistrer le brouillon')
            ->assertSee('Continuer vers la modération')->assertSee('data-count="description"', false)->assertSee('Votre mission en résumé');
        $html = $page->getContent();
        $this->assertMatchesRegularExpression('/data-check="title" class="ok"/', $html);
        $this->assertMatchesRegularExpression('/data-check="budget" class="ok"/', $html);
        $this->assertMatchesRegularExpression('/data-check="deadline" class="ok"/', $html);
        $this->assertMatchesRegularExpression('/data-check="inputs" data-optional class="ok"/', $html);
    }

    public function test_proposal_pages_follow_the_new_layout_with_filters_cards_and_the_selection_side_panel(): void
    {
        $m = $this->openMission();
        $this->propose($m)->assertRedirect();
        $this->propose($m, $this->other, ['price_xof' => '95000'])->assertRedirect();
        $pv = $this->latest($m, $this->freelancer);

        $this->actingAs($this->freelancer)->get('/freelance/propositions')->assertOk()->assertSee('Mes propositions')->assertSee('Propositions envoyées')->assertSee('Réviser')->assertSee('Voir la mission');
        $this->actingAs($this->freelancer)->get('/freelance/propositions?statut=retenues')->assertOk()->assertSee('Aucune proposition dans cette catégorie');
        $this->actingAs($this->freelancer)->get("/missions/{$m->fresh()->slug}/proposition")->assertOk()->assertSee('Votre offre')->assertSee('Votre proposition en résumé')->assertSee('accord figé');
        $this->actingAs($this->client)->get("/espace/missions/{$m->id}/propositions")->assertOk()->assertSee('Propositions reçues')->assertSee('Comparer la sélection (2 ou 3)')->assertSee('Retenir cette proposition');
        $this->actingAs($this->client)->get("/espace/missions/{$m->id}/propositions/{$pv->id}/choisir")->assertOk()->assertSee('Ce qui va se passer')->assertSee('Conditions que vous acceptez')->assertSee('une seule');
    }
}

<?php

namespace Tests\Feature;

use App\Modules\Accounts\Actions\GrantAdministrator;
use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\FreelanceProfile;
use App\Modules\Missions\Actions\MissionAlerts;
use App\Modules\Missions\Actions\MissionModeration;
use App\Modules\Missions\Models\Mission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\Support\OrderFixtures;
use Tests\TestCase;

/** F-11 (alertes de missions, missions pour vous) et F-12 (invitation d'un freelance à une mission). */
class MissionAlertsInvitationsTest extends TestCase
{
    use OrderFixtures, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->setUpParties();
        $this->admin = User::factory()->create();
        app(GrantAdministrator::class)($this->admin, 'test');
    }

    private function openMission(?User $client = null, array $o = []): Mission
    {
        $client ??= $this->client;
        $this->actingAs($client)->post('/espace/missions', ['title' => $o['title'] ?? 'Conversion de douze plans PDF en fichiers DWG', 'category_id' => $o['category_id'] ?? $this->service->category_id])->assertRedirect();
        $m = Mission::query()->orderByDesc('created_at')->orderByDesc('id')->firstOrFail();
        $v = $m->versions()->first();
        $form = array_merge(['title' => 'Conversion de douze plans PDF en fichiers DWG', 'category_id' => $this->service->category_id,
            'description' => str_repeat('Douze plans d’architecture en PDF à convertir en DWG AutoCAD 2018, calques conservés. ', 3), 'budget_xof' => '120 000',
            'application_deadline' => now()->addDays(5)->format('Y-m-d'), 'client_inputs' => "Nombre de plans\nVersion AutoCAD", 'intent' => 'save'], $o);
        $this->actingAs($client)->post("/espace/missions/{$m->id}/modifier", $form + ['revision_no' => $v->revision_no])->assertRedirect()->assertSessionHas('status');
        $this->actingAs($client)->post("/espace/missions/{$m->id}/soumettre", ['revision_no' => $v->fresh()->revision_no])->assertRedirect()->assertSessionHas('status');
        app(MissionModeration::class)->approve($this->admin, $v->id);

        return $m->refresh();
    }

    private function alert(User $u, ?string $category = null, ?string $min = null)
    {
        return $this->actingAs($u)->post('/freelance/alertes', array_filter(['category' => $category ?? $this->service->category->slug, 'min_budget' => $min], fn ($v) => $v !== null));
    }

    private function alerts(User $u): int
    {
        return DB::table('app_notifications')->where('user_id', $u->id)->where('type', 'mission_alert')->count();
    }

    private function slug(): string
    {
        return $this->freelancer->freelanceProfile->slug;
    }

    // ---- F-11 -------------------------------------------------------------------------------------------------------------------------------------

    public function test_alert_creation_rules(): void
    {
        $this->alert($this->freelancer, null, '50 000')->assertSessionHas('status');
        $this->assertSame(50000, (int) DB::table('mission_alerts')->where('user_id', $this->freelancer->id)->value('min_budget_xof'));
        $this->alert($this->freelancer, null, '50000')->assertSessionHas('error');                          // doublon
        $this->alert($this->freelancer, null, 'abc')->assertSessionHas('error');
        $this->alert($this->freelancer, 'inconnue')->assertSessionHas('error');
        $this->assertSame(1, DB::table('mission_alerts')->count());
        foreach ([1000, 2000, 3000, 4000] as $b) {
            $this->alert($this->freelancer, null, (string) $b)->assertSessionHas('status');
        }
        $this->alert($this->freelancer, null, '9000')->assertSessionHas('error');                           // 5 au plus
        $this->assertSame(5, DB::table('mission_alerts')->count());
        $this->actingAs($this->client)->post('/freelance/alertes', ['category' => $this->service->category->slug])->assertRedirect();   // réservé aux freelances
    }

    public function test_first_publication_notifies_only_matching_active_available_freelancers_once(): void
    {
        $this->alert($this->freelancer, null, '100 000');
        $this->alert($this->freelancer);                                                                     // deux alertes qui correspondent : une seule notification
        $below = User::factory()->create();
        $below->roles()->firstOrCreate(['role' => 'freelance']);
        FreelanceProfile::factory()->create(['user_id' => $below->id, 'display_name' => 'Budget haut']);
        $this->alert($below, null, '500 000');
        $paused = User::factory()->create();
        $paused->roles()->firstOrCreate(['role' => 'freelance']);
        FreelanceProfile::factory()->create(['user_id' => $paused->id, 'display_name' => 'En pause']);
        $this->alert($paused);
        DB::table('mission_alerts')->where('user_id', $paused->id)->update(['active' => false]);
        $away = User::factory()->create();
        $away->roles()->firstOrCreate(['role' => 'freelance']);
        FreelanceProfile::factory()->create(['user_id' => $away->id, 'display_name' => 'Indisponible', 'unavailable_at' => now()]);
        $this->alert($away);

        $m = $this->openMission();

        $this->assertSame(1, $this->alerts($this->freelancer));
        $this->assertSame(0, $this->alerts($below));
        $this->assertSame(0, $this->alerts($paused));
        $this->assertSame(0, $this->alerts($away));
        $this->assertSame(0, $this->alerts($this->client));
        $n = DB::table('app_notifications')->where('user_id', $this->freelancer->id)->where('type', 'mission_alert')->first();
        $this->assertStringContainsString('Conversion de douze plans', $n->title);
        $this->assertSame('mission_alert:'.$m->id, $n->dedupe_key);
        $this->assertSame(0, app(MissionAlerts::class)->notifyPublished($m->id));   // idempotent
    }

    public function test_daily_cap_and_own_mission_are_respected(): void
    {
        config(['freeci.missions.alerts.daily_notifications' => 1]);
        $this->alert($this->freelancer);
        $this->openMission();
        $this->openMission(null, ['title' => 'Seconde mission de plans en DWG']);
        $this->assertSame(1, $this->alerts($this->freelancer));
        // le freelance qui publie lui-même n'est pas alerté de sa propre mission
        DB::table('app_notifications')->where('type', 'mission_alert')->delete();
        $this->openMission($this->freelancer, ['title' => 'Ma propre mission de plans']);
        $this->assertSame(0, $this->alerts($this->freelancer));
    }

    public function test_alert_actions_are_owner_only_and_pause_resume_delete_work(): void
    {
        $this->alert($this->freelancer);
        $id = DB::table('mission_alerts')->value('id');
        $other = User::factory()->create();
        $other->roles()->firstOrCreate(['role' => 'freelance']);
        $this->actingAs($other)->post("/freelance/alertes/{$id}/etat", ['active' => '0'])->assertSessionHas('error');
        $this->actingAs($other)->post("/freelance/alertes/{$id}/supprimer")->assertSessionHas('error');
        $this->assertTrue((bool) DB::table('mission_alerts')->where('id', $id)->value('active'));
        $this->actingAs($this->freelancer)->post("/freelance/alertes/{$id}/etat", ['active' => '0'])->assertSessionHas('status');
        $this->assertFalse((bool) DB::table('mission_alerts')->where('id', $id)->value('active'));
        $this->actingAs($this->freelancer)->get('/freelance/alertes')->assertOk()->assertSee('En pause');
        $this->actingAs($this->freelancer)->post("/freelance/alertes/{$id}/supprimer")->assertSessionHas('status');
        $this->assertSame(0, DB::table('mission_alerts')->count());
    }

    public function test_recommended_missions_use_alerts_then_services_and_hide_proposed_ones(): void
    {
        $m = $this->openMission();
        $other = Category::query()->where('id', '<>', $this->service->category_id)->first() ?? Category::factory()->create();
        $this->openMission(null, ['title' => 'Mission hors catégorie de service', 'category_id' => $other->id]);

        // sans alerte : catégories de ses services publiés
        $this->actingAs($this->freelancer)->get('/freelance/missions-pour-vous')->assertOk()->assertSee('Conversion de douze plans')->assertDontSee('Mission hors catégorie')->assertSee('Catégorie de vos services');
        // avec une alerte sur une autre catégorie : seule celle-là compte
        $this->alert($this->freelancer, $other->slug);
        $this->actingAs($this->freelancer)->get('/freelance/missions-pour-vous')->assertOk()->assertSee('Mission hors catégorie')->assertDontSee('Conversion de douze plans');
        // alerte avec budget minimum trop haut : rien
        DB::table('mission_alerts')->delete();
        $this->alert($this->freelancer, null, '900 000');
        $this->actingAs($this->freelancer)->get('/freelance/missions-pour-vous')->assertOk()->assertDontSee('Conversion de douze plans');
        DB::table('mission_alerts')->delete();
        $this->alert($this->freelancer, null, '100 000');
        $this->actingAs($this->freelancer)->get('/freelance/missions-pour-vous')->assertOk()->assertSee('Conversion de douze plans');
        // une proposition active la retire de la liste
        $this->actingAs($this->freelancer)->post("/missions/{$m->slug}/proposition", ['price_xof' => '95 000', 'delivery_days' => '6', 'revisions_included' => '2', 'validity_days' => '7', 'delivery_mode' => 'message',
            'scope' => 'Conversion des douze plans en DWG AutoCAD 2018, calques conservés, un fichier par plan et un PDF de contrôle.', 'deliverables' => "Douze fichiers DWG\nUn PDF de contrôle", 'expected_number' => 0])->assertSessionHasNoErrors();
        $this->actingAs($this->freelancer)->get('/freelance/missions-pour-vous')->assertOk()->assertDontSee('Conversion de douze plans');
        $this->actingAs($this->freelancer)->get('/freelance')->assertOk()->assertSee('Missions pour vous');
    }

    // ---- F-12 -------------------------------------------------------------------------------------------------------------------------------------

    private function invite(array $o = [], ?User $by = null)
    {
        return $this->actingAs($by ?? $this->client)->post('/freelances/'.($o['slug'] ?? $this->slug()).'/inviter', array_filter(['mission' => $o['mission'] ?? null, 'message' => $o['message'] ?? null], fn ($v) => $v !== null));
    }

    public function test_client_invites_and_freelancer_sees_only_public_mission_data(): void
    {
        $m = $this->openMission();
        $this->actingAs($this->client)->get('/freelances/'.$this->slug().'/inviter')->assertOk()->assertSee('Conversion de douze plans');
        $this->invite(['mission' => $m->id, 'message' => 'Votre portfolio me convient.'])->assertRedirect("/espace/missions/{$m->id}/invitations")->assertSessionHas('status');
        $inv = DB::table('mission_invitations')->first();
        $this->assertSame('pending', $inv->state);
        $n = DB::table('app_notifications')->where('user_id', $this->freelancer->id)->where('type', 'mission_invitation')->first();
        $this->assertNotNull($n);
        $this->assertSame('essential', $n->category);

        $page = $this->actingAs($this->freelancer)->get('/freelance/invitations')->assertOk()->assertSee('Conversion de douze plans')->assertSee('Votre portfolio me convient.')->assertSee('À traiter');
        $page->assertDontSee('Fanta Client');
        $this->actingAs($this->client)->get("/espace/missions/{$m->id}/invitations")->assertOk()->assertSee('Kader Freelance')->assertSee('En attente');
        $this->actingAs($this->client)->get("/espace/missions/{$m->id}")->assertOk()->assertSee('Invitations');
    }

    public function test_invitation_refusals(): void
    {
        $m = $this->openMission();
        $this->invite(['mission' => $m->id, 'message' => 'Appelez-moi au 07 08 09 10 11'])->assertSessionHas('error');
        $this->invite(['mission' => $m->id, 'message' => 'Écrivez à moi@exemple.ci'])->assertSessionHas('error');
        $this->invite(['mission' => $m->id])->assertSessionHas('status');
        $this->invite(['mission' => $m->id])->assertSessionHas('error');                                   // déjà invité
        $this->assertSame(1, DB::table('mission_invitations')->count());

        // mission d'un autre client
        $this->invite(['mission' => $m->id], $this->freelancer)->assertSessionHas('error');
        // se soi-même inviter : profil du freelance lui-même → 404 sur la page, refus à l'envoi
        $this->actingAs($this->freelancer)->get('/freelances/'.$this->slug().'/inviter')->assertNotFound();
        // mission non ouverte
        $closed = $this->openMission(null, ['title' => 'Mission qui sera fermée bientôt']);
        DB::table('missions')->where('id', $closed->id)->update(['status' => 'closed']);
        $this->invite(['mission' => $closed->id])->assertSessionHas('error');
        // profil inconnu
        $this->invite(['mission' => $m->id, 'slug' => 'inconnu'])->assertNotFound();
        // freelance indisponible
        $other = User::factory()->create();
        $other->roles()->firstOrCreate(['role' => 'freelance']);
        $p = FreelanceProfile::factory()->create(['user_id' => $other->id, 'display_name' => 'Absent', 'unavailable_at' => now()]);
        $this->invite(['mission' => $m->id, 'slug' => $p->slug])->assertSessionHas('error');
        $this->actingAs($this->client)->get('/freelances/'.$p->slug.'/inviter')->assertOk()->assertSee('indisponible');
        $this->assertSame(1, DB::table('mission_invitations')->count());
    }

    public function test_caps_per_mission_and_per_day(): void
    {
        $m = $this->openMission();
        config(['freeci.missions.invitations.per_mission' => 1]);
        $a = FreelanceProfile::factory()->create(['user_id' => tap(User::factory()->create(), fn ($u) => $u->roles()->firstOrCreate(['role' => 'freelance']))->id, 'display_name' => 'Deuxième']);
        $this->invite(['mission' => $m->id])->assertSessionHas('status');
        $this->invite(['mission' => $m->id, 'slug' => $a->slug])->assertSessionHas('error');
        config(['freeci.missions.invitations.per_mission' => 10, 'freeci.missions.invitations.per_client_day' => 1]);
        $this->invite(['mission' => $m->id, 'slug' => $a->slug])->assertSessionHas('error');
        $this->assertSame(1, DB::table('mission_invitations')->count());
    }

    public function test_freelancer_declines_with_a_listed_reason_and_client_is_told(): void
    {
        $m = $this->openMission();
        $this->invite(['mission' => $m->id]);
        $id = DB::table('mission_invitations')->value('id');
        $other = User::factory()->create();
        $other->roles()->firstOrCreate(['role' => 'freelance']);
        $this->actingAs($other)->post("/freelance/invitations/{$id}/decliner", ['reason' => 'budget'])->assertSessionHas('error');
        $this->actingAs($this->freelancer)->post("/freelance/invitations/{$id}/decliner", ['reason' => 'texte libre'])->assertSessionHas('error');
        $this->assertSame('pending', DB::table('mission_invitations')->where('id', $id)->value('state'));
        $this->actingAs($this->freelancer)->post("/freelance/invitations/{$id}/decliner", ['reason' => 'budget'])->assertSessionHas('status');
        $this->assertSame('declined', DB::table('mission_invitations')->where('id', $id)->value('state'));
        $this->actingAs($this->freelancer)->post("/freelance/invitations/{$id}/decliner", ['reason' => 'budget'])->assertSessionHas('error');     // déjà traitée
        $this->actingAs($this->client)->get("/espace/missions/{$m->id}/invitations")->assertOk()->assertSee('Déclinée')->assertSee('Budget trop bas');
        $this->assertSame(1, DB::table('app_notifications')->where('user_id', $this->client->id)->where('type', 'invitation_update')->count());
        $this->actingAs($this->client)->post("/espace/invitations/{$id}/retirer")->assertSessionHas('error');
    }

    public function test_proposal_marks_invitation_answered_and_it_can_no_longer_be_withdrawn_but_others_still_can_propose(): void
    {
        $m = $this->openMission();
        $this->invite(['mission' => $m->id]);
        $id = DB::table('mission_invitations')->value('id');
        $this->actingAs($this->freelancer)->post("/missions/{$m->slug}/proposition", ['price_xof' => '95 000', 'delivery_days' => '6', 'revisions_included' => '2', 'validity_days' => '7', 'delivery_mode' => 'message',
            'scope' => 'Conversion des douze plans en DWG AutoCAD 2018, calques conservés, un fichier par plan et un PDF de contrôle.', 'deliverables' => "Douze fichiers DWG\nUn PDF de contrôle", 'expected_number' => 0])->assertSessionHasNoErrors();
        $this->actingAs($this->freelancer)->get('/freelance/invitations')->assertOk()->assertSee('Proposition envoyée');
        $this->actingAs($this->client)->get("/espace/missions/{$m->id}/invitations")->assertOk()->assertSee('Proposition reçue');
        $this->actingAs($this->client)->post("/espace/invitations/{$id}/retirer")->assertSessionHas('error');
        $this->assertSame('pending', DB::table('mission_invitations')->where('id', $id)->value('state'));
    }

    public function test_client_withdraws_a_pending_invitation_and_expiry_is_derived(): void
    {
        $m = $this->openMission();
        $this->invite(['mission' => $m->id]);
        $id = DB::table('mission_invitations')->value('id');
        $this->actingAs($this->client)->post("/espace/invitations/{$id}/retirer")->assertSessionHas('status');
        $this->assertSame('withdrawn', DB::table('mission_invitations')->where('id', $id)->value('state'));
        $this->actingAs($this->freelancer)->get('/freelance/invitations')->assertOk()->assertSee('retiré')->assertDontSee('Faire une proposition');
        $this->actingAs($this->freelancer)->post("/freelance/invitations/{$id}/decliner", ['reason' => 'budget'])->assertSessionHas('error');

        $m2 = $this->openMission(null, ['title' => 'Autre mission qui va expirer']);
        $this->invite(['mission' => $m2->id]);
        DB::table('missions')->where('id', $m2->id)->update(['status' => 'closed']);
        $this->actingAs($this->freelancer)->get('/freelance/invitations')->assertOk()->assertSee('Expirée');
        $this->actingAs($this->freelancer)->get('/freelance')->assertOk();
    }

    public function test_invitation_pages_are_private(): void
    {
        $this->get('/freelances/'.$this->slug().'/inviter')->assertRedirect('/connexion');
        $this->get('/freelance/invitations')->assertRedirect('/connexion');
        $this->get('/freelance/alertes')->assertRedirect('/connexion');
        $m = $this->openMission();
        $other = User::factory()->create();
        $this->actingAs($other)->get("/espace/missions/{$m->id}/invitations")->assertNotFound();
        $this->actingAs($this->client)->get('/freelance/invitations')->assertStatus(302);                   // pas d'espace freelance
        $this->actingAs($this->client)->get('/freelances/'.$this->slug())->assertOk()->assertSee('Inviter à une mission');
        $this->actingAs($this->freelancer)->get('/freelances/'.$this->slug())->assertOk()->assertDontSee('Inviter à une mission');
    }
}

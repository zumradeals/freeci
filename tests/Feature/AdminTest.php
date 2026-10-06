<?php

namespace Tests\Feature;

use App\Mail\VerifyEmailMail;
use App\Modules\Accounts\Actions\GrantAdministrator;
use App\Modules\Accounts\Models\User;
use App\Modules\Accounts\Security\Totp;
use App\Modules\Accounts\Security\TwoFactor;
use App\Modules\Admin\Queries\AdminDashboard;
use App\Modules\Catalog\Models\FreelanceProfile;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\ServiceVersion;
use App\Modules\Missions\Models\Mission;
use App\Modules\Missions\Models\MissionVersion;
use App\Modules\Notifications\Models\AppNotification;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\AdminFixtures;
use Tests\Support\OrderFixtures;
use Tests\TestCase;

/** Lot 8 : accès sécurisé (courriel, MFA, récupération, confirmation récente), modération web, suspension de comptes, audit. */
class AdminTest extends TestCase
{
    use AdminFixtures, OrderFixtures, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private_files');
        $this->setUpParties();
        $this->admin = $this->readyAdmin(['name' => 'Admin Un']);
    }

    private function code(User $u): string
    {
        return Totp::code($this->secretOf($u), Totp::step());
    }

    /** Version de service en contrôle, soumise par le freelance. */
    private function serviceInReview(): ServiceVersion
    {
        $this->actingAs($this->freelancer)->post('/freelance/services', ['title' => 'Mise en plan 2D complète d’un appartement', 'category_id' => $this->service->category_id])->assertRedirect();
        $s = Service::query()->orderByDesc('created_at')->orderByDesc('id')->firstOrFail();
        $v = $s->versions()->first();
        $this->actingAs($this->freelancer)->post("/freelance/services/{$s->id}/modifier", [
            'title' => $v->title, 'category_id' => $this->service->category_id, 'summary' => 'Plans cotés au format PDF et DWG à partir de vos relevés.',
            'scope' => str_repeat('Un logement jusqu’à 120 m², relevés fournis par le client. ', 4), 'price_xof' => '45000', 'delivery_days' => '6', 'revisions_included' => '2',
            'deliverables' => 'Un plan PDF', 'delivery_mode' => 'message', 'revision_no' => $v->revision_no,
        ])->assertRedirect();
        $this->actingAs($this->freelancer)->post("/freelance/services/{$s->id}/soumettre", ['revision_no' => $v->fresh()->revision_no])->assertRedirect();

        return $s->versions()->where('state', 'in_review')->firstOrFail();
    }

    private function missionInReview(?User $client = null): MissionVersion
    {
        $client ??= $this->client;
        $this->actingAs($client)->post('/espace/missions', ['title' => 'Conversion de douze plans PDF en fichiers DWG', 'category_id' => $this->service->category_id])->assertRedirect();
        $m = Mission::query()->orderByDesc('created_at')->orderByDesc('id')->firstOrFail();
        $v = $m->versions()->first();
        $this->actingAs($client)->post("/espace/missions/{$m->id}/modifier", [
            'title' => 'Conversion de douze plans PDF en fichiers DWG', 'category_id' => $this->service->category_id,
            'description' => str_repeat('Douze plans d’architecture en PDF à convertir en DWG AutoCAD 2018, calques conservés. ', 3),
            'budget_xof' => '120 000', 'application_deadline' => now()->addDays(5)->format('Y-m-d'), 'client_inputs' => "Nombre de plans\nVersion AutoCAD", 'intent' => 'save', 'revision_no' => $v->revision_no,
        ])->assertRedirect()->assertSessionHas('status');
        $this->actingAs($client)->post("/espace/missions/{$m->id}/soumettre", ['revision_no' => $v->fresh()->revision_no])->assertRedirect()->assertSessionHas('status');

        return $m->versions()->where('state', 'in_review')->firstOrFail();
    }

    // ---------- accès ----------

    public function test_non_admins_and_anonymous_get_nothing_and_attempts_are_logged(): void
    {
        $urls = ['/admin', '/admin/moderation', '/admin/utilisateurs', '/admin/journal', '/admin/securite', '/admin/activation'];
        foreach ($urls as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
        foreach ($urls as $url) {
            $this->actingAs($this->client)->get($url)->assertNotFound();
        }
        $this->actingAs($this->client)->post('/admin/utilisateurs/'.$this->freelancer->id.'/suspendre', ['reason' => 'Motif suffisamment long'])->assertNotFound();
        $this->assertNull($this->freelancer->fresh()->suspended_at);
        $this->assertGreaterThan(0, DB::table('security_events')->where('type', 'admin_denied')->where('user_id', $this->client->id)->count());
    }

    public function test_no_admin_right_can_be_attributed_from_registration_or_profile_forms(): void
    {
        $this->post('/inscription', ['name' => 'Pirate', 'email' => 'pirate@example.test', 'password' => 'Motdepasse-2026', 'password_confirmation' => 'Motdepasse-2026',
            'is_admin' => '1', 'role' => 'administrator', 'capability' => 'administrator', 'email_verified_at' => now()->toDateTimeString(), 'two_factor_confirmed_at' => now()->toDateTimeString()]);
        $u = User::where('email', 'pirate@example.test')->first();
        $this->assertNotNull($u);
        $this->assertFalse($u->isAdministrator());
        $this->assertNull($u->email_verified_at);
        $this->assertNull($u->two_factor_confirmed_at);
        $this->actingAs($u)->get('/admin')->assertNotFound();
        $this->assertSame([], array_intersect(['is_admin', 'role', 'capability', 'email_verified_at', 'two_factor_secret', 'suspended_at'], (new User)->getFillable()));
    }

    public function test_a_grant_alone_never_opens_the_admin_and_missing_prerequisites_are_explained(): void
    {
        $bare = User::factory()->create(['email_verified_at' => null]);
        app(GrantAdministrator::class)($bare, 'test');
        foreach (['/admin', '/admin/moderation', '/admin/utilisateurs'] as $url) {
            $this->actingAs($bare)->get($url)->assertRedirect(route('admin.activation'));
        }
        $this->actingAs($bare)->get('/admin/activation')->assertOk()->assertSee('Vérifier votre adresse')->assertSee('freeci:admin:verify-email');
        // adresse vérifiée mais sans MFA : toujours fermé
        $bare->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($bare)->get('/admin')->assertRedirect(route('admin.activation'));
        // toute action d'administration, même appelée directement, exige les prérequis
        $bare->forceFill(['email_verified_at' => null])->save();
        $this->assertSame(403, $this->actingAs($bare)->post('/admin/activation/mfa/valider', ['code' => '123456'])->getStatusCode());
    }

    public function test_email_verification_is_never_faked_by_a_log_mailer_and_works_with_a_real_one(): void
    {
        $bare = User::factory()->create(['email_verified_at' => null]);
        app(GrantAdministrator::class)($bare, 'test');
        config(['mail.default' => 'log']);
        $this->actingAs($bare)->post('/admin/activation/courriel')->assertSessionHas('error');
        $this->assertNull($bare->fresh()->email_verified_at);
        $this->assertSame(1, DB::table('security_events')->where('type', 'email_verification_unavailable')->count());

        config(['mail.default' => 'smtp']);
        Mail::fake();
        $this->actingAs($bare)->post('/admin/activation/courriel')->assertSessionHas('status');
        $url = null;
        Mail::assertSent(VerifyEmailMail::class, function ($m) use (&$url) {
            $url = $m->url;

            return true;
        });
        $this->assertNull($bare->fresh()->email_verified_at, 'envoyé ≠ vérifié');
        $this->actingAs($this->client)->get($url)->assertForbidden();                      // lien d'un autre compte
        $this->actingAs($bare)->get(preg_replace('/signature=[^&]+/', 'signature=faux', $url))->assertForbidden();
        $this->assertNull($bare->fresh()->email_verified_at);
        $this->actingAs($bare)->get($url)->assertRedirect(route('admin.activation'));
        $this->assertNotNull($bare->fresh()->email_verified_at);
    }

    public function test_mfa_enrolment_challenge_and_single_use_recovery_codes(): void
    {
        $u = User::factory()->create(['email_verified_at' => now()]);
        app(GrantAdministrator::class)($u, 'test');
        $this->actingAs($u)->post('/admin/activation/mfa')->assertRedirect(route('admin.activation'));
        $secret = $this->secretOf($u);
        $this->assertFalse($u->fresh()->hasTwoFactor(), 'un secret proposé n\'active rien');
        $this->actingAs($u)->post('/admin/activation/mfa/valider', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertFalse($u->fresh()->hasTwoFactor());
        $r = $this->actingAs($u)->post('/admin/activation/mfa/valider', ['code' => Totp::code($secret, Totp::step())]);
        $r->assertOk()->assertSee('ne seront plus jamais affichés');
        $this->assertTrue($u->fresh()->hasTwoFactor());
        $this->assertSame(TwoFactor::CODES, DB::table('two_factor_recovery_codes')->where('user_id', $u->id)->count());
        preg_match_all('/<li>([a-z0-9]{5}-[a-z0-9]{5})<\/li>/', $r->getContent(), $m);
        $codes = $m[1];
        $this->assertCount(TwoFactor::CODES, $codes);
        $this->assertSame([], DB::table('two_factor_recovery_codes')->where('code_hash', $codes[0])->pluck('id')->all(), 'seule l\'empreinte est stockée');

        // nouvelle session : défi exigé
        $this->flushSession();
        $this->actingAs($u->fresh())->get('/admin')->assertRedirect(route('admin.mfa'));
        $this->actingAs($u->fresh())->post('/admin/verification', ['code' => '111111'])->assertSessionHasErrors('code');
        $this->actingAs($u->fresh())->post('/admin/verification', ['code' => $codes[0]])->assertRedirect();
        $this->assertSame(TwoFactor::CODES - 1, app(TwoFactor::class)->remaining($u));
        // le même code de récupération ne sert qu'une fois
        $this->actingAs($u->fresh())->post('/admin/verification', ['code' => $codes[0]])->assertSessionHasErrors('code');
        // un code TOTP déjà accepté ne se rejoue pas
        $step = Totp::step();
        $this->assertNull(Totp::verify($secret, Totp::code($secret, $step), $step));
        $this->assertSame(1, DB::table('security_events')->where('type', 'mfa_recovery_used')->count());
    }

    public function test_totp_codes_are_single_use_and_attempts_are_rate_limited_then_logged(): void
    {
        $u = $this->readyAdmin(['email' => 'second@example.test']);
        $good = $this->code($u);
        $this->actingAs($u)->post('/admin/verification', ['code' => $good])->assertRedirect();
        $this->flushSession();
        $this->actingAs($u->fresh())->post('/admin/verification', ['code' => $good])->assertSessionHasErrors('code');   // rejeu refusé

        RateLimiter::clear('mfa:'.$u->id);
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($u->fresh())->post('/admin/verification', ['code' => '999999'])->assertSessionHasErrors('code');
        }
        $this->actingAs($u->fresh())->post('/admin/verification', ['code' => Totp::code($this->secretOf($u), Totp::step() + 1)])->assertSessionHasErrors('code');   // même un bon code est refusé pendant le blocage
        $this->assertGreaterThan(0, DB::table('security_events')->where('type', 'mfa_locked')->count());
        $this->assertSame(0, DB::table('security_events')->where('meta', 'like', '%999999%')->count(), 'aucun code dans le journal');
    }

    public function test_session_must_pass_mfa_and_revoked_or_expired_grants_close_everything_at_once(): void
    {
        $this->actingAs($this->admin)->get('/admin')->assertRedirect(route('admin.mfa'));      // pas de défi franchi dans cette session
        $this->asAdmin($this->admin)->get('/admin')->assertOk();
        // un défi franchi pour un autre compte ne vaut rien
        $other = $this->readyAdmin(['email' => 'autre@example.test']);
        $this->actingAs($this->admin)->withSession(['admin_mfa' => ['user' => $other->id, 'at' => time()]])->get('/admin')->assertRedirect(route('admin.mfa'));
        // défi trop ancien
        $this->actingAs($this->admin)->withSession(['admin_mfa' => ['user' => $this->admin->id, 'at' => time() - 9 * 3600]])->get('/admin')->assertRedirect(route('admin.mfa'));

        DB::table('staff_grants')->where('user_id', $this->admin->id)->update(['expires_at' => now()->subSecond()]);
        $this->asAdmin($this->admin->fresh())->get('/admin')->assertNotFound();
        $this->asAdmin($this->admin->fresh())->post('/admin/moderation/service/'.Str::uuid().'/approuver')->assertNotFound();
        DB::table('staff_grants')->where('user_id', $this->admin->id)->update(['expires_at' => null, 'revoked_at' => now()]);
        $this->asAdmin($this->admin->fresh())->get('/admin/utilisateurs')->assertNotFound();
    }

    public function test_sensitive_operations_need_a_recent_identity_confirmation(): void
    {
        $v = $this->serviceInReview();
        $this->asAdmin($this->admin)->post("/admin/moderation/service/{$v->id}/approuver")->assertRedirect();
        $s = $v->service->fresh();
        $this->asAdmin($this->admin, recent: false)->post("/admin/moderation/en-ligne/service/{$s->id}/suspendre", ['reason' => 'Contenu contraire aux règles de la plateforme.'])->assertRedirect(route('admin.reauth'));
        $this->assertSame('published', $s->fresh()->status->value);
        $this->asAdmin($this->admin, recent: false)->post('/admin/utilisateurs/'.$this->client->id.'/suspendre', ['reason' => 'Motif suffisamment long'])->assertRedirect(route('admin.reauth'));
        $this->assertNull($this->client->fresh()->suspended_at);

        $admin = $this->admin;
        $this->asAdmin($admin, recent: false)->post('/admin/confirmation', ['password' => 'mauvais'])->assertSessionHasErrors('password');
        $this->assertSame(1, DB::table('security_events')->where('type', 'reauth_failed')->count());
        $admin->forceFill(['password' => 'Motdepasse-2026'])->save();
        $this->asAdmin($admin->fresh(), recent: false)->post('/admin/confirmation', ['password' => 'Motdepasse-2026'])->assertRedirect();
        $this->assertSame(1, DB::table('security_events')->where('type', 'reauth_ok')->count());
    }

    public function test_console_recovery_is_explicit_logged_and_closes_sessions(): void
    {
        $bare = User::factory()->create(['email' => 'chef@example.test', 'email_verified_at' => null]);
        app(GrantAdministrator::class)($bare, 'test');
        $this->assertSame(1, Artisan::call('freeci:admin:verify-email', ['email' => 'chef@example.test', '--yes' => true]), 'sans --attest : refusé');
        $this->assertNull($bare->fresh()->email_verified_at);
        $this->assertSame(1, Artisan::call('freeci:admin:verify-email', ['email' => $this->client->email, '--attest' => true, '--yes' => true]), 'refusé pour un non-administrateur');
        $this->assertSame(0, Artisan::call('freeci:admin:verify-email', ['email' => 'chef@example.test', '--attest' => true, '--yes' => true]));
        $this->assertNotNull($bare->fresh()->email_verified_at);
        $ev = DB::table('security_events')->where('type', 'email_verified')->first();
        $this->assertStringContainsString('console', $ev->meta);

        $this->assertSame(0, Artisan::call('freeci:admin:status', ['email' => $this->admin->email]));
        $this->assertStringContainsString('OUI', Artisan::output());

        DB::table('sessions')->insert(['id' => 'sess1', 'user_id' => $this->admin->id, 'payload' => '', 'last_activity' => time()]);
        $this->assertSame(0, Artisan::call('freeci:admin:mfa-reset', ['email' => $this->admin->email, '--yes' => true]));
        $fresh = $this->admin->fresh();
        $this->assertFalse($fresh->hasTwoFactor());
        $this->assertNull($fresh->two_factor_secret);
        $this->assertSame(0, DB::table('two_factor_recovery_codes')->where('user_id', $this->admin->id)->count());
        $this->assertSame(0, DB::table('sessions')->where('user_id', $this->admin->id)->count());
        $this->assertSame(1, DB::table('security_events')->where('type', 'mfa_reset')->count());
        $this->asAdmin($fresh)->get('/admin')->assertRedirect(route('admin.activation'));       // fermé jusqu'à réactivation
    }

    // ---------- modération ----------

    public function test_moderation_queue_review_with_comparison_approve_refuse_and_notify(): void
    {
        $v = $this->serviceInReview();
        $mv = $this->missionInReview();
        $this->asAdmin($this->admin)->get('/admin/moderation')->assertOk()->assertSee('Mise en plan 2D complète')->assertSee('Première soumission');
        $this->asAdmin($this->admin)->get('/admin/moderation?onglet=missions')->assertOk()->assertSee('Conversion de douze plans');
        $this->asAdmin($this->admin)->get("/admin/moderation/services/{$v->id}")->assertOk()->assertSee('rien n’est encore public');

        // refus : motif obligatoire, l'auteur est notifié
        $this->asAdmin($this->admin)->post("/admin/moderation/service/{$v->id}/refuser", ['reason' => 'court'])->assertSessionHasErrors('reason');
        $this->asAdmin($this->admin)->post("/admin/moderation/service/{$v->id}/refuser", ['reason' => 'Précisez les formats de fichiers remis.'])->assertRedirect()->assertSessionHas('status');
        $this->assertSame('changes_requested', $v->fresh()->state);
        $this->assertTrue(AppNotification::where('user_id', $this->freelancer->id)->where('type', 'moderation_decision')->exists());

        // approbation d'une mission
        $this->asAdmin($this->admin)->post("/admin/moderation/mission/{$mv->id}/approuver")->assertRedirect()->assertSessionHas('status');
        $this->assertSame('open', $mv->mission->fresh()->status);
        $this->assertTrue(AppNotification::where('user_id', $this->client->id)->where('type', 'moderation_decision')->exists());

        // le journal contient les actions (réussies), avec auteur et motif
        $rows = DB::table('admin_actions')->orderBy('id')->get();
        $this->assertSame(['service.request_changes', 'mission.approve'], $rows->pluck('action')->all());
        $this->assertSame([$this->admin->id, $this->admin->id], $rows->pluck('actor_id')->all());
        $this->assertSame('Précisez les formats de fichiers remis.', $rows[0]->reason);
        $this->assertSame('modération (web)', DB::table('service_events')->where('type', 'changes_requested')->value('actor_label'));
    }

    public function test_second_version_is_compared_with_the_public_one(): void
    {
        $v = $this->serviceInReview();
        $this->asAdmin($this->admin)->post("/admin/moderation/service/{$v->id}/approuver")->assertRedirect();
        $s = $v->service->fresh();
        $this->actingAs($this->freelancer)->post("/freelance/services/{$s->id}/nouvelle-version")->assertRedirect();
        $v2 = $s->versions()->whereIn('state', ServiceVersion::OPEN)->firstOrFail();
        $this->actingAs($this->freelancer)->post("/freelance/services/{$s->id}/modifier", [
            'title' => 'Mise en plan 2D complète et métré', 'category_id' => $this->service->category_id, 'summary' => $v2->summary, 'scope' => $v2->scope, 'price_xof' => '50000', 'delivery_days' => '6',
            'revisions_included' => '2', 'deliverables' => 'Un plan PDF', 'delivery_mode' => 'message', 'revision_no' => $v2->revision_no,
        ])->assertRedirect();
        $this->actingAs($this->freelancer)->post("/freelance/services/{$s->id}/soumettre", ['revision_no' => $v2->fresh()->revision_no])->assertRedirect();
        $this->asAdmin($this->admin)->get('/admin/moderation/services/'.$v2->id)->assertOk()->assertSee('et métré')->assertSee('modifié');
    }

    public function test_nobody_moderates_their_own_content_and_every_refusal_is_audited(): void
    {
        // l'administrateur est aussi freelance : son propre service
        $this->admin->roles()->firstOrCreate(['role' => 'freelance']);
        FreelanceProfile::factory()->create(['user_id' => $this->admin->id, 'display_name' => 'Admin Un']);
        $this->actingAs($this->admin)->post('/freelance/services', ['title' => 'Mise en plan 2D complète d’un appartement', 'category_id' => $this->service->category_id])->assertRedirect();
        $s = Service::query()->orderByDesc('created_at')->orderByDesc('id')->firstOrFail();
        $v = $s->versions()->first();
        $this->actingAs($this->admin)->post("/freelance/services/{$s->id}/modifier", [
            'title' => $v->title, 'category_id' => $this->service->category_id, 'summary' => 'Plans cotés au format PDF et DWG à partir de vos relevés.', 'scope' => str_repeat('Un logement jusqu’à 120 m², relevés fournis. ', 5),
            'price_xof' => '45000', 'delivery_days' => '6', 'revisions_included' => '2', 'deliverables' => 'Un plan PDF', 'delivery_mode' => 'message', 'revision_no' => $v->revision_no,
        ])->assertRedirect();
        $this->actingAs($this->admin)->post("/freelance/services/{$s->id}/soumettre", ['revision_no' => $v->fresh()->revision_no])->assertRedirect();

        $this->asAdmin($this->admin)->post("/admin/moderation/service/{$v->id}/approuver")->assertRedirect()->assertSessionHas('error');
        $this->assertSame('in_review', $v->fresh()->state);
        $refused = DB::table('admin_actions')->where('result', 'refused')->first();
        $this->assertSame('service.approve', $refused->action);
        $this->assertStringContainsString('propre', $refused->detail);
        $this->asAdmin($this->admin)->get("/admin/moderation/services/{$v->id}")->assertSee('vous ne pouvez pas le modérer');
    }

    public function test_suspend_and_reinstate_services_and_missions_keep_orders_untouched(): void
    {
        $v = $this->serviceInReview();
        $this->asAdmin($this->admin)->post("/admin/moderation/service/{$v->id}/approuver")->assertRedirect();
        $s = $v->service->fresh();
        $order = $this->placeOrder();
        $this->asAdmin($this->admin)->post("/admin/moderation/en-ligne/service/{$s->id}/suspendre", ['reason' => 'Contenu contraire aux règles de la plateforme.'])->assertRedirect()->assertSessionHas('status');
        $this->assertSame('suspended', $s->fresh()->status->value);
        $this->assertSame('awaiting_acceptance', DB::table('orders')->where('id', $order->id)->value('state'), 'la commande en cours n\'est pas touchée');
        $this->assertTrue(AppNotification::where('user_id', $this->freelancer->id)->where('title', 'like', '%suspendu%')->exists());
        $this->asAdmin($this->admin)->post("/admin/moderation/en-ligne/service/{$s->id}/remettre")->assertRedirect()->assertSessionHas('status');
        $this->assertSame('published', $s->fresh()->status->value);

        $mv = $this->missionInReview();
        $this->asAdmin($this->admin)->post("/admin/moderation/mission/{$mv->id}/approuver")->assertRedirect();
        $m = $mv->mission->fresh();
        $this->get('/missions')->assertSee('Conversion de douze plans');
        $this->asAdmin($this->admin)->post("/admin/moderation/en-ligne/mission/{$m->id}/suspendre", ['reason' => 'Annonce contraire aux règles de la plateforme.'])->assertRedirect()->assertSessionHas('status');
        $this->assertSame('suspended', $m->fresh()->status);
        $this->get('/missions')->assertDontSee('Conversion de douze plans');
        $this->actingAs($this->freelancer)->post("/missions/{$m->slug}/proposition", ['price_xof' => '95000', 'delivery_days' => '6', 'revisions_included' => '2', 'validity_days' => '7', 'delivery_mode' => 'message', 'scope' => str_repeat('Conversion des plans en DWG. ', 4), 'deliverables' => 'DWG', 'expected_number' => 0]);
        $this->assertSame(0, DB::table('proposals')->count(), 'plus de proposition sur une mission suspendue');
        $this->asAdmin($this->admin)->post("/admin/moderation/en-ligne/mission/{$m->id}/remettre")->assertRedirect()->assertSessionHas('status');
        $this->assertSame('open', $m->fresh()->status);
        $this->assertSame(4, DB::table('admin_actions')->whereIn('action', ['service.suspend', 'service.reinstate', 'mission.suspend', 'mission.reinstate'])->count());
        $this->asAdmin($this->admin)->get('/admin/moderation?onglet=en-ligne&type=mission')->assertOk()->assertSee('Conversion de douze plans');
    }

    // ---------- utilisateurs ----------

    public function test_user_search_detail_and_suspension_with_history_and_reactivation(): void
    {
        $this->asAdmin($this->admin)->get('/admin/utilisateurs?q=Fanta')->assertOk()->assertSee('Fanta Client')->assertDontSee('Kader Freelance');
        $this->asAdmin($this->admin)->get('/admin/utilisateurs?q=zzzz')->assertOk()->assertSee('Aucun utilisateur ne correspond');
        $this->asAdmin($this->admin)->get('/admin/utilisateurs/'.$this->client->id)->assertOk()->assertSee('Fanta Client')->assertDontSee($this->client->password);

        $this->asAdmin($this->admin)->post('/admin/utilisateurs/'.$this->client->id.'/suspendre', ['reason' => 'court'])->assertSessionHasErrors('reason');
        $this->asAdmin($this->admin)->post('/admin/utilisateurs/'.$this->client->id.'/suspendre', ['reason' => 'Usurpation d’identité signalée et vérifiée.'])->assertRedirect()->assertSessionHas('status');
        $this->assertNotNull($this->client->fresh()->suspended_at);
        $this->assertTrue(AppNotification::where('user_id', $this->client->id)->where('type', 'account_status')->exists());
        $this->asAdmin($this->admin)->get('/admin/utilisateurs/'.$this->client->id)->assertSee('Usurpation d’identité')->assertSee('Réactiver le compte');
        $this->asAdmin($this->admin)->get('/admin/utilisateurs?statut=suspended')->assertSee('Fanta Client');
        $this->actingAs($this->client->fresh())->get('/espace')->assertOk()->assertSee('Votre compte est suspendu');

        $this->asAdmin($this->admin)->post('/admin/utilisateurs/'.$this->client->id.'/reactiver', ['reason' => 'Vérification terminée, compte rétabli.'])->assertRedirect()->assertSessionHas('status');
        $this->assertNull($this->client->fresh()->suspended_at);
        $this->assertSame(['suspended', 'reactivated'], DB::table('account_restrictions')->orderBy('id')->pluck('action')->all());
        $this->assertSame(['user.suspend', 'user.reactivate'], DB::table('admin_actions')->orderBy('id')->pluck('action')->all());
        $this->expectException(QueryException::class);
        DB::table('account_restrictions')->update(['reason' => 'effacé']);                                  // historique en ajout seul
    }

    public function test_admins_and_self_cannot_be_suspended_and_double_suspension_is_refused(): void
    {
        $other = $this->readyAdmin(['email' => 'autre@example.test']);
        $this->asAdmin($this->admin)->post('/admin/utilisateurs/'.$other->id.'/suspendre', ['reason' => 'Motif suffisamment long'])->assertSessionHas('error');
        $this->asAdmin($this->admin)->post('/admin/utilisateurs/'.$this->admin->id.'/suspendre', ['reason' => 'Motif suffisamment long'])->assertSessionHas('error');
        $this->assertNull($other->fresh()->suspended_at);
        $this->assertNull($this->admin->fresh()->suspended_at);
        $this->asAdmin($this->admin)->post('/admin/utilisateurs/'.$this->client->id.'/suspendre', ['reason' => 'Motif suffisamment long'])->assertSessionHas('status');
        $this->asAdmin($this->admin)->post('/admin/utilisateurs/'.$this->client->id.'/suspendre', ['reason' => 'Motif suffisamment long'])->assertSessionHas('error');
        $this->assertSame(1, DB::table('account_restrictions')->count());
    }

    public function test_suspension_blocks_new_activity_but_active_orders_continue(): void
    {
        $this->service->update(['delivery_requires_files' => false]);
        $active = $this->placeOrder();                                                       // commande en cours (client Fanta)
        $this->asAdmin($this->admin)->post('/admin/utilisateurs/'.$this->client->id.'/suspendre', ['reason' => 'Suspension de test suffisamment motivée.'])->assertSessionHas('status');
        $this->client->refresh();

        // nouvelle demande : refusée
        $other = Service::factory()->create(['freelance_profile_id' => $this->service->freelance_profile_id, 'title' => 'Autre prestation', 'accepts_requests' => true]);
        $before = DB::table('orders')->count();
        $this->actingAs($this->client)->post('/services/'.$this->service->slug.'/demande', $this->requestPayload())->assertSessionHas('error');
        $this->assertSame($before, DB::table('orders')->count());
        // nouvelle conversation : refusée ; mission : refusée
        $this->actingAs($this->client)->post("/services/{$other->slug}/contacter", ['body' => 'Bonjour', 'client_key' => (string) Str::uuid()])->assertSessionHas('error');
        $this->actingAs($this->client)->post('/espace/missions', ['title' => 'Conversion de douze plans PDF en fichiers DWG', 'category_id' => $this->service->category_id]);
        $this->actingAs($this->client)->get('/espace/commandes')->assertOk();

        // la commande en cours se poursuit : le freelance peut l'accepter ; le client peut encore la voir, écrire dans le dossier, annuler
        $this->accept($active)->assertRedirect()->assertSessionMissing('error');
        $this->assertSame('awaiting_payment', DB::table('orders')->where('id', $active->id)->value('state'));
        $this->actingAs($this->client)->get("/commandes/{$active->reference}")->assertOk();
        $c = DB::table('conversations')->where('order_id', $active->id)->first();
        if ($c !== null) {
            $this->actingAs($this->client)->post("/espace/messages/{$c->id}", ['body' => 'Question sur la commande', 'client_key' => (string) Str::uuid()])->assertSessionMissing('error');
        }

        // un freelance suspendu ne reçoit plus de nouvelle demande : ses services quittent le catalogue ; il ne peut plus accepter une NOUVELLE demande
        $this->asAdmin($this->admin)->post('/admin/utilisateurs/'.$this->freelancer->id.'/suspendre', ['reason' => 'Suspension de test suffisamment motivée.'])->assertSessionHas('status');
        $this->get('/services')->assertDontSee('Autre prestation');
        $this->assertSame(0, Service::query()->published()->count());
        $third = User::factory()->create();
        $third->roles()->firstOrCreate(['role' => 'client']);
        $this->actingAs($third)->post('/services/'.$this->service->slug.'/demande', $this->requestPayload())->assertStatus(404);
    }

    // ---------- tableau de bord, audit ----------

    public function test_dashboard_counts_are_real_and_follow_ups_are_not_presented_as_disputes(): void
    {
        $this->serviceInReview();
        $this->missionInReview();
        $this->client->forceFill(['suspended_at' => now()])->save();
        $this->asAdmin($this->admin)->get('/admin')->assertOk()->assertSee('Besoins de suivi enregistrés')->assertSee('Ce ne sont pas des litiges pris en charge')
            ->assertSee('Aucun besoin de suivi enregistré')->assertDontSee('litige en cours');
        $d = app(AdminDashboard::class)();
        $this->assertSame([1, 1, 1], [$d['servicesInReview'], $d['missionsInReview'], $d['suspended']]);
        $this->assertSame(User::count(), $d['users']);
    }

    public function test_audit_log_is_filterable_paginated_and_free_of_secrets(): void
    {
        $v = $this->serviceInReview();
        $this->asAdmin($this->admin)->post("/admin/moderation/service/{$v->id}/refuser", ['reason' => 'Précisez les formats de fichiers remis.'])->assertRedirect();
        $this->asAdmin($this->admin)->post('/admin/utilisateurs/'.$this->client->id.'/suspendre', ['reason' => 'Motif de suspension suffisamment long.'])->assertRedirect();
        $this->asAdmin($this->admin)->get('/admin/journal')->assertOk()->assertSee('Admin Un')->assertSee('Compte suspendu')->assertSee('Service : correction demandée');
        $this->asAdmin($this->admin)->get('/admin/journal?action=user.suspend')->assertSee('Motif de suspension suffisamment long.')->assertDontSee('Précisez les formats de fichiers remis.');
        $this->asAdmin($this->admin)->get('/admin/journal?result=refused')->assertSee('Aucune action ne correspond');
        $this->asAdmin($this->admin)->get('/admin/journal?actor=inconnu')->assertSee('Aucune action ne correspond');
        $this->asAdmin($this->admin)->get('/admin/journal?from=2999-01-01')->assertSee('Aucune action ne correspond');
        $this->asAdmin($this->admin)->get('/admin/journal/securite')->assertOk()->assertSee('Double authentification activée');
        // aucun secret / mot de passe / code dans les journaux
        $secret = $this->secretOf($this->admin);
        $all = json_encode(DB::table('security_events')->get()).json_encode(DB::table('admin_actions')->get()).json_encode(DB::table('account_restrictions')->get());
        $this->assertStringNotContainsString($secret, $all);
        $this->assertStringNotContainsString($this->admin->password, $all);
        // ajout seul
        $this->expectException(QueryException::class);
        DB::table('admin_actions')->delete();
    }

    public function test_the_admin_has_no_general_access_to_private_conversations_briefs_or_files(): void
    {
        $order = $this->placeOrder();
        $c = DB::table('conversations')->where('order_id', $order->id)->first();
        $this->asAdmin($this->admin)->get("/commandes/{$order->reference}")->assertNotFound();
        if ($c !== null) {
            $this->asAdmin($this->admin)->get("/espace/messages/{$c->id}")->assertNotFound();
        }
        $this->asAdmin($this->admin)->get('/admin/utilisateurs/'.$this->client->id)->assertDontSee('12 plans')->assertDontSee('Villa R+1');
        $this->asAdmin($this->admin)->get('/admin')->assertDontSee('Villa R+1');
    }
}

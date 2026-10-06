<?php

namespace Tests\Feature;

use App\Mail\AccountNoticeMail;
use App\Modules\Accounts\Actions\AccountClosure;
use App\Shared\TaskHeartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\AdminFixtures;
use Tests\Support\OrderFixtures;
use Tests\TestCase;

/** Lot 13 — compte (informations, mot de passe, sessions, adresse, export, fermeture), pages d'information, exploitation. Tests locaux : aucun serveur réel. */
class AccountManagementTest extends TestCase
{
    use AdminFixtures, OrderFixtures, RefreshDatabase;

    private const PW = 'Motdepasse2024';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private_files');
        $this->setUpParties();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->client->forceFill(['password' => self::PW, 'email_verified_at' => now()])->save();
        $this->freelancer->forceFill(['password' => self::PW])->save();
    }

    private function realMail(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();
    }

    public function test_name_and_password_changes_require_the_current_password_and_close_other_sessions(): void
    {
        $this->actingAs($this->client)->post('/espace/compte/nom', ['name' => 'Fanta K.'])->assertRedirect();
        $this->assertSame('Fanta K.', $this->client->fresh()->name);

        $this->post('/espace/compte/mot-de-passe', ['current_password' => 'mauvais', 'password' => 'Nouveaumdp2024', 'password_confirmation' => 'Nouveaumdp2024'])->assertSessionHasErrors('current_password');
        DB::table('sessions')->insert(['id' => 'other-session', 'user_id' => $this->client->id, 'ip_address' => '1.2.3.4', 'user_agent' => 'Mozilla Firefox/120 Linux', 'payload' => '', 'last_activity' => time()]);
        DB::table('sessions')->insert(['id' => 'foreign-session', 'user_id' => $this->freelancer->id, 'ip_address' => '1.2.3.5', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);
        $this->post('/espace/compte/mot-de-passe', ['current_password' => self::PW, 'password' => 'Nouveaumdp2024', 'password_confirmation' => 'Nouveaumdp2024'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('Nouveaumdp2024', $this->client->fresh()->password));
        $this->assertFalse(DB::table('sessions')->where('id', 'other-session')->exists());
        $this->assertTrue(DB::table('sessions')->where('id', 'foreign-session')->exists(), 'la session d’un autre compte n’est jamais touchée');
    }

    public function test_a_session_of_another_account_cannot_be_revoked(): void
    {
        DB::table('sessions')->insert(['id' => 'foreign-session', 'user_id' => $this->freelancer->id, 'ip_address' => '1.2.3.5', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);
        $this->actingAs($this->client)->post('/espace/compte/sessions/foreign-session/fermer')->assertRedirect();
        $this->assertTrue(DB::table('sessions')->where('id', 'foreign-session')->exists());
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->post('/espace/compte/sessions/x/fermer')->assertRedirect('/connexion');
    }

    public function test_email_change_requires_verification_of_the_new_address_and_notifies_the_old_one(): void
    {
        $this->realMail();
        $old = $this->client->email;
        $this->actingAs($this->client)->post('/espace/compte/adresse', ['email' => 'nouvelle@example.test', 'current_password' => 'faux'])->assertSessionHasErrors('current_password');
        $this->post('/espace/compte/adresse', ['email' => 'Nouvelle@Example.test', 'current_password' => self::PW])->assertSessionHas('status');

        $this->assertSame($old, $this->client->fresh()->email, 'rien n’est remplacé avant vérification');
        Mail::assertSent(AccountNoticeMail::class, fn ($m) => $m->hasTo('nouvelle@example.test') && $m->url !== null);
        Mail::assertSent(AccountNoticeMail::class, fn ($m) => $m->hasTo($old) && $m->url === null);

        $url = Mail::sent(AccountNoticeMail::class, fn ($m) => $m->hasTo('nouvelle@example.test'))->first()->url;
        $this->flushSession();
        $this->actingAs($this->freelancer)->get($url)->assertRedirect('/espace/compte');           // un autre compte ne peut pas confirmer
        $this->assertSame($old, $this->client->fresh()->email);
        $this->flushSession();
        $this->get(preg_replace('/signature=[^&]+/', 'signature=forged', $url))->assertForbidden();
        $this->actingAs($this->client)->get($url)->assertSessionHas('status');
        $c = $this->client->fresh();
        $this->assertSame('nouvelle@example.test', $c->email);
        $this->assertNotNull($c->email_verified_at);
        $this->actingAs($this->client)->get($url)->assertSessionHas('error');                       // usage unique
        Mail::assertSent(AccountNoticeMail::class, fn ($m) => $m->hasTo($old) && str_contains($m->subjectLine, 'remplacée'));
    }

    public function test_email_change_is_refused_without_real_mail_and_does_not_reveal_existing_addresses(): void
    {
        $old = $this->client->email;
        $this->actingAs($this->client)->post('/espace/compte/adresse', ['email' => 'x@example.test', 'current_password' => self::PW])->assertSessionHas('error');
        $this->assertSame($old, $this->client->fresh()->email);
        $this->assertSame(0, DB::table('email_change_requests')->count());

        $this->realMail();
        $this->post('/espace/compte/adresse', ['email' => $this->freelancer->email, 'current_password' => self::PW])->assertSessionHas('status');
        Mail::assertNothingSent();
        $this->assertSame(0, DB::table('email_change_requests')->count());
    }

    public function test_export_is_private_password_protected_and_excludes_other_people_and_secrets(): void
    {
        $o = $this->inProgress();
        $conv = DB::table('conversations')->insertGetId(['id' => (string) Str::uuid(), 'kind' => 'order', 'client_id' => $this->client->id, 'freelancer_id' => $this->freelancer->id, 'order_id' => $o->id, 'context_title' => 'Commande', 'created_by' => $this->client->id, 'created_at' => now(), 'updated_at' => now()], 'id');
        $convId = DB::table('conversations')->where('order_id', $o->id)->value('id');
        DB::table('messages')->insert([['conversation_id' => $convId, 'sender_id' => $this->client->id, 'body' => 'MON-MESSAGE-CLIENT', 'client_key' => 'k1', 'created_at' => now()],
            ['conversation_id' => $convId, 'sender_id' => $this->freelancer->id, 'body' => 'MESSAGE-PRIVE-DU-FREELANCE', 'client_key' => 'k2', 'created_at' => now()]]);
        DB::table('sessions')->insert(['id' => 'sess-secret-id', 'user_id' => $this->freelancer->id, 'ip_address' => '9.9.9.9', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);

        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->post('/espace/compte/export', ['current_password' => self::PW])->assertRedirect('/connexion');
        $this->actingAs($this->client)->post('/espace/compte/export', ['current_password' => 'faux'])->assertSessionHasErrors('current_password');
        $r = $this->post('/espace/compte/export', ['current_password' => self::PW])->assertOk();
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $this->assertStringContainsString('attachment', $r->headers->get('Content-Disposition'));
        $body = $r->getContent();
        $this->assertStringContainsString('MON-MESSAGE-CLIENT', $body);
        $this->assertStringContainsString($this->client->email, $body);
        foreach (['MESSAGE-PRIVE-DU-FREELANCE', $this->freelancer->email, 'sess-secret-id', '9.9.9.9', $this->client->password, 'two_factor', 'remember_token'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $body, "l'export ne doit pas contenir : {$forbidden}");
        }
        $this->assertSame(1, DB::table('security_events')->where('type', 'data_exported')->where('user_id', $this->client->id)->count());
    }

    public function test_closure_is_delayed_blocked_by_active_orders_and_never_deletes_what_must_be_kept(): void
    {
        $o = $this->inProgress();
        $this->actingAs($this->freelancer)->post('/espace/compte/fermeture', ['current_password' => self::PW])->assertSessionHasErrors('confirm');
        $this->post('/espace/compte/fermeture', ['current_password' => self::PW, 'confirm' => 1])->assertSessionHas('status');
        $this->assertSame(1, DB::table('account_closure_requests')->where('state', 'requested')->count());

        // Nouvelle activité refusée, mais la commande en cours se poursuit.
        $this->get('/espace/compte')->assertOk()->assertSee('Obstacles actuels')->assertSee('commandes ou demandes sont encore en cours');
        $this->artisan('freeci:accounts:close')->assertSuccessful();
        $this->assertSame('Kader Freelance', $this->freelancer->fresh()->name, 'pas encore échue');
        DB::table('account_closure_requests')->update(['due_at' => now()->subMinute()]);
        $this->artisan('freeci:accounts:close')->assertSuccessful();
        $f = $this->freelancer->fresh();
        $this->assertSame('Kader Freelance', $f->name, 'une commande active empêche la fermeture');
        $this->assertNull($f->closed_at);
        $this->assertNotNull(DB::table('account_closure_requests')->where('state', 'requested')->value('last_blockers'));

        // Annulation possible.
        $this->post('/espace/compte/fermeture/annuler')->assertRedirect();
        $this->assertSame(0, DB::table('account_closure_requests')->where('state', 'requested')->count());
        $this->assertSame($o->reference, DB::table('orders')->where('id', $o->id)->value('reference'));
    }

    public function test_due_closure_without_obligations_anonymizes_in_place_and_keeps_closed_orders_and_financial_records(): void
    {
        $o = $this->inProgress();
        DB::table('orders')->where('id', $o->id)->update(['state' => 'closed', 'closure_reason' => 'validated', 'closed_at' => now()]);   // clôturée, environnement de test : aucun reversement réel dû
        $payments = DB::table('payments')->where('order_id', $o->id)->count();
        DB::table('favorites')->insert(['user_id' => $this->freelancer->id, 'kind' => 'service', 'target_id' => $this->service->id, 'created_at' => now()]);
        DB::table('sessions')->insert(['id' => 's-f', 'user_id' => $this->freelancer->id, 'ip_address' => '1.1.1.1', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);
        $email = $this->freelancer->email;

        $this->actingAs($this->freelancer)->post('/espace/compte/fermeture', ['current_password' => self::PW, 'confirm' => 1])->assertSessionHas('status');
        // Pendant la demande : aucune nouvelle activité.
        $this->post("/services/{$this->service->slug}/contacter", ['body' => 'Bonjour tout le monde', 'operation_key' => 'k'])->assertRedirect();
        DB::table('account_closure_requests')->update(['due_at' => now()->subMinute()]);
        $this->artisan('freeci:accounts:close')->assertSuccessful();

        $f = $this->freelancer->fresh();
        $this->assertNotNull($f->closed_at);
        $this->assertSame('Compte fermé', $f->name);
        $this->assertStringEndsWith('@compte-ferme.invalid', $f->email);
        $this->assertNotSame($email, $f->email);
        $this->assertSame(0, DB::table('favorites')->where('user_id', $f->id)->count());
        $this->assertSame(0, DB::table('sessions')->where('user_id', $f->id)->count());
        $profile = DB::table('freelance_profiles')->where('user_id', $f->id)->first();
        $this->assertNull($profile->published_at);
        $this->assertSame('Ancien freelance', $profile->display_name);
        $this->assertSame('archived', DB::table('services')->where('id', $this->service->id)->value('status'));
        // Conservés : commande, paiements, journal.
        $this->assertSame($o->reference, DB::table('orders')->where('id', $o->id)->value('reference'));
        $this->assertSame($payments, DB::table('payments')->where('order_id', $o->id)->count());
        $this->assertSame('completed', DB::table('account_closure_requests')->where('user_id', $f->id)->value('state') === 'completed' ? 'completed' : 'x');
        // Connexion impossible avec l'ancienne adresse.
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->post('/connexion', ['email' => $email, 'password' => self::PW])->assertSessionHasErrors('email');
        $this->assertGuest();
        // Le service n'est plus public.
        $this->get("/services/{$this->service->slug}")->assertStatus(410);
    }

    public function test_staff_and_suspended_accounts_cannot_be_closed(): void
    {
        $admin = $this->readyAdmin(['password' => self::PW]);
        $this->assertContains('Une habilitation du personnel est en vigueur : elle doit d’abord être révoquée.', app(AccountClosure::class)->blockers($admin->id));
        DB::table('users')->where('id', $this->client->id)->update(['suspended_at' => now()]);
        $this->assertNotEmpty(array_filter(app(AccountClosure::class)->blockers($this->client->id), fn ($b) => str_contains($b, 'suspendu')));
    }

    public function test_info_pages_are_drafts_until_approved_and_never_invent_identity(): void
    {
        foreach (['fonctionnement', 'aide', 'contact', 'conditions', 'confidentialite', 'mentions-legales'] as $slug) {
            $this->get("/informations/{$slug}")->assertOk()->assertSee('Brouillon — texte non adopté')->assertSee('noindex', false);
        }
        $this->get('/informations/mentions-legales')->assertSee('à renseigner');
        $this->get('/informations/inconnue')->assertNotFound();
        $this->get('/')->assertSee('/informations/conditions', false)->assertSee('/informations/mentions-legales', false);

        config(['freeci.legal.operator_name' => 'Société Test SARL', 'freeci.legal.contact_email' => 'contact@example.test']);
        DB::table('legal_pages')->insert(['slug' => 'conditions', 'published_body' => 'Texte adopté.', 'published_version' => 1, 'published_at' => now()]);
        $this->get('/informations/conditions')->assertOk()->assertDontSee('Brouillon — texte non adopté');
        $this->get('/informations/mentions-legales')->assertSee('Société Test SARL')->assertSee('Brouillon — texte non adopté');
        $this->get('/informations/contact')->assertSee('contact@example.test');
    }

    public function test_operations_page_is_admin_only_informative_and_exposes_no_secret(): void
    {
        config(['freeci.payments.genius.sandbox.api_secret' => 'sk_sandbox_SUPERSECRETVALUE', 'freeci.payments.genius.sandbox.webhook_secret' => 'whsec_SUPERSECRETWEBHOOK', 'mail.mailers.smtp.password' => 'SMTP-PASSWORD-VALUE']);
        $dir = sys_get_temp_dir().'/fc-ops-'.uniqid();
        mkdir($dir);
        config(['freeci.ops.backup_dir' => $dir]);
        $admin = $this->readyAdmin();

        $this->actingAs($this->client)->get('/admin/exploitation')->assertNotFound();
        $page = $this->asAdmin($admin)->get('/admin/exploitation')->assertOk();
        $page->assertSee('Préparation à l’ouverture')->assertSee('informative')->assertSee('État inconnu')->assertDontSee('SUPERSECRET')->assertDontSee('SMTP-PASSWORD-VALUE');
        $page->assertSee('Non activés');                                                       // paiement réel non activé, sans bloquer le sandbox

        file_put_contents($dir.'/STATUS', 'local_ok_at='.now()->subHour()->utc()->format('Y-m-d\TH:i:s\Z')."\noffsite=disabled\n");
        $this->asAdmin($admin)->get('/admin/exploitation')->assertSee('NON ACTIVÉE')->assertDontSee('Copie hors VPS confirmée');
        file_put_contents($dir.'/STATUS', 'local_ok_at='.now()->subHour()->utc()->format('Y-m-d\TH:i:s\Z')."\noffsite=failed_transfer\n");
        $this->asAdmin($admin)->get('/admin/exploitation')->assertSee('EN ÉCHEC');
        unlink($dir.'/STATUS');
        rmdir($dir);

        $this->artisan('freeci:readiness')->assertSuccessful();
    }

    public function test_task_heartbeat_records_success_and_failure_for_the_admin_view(): void
    {
        TaskHeartbeat::mark('orders:expire', true);
        $row = DB::table('system_task_runs')->where('task', 'orders:expire')->first();
        $this->assertNotNull($row->last_ok_at);
        DB::table('system_task_runs')->update(['last_ok_at' => now()->subMinutes(10)]);
        TaskHeartbeat::mark('orders:expire', false);
        $this->assertNotNull(DB::table('system_task_runs')->where('task', 'orders:expire')->value('last_failed_at'));
        $admin = $this->readyAdmin();
        $this->asAdmin($admin)->get('/admin/exploitation')->assertSee('Dernier passage en échec');
    }
}

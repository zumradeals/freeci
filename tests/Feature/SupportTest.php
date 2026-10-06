<?php

namespace Tests\Feature;

use App\Modules\Accounts\Actions\GrantSupport;
use App\Modules\Accounts\Models\User;
use App\Modules\Accounts\Security\Totp;
use App\Modules\Accounts\Security\TwoFactor;
use App\Modules\Files\Actions\ScanBriefFile;
use App\Modules\Notifications\Models\AppNotification;
use App\Modules\Orders\Models\Delivery;
use App\Modules\Orders\Models\Order;
use App\Modules\Support\Contracts\PayoutExecution;
use App\Modules\Support\Support\PayoutHolds;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\Support\AdminFixtures;
use Tests\Support\OrderFixtures;
use Tests\TestCase;

/** Lot 9 : assistance, signalements, litiges, traitement par le personnel habilité, décisions sans exécution financière. */
class SupportTest extends TestCase
{
    use AdminFixtures, OrderFixtures, RefreshDatabase;

    private User $support;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private_files');
        $this->setUpParties();
        $this->service->update(['delivery_requires_files' => false]);
        $this->useFakeScanner();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->admin = $this->readyAdmin(['name' => 'Admin Un']);
        $this->support = $this->readyStaffSupport('support@example.test', 'Agent Support');
    }

    private function readyStaffSupport(string $email, string $name): User
    {
        $u = User::factory()->create(['email' => $email, 'name' => $name, 'email_verified_at' => now()]);
        app(GrantSupport::class)($u, 'test');
        $mfa = app(TwoFactor::class);
        $mfa->confirm($u, Totp::code($mfa->begin($u)['secret'], Totp::step()));

        return $u->fresh();
    }

    private function pdf(string $name = 'preuve.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");
    }

    /** Commande livrée par message (une livraison soumise). */
    private function delivered(): Order
    {
        $o = $this->inProgress();
        $this->actingAs($this->freelancer)->post("/commandes/{$o->reference}/livraison/message", ['message' => 'Voici la livraison complète des plans, formats DWG et PDF.'])->assertRedirect();
        $d = Delivery::query()->where('order_id', $o->id)->where('state', 'draft')->firstOrFail();
        $this->actingAs($this->freelancer)->post("/commandes/{$o->reference}/livraison/soumettre", ['delivery_id' => $d->id, 'expected_version' => $o->fresh()->row_version, 'operation_key' => (string) Str::uuid()])->assertRedirect();
        $this->assertSame('delivered', $o->fresh()->state->value);

        return $o->fresh();
    }

    private function dispute(Order $o, ?User $by = null, string $kind = 'dispute', string $reason = 'La livraison ne correspond pas à ce qui était convenu dans l’accord.')
    {
        return $this->actingAs($by ?? $this->client)->post("/commandes/{$o->reference}/litige", ['kind' => $kind, 'reason' => $reason, 'operation_key' => (string) Str::uuid(), 'confirm' => '1']);
    }

    private function caseRef(): string
    {
        return (string) DB::table('support_cases')->orderByDesc('reference')->value('reference');
    }

    private function staff(User $u, string $method, string $url, array $data = [])
    {
        return $this->asAdmin($u)->{$method}($url, $data);
    }

    /** Dossier affecté et ouvert (accès motivé) pour $u. */
    private function takeAndOpen(string $ref, User $u): void
    {
        $this->staff($u, 'post', "/admin/assistance/{$ref}/prendre")->assertRedirect()->assertSessionHas('status');
        $this->staff($u, 'post', "/admin/assistance/{$ref}/ouvrir", ['reason' => 'Examen du litige signalé par les parties.'])->assertRedirect()->assertSessionHas('status');
    }

    private function decide(string $ref, User $u, array $d = [])
    {
        $c = DB::table('support_cases')->where('reference', $ref)->first();

        return $this->staff($u, 'post', "/admin/assistance/{$ref}/decision", $d + ['outcome' => 'continue', 'financial_need' => 'none', 'reason' => 'Après examen des deux parties, la prestation se poursuit selon l’accord.',
            'version' => $c->row_version, 'operation_key' => (string) Str::uuid(), 'confirm' => '1']);
    }

    // ---------- assistance et signalements ----------

    public function test_contact_support_from_the_space_and_an_order_with_idempotence_and_limits(): void
    {
        $o = $this->placeOrder();
        $key = (string) Str::uuid();
        $payload = ['subject' => 'Question sur ma commande', 'body' => 'Je ne vois pas où saisir mon brief, pouvez-vous m’aider ?', 'category' => 'order', 'order' => $o->reference, 'operation_key' => $key];
        $this->actingAs($this->client)->post('/espace/assistance', $payload)->assertRedirect();
        $this->actingAs($this->client)->post('/espace/assistance', $payload)->assertRedirect();            // double envoi : un seul dossier
        $this->assertSame(1, DB::table('support_cases')->count());
        $c = DB::table('support_cases')->first();
        $this->assertSame([$this->client->id, $o->id, 'support', 'open'], [$c->requester_id, $c->order_id, $c->kind, $c->status]);

        // commande d'autrui ou inexistante : même réponse
        $this->actingAs($this->freelancer)->post('/espace/assistance', ['operation_key' => (string) Str::uuid(), 'order' => 'FC-0000-00000'] + $payload)->assertSessionHasErrors('order');
        $other = User::factory()->create();
        $this->actingAs($other)->post('/espace/assistance', ['operation_key' => (string) Str::uuid()] + $payload)->assertSessionHasErrors('order');

        $this->actingAs($this->client)->get('/espace/assistance')->assertOk()->assertSee('Question sur ma commande')->assertSee('Reçu : pas encore pris en charge');
        $this->actingAs($this->client)->get("/commandes/{$o->reference}")->assertOk()->assertSee('Contacter le support');

        // limite de dossiers par jour
        config(['freeci.support.per_day' => 2]);
        $this->actingAs($this->client)->post('/espace/assistance', ['operation_key' => (string) Str::uuid(), 'subject' => 'Autre question', 'body' => 'Une deuxième question assez longue.', 'category' => 'other']);
        $this->actingAs($this->client)->post('/espace/assistance', ['operation_key' => (string) Str::uuid(), 'subject' => 'Encore une', 'body' => 'Une troisième question assez longue.', 'category' => 'other'])->assertSessionHas('error');
        $this->assertSame(2, DB::table('support_cases')->count());
    }

    public function test_reports_target_content_without_informing_the_reported_and_without_opening_the_conversation(): void
    {
        $slug = $this->service->slug;
        $this->actingAs($this->client)->post("/espace/assistance/signaler/service/{$slug}", ['reason' => 'fraud', 'body' => 'Ce service me semble frauduleux, prix incohérent.', 'operation_key' => (string) Str::uuid()])->assertRedirect();
        $c = DB::table('support_cases')->first();
        $this->assertSame(['report', 'service', $this->service->id], [$c->kind, $c->target_type, $c->target_id]);
        // doublon : refusé, renvoie vers le dossier existant
        $this->actingAs($this->client)->post("/espace/assistance/signaler/service/{$slug}", ['reason' => 'fraud', 'body' => 'Ce service me semble frauduleux, prix incohérent.', 'operation_key' => (string) Str::uuid()])->assertSessionHas('error');
        $this->assertSame(1, DB::table('support_cases')->count());
        // son propre contenu : refusé
        $this->actingAs($this->freelancer)->post("/espace/assistance/signaler/service/{$slug}", ['reason' => 'other', 'body' => 'Je signale mon propre service pour test.', 'operation_key' => (string) Str::uuid()])->assertSessionHas('error');
        // la personne signalée ne voit rien
        $this->actingAs($this->freelancer)->get('/espace/assistance')->assertDontSee('signalé');
        $this->actingAs($this->freelancer)->get('/espace/assistance/'.$c->reference)->assertNotFound();

        // message : seulement un message reçu, d'une conversation dont on est participant ; copie du seul message signalé
        $this->actingAs($this->client)->post("/services/{$slug}/contacter", ['body' => 'Bonjour, première question.', 'client_key' => (string) Str::uuid()])->assertRedirect();
        $conv = DB::table('conversations')->first();
        $this->actingAs($this->freelancer)->post("/espace/messages/{$conv->id}", ['body' => 'Contactez-moi sur WhatsApp au 0102030405 hors plateforme.', 'client_key' => (string) Str::uuid()]);
        $this->actingAs($this->client)->post("/espace/messages/{$conv->id}", ['body' => 'Message secret du client NE-PAS-FUITER.', 'client_key' => (string) Str::uuid()]);
        $bad = DB::table('messages')->where('body', 'like', 'Contactez-moi%')->value('id');
        $mine = DB::table('messages')->where('body', 'like', 'Message secret%')->value('id');
        $stranger = User::factory()->create();
        $this->actingAs($stranger)->post("/espace/assistance/signaler/message/{$bad}", ['reason' => 'private_contact', 'body' => 'Message d’un tiers que je ne devrais pas voir.', 'operation_key' => (string) Str::uuid()])->assertSessionHas('error');
        $this->actingAs($this->client)->post("/espace/assistance/signaler/message/{$mine}", ['reason' => 'other', 'body' => 'Je signale mon propre message pour test.', 'operation_key' => (string) Str::uuid()])->assertSessionHas('error');
        $this->actingAs($this->client)->post("/espace/assistance/signaler/message/{$bad}", ['reason' => 'private_contact', 'body' => 'Le vendeur me demande de sortir de la plateforme.', 'operation_key' => (string) Str::uuid()])->assertRedirect();
        $r = DB::table('support_cases')->where('target_type', 'message')->first();
        $this->assertStringContainsString('WhatsApp', $r->target_snapshot);
        $this->assertStringNotContainsString('NE-PAS-FUITER', json_encode(DB::table('support_cases')->get()));

        $this->actingAs($this->client)->get("/services/{$slug}")->assertSee('Signaler ce service');
    }

    public function test_requester_follow_up_separates_channels_and_never_shows_internal_notes(): void
    {
        $this->actingAs($this->client)->post('/espace/assistance', ['operation_key' => (string) Str::uuid(), 'subject' => 'Accès à mon compte', 'body' => 'Je n’arrive plus à me connecter depuis hier.', 'category' => 'account']);
        $ref = $this->caseRef();
        $this->takeAndOpen($ref, $this->support);
        $this->staff($this->support, 'post', "/admin/assistance/{$ref}/reponse", ['body' => 'Nous regardons votre accès, merci de patienter.', 'visibility' => 'requester', 'client_key' => (string) Str::uuid()])->assertRedirect()->assertSessionHas('status');
        $this->staff($this->support, 'post', "/admin/assistance/{$ref}/reponse", ['body' => 'NOTE-INTERNE-CONFIDENTIELLE : compte suspect.', 'visibility' => 'internal', 'client_key' => (string) Str::uuid()])->assertRedirect();
        $this->staff($this->support, 'post', "/admin/assistance/{$ref}/etat", ['status' => 'awaiting_requester', 'version' => DB::table('support_cases')->where('reference', $ref)->value('row_version')])->assertRedirect()->assertSessionHas('status');

        $r = $this->actingAs($this->client)->get("/espace/assistance/{$ref}");
        $r->assertOk()->assertSee('Nous regardons votre accès')->assertSee('L’équipe attend votre réponse')->assertDontSee('NOTE-INTERNE')->assertDontSee('Agent Support');
        $this->assertTrue(AppNotification::where('user_id', $this->client->id)->where('type', 'support_update')->exists());
        $this->assertSame(1, AppNotification::where('user_id', $this->client->id)->where('type', 'support_update')->count(), 'la note interne ne notifie personne');
        // le demandeur répond : l'examen reprend ; un tiers n'a aucun accès
        $this->actingAs($this->client)->post("/espace/assistance/{$ref}/reponse", ['body' => 'Voici plus de détails sur mon problème.', 'client_key' => (string) Str::uuid()])->assertRedirect();
        $this->assertSame('in_review', DB::table('support_cases')->where('reference', $ref)->value('status'));
        $this->actingAs($this->freelancer)->get("/espace/assistance/{$ref}")->assertNotFound();
        $this->actingAs($this->freelancer)->post("/espace/assistance/{$ref}/reponse", ['body' => 'Intrusion dans le dossier.', 'client_key' => (string) Str::uuid()])->assertNotFound();
        // double envoi du même message : une seule fois
        $k = (string) Str::uuid();
        $this->actingAs($this->client)->post("/espace/assistance/{$ref}/reponse", ['body' => 'Un seul envoi.', 'client_key' => $k]);
        $this->actingAs($this->client)->post("/espace/assistance/{$ref}/reponse", ['body' => 'Un seul envoi.', 'client_key' => $k]);
        $this->assertSame(1, DB::table('support_messages')->where('body', 'Un seul envoi.')->count());
    }

    public function test_attachments_use_the_private_scan_chain_and_respect_channels(): void
    {
        $o = $this->delivered();
        $this->dispute($o)->assertRedirect();
        $ref = $this->caseRef();
        $this->actingAs($this->client)->post("/espace/assistance/{$ref}/reponse", ['body' => 'Voici ma preuve : capture de la livraison.', 'client_key' => (string) Str::uuid(), 'file' => $this->pdf()])->assertRedirect()->assertSessionMissing('error');
        $f = DB::table('file_assets')->whereNotNull('support_message_id')->first();
        $this->assertSame('quarantined', $f->state);
        $this->assertNull($f->order_id);
        $this->actingAs($this->client)->get("/espace/assistance/{$ref}")->assertSee('en cours de contrôle de sécurité');
        (app(ScanBriefFile::class))($f->id);
        $this->assertSame('clean', DB::table('file_assets')->where('id', $f->id)->value('state'));
        $link = $this->actingAs($this->client)->get("/espace/assistance/{$ref}")->assertSee('preuve.pdf')->getContent();
        preg_match('#href="([^"]*assistance/fichiers/[^"]+)"#', $link, $m);
        $url = html_entity_decode($m[1]);
        $this->actingAs($this->client)->get($url)->assertOk();
        $this->actingAs($this->freelancer)->get($url)->assertNotFound();                    // lien lié à l'utilisateur
        $this->assertSame(1, DB::table('support_messages')->whereIn('visibility', ['parties'])->where('body', 'like', 'Voici ma preuve%')->count());
        // l'autre partie voit la pièce (canal « parties »)
        $this->actingAs($this->freelancer)->get("/espace/assistance/{$ref}")->assertOk()->assertSee('preuve.pdf');
        // le personnel n'accède à la pièce qu'affecté + dossier ouvert
        $staffUrl = fn () => URL::temporarySignedRoute('admin.support.files.download', now()->addMinutes(5), ['reference' => $ref, 'file' => $f->id, 'u' => $this->support->id]);
        $this->asAdmin($this->support)->get($staffUrl())->assertNotFound();
        $this->takeAndOpen($ref, $this->support);
        $this->asAdmin($this->support)->get($staffUrl())->assertOk();
        $this->assertSame(1, DB::table('admin_actions')->where('action', 'case.download')->count());
        // un fichier infecté ou mal formé reste refusé
        $this->actingAs($this->client)->post("/espace/assistance/{$ref}/reponse", ['body' => 'Fichier non conforme.', 'client_key' => (string) Str::uuid(),
            'file' => UploadedFile::fake()->createWithContent('virus.php.pdf', 'MZ....')])->assertSessionHas('error');
    }

    // ---------- litiges ----------

    public function test_opening_a_dispute_suspends_order_actions_blocks_payouts_and_never_validates_by_silence(): void
    {
        $o = $this->delivered();
        $this->assertFalse(PayoutHolds::isHeld($o->id));
        $this->actingAs($this->client)->get("/commandes/{$o->reference}/litige")->assertOk()->assertSee('suspendus')->assertSee('Ouvrir le dossier');
        $this->dispute($o)->assertRedirect()->assertSessionHas('status');
        $ref = $this->caseRef();

        $this->assertSame('disputed', $o->fresh()->state->value);
        $this->assertTrue(PayoutHolds::isHeld($o->id));
        $c = DB::table('support_cases')->where('reference', $ref)->first();
        $this->assertSame(['dispute', $this->client->id, $this->freelancer->id, 'delivered', 'high'], [$c->kind, $c->requester_id, $c->counterparty_id, $c->order_state_before, $c->priority]);
        $this->assertTrue(AppNotification::where('user_id', $this->freelancer->id)->where('type', 'dispute_update')->exists());
        $this->assertGreaterThan(0, DB::table('order_events')->where('order_id', $o->id)->where('type', 'dispute_opened')->count());
        // les deux parties voient le motif ; le dossier est suivi
        $this->actingAs($this->freelancer)->get("/espace/assistance/{$ref}")->assertOk()->assertSee('autre partie')->assertSee('ne correspond pas');
        $this->actingAs($this->freelancer)->get("/commandes/{$o->reference}")->assertSee('En litige')->assertSee('Actions suspendues');

        // actions suspendues : correction, validation, report, livraison — aucune ne passe
        $d = Delivery::query()->where('order_id', $o->id)->where('state', 'submitted')->firstOrFail();
        $v = fn () => $o->fresh()->row_version;
        $this->actingAs($this->client)->post("/commandes/{$o->reference}/validation", ['delivery_id' => $d->id, 'expected_version' => $v(), 'operation_key' => (string) Str::uuid(), 'confirm' => '1']);
        $this->actingAs($this->client)->post("/commandes/{$o->reference}/correction", ['delivery_id' => $d->id, 'reason' => 'Le calque COTATION manque sur les plans 3 et 7.', 'expected_version' => $v(), 'operation_key' => (string) Str::uuid()]);
        $this->actingAs($this->freelancer)->post("/commandes/{$o->reference}/report", ['proposed_date' => now()->addDays(10)->format('Y-m-d'), 'reason' => 'Besoin de plus de temps.', 'expected_version' => $v(), 'operation_key' => (string) Str::uuid()]);
        $this->assertSame('disputed', $o->fresh()->state->value);
        $this->assertSame([0, 0], [DB::table('correction_requests')->where('order_id', $o->id)->count(), DB::table('extension_requests')->where('order_id', $o->id)->count()]);

        // aucun silence ne valide : délai d'examen dépassé, tâches planifiées exécutées
        DB::table('deliveries')->where('order_id', $o->id)->getConnection()->statement('ALTER TABLE deliveries DISABLE TRIGGER deliveries_frozen');
        DB::table('deliveries')->where('id', $d->id)->update(['review_deadline_at' => now()->subDays(3)]);
        Artisan::call('freeci:orders:expire');
        $this->assertSame('disputed', $o->fresh()->state->value);
        $this->assertSame(0, DB::table('order_follow_ups')->where('order_id', $o->id)->count());
        $this->assertSame(0, DB::table('order_events')->where('order_id', $o->id)->whereIn('type', ['validated', 'closed'])->count());
        // les tableaux de bord et listes restent lisibles pour une commande en litige
        foreach ([[$this->client, ['/espace', '/espace/commandes']], [$this->freelancer, ['/freelance', '/freelance/commandes']]] as [$u, $urls]) {
            foreach ($urls as $url) {
                $r = $this->actingAs($u)->get($url)->assertOk();
                if (str_ends_with($url, 'commandes')) {
                    $r->assertSee('En litige');
                }
            }
        }
        // un seul dossier vivant : second litige refusé ; messages de la commande toujours possibles
        $this->dispute($o, $this->freelancer)->assertSessionHas('error');
        $this->assertSame(1, DB::table('support_cases')->count());
        $this->actingAs($this->client)->get("/commandes/{$o->reference}/litige")->assertSee($ref);
    }

    public function test_dispute_and_claim_are_distinct_and_never_promise_to_block_sent_funds(): void
    {
        $o = $this->delivered();
        // aucun reversement exécuté : pas de réclamation
        $this->dispute($o, null, 'claim')->assertSessionHas('error');
        $this->assertSame('delivered', $o->fresh()->state->value);
        // reversement DÉJÀ exécuté (futur module) : litige refusé, réclamation possible, aucun blocage ni changement d'état
        $this->app->bind(PayoutExecution::class, fn () => new class implements PayoutExecution
        {
            public function executed(string $orderId): bool
            {
                return true;
            }
        });
        $this->dispute($o)->assertSessionHas('error');
        $this->actingAs($this->client)->get("/commandes/{$o->reference}/litige")->assertSee('Réclamation après versement')->assertSee('déjà été envoyé');
        $this->dispute($o, null, 'claim', 'Le reversement a été envoyé alors que la livraison était non conforme.')->assertRedirect()->assertSessionHas('status');
        $ref = $this->caseRef();
        $this->assertSame('delivered', $o->fresh()->state->value);
        $this->assertFalse(PayoutHolds::isHeld($o->id));
        $this->assertSame(0, DB::table('payout_holds')->count());
        $this->actingAs($this->client)->get("/espace/assistance/{$ref}")->assertSee('ne peut ni le bloquer ni le récupérer')->assertSee('n’est pas garanti');
        $this->actingAs($this->freelancer)->get("/espace/assistance/{$ref}")->assertNotFound();            // la réclamation n'implique pas l'autre partie
        $this->takeAndOpen($ref, $this->support);
        $this->decide($ref, $this->support, ['outcome' => 'answered', 'financial_need' => 'refund'])->assertRedirect()->assertSessionHas('status');
        $dec = DB::table('support_decisions')->first();
        $this->assertSame(['answered', 'refund', 'to_process', null], [$dec->outcome, $dec->financial_need, $dec->financial_status, $dec->order_state_to]);
        $this->assertSame('delivered', $o->fresh()->state->value);
    }

    public function test_cancellation_requests_and_eligibility_by_state(): void
    {
        $buyer = User::factory()->create();
        $buyer->roles()->firstOrCreate(['role' => 'client']);
        $o = $this->placeOrder($buyer);                            // en attente de réponse : ni litige ni annulation après paiement
        $this->actingAs($buyer)->get("/commandes/{$o->reference}/litige")->assertSee('Aucun litige ni aucune annulation');
        $this->dispute($o, $buyer)->assertSessionHas('error');
        $this->dispute($o, $buyer, 'cancellation')->assertSessionHas('error');
        $this->assertSame(0, DB::table('support_cases')->count());

        $p = $this->inProgress();                                  // payée, en cours : annulation possible
        $this->dispute($p, $this->freelancer, 'cancellation', 'Le client ne répond plus et le brief est inexploitable.')->assertRedirect();
        $this->assertSame('disputed', $p->fresh()->state->value);
        $this->assertSame('cancellation', DB::table('support_cases')->value('kind'));
        $this->assertTrue(PayoutHolds::isHeld($p->id));
        // un non-partie ne peut rien ouvrir
        $this->dispute($p, User::factory()->create(), 'dispute')->assertNotFound();
    }

    // ---------- traitement par le personnel ----------

    public function test_staff_access_is_limited_to_assigned_opened_cases_and_ends_with_the_assignment(): void
    {
        $o = $this->delivered();
        $this->dispute($o)->assertRedirect();
        $ref = $this->caseRef();
        $this->actingAs($this->client)->post("/espace/assistance/{$ref}/reponse", ['body' => 'TEXTE-PRIVE-DU-LITIGE à protéger.', 'client_key' => (string) Str::uuid()]);

        // personnel non affecté : métadonnées seulement, jamais le contenu
        $this->asAdmin($this->support)->get('/admin/assistance')->assertOk()->assertSee('Litige')->assertDontSee('TEXTE-PRIVE');
        $this->asAdmin($this->support)->get("/admin/assistance/{$ref}")->assertOk()->assertSee('n’est visible que de la personne')->assertDontSee('TEXTE-PRIVE')->assertDontSee('Voici la livraison complète');
        $this->staff($this->support, 'post', "/admin/assistance/{$ref}/ouvrir", ['reason' => 'Je veux lire le dossier sans être affecté.'])->assertSessionHas('error');
        $this->staff($this->support, 'post', "/admin/assistance/{$ref}/reponse", ['body' => 'Intrus.', 'visibility' => 'internal', 'client_key' => (string) Str::uuid()])->assertNotFound();
        // affecté mais pas encore ouvert : toujours pas de contenu ; ouverture : motif obligatoire
        $this->staff($this->support, 'post', "/admin/assistance/{$ref}/prendre")->assertSessionHas('status');
        $this->asAdmin($this->support)->get("/admin/assistance/{$ref}")->assertDontSee('TEXTE-PRIVE')->assertSee('Ouvrir le dossier');
        $this->staff($this->support, 'post', "/admin/assistance/{$ref}/ouvrir", ['reason' => 'court'])->assertSessionHasErrors('reason');
        $this->staff($this->support, 'post', "/admin/assistance/{$ref}/decision", ['outcome' => 'continue', 'financial_need' => 'none', 'reason' => str_repeat('Motif de décision. ', 3), 'version' => 1, 'operation_key' => (string) Str::uuid(), 'confirm' => '1'])->assertSessionHas('error');
        $this->staff($this->support, 'post', "/admin/assistance/{$ref}/ouvrir", ['reason' => 'Examen du litige entre les deux parties.'])->assertSessionHas('status');
        $page = $this->asAdmin($this->support)->get("/admin/assistance/{$ref}")->assertOk()->assertSee('TEXTE-PRIVE-DU-LITIGE')->assertSee('Accord :')->assertSee('Livraisons soumises')->assertSee('Voici la livraison complète');
        $page->assertDontSee('Messages de la commande');
        $this->assertGreaterThan(0, DB::table('admin_actions')->where('action', 'case.view')->count());
        $this->assertSame(1, DB::table('admin_actions')->where('action', 'case.open')->where('result', 'done')->count());

        // un autre agent n'a aucun accès au contenu
        $other = $this->readyStaffSupport('autre@example.test', 'Autre Agent');
        $this->asAdmin($other)->get("/admin/assistance/{$ref}")->assertDontSee('TEXTE-PRIVE');
        $this->staff($other, 'post', "/admin/assistance/{$ref}/prendre")->assertSessionHas('error');      // déjà affecté

        // fin d'affectation : l'accès au contenu est retiré
        $this->staff($this->support, 'post', "/admin/assistance/{$ref}/liberer")->assertSessionHas('status');
        $this->asAdmin($this->support)->get("/admin/assistance/{$ref}")->assertDontSee('TEXTE-PRIVE');
        $row = DB::table('support_cases')->where('reference', $ref)->first();
        $this->assertSame([null, null, null], [$row->assignee_id, $row->access_reason, $row->access_opened_at]);

        // révocation de l'habilitation : les affectations prennent fin et l'accès disparaît
        $this->takeAndOpen($ref, $other);
        $this->assertSame($other->id, DB::table('support_cases')->where('reference', $ref)->value('assignee_id'));
        Artisan::call('freeci:staff:revoke', ['email' => 'autre@example.test']);
        $this->assertNull(DB::table('support_cases')->where('reference', $ref)->value('assignee_id'));
        $this->asAdmin($other->fresh())->get("/admin/assistance/{$ref}")->assertNotFound();
        // le personnel « support » n'a ni modération, ni utilisateurs, ni audit
        foreach (['/admin/moderation', '/admin/utilisateurs', '/admin/journal'] as $u) {
            $this->asAdmin($this->support)->get($u)->assertNotFound();
        }
        $this->asAdmin($this->support)->get('/admin')->assertRedirect(route('admin.support'));
    }

    public function test_staff_cannot_handle_cases_where_they_are_involved_and_only_admins_assign_others(): void
    {
        $o = $this->delivered();
        // l'agent de support est aussi le CLIENT de la commande
        DB::table('orders')->where('id', $o->id)->update(['client_id' => $this->support->id]);
        $this->dispute($o->fresh(), $this->freelancer)->assertRedirect();
        $ref = $this->caseRef();
        $this->staff($this->support, 'post', "/admin/assistance/{$ref}/prendre")->assertSessionHas('error');
        $this->asAdmin($this->support)->get("/admin/assistance/{$ref}")->assertOk()->assertSee('partie prenante');
        $this->assertNull(DB::table('support_cases')->where('reference', $ref)->value('assignee_id'));
        $this->assertSame(1, DB::table('admin_actions')->where('action', 'case.claim')->where('result', 'refused')->count());
        // l'administrateur ne peut pas non plus affecter à une personne en conflit ; un agent ne peut pas affecter un autre
        $this->staff($this->admin, 'post', "/admin/assistance/{$ref}/affecter", ['assignee' => $this->support->id])->assertSessionHas('error');
        $this->staff($this->support, 'post', "/admin/assistance/{$ref}/affecter", ['assignee' => $this->support->id])->assertSessionHas('error');
        // la base refuse elle aussi l'auto-traitement
        $this->expectException(QueryException::class);
        DB::table('support_cases')->where('reference', $ref)->update(['assignee_id' => $this->freelancer->id]);
    }

    public function test_admin_can_assign_to_eligible_staff_and_ineligible_staff_is_refused(): void
    {
        $this->actingAs($this->client)->post('/espace/assistance', ['operation_key' => (string) Str::uuid(), 'subject' => 'Une question', 'body' => 'Une question suffisamment détaillée.', 'category' => 'other']);
        $ref = $this->caseRef();
        $this->staff($this->admin, 'post', "/admin/assistance/{$ref}/affecter", ['assignee' => $this->support->id])->assertSessionHas('status');
        $this->assertSame($this->support->id, DB::table('support_cases')->where('reference', $ref)->value('assignee_id'));
        $bare = User::factory()->create(['email_verified_at' => null]);
        app(GrantSupport::class)($bare, 'test');                                  // habilité mais sans adresse vérifiée ni MFA
        $this->staff($this->admin, 'post', "/admin/assistance/{$ref}/liberer")->assertSessionHas('status');
        $this->staff($this->admin, 'post', "/admin/assistance/{$ref}/affecter", ['assignee' => $bare->id])->assertSessionHas('error');
        $this->actingAs($bare)->get('/admin/assistance')->assertRedirect(route('admin.activation'));
        $this->actingAs($this->client)->get('/admin/assistance')->assertNotFound();
    }

    // ---------- décisions ----------

    public function test_decision_continue_restores_the_previous_state_and_notifies_without_any_financial_execution(): void
    {
        $o = $this->delivered();
        $before = $o->fresh()->row_version;
        $this->dispute($o)->assertRedirect();
        $ref = $this->caseRef();
        $this->takeAndOpen($ref, $this->support);
        $this->decide($ref, $this->support, ['financial_need' => ''])->assertSessionHasErrors('financial_need');                       // suite financière : choix explicite
        $this->decide($ref, $this->support)->assertRedirect()->assertSessionHas('status');

        $this->assertSame('delivered', $o->fresh()->state->value);
        $this->assertGreaterThan($before, $o->fresh()->row_version);
        $this->assertFalse(PayoutHolds::isHeld($o->id), 'décision sans suite financière : blocage interne levé');
        $c = DB::table('support_cases')->where('reference', $ref)->first();
        $this->assertSame(['decided', null, null], [$c->status, $c->assignee_id, $c->access_opened_at]);          // l'accès prend fin avec la décision
        foreach ([$this->client, $this->freelancer] as $u) {
            $this->assertTrue(AppNotification::where('user_id', $u->id)->where('type', 'dispute_update')->where('title', 'like', 'Décision%')->exists());
            $this->actingAs($u)->get("/espace/assistance/{$ref}")->assertSee('Décision rendue')->assertSee('reprend là où elle en était')->assertDontSee('Cette décision ne vaut pas encore remboursement');
        }
        $this->actingAs($this->client)->get("/commandes/{$o->reference}")->assertDontSee('Actions suspendues');
        // la commande reprend : le client peut encore choisir (valider ou corriger) ; rien n'a été validé à sa place
        $this->assertSame(0, DB::table('order_events')->where('order_id', $o->id)->where('type', 'validated')->count());
        // double décision : refusée (dossier déjà décidé) et unique en base
        $this->decide($ref, $this->support)->assertSessionHas('error');
        $this->assertSame(1, DB::table('support_decisions')->count());
        $this->expectException(QueryException::class);
        DB::table('support_decisions')->insert(['case_id' => $c->id, 'outcome' => 'continue', 'reason' => 'x', 'decided_by' => $this->support->id, 'created_at' => now()]);
    }

    public function test_decision_validate_delivery_closes_commercially_and_cancel_keeps_the_hold_with_a_financial_need_to_process(): void
    {
        $o = $this->delivered();
        $this->dispute($o, $this->freelancer, 'dispute', 'Le client ne répond plus depuis plus de sept jours et bloque la commande.')->assertRedirect();
        $ref = $this->caseRef();
        $this->takeAndOpen($ref, $this->support);
        $this->decide($ref, $this->support, ['outcome' => 'validate_delivery', 'financial_need' => 'release'])->assertRedirect()->assertSessionHas('status');
        $this->assertSame('closed', $o->fresh()->state->value);
        $this->assertSame('validated', $o->fresh()->closure_reason->value);
        $this->assertFalse(PayoutHolds::isHeld($o->id), 'reversement à autoriser : le blocage interne est levé, aucun reversement n’est déclenché');
        $this->assertSame(0, DB::table('ledger_batches')->where('order_id', $o->id)->where('kind', '<>', 'payment_confirmed')->count(), 'aucune opération financière');
        $this->assertSame('to_process', DB::table('support_decisions')->value('financial_status'));
        $this->assertSame(['disputed', 'closed'], [DB::table('support_decisions')->value('order_state_from'), DB::table('support_decisions')->value('order_state_to')]);

        // annulation après paiement : la commande est annulée, le blocage reste, le remboursement est « à traiter » — jamais « remboursé »
        $p = $this->inProgress();
        $this->dispute($p, null, 'cancellation', 'Je souhaite annuler : le prestataire n’a plus donné de nouvelles.')->assertRedirect();
        $ref2 = $this->caseRef();
        $this->takeAndOpen($ref2, $this->support);
        $this->decide($ref2, $this->support, ['outcome' => 'cancel', 'financial_need' => 'none'])->assertRedirect();      // « aucune » reste un choix explicite
        $this->assertSame('cancelled', $p->fresh()->state->value);
        $this->assertSame('cancelled_after_payment', $p->fresh()->closure_reason->value);
        $q = $this->inProgress();
        $this->dispute($q, null, 'cancellation', 'Annulation demandée : le brief n’est plus pertinent pour moi.')->assertRedirect();
        $ref3 = $this->caseRef();
        $this->takeAndOpen($ref3, $this->support);
        $this->decide($ref3, $this->support, ['outcome' => 'cancel', 'financial_need' => 'partial'])->assertSessionHas('error');         // répartition : précision humaine obligatoire
        $this->decide($ref3, $this->support, ['outcome' => 'cancel', 'financial_need' => 'partial', 'financial_note' => 'Retenir le travail déjà livré, rembourser le solde : montant à fixer par la finance.'])->assertRedirect()->assertSessionHas('status');
        $this->assertTrue(PayoutHolds::isHeld($q->id), 'suite financière à traiter : le blocage interne reste');
        $this->assertSame('cancelled', $q->fresh()->state->value);
        $this->actingAs($this->client)->get("/espace/assistance/{$ref3}")->assertSee('À traiter financièrement')->assertSee('aucune opération financière n’a été exécutée');
        $this->asAdmin($this->support)->get('/admin/assistance?onglet=financier')->assertOk()->assertSee($ref3)->assertSee('Une décision ne rembourse ni ne verse rien')->assertSee('Actif');
        $this->assertSame(0, DB::table('payments')->where('state', 'refunded')->count());
        // les décisions et leurs motifs sont journalisés
        $this->assertSame(3, DB::table('admin_actions')->where('action', 'case.decide')->where('result', 'done')->count());
    }

    public function test_a_suspended_account_keeps_access_to_its_active_case_and_can_contact_support(): void
    {
        $o = $this->delivered();
        $this->dispute($o)->assertRedirect();
        $ref = $this->caseRef();
        DB::table('users')->where('id', $this->client->id)->update(['suspended_at' => now()]);
        $this->actingAs($this->client->fresh())->get("/espace/assistance/{$ref}")->assertOk();
        $this->actingAs($this->client->fresh())->post("/espace/assistance/{$ref}/reponse", ['body' => 'Je poursuis dans le dossier malgré la suspension.', 'client_key' => (string) Str::uuid()])->assertRedirect()->assertSessionMissing('error');
        $this->actingAs($this->client->fresh())->post('/espace/assistance', ['operation_key' => (string) Str::uuid(), 'subject' => 'Contester la suspension', 'body' => 'Je souhaite contester la suspension de mon compte.', 'category' => 'account'])->assertRedirect();
        // un contact bloqué ne retire pas l'accès des parties au dossier
        DB::table('contact_blocks')->insert(['blocker_id' => $this->freelancer->id, 'blocked_id' => $this->client->id, 'created_at' => now()]);
        $this->actingAs($this->freelancer)->post("/espace/assistance/{$ref}/reponse", ['body' => 'Voici ma réponse et mes arguments.', 'client_key' => (string) Str::uuid()])->assertRedirect()->assertSessionMissing('error');
    }

    // ---------- besoins de suivi ----------

    public function test_follow_ups_are_visible_to_staff_with_their_origin_and_become_cases_only_explicitly_and_once(): void
    {
        $o = $this->delivered();
        $d = Delivery::query()->where('order_id', $o->id)->where('state', 'submitted')->firstOrFail();
        $id = DB::table('order_follow_ups')->insertGetId(['order_id' => $o->id, 'delivery_id' => $d->id, 'kind' => 'review_silence', 'recorded_at' => now()]);
        $this->assertSame(0, DB::table('support_cases')->count(), 'jamais de litige créé silencieusement');

        $this->asAdmin($this->support)->get('/admin/assistance?onglet=suivis')->assertOk()->assertSee($o->reference)->assertSee('Silence du client')->assertSee('Origine conservée')->assertSee('Ouvrir un dossier de suivi');
        $this->staff($this->support, 'post', "/admin/assistance/suivis/{$id}/dossier")->assertRedirect()->assertSessionHas('status');
        $c = DB::table('support_cases')->first();
        $this->assertSame(['follow_up', 'follow_up', (string) $id, null, 'open'], [$c->kind, $c->origin, $c->origin_ref, $c->requester_id, $c->status]);
        $this->assertSame('delivered', $o->fresh()->state->value, 'la commande n’est pas modifiée');
        $this->assertFalse(PayoutHolds::isHeld($o->id));
        $this->staff($this->support, 'post', "/admin/assistance/suivis/{$id}/dossier")->assertRedirect()->assertSessionHas('error');        // une seule fois
        $this->assertSame(1, DB::table('support_cases')->count());
        // pas de décision de litige sur un dossier de suivi ; clôture avec conclusion
        $this->takeAndOpen($c->reference, $this->support);
        $this->decide($c->reference, $this->support)->assertSessionHas('error');
        $this->staff($this->support, 'post', "/admin/assistance/{$c->reference}/cloture", ['note' => 'Aucune action nécessaire pour le moment.'])->assertSessionHas('status');
        $this->assertSame('closed', DB::table('support_cases')->value('status'));
        $this->assertSame(0, DB::table('support_cases')->whereIn('kind', ['dispute', 'cancellation'])->count());
        $this->asAdmin($this->support)->get('/admin/assistance?onglet=suivis')->assertSee($c->reference);
    }

    public function test_status_changes_use_the_expected_version_and_the_queue_filters_work(): void
    {
        $this->actingAs($this->client)->post('/espace/assistance', ['operation_key' => (string) Str::uuid(), 'subject' => 'Première demande', 'body' => 'Une première demande assez longue.', 'category' => 'other']);
        $ref = $this->caseRef();
        $this->takeAndOpen($ref, $this->support);
        $v = DB::table('support_cases')->where('reference', $ref)->value('row_version');
        $this->staff($this->support, 'post', "/admin/assistance/{$ref}/etat", ['status' => 'awaiting_requester', 'version' => $v - 1])->assertSessionHas('error');
        $this->staff($this->support, 'post', "/admin/assistance/{$ref}/etat", ['status' => 'awaiting_requester', 'version' => $v, 'priority' => 'high'])->assertSessionHas('status');
        $this->staff($this->support, 'post', "/admin/assistance/{$ref}/etat", ['status' => 'decided', 'version' => $v + 1])->assertSessionHas('error');
        $this->asAdmin($this->support)->get('/admin/assistance?qui=mine&statut=awaiting_requester&type=support&q=Premi')->assertSee('Première demande');
        $this->asAdmin($this->support)->get('/admin/assistance?type=dispute')->assertSee('Aucun dossier ne correspond');
        $this->asAdmin($this->support)->get('/admin/assistance?qui=unassigned')->assertSee('Aucun dossier ne correspond');
        $this->asAdmin($this->admin)->get('/admin/journal?action=case.status')->assertSee('Dossier : changement d’état');
    }
}

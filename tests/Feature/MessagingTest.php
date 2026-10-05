<?php

namespace Tests\Feature;

use App\Livewire\ConversationFreshness;
use App\Livewire\UnreadBadge;
use App\Modules\Accounts\Actions\GrantAdministrator;
use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Models\FreelanceProfile;
use App\Modules\Messaging\Models\Conversation;
use App\Modules\Messaging\Models\Message;
use App\Modules\Messaging\Queries\Inbox;
use App\Modules\Missions\Actions\MissionModeration;
use App\Modules\Missions\Models\Mission;
use App\Modules\Missions\Models\Proposal;
use App\Modules\Orders\Models\Order;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\FakeScanner;
use Tests\Support\OrderFixtures;
use Tests\TestCase;

/** Lot 7 : messagerie privée. */
class MessagingTest extends TestCase
{
    use OrderFixtures, RefreshDatabase;

    private User $admin;

    private User $stranger;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private_files');
        $this->setUpParties();
        $this->useFakeScanner();
        config(['freeci.messaging.per_10_minutes' => 1000]);
        $this->admin = User::factory()->create();
        app(GrantAdministrator::class)($this->admin, 'test');
        $this->stranger = User::factory()->create(['name' => 'Tiers Curieux']);
    }

    private function pdf(string $name = 'note.pdf', string $extra = ''): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<<>>endobj\n{$extra}trailer<<>>\n%%EOF\n");
    }

    private function say(User $who, Conversation|string $c, string $body, ?UploadedFile $file = null, ?string $key = null)
    {
        $id = $c instanceof Conversation ? $c->id : $c;

        return $this->actingAs($who)->post("/espace/messages/{$id}", ['body' => $body, 'client_key' => $key ?? (string) Str::uuid(), 'file' => $file]);
    }

    /** Le client ouvre une conversation au sujet du service et envoie le premier message. */
    private function open(string $body = 'Bonjour, ce service convient-il à une villa R+1 ?'): Conversation
    {
        $this->actingAs($this->client)->post("/services/{$this->service->slug}/contacter", ['body' => $body, 'client_key' => (string) Str::uuid()])->assertRedirect()->assertSessionHas('status');

        return Conversation::query()->orderByDesc('created_at')->firstOrFail();
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

    // ---------- accès ----------

    public function test_only_the_two_participants_see_or_use_a_conversation_and_never_an_administrator(): void
    {
        $c = $this->open('Message très confidentiel XYZ-7781');
        $this->assertSame(['service', $this->client->id, $this->freelancer->id], [$c->kind, $c->client_id, $c->freelancer_id]);

        foreach ([$this->stranger, $this->admin] as $who) {
            $this->actingAs($who)->get("/espace/messages/{$c->id}")->assertNotFound();
            $this->say($who, $c, 'intrusion')->assertNotFound();
            $this->actingAs($who)->post("/espace/messages/{$c->id}/bloquer")->assertNotFound();
            $this->actingAs($who)->get('/espace/messages')->assertOk()->assertDontSee('XYZ-7781');
        }
        $this->assertSame(1, Message::count());
        $this->actingAs($this->freelancer)->get("/espace/messages/{$c->id}?espace=freelance")->assertOk()->assertSee('XYZ-7781');
        $this->actingAs($this->client)->get('/espace/messages')->assertOk()->assertSee('Kader Freelance')->assertSee('Service');
        // propre service : impossible ; visiteur : connexion ; service non publié : introuvable
        $this->actingAs($this->freelancer)->get("/services/{$this->service->slug}/contacter")->assertRedirect()->assertSessionHas('error');
        auth()->forgetGuards();
        $this->get("/services/{$this->service->slug}/contacter")->assertRedirect('/connexion');
        $this->actingAs($this->client)->get('/services/inexistant/contacter')->assertNotFound();
        $this->service->update(['status' => 'draft']);
        $this->actingAs($this->stranger)->get("/services/{$this->service->slug}/contacter")->assertNotFound();
    }

    // ---------- non-lus, pagination, doubles soumissions, débit ----------

    public function test_unread_counters_pagination_double_submit_and_rate_limit(): void
    {
        $c = $this->open('Premier message');
        $this->say($this->client, $c, 'Deuxième message');
        $inbox = app(Inbox::class);
        $this->assertSame(2, $inbox->totalUnread($this->freelancer->fresh()));
        $this->assertSame(0, $inbox->totalUnread($this->client->fresh()), 'ses propres messages ne comptent pas');
        $this->actingAs($this->freelancer)->get('/espace/messages?espace=freelance')->assertOk()->assertSee('2 non lus');
        Livewire::actingAs($this->freelancer)->test(UnreadBadge::class, ['kind' => 'messages'])->assertSee('2');
        Livewire::actingAs($this->freelancer)->test(ConversationFreshness::class, ['conversation' => $c->id, 'after' => 0])->assertSee('nouveaux messages');
        Livewire::actingAs($this->freelancer)->test(ConversationFreshness::class, ['conversation' => $c->id, 'after' => $c->fresh()->last_message_id])->assertDontSee('nouveau');

        $this->actingAs($this->freelancer)->get("/espace/messages/{$c->id}")->assertOk()->assertSee('Nouveau');       // marqué lu à l'affichage
        $this->assertSame(0, $inbox->totalUnread($this->freelancer->fresh()));
        $this->say($this->freelancer, $c, 'Réponse du freelance');
        $this->assertSame(1, $inbox->totalUnread($this->client->fresh()));

        // double soumission : même clé = un seul message
        $key = (string) Str::uuid();
        $this->say($this->client, $c, 'Envoi unique', null, $key)->assertRedirect()->assertSessionHas('status', 'Message envoyé.');
        $this->say($this->client, $c, 'Envoi unique', null, $key)->assertRedirect()->assertSessionHas('status', 'Ce message avait déjà été envoyé.');
        $this->assertSame(1, Message::where('body', 'Envoi unique')->count());
        $this->dbRefuses(fn () => DB::table('messages')->where('id', Message::first()->id)->update(['body' => 'réécrit']), 'ajout seul');

        // pagination : 20 par page, les plus anciens à la demande
        $this->withoutMiddleware(ThrottleRequests::class);
        for ($i = 1; $i <= 45; $i++) {
            $this->say($this->freelancer, $c, "Message numéro {$i}");
        }
        $page = $this->actingAs($this->client)->get("/espace/messages/{$c->id}")->assertOk();
        $page->assertSee('Message numéro 45')->assertSee('Message numéro 26')->assertDontSee('Message numéro 25 ')->assertSee('Messages plus anciens');
        $oldest = Message::where('body', 'Message numéro 26')->value('id');
        $this->actingAs($this->client)->get("/espace/messages/{$c->id}?avant={$oldest}")->assertOk()->assertSee('Message numéro 25')->assertSee('Message numéro 6')->assertDontSee('Message numéro 26<');
        $this->assertSame(0, $inbox->totalUnread($this->client->fresh()), 'après lecture de la dernière page');

        // limitation des envois
        config(['freeci.messaging.per_10_minutes' => 3]);
        $n = Message::where('sender_id', $this->client->id)->count();
        for ($i = 0; $i < 6; $i++) {
            $this->say($this->client, $c, "Rafale {$i}");
        }
        $this->assertSame(3, Message::where('sender_id', $this->client->id)->where('created_at', '>', now()->subMinutes(10))->count());
        $this->say($this->client, $c, 'Encore')->assertRedirect()->assertSessionHas('error');
        $this->assertNotNull($n);
    }

    // ---------- pièces jointes ----------

    public function test_attachments_use_the_private_scan_chain_and_an_uncontrolled_file_is_never_downloadable(): void
    {
        $c = $this->open();
        FakeScanner::$unavailable = true;
        $this->say($this->client, $c, 'Voici le plan', $this->pdf('plan.pdf'))->assertRedirect()->assertSessionHas('status');
        $f = DB::table('file_assets')->whereNotNull('message_id')->first();
        $this->assertSame(['quarantined', null], [$f->state, $f->order_id], 'quarantaine ; jamais un fichier de commande');
        Storage::disk('private_files')->assertExists($f->storage_key);
        $link = fn (User $u) => URL::temporarySignedRoute('messages.files.download', now()->addMinutes(5), ['file' => $f->id, 'u' => $u->id]);

        $this->actingAs($this->freelancer)->get("/espace/messages/{$c->id}")->assertOk()->assertSee('plan.pdf')->assertSee('Non téléchargeable avant la fin du contrôle');
        $this->actingAs($this->freelancer)->get($link($this->freelancer))->assertNotFound();            // lien fabriqué : refusé tant que non contrôlé
        FakeScanner::$unavailable = false;
        Artisan::call('freeci:files:scan');
        $this->assertSame('clean', DB::table('file_assets')->where('id', $f->id)->value('state'));
        $r = $this->actingAs($this->freelancer)->get($link($this->freelancer))->assertOk();
        $this->assertSame('nosniff', $r->headers->get('x-content-type-options'));
        $this->assertStringContainsString('attachment', $r->headers->get('content-disposition'));
        $this->actingAs($this->client)->get($link($this->client))->assertOk();
        foreach ([$this->stranger, $this->admin] as $who) {
            $this->actingAs($who)->get($link($who))->assertNotFound();
        }
        $this->actingAs($this->stranger)->get($link($this->freelancer))->assertNotFound();           // lien lié à l'utilisateur
        $this->actingAs($this->freelancer)->get("/espace/messages/fichiers/{$f->id}")->assertForbidden();   // non signé

        // fichier infecté : refusé, supprimé, jamais téléchargeable ; scanner absent : pièce jointe refusée, aucun message enregistré
        $this->say($this->client, $c, 'Fichier suspect', $this->pdf('virus.pdf', 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE'))->assertRedirect();
        $bad = DB::table('file_assets')->where('original_name', 'virus.pdf')->first();
        $this->assertSame('rejected', $bad->state);
        Storage::disk('private_files')->assertMissing($bad->storage_key);
        $before = Message::count();
        FakeScanner::$operational = false;
        $this->say($this->client, $c, 'Sans contrôle', $this->pdf('autre.pdf'))->assertRedirect()->assertSessionHas('error');
        FakeScanner::$operational = true;
        $this->say($this->client, $c, 'Format interdit', UploadedFile::fake()->createWithContent('outil.exe', 'MZ......'))->assertRedirect()->assertSessionHasErrors('file');
        $this->assertSame($before, Message::count());
        // un fichier de message n'est jamais un fichier de brief ou de livraison (base)
        $order = $this->placeOrder();
        $this->dbRefuses(fn () => DB::table('file_assets')->where('id', $f->id)->update(['order_id' => $order->id]), 'file_assets');
    }

    // ---------- un message n'est jamais un acte de commande ----------

    public function test_a_message_or_attachment_never_delivers_amends_the_agreement_accepts_an_extension_or_validates(): void
    {
        $this->service->update(['delivery_requires_files' => false]);
        $order = $this->inProgress();
        $before = $order->fresh()->only(['state', 'row_version', 'due_at', 'closed_at']);
        $agreement = $order->agreement->fresh()->only(['price_xof', 'scope', 'delivery_days', 'revisions_included']);
        $c = $this->actingAs($this->client)->get("/commandes/{$order->reference}/messages")->assertRedirect()->baseResponse;
        $conv = Conversation::where('order_id', $order->id)->firstOrFail();
        foreach (['Voici ma livraison finale, merci de valider', 'J’accepte votre report d’échéance', 'Le prix passe à 1 000 FCFA, accord modifié', 'Je valide la livraison'] as $text) {
            $this->say($this->freelancer, $conv, $text, $this->pdf('livraison-finale.pdf'))->assertRedirect()->assertSessionHas('status');
            $this->say($this->client, $conv, $text)->assertRedirect();
        }
        $after = $order->fresh()->only(['state', 'row_version', 'due_at', 'closed_at']);
        $this->assertSame([$before['state'], $before['row_version'], $before['due_at']->toIso8601String(), null], [$after['state'], $after['row_version'], $after['due_at']->toIso8601String(), $after['closed_at']]);
        $this->assertSame($agreement, $order->agreement->fresh()->only(array_keys($agreement)));
        $this->assertSame(0, DB::table('deliveries')->count());
        $this->assertSame(0, DB::table('extension_requests')->count());
        $this->assertSame(0, DB::table('file_assets')->whereNull('message_id')->count());
        $this->assertSame(0, DB::table('order_events')->where('order_id', $order->id)->whereIn('type', ['delivery_submitted', 'validated', 'extension_accepted', 'closed'])->count());
        $this->actingAs($this->client)->get("/commandes/{$order->reference}")->assertOk()->assertDontSee('livraison-finale.pdf');
        $this->actingAs($this->freelancer)->get("/espace/messages/{$conv->id}?espace=freelance")->assertOk()->assertSee('ne valent ni livraison, ni modification de l’accord');
    }

    // ---------- contexte : service → commande, proposition → commande ----------

    public function test_the_service_thread_continues_in_the_order_and_the_context_is_kept(): void
    {
        $c = $this->open('Question avant de commander');
        $this->say($this->freelancer, $c, 'Oui, tout à fait.');
        $order = $this->placeOrder();
        $this->assertSame($order->id, $c->fresh()->order_id, 'le fil du service est rattaché à la commande');
        $this->assertSame(1, Conversation::count());
        $this->assertSame(2, Message::count(), 'historique conservé');
        $this->actingAs($this->client)->get("/commandes/{$order->reference}")->assertOk()->assertSee('Messages');
        $this->actingAs($this->client)->get("/commandes/{$order->reference}/messages")->assertRedirect(route('messages.show', ['conversation' => $c->id]));
        $this->actingAs($this->client)->get("/espace/messages/{$c->id}")->assertOk()->assertSee('Question avant de commander')->assertSee('rattachée à la commande');
        $this->actingAs($this->stranger)->get("/commandes/{$order->reference}/messages")->assertNotFound();
        // une seconde commande du même couple obtient son propre fil (le contexte du premier n'est pas détourné)
        $this->actingAs($this->freelancer)->post("/commandes/{$order->reference}/decline", ['expected_version' => $order->fresh()->row_version, 'operation_key' => 'd1', 'reason' => 'Indisponible cette semaine'])->assertRedirect();
        $order2 = $this->placeOrder();
        $this->assertNull(Conversation::where('order_id', $order2->id)->value('id'));
        $this->actingAs($this->client)->get("/commandes/{$order2->reference}/messages")->assertRedirect();
        $this->assertSame(2, Conversation::count());
    }

    public function test_a_proposal_conversation_moves_to_the_order_without_exposing_other_candidates(): void
    {
        $other = User::factory()->create(['name' => 'Concurrent Freelance']);
        $other->roles()->firstOrCreate(['role' => 'freelance']);
        FreelanceProfile::factory()->create(['user_id' => $other->id, 'display_name' => 'Concurrent Freelance']);
        $mission = $this->openMission();
        $make = function (User $who, int $price) use ($mission) {
            $this->actingAs($who)->post("/missions/{$mission->fresh()->slug}/proposition", ['price_xof' => (string) $price, 'delivery_days' => '6', 'revisions_included' => '2', 'validity_days' => '7', 'delivery_mode' => 'message',
                'scope' => 'Conversion des douze plans en DWG AutoCAD 2018, calques conservés, un fichier par plan et un PDF de contrôle.', 'deliverables' => 'Douze fichiers DWG', 'expected_number' => 0])->assertRedirect();

            return Proposal::where('mission_id', $mission->id)->where('freelancer_id', $who->id)->firstOrFail();
        };
        $pa = $make($this->freelancer, 95000);
        $pb = $make($other, 80000);

        // le client écrit à chacun ; chaque candidat n'a accès qu'à SA conversation
        $this->actingAs($this->client)->post("/espace/propositions/{$pa->id}/message", ['body' => 'Question à Kader sur les calques', 'client_key' => (string) Str::uuid()])->assertRedirect();
        $this->actingAs($this->client)->post("/espace/propositions/{$pb->id}/message", ['body' => 'Question au concurrent sur le délai', 'client_key' => (string) Str::uuid()])->assertRedirect();
        $ca = Conversation::where('proposal_id', $pa->id)->firstOrFail();
        $cb = Conversation::where('proposal_id', $pb->id)->firstOrFail();
        $this->actingAs($this->freelancer)->get("/espace/messages/{$cb->id}")->assertNotFound();
        $this->actingAs($other)->get("/espace/messages/{$ca->id}")->assertNotFound();
        $this->actingAs($this->freelancer)->get('/espace/messages?espace=freelance')->assertOk()->assertSee('Question à Kader')->assertDontSee('Question au concurrent');
        $this->actingAs($this->freelancer)->post("/espace/propositions/{$pb->id}/message", ['body' => 'intrusion', 'client_key' => (string) Str::uuid()])->assertNotFound();
        $this->actingAs($this->stranger)->post("/espace/propositions/{$pa->id}/message", ['body' => 'intrusion', 'client_key' => (string) Str::uuid()])->assertNotFound();
        $this->actingAs($this->freelancer)->post("/espace/propositions/{$pa->id}/message", ['body' => 'Réponse de Kader', 'client_key' => (string) Str::uuid()])->assertRedirect();

        // sélection de A : sa conversation rejoint la commande ; celle de B reste à part et inaccessible
        $pv = $pa->versions()->first();
        $this->actingAs($this->client)->post("/espace/missions/{$mission->id}/propositions/{$pv->id}/choisir", ['expected_version' => $mission->fresh()->row_version, 'operation_key' => (string) Str::uuid(), 'answers' => ['12', 'AutoCAD'], 'conditions' => '1'])->assertRedirect()->assertSessionHas('status');
        $order = Order::firstOrFail();
        $this->assertSame($order->id, $ca->fresh()->order_id);
        $this->assertNull($cb->fresh()->order_id);
        $this->assertSame(2, Message::where('conversation_id', $ca->id)->count(), 'contexte conservé');
        $this->actingAs($this->freelancer)->get("/commandes/{$order->reference}/messages")->assertRedirect();
        $this->actingAs($other)->get("/commandes/{$order->reference}/messages")->assertNotFound();
        $this->actingAs($other)->get("/espace/messages/{$ca->id}")->assertNotFound();
    }

    private function openMission(): Mission
    {
        $this->actingAs($this->client)->post('/espace/missions', ['title' => 'Conversion de douze plans PDF en fichiers DWG', 'category_id' => $this->service->category_id])->assertRedirect();
        $m = Mission::firstOrFail();
        $v = $m->versions()->first();
        $this->actingAs($this->client)->post("/espace/missions/{$m->id}/modifier", ['title' => $v->title, 'category_id' => $this->service->category_id, 'description' => str_repeat('Douze plans d’architecture en PDF à convertir en DWG, calques conservés. ', 3),
            'budget_xof' => '120000', 'application_deadline' => now()->addDays(5)->format('Y-m-d'), 'client_inputs' => "Nombre de plans\nVersion AutoCAD", 'revision_no' => $v->revision_no])->assertRedirect();
        $this->actingAs($this->client)->post("/espace/missions/{$m->id}/soumettre", ['revision_no' => $v->fresh()->revision_no])->assertRedirect();
        app(MissionModeration::class)->approve($this->admin, $v->id);

        return $m->refresh();
    }

    // ---------- blocage ----------

    public function test_blocking_suspends_unnecessary_exchanges_but_keeps_history_and_active_order_exchanges(): void
    {
        $c = $this->open('Avant blocage');
        $this->actingAs($this->freelancer)->post("/espace/messages/{$c->id}/bloquer")->assertRedirect()->assertSessionHas('status');
        // plus de nouveaux messages dans une conversation non indispensable (dans les deux sens), historique lisible
        $this->say($this->client, $c, 'Après blocage')->assertRedirect()->assertSessionHas('error');
        $this->say($this->freelancer, $c, 'Je réponds quand même')->assertRedirect()->assertSessionHas('error');
        $this->assertSame(1, Message::count());
        $this->actingAs($this->client)->get("/espace/messages/{$c->id}")->assertOk()->assertSee('Avant blocage')->assertSee('n’accepte plus de nouveaux messages');
        $this->actingAs($this->freelancer)->get("/espace/messages/{$c->id}?espace=freelance")->assertOk()->assertSee('Débloquer ce contact');
        // pas de nouvelle conversation non nécessaire avec ce contact
        $other = Conversation::count();
        $this->actingAs($this->client)->get("/services/{$this->service->slug}/contacter")->assertRedirect();         // l'existante : lecture seule
        $this->assertSame($other, Conversation::count());

        // une commande ACTIVE reste joignable : échanges indispensables
        $order = $this->placeOrder();
        $this->assertSame($order->id, $c->fresh()->order_id);
        $this->say($this->client, $c, 'Question sur ma commande en cours')->assertRedirect()->assertSessionHas('status');
        $this->say($this->freelancer, $c, 'Réponse sur la commande')->assertRedirect()->assertSessionHas('status');
        $this->actingAs($this->freelancer)->post("/commandes/{$order->reference}/accept", ['expected_version' => $order->fresh()->row_version, 'operation_key' => 'a1'])->assertRedirect();     // le blocage n'empêche pas la commande
        // commande annulée = terminée : le blocage reprend ses effets
        $this->actingAs($this->client)->post("/commandes/{$order->reference}/cancel", ['expected_version' => $order->fresh()->row_version, 'operation_key' => 'c1'])->assertRedirect();
        $this->say($this->client, $c, 'Après annulation')->assertRedirect()->assertSessionHas('error');
        // déblocage : retour à la normale, rien n'a été supprimé
        $this->actingAs($this->freelancer)->post("/espace/messages/{$c->id}/debloquer")->assertRedirect()->assertSessionHas('status');
        $this->say($this->client, $c, 'Après déblocage')->assertRedirect()->assertSessionHas('status');
        $this->assertSame(4, Message::count());
    }

    // ---------- journaux ----------

    public function test_message_content_never_reaches_notifications_or_technical_logs(): void
    {
        $marker = 'SECRET-'.Str::random(12);
        $log = storage_path('logs/laravel.log');
        @file_put_contents($log, '');
        $c = $this->open("Contenu privé {$marker}");
        $this->say($this->freelancer, $c, "Réponse privée {$marker}", $this->pdf('plan-secret.pdf'));
        $this->assertSame(0, DB::table('app_notifications')->where('title', 'like', "%{$marker}%")->orWhere('body', 'like', "%{$marker}%")->count());
        $this->assertStringNotContainsString($marker, (string) @file_get_contents($log));
        $this->assertStringNotContainsString('plan-secret', (string) DB::table('app_notifications')->get()->toJson());
    }

    public function test_the_send_route_is_rate_limited(): void
    {
        $c = $this->open();
        for ($i = 0; $i < 40; $i++) {
            $r = $this->say($this->client, $c, "Rafale {$i}");
            if ($r->status() === 429) {
                $this->assertGreaterThan(20, $i);

                return;
            }
        }
        $this->fail('La route d’envoi devrait être limitée.');
    }
}

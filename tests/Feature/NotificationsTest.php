<?php

namespace Tests\Feature;

use App\Livewire\UnreadBadge;
use App\Mail\NotificationMail;
use App\Modules\Accounts\Actions\GrantAdministrator;
use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Moderation\ServiceModeration;
use App\Modules\Messaging\Models\Conversation;
use App\Modules\Messaging\Models\Message;
use App\Modules\Notifications\Actions\NotificationCenter;
use App\Modules\Notifications\Actions\NotificationRouter;
use App\Modules\Notifications\Actions\Notify;
use App\Modules\Notifications\Jobs\SendNotificationEmail;
use App\Modules\Notifications\Models\AppNotification;
use App\Modules\Notifications\Support\MailStatus;
use App\Modules\Orders\Models\Delivery;
use App\Modules\Orders\Models\OrderEvent;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\OrderFixtures;
use Tests\TestCase;

/** Lot 7 : notifications internes, préférences, courriels en file avec reprises. */
class NotificationsTest extends TestCase
{
    use OrderFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private_files');
        $this->setUpParties();
        $this->useFakeScanner();
        config(['freeci.messaging.per_10_minutes' => 1000]);
    }

    private function mailOn(): void
    {
        config(['mail.default' => 'smtp', 'freeci.notifications.emails' => true]);
    }

    private function mine(User $u, ?string $type = null)
    {
        return AppNotification::query()->where('user_id', $u->id)->when($type, fn ($q) => $q->where('type', $type))->orderBy('id')->get();
    }

    // ---------- événements, liens, lecture ----------

    public function test_business_events_notify_the_right_people_with_links_that_recheck_authorizations(): void
    {
        $order = $this->placeOrder();
        $n = $this->mine($this->freelancer, 'order_requested')->first();
        $this->assertNotNull($n);
        $this->assertSame(0, $this->mine($this->client)->count(), 'l’auteur de l’action n’est pas notifié de son propre geste');
        $this->assertSame(['orders.show', ['reference' => $order->reference]], [$n->route, $n->route_params]);

        $this->accept($order)->assertRedirect();
        $a = $this->mine($this->client, 'order_accepted')->first();
        $this->assertNotNull($a);

        // centre : non lues, ouverture = lecture + redirection vers l'écran (qui revérifie les droits)
        $this->actingAs($this->freelancer)->get('/espace/notifications?espace=freelance')->assertOk()->assertSee('Nouvelle demande de prestation')->assertSee('Nouveau');
        $this->assertSame(1, app(NotificationCenter::class)->unread($this->freelancer));
        $this->actingAs($this->freelancer)->get("/notifications/{$n->id}/ouvrir")->assertRedirect(route('orders.show', $order->reference));
        $this->assertNotNull($n->fresh()->read_at);
        $this->assertSame(0, app(NotificationCenter::class)->unread($this->freelancer));

        // la notification d'autrui est introuvable ; un lien vers un dossier dont on n'est pas partie reste refusé par l'écran cible
        $this->actingAs($this->freelancer)->get("/notifications/{$a->id}/ouvrir")->assertNotFound();
        $this->actingAs($this->freelancer)->post("/notifications/{$a->id}/lire")->assertRedirect();
        $this->assertNull($a->fresh()->read_at);
        $stranger = User::factory()->create();
        DB::table('app_notifications')->insert(['user_id' => $stranger->id, 'type' => 'order_accepted', 'category' => 'essential', 'dedupe_key' => 'forge', 'title' => 'Lien forgé', 'route' => 'orders.show', 'route_params' => json_encode(['reference' => $order->reference])]);
        $forged = AppNotification::where('user_id', $stranger->id)->firstOrFail();
        $this->actingAs($stranger)->get("/notifications/{$forged->id}/ouvrir")->assertRedirect(route('orders.show', $order->reference));
        $this->actingAs($stranger)->get("/commandes/{$order->reference}")->assertNotFound();

        // tout marquer comme lu
        $this->actingAs($this->client)->post('/espace/notifications/tout-lire')->assertRedirect()->assertSessionHas('status');
        $this->assertSame(0, app(NotificationCenter::class)->unread($this->client));
    }

    public function test_delivery_correction_extension_validation_selection_and_moderation_events_all_notify(): void
    {
        $this->service->update(['delivery_requires_files' => false]);
        $order = $this->inProgress();
        $this->actingAs($this->freelancer)->post("/commandes/{$order->reference}/livraison/message", ['message' => 'Livraison complète des plans.'])->assertRedirect();
        $d = Delivery::where('state', 'draft')->firstOrFail();
        $this->actingAs($this->freelancer)->post("/commandes/{$order->reference}/livraison/soumettre", ['delivery_id' => $d->id, 'expected_version' => $order->fresh()->row_version, 'operation_key' => 'k1'])->assertRedirect();
        $this->assertSame(1, $this->mine($this->client, 'delivery_submitted')->count());
        $this->actingAs($this->client)->post("/commandes/{$order->reference}/correction", ['delivery_id' => $d->id, 'reason' => 'Le calque COTATION manque sur le plan 3.', 'expected_version' => $order->fresh()->row_version, 'operation_key' => 'k2'])->assertRedirect();
        $this->assertSame(1, $this->mine($this->freelancer, 'correction_requested')->count());
        $date = $order->fresh()->due_at->copy()->addDays(3)->format('Y-m-d');
        $this->actingAs($this->freelancer)->post("/commandes/{$order->reference}/report", ['proposed_date' => $date, 'reason' => 'Besoin de trois jours de plus.', 'expected_version' => $order->fresh()->row_version, 'operation_key' => 'k3'])->assertRedirect();
        $this->assertSame(1, $this->mine($this->client, 'extension_requested')->count());
        $ext = DB::table('extension_requests')->value('id');
        $this->actingAs($this->client)->post("/commandes/{$order->reference}/report/accepter", ['extension_id' => $ext, 'expected_version' => $order->fresh()->row_version, 'operation_key' => 'k4'])->assertRedirect();
        $this->assertSame(1, $this->mine($this->freelancer, 'extension_decided')->count());
        $this->assertSame(2, $this->mine($this->client, 'payment_confirmed')->count() + $this->mine($this->freelancer, 'payment_confirmed')->count());
        $this->assertSame(2, $this->mine($this->client, 'work_started')->count() + $this->mine($this->freelancer, 'work_started')->count());
        $this->actingAs($this->freelancer)->post("/commandes/{$order->reference}/livraison/message", ['message' => 'Nouvelle version avec calque.'])->assertRedirect();
        $d2 = Delivery::where('state', 'draft')->firstOrFail();
        $this->actingAs($this->freelancer)->post("/commandes/{$order->reference}/livraison/soumettre", ['delivery_id' => $d2->id, 'expected_version' => $order->fresh()->row_version, 'operation_key' => 'k5'])->assertRedirect();
        $this->actingAs($this->client)->post("/commandes/{$order->reference}/validation", ['delivery_id' => $d2->id, 'expected_version' => $order->fresh()->row_version, 'operation_key' => 'k6', 'confirm' => '1'])->assertRedirect();
        $this->assertSame(1, $this->mine($this->freelancer, 'order_validated')->count());

        // modération d'un service : le propriétaire est notifié
        $admin = User::factory()->create();
        app(GrantAdministrator::class)($admin, 'test');
        $this->actingAs($this->freelancer)->post('/freelance/services', ['title' => 'Mise en plan 2D complète d’un appartement', 'category_id' => $this->service->category_id])->assertRedirect();
        $s = Service::query()->orderByDesc('created_at')->orderByDesc('id')->firstOrFail();
        $v = $s->versions()->first();
        $this->actingAs($this->freelancer)->post("/freelance/services/{$s->id}/modifier", ['title' => $v->title, 'category_id' => $this->service->category_id, 'summary' => 'Plans cotés au format PDF et DWG à partir de vos relevés.',
            'scope' => str_repeat('Un logement jusqu’à 120 m², relevés fournis par le client. ', 4), 'price_xof' => '45000', 'delivery_days' => '6', 'revisions_included' => '2', 'deliverables' => 'Un plan PDF', 'delivery_mode' => 'message', 'revision_no' => $v->revision_no])->assertRedirect();
        $this->actingAs($this->freelancer)->post("/freelance/services/{$s->id}/soumettre", ['revision_no' => $v->fresh()->revision_no])->assertRedirect();
        app(ServiceModeration::class)->requestChanges($admin, $v->id, 'Précisez les formats de fichiers remis.');
        $this->assertSame(1, $this->mine($this->freelancer, 'moderation_decision')->count());
        $this->assertStringContainsString('correction demandée', $this->mine($this->freelancer, 'moderation_decision')->first()->title);
    }

    // ---------- doublons ----------

    public function test_a_repeated_business_event_never_creates_a_second_notification(): void
    {
        $order = $this->placeOrder();
        $event = OrderEvent::where('order_id', $order->id)->where('type', 'requested')->firstOrFail();
        $router = app(NotificationRouter::class);
        $router->order($event);
        $router->order($event);
        $this->assertSame(1, $this->mine($this->freelancer, 'order_requested')->count());
        $notify = app(Notify::class);
        $this->assertTrue($notify($this->client->id, 'order_accepted', 'manuel:1', 'Titre', null, 'orders.show', ['reference' => $order->reference]));
        $this->assertFalse($notify($this->client->id, 'order_accepted', 'manuel:1', 'Titre', null, 'orders.show', ['reference' => $order->reference]));
        // une commande rejouée avec la même clé n'ajoute ni historique ni notification
        $key = (string) Str::uuid();
        $v = $order->fresh()->row_version;
        $this->accept($order, $key, $v)->assertRedirect();
        $this->accept($order, $key, $v)->assertRedirect();
        $this->assertSame(1, $this->mine($this->client, 'order_accepted')->where('dedupe_key', '!=', 'manuel:1')->count());
        $this->dbRefusesDuplicate();
    }

    private function dbRefusesDuplicate(): void
    {
        $n = AppNotification::firstOrFail();
        try {
            DB::transaction(fn () => DB::table('app_notifications')->insert(['user_id' => $n->user_id, 'type' => $n->type, 'category' => $n->category, 'dedupe_key' => $n->dedupe_key, 'title' => 'x', 'route' => 'orders.show']));
            $this->fail('doublon accepté par la base');
        } catch (QueryException) {
            $this->assertTrue(true);
        }
    }

    public function test_unread_messages_are_grouped_in_one_notification_without_their_content(): void
    {
        $this->actingAs($this->client)->post("/services/{$this->service->slug}/contacter", ['body' => 'Premier message secret ABC', 'client_key' => (string) Str::uuid()])->assertRedirect();
        $c = Conversation::firstOrFail();
        foreach (['Deuxième secret DEF', 'Troisième secret GHI'] as $t) {
            $this->actingAs($this->client)->post("/espace/messages/{$c->id}", ['body' => $t, 'client_key' => (string) Str::uuid()]);
        }
        $n = $this->mine($this->freelancer, 'message_received');
        $this->assertCount(1, $n);
        $this->assertSame([3, '3 nouveaux messages de Fanta Client'], [$n[0]->count, $n[0]->title]);
        $this->assertStringNotContainsString('secret', json_encode($n[0]->toArray()));
        $this->actingAs($this->freelancer)->get("/espace/messages/{$c->id}")->assertOk();
        $this->actingAs($this->freelancer)->get("/notifications/{$n[0]->id}/ouvrir")->assertRedirect(route('messages.show', ['conversation' => $c->id]));
        $this->actingAs($this->client)->post("/espace/messages/{$c->id}", ['body' => 'Quatrième', 'client_key' => (string) Str::uuid()]);
        $this->assertCount(2, $this->mine($this->freelancer, 'message_received'), 'une fois lue, un nouveau message crée une nouvelle notification');
    }

    // ---------- préférences ----------

    public function test_optional_preferences_apply_but_essential_notifications_cannot_be_disabled(): void
    {
        $this->actingAs($this->freelancer)->get('/espace/notifications/preferences?espace=freelance')->assertOk()->assertSee('Notifications facultatives')->assertSee('Notifications indispensables')->assertSee('Nouvelle demande de prestation')
            ->assertSee('L’envoi de courriels n’est pas configuré');
        // tentative de désactiver un type indispensable : ignorée
        $this->actingAs($this->freelancer)->post('/espace/notifications/preferences', ['pref' => ['order_requested' => ['in_app' => '0'], 'message_received' => ['in_app' => '0', 'email' => '0'], 'proposal_received' => ['in_app' => '1']]])->assertRedirect()->assertSessionHas('status');
        $this->assertSame(0, DB::table('notification_preferences')->where('type', 'order_requested')->count());
        $this->placeOrder();
        $this->assertSame(1, $this->mine($this->freelancer, 'order_requested')->count());
        // message désactivé dans l'application : aucune notification ; la conversation fonctionne
        $this->actingAs($this->client)->post("/services/{$this->service->slug}/contacter", ['body' => 'Bonjour', 'client_key' => (string) Str::uuid()])->assertRedirect()->assertSessionHas('status');
        $this->assertSame(0, $this->mine($this->freelancer, 'message_received')->count());
        $this->assertSame(1, Message::count());
    }

    // ---------- courriels : honnêteté, file, reprises ----------

    public function test_without_real_mail_nothing_is_queued_or_presented_as_sent(): void
    {
        Queue::fake();
        $this->assertFalse(MailStatus::deliverable());
        $this->placeOrder();
        $n = $this->mine($this->freelancer, 'order_requested')->first();
        $this->assertSame('unavailable', $n->email_state);
        Queue::assertNothingPushed();
        $this->actingAs($this->freelancer)->get('/espace/notifications?espace=freelance')->assertOk()->assertSee('Nouvelle demande');            // l'application fonctionne sans courrier
        $this->assertSame(0, Artisan::call('freeci:notifications:status'));
        $out = Artisan::output();
        $this->assertStringContainsString('NON', $out);
        $this->assertSame(0, DB::table('app_notifications')->where('email_state', 'sent')->count());
        $this->assertSame(0, Artisan::call('freeci:notifications:retry', ['--failed' => true]));
        $this->assertStringContainsString('Courrier non configuré', Artisan::output());
    }

    public function test_emails_are_queued_for_essential_events_only_by_default_and_never_carry_private_content(): void
    {
        $this->mailOn();
        Queue::fake();
        $this->actingAs($this->client)->post("/services/{$this->service->slug}/contacter", ['body' => 'Contenu privé NE-PAS-ENVOYER', 'client_key' => (string) Str::uuid()])->assertRedirect();
        Queue::assertNothingPushed();                                                       // message : facultatif, courriel désactivé par défaut
        $order = $this->placeOrder();
        Queue::assertPushed(SendNotificationEmail::class, 1);
        $n = $this->mine($this->freelancer, 'order_requested')->first();
        $this->assertSame('pending', $n->email_state);

        // exécution de la tâche : le serveur de courrier accepte → « envoyé » ; le contenu reste générique
        Queue::fake([]);
        Mail::fake();
        (new SendNotificationEmail($n->id))->handle();
        Mail::assertSent(NotificationMail::class, function (NotificationMail $m) {
            $html = $m->render();
            $this->assertStringNotContainsString('NE-PAS-ENVOYER', $html);
            $this->assertStringContainsString('Ce courriel ne contient volontairement ni message privé ni pièce jointe', $html);

            return $m->hasTo($this->freelancer->email);
        });
        $n->refresh();
        $this->assertSame(['sent', 1], [$n->email_state, $n->email_attempts]);
        (new SendNotificationEmail($n->id))->handle();                                    // rejouée : aucun second courriel
        Mail::assertSent(NotificationMail::class, 1);

        // optionnel activé par l'utilisateur : courriel prévu pour le message suivant
        $this->actingAs($this->freelancer)->post('/espace/notifications/preferences', ['pref' => ['message_received' => ['in_app' => '1', 'email' => '1']]])->assertRedirect();
        DB::table('app_notifications')->where('user_id', $this->freelancer->id)->update(['read_at' => now()]);   // le groupement ne s'applique qu'aux non-lus
        Queue::fake();
        $c = Conversation::firstOrFail();
        $this->actingAs($this->client)->post("/espace/messages/{$c->id}", ['body' => 'Encore un message', 'client_key' => (string) Str::uuid()]);
        Queue::assertPushed(SendNotificationEmail::class, 1);
        $this->assertNotNull($order);
    }

    public function test_a_failing_mail_server_is_retried_then_marked_failed_and_can_be_requeued_without_secrets(): void
    {
        $this->mailOn();
        Queue::fake();
        $this->placeOrder();
        $n = $this->mine($this->freelancer, 'order_requested')->first();
        $job = new SendNotificationEmail($n->id);
        $this->assertSame(5, $job->tries);
        $this->assertSame([60, 300, 900, 3600], $job->backoff());

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP password=hunter2 refused'));
        try {
            $job->handle();
            $this->fail('échec attendu');
        } catch (\RuntimeException $e) {
            $this->assertSame(1, $n->fresh()->email_attempts);
        }
        $job->failed($e);                                                                  // reprises épuisées
        $n->refresh();
        $this->assertSame(['failed', 'transport_error'], [$n->email_state, $n->email_error]);
        $this->assertStringNotContainsString('hunter2', json_encode($n->toArray()), 'jamais le message du serveur ni de secret');
        $this->actingAs($this->freelancer)->get('/espace/notifications?espace=freelance')->assertOk()->assertSee('Nouvelle demande');   // l'application n'est pas affectée

        // reprise manuelle : remise en file
        $this->assertSame(0, Artisan::call('freeci:notifications:retry', ['--failed' => true]));
        $this->assertSame('pending', $n->fresh()->email_state);
        Queue::assertPushed(SendNotificationEmail::class, 2);
        // une notification déjà lue n'est plus envoyée
        $n->forceFill(['read_at' => now()])->save();
        Mail::swap(new MailManager($this->app));
        Mail::fake();
        (new SendNotificationEmail($n->id))->handle();
        Mail::assertNothingSent();
        $this->assertSame('none', $n->fresh()->email_state);
    }

    public function test_the_scheduler_requeues_stale_emails_and_can_drain_the_queue(): void
    {
        $this->assertSame(0, Artisan::call('schedule:list'));
        $this->assertStringContainsString('freeci:notifications:retry --stale', Artisan::output());
        $this->assertStringNotContainsString('queue:work', Artisan::output(), 'le vidage par le planificateur est optionnel (FREECI_QUEUE_VIA_SCHEDULER)');
        $this->mailOn();
        Queue::fake();
        $this->placeOrder();
        $n = $this->mine($this->freelancer, 'order_requested')->first();
        DB::table('app_notifications')->where('id', $n->id)->update(['updated_at' => now()->subMinutes(30)]);
        Queue::fake();
        $this->assertSame(0, Artisan::call('freeci:notifications:retry', ['--stale' => true]));
        Queue::assertPushed(SendNotificationEmail::class, 1);
    }

    public function test_the_unread_badge_counts_notifications_and_the_center_is_private(): void
    {
        $this->placeOrder();
        Livewire::actingAs($this->freelancer)->test(UnreadBadge::class, ['kind' => 'notifications'])->assertSee('count-badge', false);
        Livewire::actingAs($this->client)->test(UnreadBadge::class, ['kind' => 'notifications'])->assertDontSee('count-badge', false);
        auth()->forgetGuards();
        $this->get('/espace/notifications')->assertRedirect('/connexion');
        $this->actingAs($this->client)->get('/espace/notifications')->assertOk()->assertSee('Aucune notification');
    }
}

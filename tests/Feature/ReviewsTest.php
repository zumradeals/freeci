<?php

namespace Tests\Feature;

use App\Modules\Accounts\Actions\GrantAdministrator;
use App\Modules\Accounts\Models\User;
use App\Modules\Accounts\Security\Totp;
use App\Modules\Accounts\Security\TwoFactor;
use App\Modules\Admin\Actions\ModerateReviews;
use App\Modules\Orders\Actions\RespondToReview;
use App\Modules\Orders\Actions\SubmitReview;
use App\Modules\Orders\Exceptions\OrderForbidden;
use App\Modules\Orders\Exceptions\ReviewConflict;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Queries\ReviewQueries;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\AdminFixtures;
use Tests\Support\OrderFixtures;
use Tests\TestCase;

/**
 * Lot 12 — avis. Éligibilité et unicité, exclusion du sandbox de la réputation publique, réponses, signalement, modération sans réécriture, agrégats par origine.
 * Tests locaux : les commandes sont amenées à l'état « clôturée, validée par le client » par écriture directe (le cycle de livraison est testé ailleurs).
 */
class ReviewsTest extends TestCase
{
    use AdminFixtures, OrderFixtures, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private_files');
        $this->setUpParties();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->admin = $this->readyAdmin(['name' => 'Admin Un']);
    }

    /** Commande clôturée après validation du client. `$live` : commande RÉELLE (sinon test/sandbox). `$explicit` : validée par le client (sinon par décision du support). */
    private function closed(bool $live = true, bool $explicit = true): Order
    {
        $o = $this->inProgress();
        if ($live) {
            DB::statement('ALTER TABLE orders DISABLE TRIGGER orders_environment_fixed');
            DB::table('orders')->where('id', $o->id)->update(['environment' => 'live']);
            DB::statement('ALTER TABLE orders ENABLE TRIGGER orders_environment_fixed');
        }
        DB::table('orders')->where('id', $o->id)->update(['state' => 'closed', 'closure_reason' => 'validated', 'closed_at' => now(), 'validated_at' => now()]);
        DB::table('order_events')->insert(['order_id' => $o->id, 'type' => $explicit ? 'validated' : 'dispute_validated', 'actor_id' => $explicit ? $this->client->id : $this->admin->id, 'occurred_at' => now()]);

        return $o->fresh();
    }

    private function submit(Order $o, int $rating = 5, string $comment = 'Travail soigné, livré dans les délais annoncés.', ?string $key = null)
    {
        return $this->actingAs($this->client)->post("/commandes/{$o->reference}/avis", ['rating' => $rating, 'comment' => $comment, 'operation_key' => $key ?? (string) Str::uuid()]);
    }

    private function review(Order $o): ?object
    {
        return DB::table('reviews')->where('order_id', $o->id)->first();
    }

    // ---------------------------------------------------------------- éligibilité et unicité

    public function test_only_the_client_of_an_explicitly_validated_and_closed_order_can_review_once(): void
    {
        // commande non clôturée : refusée
        $open = $this->inProgress();
        $this->submit($open)->assertSessionHas('error');
        $this->assertSame(0, DB::table('reviews')->count());

        $o = $this->closed();
        // ni le freelance, ni un tiers, ni un administrateur ne peuvent déposer un avis à la place du client
        $this->actingAs($this->freelancer)->post("/commandes/{$o->reference}/avis", ['rating' => 5, 'comment' => 'Avis du freelance sur lui-même.', 'operation_key' => 'f1'])->assertNotFound();
        $this->actingAs(User::factory()->create())->post("/commandes/{$o->reference}/avis", ['rating' => 5, 'comment' => 'Avis d’un tiers quelconque ici.', 'operation_key' => 't1'])->assertNotFound();
        $this->asAdmin($this->admin)->post("/commandes/{$o->reference}/avis", ['rating' => 1, 'comment' => 'Avis créé par un administrateur.', 'operation_key' => 'a1'])->assertNotFound();
        $this->assertSame(0, DB::table('reviews')->count());
        // validations : note 1–5, commentaire borné
        $this->submit($o, 6)->assertSessionHasErrors('rating');
        $this->submit($o, 4, 'court')->assertSessionHas('error');
        $this->submit($o, 4, str_repeat('x', 1501))->assertSessionHas('error');
        // dépôt valide, puis un seul avis par commande (même avec une autre clé d'opération)
        $this->submit($o, 4)->assertRedirect(route('orders.review', $o->reference))->assertSessionHas('status');
        $r = $this->review($o);
        $this->assertSame([4, $this->client->id, $this->freelancer->id, 'service', $this->service->id, true], [(int) $r->rating, $r->author_id, $r->subject_id, $r->origin, $r->service_id, (bool) $r->counts_public]);
        $this->submit($o, 5, 'Un second avis sur la même commande.')->assertSessionHas('error');
        $this->assertSame(1, DB::table('reviews')->count());
        // le rejeu de la même opération est idempotent
        $this->submit($o, 4, 'Travail soigné, livré dans les délais annoncés.', 'meme-cle');
        $this->assertSame(1, DB::table('reviews')->count());
    }

    public function test_a_validation_decided_by_support_opens_no_review(): void
    {
        $o = $this->closed(true, false);
        $this->submit($o)->assertSessionHas('error');
        $this->assertSame(0, DB::table('reviews')->count());
        $this->actingAs($this->client)->get("/commandes/{$o->reference}/avis")->assertOk()->assertSee('validée par une décision du support');
    }

    public function test_rating_and_comment_are_immutable_for_everyone_including_the_database(): void
    {
        $o = $this->closed();
        $this->submit($o, 2, 'Avis sévère mais motivé par la livraison.');
        $id = $this->review($o)->id;
        foreach (['rating' => 5, 'comment' => 'Texte réécrit par un administrateur ici.', 'author_id' => $this->freelancer->id, 'visible_at' => now()->subYear()] as $col => $val) {
            try {
                DB::transaction(fn () => DB::table('reviews')->where('id', $id)->update([$col => $val]));
                $this->fail("{$col} modifiable");
            } catch (QueryException $e) {
                $this->assertStringContainsString('ne peuvent pas être modifiés', $e->getMessage());
            }
        }
        $this->expectException(QueryException::class);
        DB::table('reviews')->where('id', $id)->delete();
    }

    // ---------------------------------------------------------------- publication différée, bac à sable exclu

    public function test_a_real_review_becomes_public_after_the_provisional_delay_and_feeds_the_real_average(): void
    {
        $o = $this->closed();
        $this->submit($o, 4);
        $slug = $this->service->slug;
        // avant la date de publication : invisible partout, aucune moyenne
        $this->get("/services/{$slug}")->assertOk()->assertSee('Aucun avis pour l’instant')->assertDontSee('Travail soigné');
        $this->assertSame([], app(ReviewQueries::class)->forServices([$this->service->id]));
        $this->assertEqualsWithDelta(14 * 86400, strtotime($this->review($o)->visible_at) - strtotime($o->fresh()->closed_at), 5, 'clôture + 14 jours (provisoire)');
        $this->actingAs($this->freelancer)->get("/commandes/{$o->reference}/avis")->assertOk()->assertDontSee('Travail soigné');          // le freelance ne voit pas non plus avant publication

        // le délai est FIGÉ au dépôt : changer le paramètre n'affecte pas l'avis existant
        config(['freeci.reviews.publication_days' => 1]);
        $this->travel(15)->days();
        $page = $this->get("/services/{$slug}")->assertOk()->assertSee('Travail soigné')->assertSee('4,0')->assertSee('1 avis')->assertSee('Client d’une commande validée');
        $this->assertSame(['count' => 1, 'avg' => '4,0'], app(ReviewQueries::class)->forServices([$this->service->id])[$this->service->id]);
        $this->get('/services')->assertSee('4,0');                                                   // note réelle sur la carte du catalogue
        $profile = DB::table('freelance_profiles')->where('user_id', $this->freelancer->id)->value('slug');
        $this->get("/freelances/{$profile}")->assertOk()->assertSee('Travail soigné')->assertSee('Issus d’un service')->assertSee('1 avis');
        $this->assertTrue($page->isOk());
        // notification idempotente du freelance
        Artisan::call('freeci:reviews:publish');
        Artisan::call('freeci:reviews:publish');
        $this->assertSame(1, DB::table('app_notifications')->where('user_id', $this->freelancer->id)->where('type', 'review_published')->count());
    }

    public function test_sandbox_orders_never_produce_a_public_review_nor_any_reputation_effect(): void
    {
        $o = $this->closed(false);                                                     // commande de TEST
        $this->actingAs($this->client)->get("/commandes/{$o->reference}/avis")->assertOk()->assertSee('aperçu uniquement')->assertSee('jamais public');
        $this->submit($o, 1, 'Aperçu de test : tout s’est mal passé ici.');
        $r = $this->review($o);
        $this->assertFalse((bool) $r->counts_public);
        $this->travel(60)->days();
        $slug = $this->service->slug;
        $this->get("/services/{$slug}")->assertSee('Aucun avis pour l’instant')->assertDontSee('tout s’est mal passé');
        $this->assertSame([], app(ReviewQueries::class)->forServices([$this->service->id]));
        $this->assertSame([], app(ReviewQueries::class)->forProfiles([DB::table('freelance_profiles')->value('id')]));
        $this->get('/services?tri=mieux-notes')->assertOk()->assertDontSee('★');
        // l'aperçu reste visible des deux parties, clairement séparé, et le freelance peut y répondre (test) sans rien publier
        $this->actingAs($this->freelancer)->get("/commandes/{$o->reference}/avis")->assertOk()->assertSee('Aperçu de test')->assertSee('tout s’est mal passé');
        $this->actingAs($this->freelancer)->post("/avis/{$r->id}/reponse", ['reference' => $o->reference, 'body' => 'Réponse de test à l’aperçu.'])->assertSessionHas('status');
        $this->get("/services/{$slug}")->assertDontSee('Réponse de test');
        Artisan::call('freeci:reviews:publish');
        $this->assertSame(0, DB::table('app_notifications')->where('type', 'review_published')->count());
        // une commande ANTÉRIEURE à l'environnement explicite (legacy) n'est jamais publique non plus
        $this->assertFalse(app(ReviewQueries::class)->forProfiles([DB::table('freelance_profiles')->value('id')]) !== []);
    }

    // ---------------------------------------------------------------- réponse

    public function test_only_the_reviewed_freelancer_can_reply_once_and_only_when_the_review_is_visible(): void
    {
        $o = $this->closed();
        $this->submit($o, 3, 'Prestation correcte, quelques retards constatés.');
        $id = $this->review($o)->id;
        $body = ['reference' => $o->reference, 'body' => 'Merci pour votre retour, nous avons ajusté nos délais.'];
        // pas avant la publication
        $this->actingAs($this->freelancer)->post("/avis/{$id}/reponse", $body)->assertSessionHas('error');
        $this->travel(15)->days();
        // ni le client, ni un autre freelance, ni un administrateur
        $this->actingAs($this->client)->post("/avis/{$id}/reponse", $body)->assertNotFound();
        $other = User::factory()->create();
        $this->actingAs($other)->post("/avis/{$id}/reponse", $body)->assertNotFound();
        $this->asAdmin($this->admin)->post("/avis/{$id}/reponse", $body)->assertNotFound();
        $this->actingAs($this->freelancer)->post("/avis/{$id}/reponse", ['reference' => $o->reference, 'body' => 'court'])->assertSessionHas('error');
        $this->actingAs($this->freelancer)->post("/avis/{$id}/reponse", $body)->assertRedirect()->assertSessionHas('status');
        $this->actingAs($this->freelancer)->post("/avis/{$id}/reponse", $body)->assertSessionHas('error');          // une seule réponse
        $this->assertSame(1, DB::table('review_responses')->count());
        $this->get('/services/'.$this->service->slug)->assertSee('Réponse du freelance')->assertSee('nous avons ajusté nos délais');
        try {
            DB::transaction(fn () => DB::table('review_responses')->update(['body' => 'Réponse modifiée par la suite ici.']));
            $this->fail('réponse modifiable');
        } catch (QueryException $e) {
            $this->assertStringContainsString('ne peut pas être modifiée', $e->getMessage());
        }
    }

    // ---------------------------------------------------------------- origine service / mission

    public function test_reviews_from_a_mission_never_count_toward_a_service_and_are_labelled_on_the_profile(): void
    {
        $svc = $this->closed();
        $this->submit($svc, 5, 'Excellent travail sur ce service précis.');
        // seconde commande : convertie en commande issue d'une MISSION
        $m = $this->closed();
        $mid = (string) Str::uuid();
        DB::table('missions')->insert(['id' => $mid, 'client_id' => $this->client->id, 'slug' => 'mission-test-'.Str::random(6), 'status' => 'closed', 'created_at' => now(), 'updated_at' => now()]);
        DB::statement('ALTER TABLE orders DROP CONSTRAINT orders_origin_chk');            // le rattachement à une proposition est testé au lot 6 ; ici seule l'origine compte
        DB::table('orders')->where('id', $m->id)->update(['origin' => 'mission', 'mission_id' => $mid, 'service_id' => null]);
        $this->submit($m->fresh(), 1, 'Mission décevante sur la livraison finale.');
        $this->assertSame(['mission', null, $mid], [DB::table('reviews')->where('order_id', $m->id)->value('origin'), DB::table('reviews')->where('order_id', $m->id)->value('service_id'), DB::table('reviews')->where('order_id', $m->id)->value('mission_id')]);

        $this->travel(15)->days();
        $profileId = DB::table('freelance_profiles')->value('id');
        $q = app(ReviewQueries::class);
        $this->assertSame(['count' => 1, 'avg' => '5,0'], $q->forServices([$this->service->id])[$this->service->id], 'le service ne compte que ses propres avis');
        $p = $q->forProfiles([$profileId])[$profileId];
        $this->assertSame([2, '3,0', 1, '5,0', 1, '1,0'], [$p['count'], $p['avg'], $p['service']['count'], $p['service']['avg'], $p['mission']['count'], $p['mission']['avg']]);
        $this->get('/services/'.$this->service->slug)->assertSee('Excellent travail')->assertDontSee('Mission décevante');
        $slug = DB::table('freelance_profiles')->value('slug');
        $this->get("/freelances/{$slug}")->assertSee('Excellent travail')->assertSee('Mission décevante')->assertSee('À la suite d’une mission')->assertSee('Issus d’une mission')->assertSee('1 avis');
    }

    // ---------------------------------------------------------------- signalement et modération

    public function test_a_public_review_can_be_reported_and_an_admin_hides_and_restores_it_with_reason_and_history_without_rewriting_it(): void
    {
        $o = $this->closed();
        $this->submit($o, 1, 'Avis très négatif mais strictement factuel ici.');
        $id = $this->review($o)->id;
        $this->travel(15)->days();
        $slug = $this->service->slug;
        $this->get("/services/{$slug}")->assertSee('Avis très négatif');
        // signalement : réservé aux utilisateurs connectés, seulement le contenu PUBLIC, jamais son propre contenu
        $this->actingAs($this->freelancer)->get("/espace/assistance/signaler/review/{$id}")->assertOk();
        $this->actingAs($this->freelancer)->post("/espace/assistance/signaler/review/{$id}", ['reason' => 'abuse', 'body' => 'Ce commentaire contient des propos insultants.', 'operation_key' => (string) Str::uuid()])->assertRedirect();
        $this->assertSame(1, DB::table('support_cases')->where('kind', 'report')->where('target_type', 'review')->where('target_id', $id)->count());
        $this->actingAs($this->client)->post("/espace/assistance/signaler/review/{$id}", ['reason' => 'abuse', 'body' => 'Je signale mon propre avis ici.', 'operation_key' => (string) Str::uuid()])->assertSessionHas('error');

        // modération : administrateur seulement, confirmation récente, catégorie obligatoire (une note négative n'en est pas une), motif
        $this->actingAs($this->client)->get('/admin/avis')->assertNotFound();
        $this->asAdmin($this->admin)->get('/admin/avis')->assertOk()->assertSee('Une note négative, seule, n’est pas un motif de retrait')->assertSee('1 signalement');
        $this->asAdmin($this->admin, false)->post("/admin/avis/{$id}/masquer", ['target' => 'review', 'category' => 'abuse', 'reason' => 'Propos insultants confirmés après lecture.'])->assertRedirect();
        $this->assertNull(DB::table('reviews')->where('id', $id)->value('hidden_at'), 'sans confirmation récente : refusé');
        $this->asAdmin($this->admin)->post("/admin/avis/{$id}/masquer", ['target' => 'review', 'category' => 'note_negative', 'reason' => 'La note est trop basse à mon goût.'])->assertSessionHas('error');
        $this->asAdmin($this->admin)->post("/admin/avis/{$id}/masquer", ['target' => 'review', 'category' => 'abuse', 'reason' => 'court'])->assertSessionHasErrors('reason');
        $before = DB::table('reviews')->where('id', $id)->first(['rating', 'comment']);
        $this->asAdmin($this->admin)->post("/admin/avis/{$id}/masquer", ['target' => 'review', 'category' => 'abuse', 'reason' => 'Propos insultants confirmés après lecture.'])->assertSessionHas('status');
        $this->assertNotNull(DB::table('reviews')->where('id', $id)->value('hidden_at'));
        $this->assertEquals($before, DB::table('reviews')->where('id', $id)->first(['rating', 'comment']), 'ni la note ni le commentaire ne sont réécrits');
        // masqué : disparu du public, des moyennes et des signalements ; les agrégats se recalculent
        $this->get("/services/{$slug}")->assertDontSee('Avis très négatif')->assertSee('Aucun avis pour l’instant');
        $this->assertSame([], app(ReviewQueries::class)->forServices([$this->service->id]));
        $this->assertSame(1, DB::table('review_moderations')->where('review_id', $id)->where('action', 'hidden')->count());
        $this->assertSame(1, DB::table('app_notifications')->where('user_id', $this->client->id)->where('type', 'moderation_decision')->count());
        $this->assertSame(0, DB::table('app_notifications')->where('user_id', $this->client->id)->where('body', 'like', '%insultants confirmés%')->count(), 'le motif interne n’est pas communiqué');
        // rétablissement tracé
        $this->asAdmin($this->admin)->post("/admin/avis/{$id}/retablir", ['target' => 'review', 'reason' => 'Après réexamen, le contenu respecte les règles.'])->assertSessionHas('status');
        $this->get("/services/{$slug}")->assertSee('Avis très négatif');
        $this->assertSame(['count' => 1, 'avg' => '1,0'], app(ReviewQueries::class)->forServices([$this->service->id])[$this->service->id]);
        $this->assertSame(['hidden', 'restored'], DB::table('review_moderations')->where('review_id', $id)->orderBy('id')->pluck('action')->all());
        // l'historique est en ajout seul
        $this->expectException(QueryException::class);
        DB::table('review_moderations')->delete();
    }

    public function test_a_reply_can_be_hidden_separately_and_an_admin_cannot_moderate_their_own_content(): void
    {
        $o = $this->closed();
        $this->submit($o, 5, 'Très bonne prestation, je recommande vivement.');
        $id = $this->review($o)->id;
        $this->travel(15)->days();
        app(RespondToReview::class)($this->freelancer, $id, 'Merci beaucoup pour cette appréciation sincère.');
        $mod = app(ModerateReviews::class);
        $mod->hide($this->admin, $id, 'reply', 'personal_data', 'La réponse contient un numéro de téléphone privé.');
        $slug = $this->service->slug;
        $this->get("/services/{$slug}")->assertSee('Très bonne prestation')->assertDontSee('Merci beaucoup pour cette appréciation');
        $this->assertSame(['count' => 1, 'avg' => '5,0'], app(ReviewQueries::class)->forServices([$this->service->id])[$this->service->id], 'masquer une réponse ne change pas la note');
        // l'administrateur auteur de l'avis ne peut pas le modérer
        $this->client->update(['name' => 'Client Admin']);
        app(GrantAdministrator::class)($this->client, 'test');
        $mfa = app(TwoFactor::class);
        $mfa->confirm($this->client, Totp::code($mfa->begin($this->client)['secret'], Totp::step()));
        DB::table('users')->where('id', $this->client->id)->update(['email_verified_at' => now()]);
        $this->expectException(ReviewConflict::class);
        $mod->hide($this->client->fresh(), $id, 'review', 'abuse', 'Je masque mon propre avis par erreur ici.');
    }

    public function test_admin_cannot_submit_through_the_action_either(): void
    {
        $o = $this->closed();
        $this->expectException(OrderForbidden::class);
        app(SubmitReview::class)($this->admin, $o->reference, 5, 'Avis écrit par un administrateur à la place.', 'k');
    }
}

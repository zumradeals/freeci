<?php

namespace Tests\Feature;

use App\Modules\Accounts\Actions\GrantAdministrator;
use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Actions\ServiceAuthoring;
use App\Modules\Catalog\Exceptions\ModerationDenied;
use App\Modules\Catalog\Exceptions\ServiceStateConflict;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\FreelanceProfile;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\ServiceVersion;
use App\Modules\Catalog\Moderation\ServiceModeration;
use App\Modules\Catalog\Support\ImageProcessor;
use App\Modules\Orders\Models\Delivery;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\OrderFixtures;
use Tests\TestCase;

/** Lot 5 : profil freelance, services versionnés, modération, médias. */
class ServiceManagementTest extends TestCase
{
    use OrderFixtures, RefreshDatabase;

    private Category $category;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private_files');
        $this->setUpParties();
        $this->category = $this->service->category;
        $this->admin = User::factory()->create(['name' => 'Moderateur']);
        app(GrantAdministrator::class)($this->admin, 'test');
    }

    private function form(array $o = []): array
    {
        return array_merge([
            'title' => 'Mise en plan 2D complète d’un appartement', 'category_id' => $this->category->id,
            'summary' => 'Plans cotés au format PDF et DWG à partir de vos relevés.',
            'scope' => str_repeat('Un logement jusqu’à 120 m², relevés fournis par le client, une reprise comprise. ', 3),
            'price_xof' => '45 000', 'delivery_days' => '6', 'revisions_included' => '2',
            'deliverables' => "Un plan 2D coté (PDF)\nUn fichier DWG", 'exclusions' => 'Plans de structure', 'client_inputs' => "Surface approximative\nNombre de pièces",
            'delivery_mode' => 'message', 'intent' => 'save',
        ], $o);
    }

    /** Crée un brouillon complet pour $owner et retourne le service. */
    private function draft(?User $owner = null, array $o = []): Service
    {
        $owner ??= $this->freelancer;
        $this->actingAs($owner)->post('/freelance/services', ['title' => $o['title'] ?? 'Mise en plan 2D complète d’un appartement', 'category_id' => $this->category->id])->assertRedirect();
        $service = Service::query()->orderByDesc('created_at')->orderByDesc('id')->firstOrFail();
        $v = $service->versions()->first();
        $this->actingAs($owner)->post("/freelance/services/{$service->id}/modifier", $this->form($o) + ['revision_no' => $v->revision_no])->assertRedirect()->assertSessionHas('status');

        return $service->refresh();
    }

    /** L’image de couverture est obligatoire : on en attache une (sans passer par le téléversement) quand le test ne la teste pas. */
    private function withCover(ServiceVersion $v): void
    {
        if (count($v->images) < 1) {
            $v->forceFill(['images' => [['id' => (string) Str::uuid(), 'alt' => 'Plan d’étage coté', 'caption' => '']]])->save();
        }
    }

    private function submit(Service $s, ?User $owner = null)
    {
        $v = $s->versions()->whereIn('state', ServiceVersion::OPEN)->firstOrFail();
        $this->withCover($v);

        return $this->actingAs($owner ?? $this->freelancer)->post("/freelance/services/{$s->id}/soumettre", ['revision_no' => $v->revision_no]);
    }

    private function publish(Service $s): Service
    {
        $this->submit($s)->assertRedirect()->assertSessionHas('status');
        $v = $s->versions()->where('state', 'in_review')->firstOrFail();
        app(ServiceModeration::class)->approve($this->admin, $v->id);

        return $s->refresh();
    }

    private function pngUpload(string $name = 'photo.png', int $w = 800, int $h = 600): UploadedFile
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 30, 90, 160));
        ob_start();
        imagepng($im);

        return UploadedFile::fake()->createWithContent($name, (string) ob_get_clean());
    }

    // ---------- profil ----------

    public function test_profile_is_edited_by_its_owner_and_public_data_is_separate_from_private_data(): void
    {
        $this->freelancer->freelanceProfile->update(['published_at' => null, 'bio' => null, 'skills' => []]);
        $this->actingAs($this->freelancer)->post('/freelance/profil', ['display_name' => 'Kader Soro', 'headline' => 'Dessinateur DAO', 'city' => 'Abidjan', 'bio' => str_repeat('Dessinateur depuis dix ans. ', 4), 'skills' => 'AutoCAD, Revit ; Mise en plan, autocad'])->assertRedirect()->assertSessionHas('status');
        $p = $this->freelancer->freelanceProfile->fresh();
        $this->assertSame(['AutoCAD', 'Revit', 'Mise en plan', 'autocad'], $p->skills);
        $this->assertNotNull($p->slug);

        // publication refusée tant que le profil est incomplet, puis acceptée
        $p->update(['bio' => 'trop court']);
        $this->actingAs($this->freelancer)->post('/freelance/profil/publier')->assertSessionHasErrors('profile');
        $this->assertNull($p->fresh()->published_at);
        $p->update(['bio' => str_repeat('Dessinateur depuis dix ans. ', 4)]);
        $this->actingAs($this->freelancer)->post('/freelance/profil/publier')->assertRedirect()->assertSessionHas('status');
        $this->assertNotNull($p->fresh()->published_at);

        $page = $this->get('/freelances/'.$p->slug)->assertOk();
        $page->assertSee('Kader Soro')->assertSee('Dessinateur depuis dix ans')->assertSee('AutoCAD')->assertDontSee($this->freelancer->email);
        $this->actingAs($this->freelancer)->get('/freelance/profil')->assertOk()->assertSee('Données privées')->assertSee($this->freelancer->email)->assertSee('Informations publiques');
    }

    public function test_nobody_can_self_attribute_a_badge_a_role_or_an_administrator_capability_through_the_profile(): void
    {
        $before = $this->freelancer->freelanceProfile->only(['slug', 'published_at', 'user_id']);
        $roles = $this->freelancer->roles()->count();
        $this->actingAs($this->freelancer)->post('/freelance/profil', [
            'display_name' => 'Kader Soro', 'headline' => 'Dessinateur DAO', 'city' => 'Abidjan',
            'verified' => '1', 'badge' => 'verifie', 'role' => 'administrator', 'roles' => ['administrator'], 'capability' => 'administrator', 'is_admin' => '1',
            'published_at' => '2000-01-01', 'is_demo' => '0', 'user_id' => $this->client->id, 'slug' => 'pirate', 'email' => 'x@y.z',
        ])->assertRedirect();
        $p = $this->freelancer->freelanceProfile->fresh();
        $this->assertEquals($before, $p->only(['slug', 'published_at', 'user_id']), 'champs hors liste blanche ignorés');
        $this->assertSame((bool) $this->freelancer->is_demo, $p->is_demo, 'le caractère démonstration suit le compte, jamais la saisie');
        $this->assertSame($roles, $this->freelancer->roles()->count());
        $this->assertFalse($this->freelancer->fresh()->isAdministrator());
        $this->assertSame(0, DB::table('staff_grants')->where('user_id', $this->freelancer->id)->count());
        $this->assertNotSame('pirate', $p->slug);
        $this->assertSame($this->freelancer->email, $this->freelancer->fresh()->email);
    }

    public function test_an_unpublished_profile_is_a_404_and_the_public_profile_lists_published_services_only(): void
    {
        $p = $this->freelancer->freelanceProfile;
        $p->update(['published_at' => null]);
        $this->get('/freelances/'.$p->slug)->assertNotFound();
        $p->update(['published_at' => now()]);
        $draft = $this->draft();
        $live = Service::factory()->create(['freelance_profile_id' => $p->id, 'title' => 'Service visible au public']);
        $this->get('/freelances/'.$p->slug)->assertOk()->assertSee('Service visible au public')->assertDontSee('Mise en plan 2D complète d’un appartement');
        $this->get('/services/'.$draft->slug)->assertNotFound();
        $this->get('/freelances/inconnu')->assertNotFound();
    }

    // ---------- droits ----------

    public function test_only_the_owner_can_see_or_change_a_service_and_it_is_invisible_to_the_public(): void
    {
        $s = $this->draft();
        $other = User::factory()->create();
        $other->roles()->firstOrCreate(['role' => 'freelance']);
        FreelanceProfile::factory()->create(['user_id' => $other->id]);

        $this->get("/services/{$s->slug}")->assertNotFound();
        foreach ([$other, $this->client, $this->admin] as $who) {
            $this->actingAs($who)->get("/services/{$s->slug}")->assertNotFound();
        }
        foreach ([$other, $this->admin] as $who) {
            foreach (["/freelance/services/{$s->id}/modifier", "/freelance/services/{$s->id}/apercu", "/freelance/services/{$s->id}/soumettre", "/freelance/services/{$s->id}/retirer-du-catalogue", "/freelance/services/{$s->id}/nouvelle-version"] as $url) {
                $this->actingAs($who)->get($url)->assertNotFound();
            }
            $this->actingAs($who)->post("/freelance/services/{$s->id}/modifier", $this->form() + ['revision_no' => 1])->assertNotFound();
            $this->actingAs($who)->post("/freelance/services/{$s->id}/soumettre", ['revision_no' => 1])->assertNotFound();
            $this->actingAs($who)->post("/freelance/services/{$s->id}/images", ['image' => $this->pngUpload(), 'alt' => 'Une image', 'revision_no' => 1])->assertNotFound();
        }
        // un client sans espace freelance n'accède pas à « Mes services »
        $this->actingAs($this->client)->get('/freelance/services')->assertStatus(302);
        $this->assertSame('draft', $s->fresh()->status->value);
        $this->assertSame($this->form()['title'], $s->versions()->first()->title);
        // « Mes services » ne liste que les siens
        $this->actingAs($other)->get('/freelance/services')->assertOk()->assertDontSee('Mise en plan 2D complète');
        $this->actingAs($this->freelancer)->get('/freelance/services')->assertOk()->assertSee('Mise en plan 2D complète')->assertSee('Brouillon');
    }

    public function test_extra_fields_cannot_publish_or_reprice_a_service(): void
    {
        $s = $this->draft();
        $v = $s->versions()->first();
        $this->actingAs($this->freelancer)->post("/freelance/services/{$s->id}/modifier", $this->form(['status' => 'published', 'published_at' => now()->toDateTimeString(), 'slug' => 'pirate', 'is_demo' => '0', 'row_version' => 99, 'state' => 'published']) + ['revision_no' => $v->revision_no])->assertRedirect();
        $s->refresh();
        $this->assertSame('draft', $s->status->value);
        $this->assertNull($s->published_at);
        $this->assertStringStartsWith('brouillon-', $s->slug);
        $this->assertSame('draft', $s->versions()->first()->state);
        $this->assertSame(0, (int) $s->price_xof, 'le contenu public n’est jamais écrit par le propriétaire');
    }

    // ---------- saisie ----------

    public function test_forms_show_understandable_errors_and_keep_the_input(): void
    {
        $s = $this->draft();
        $v = $s->versions()->first();
        $r = $this->actingAs($this->freelancer)->from("/freelance/services/{$s->id}/modifier")->post("/freelance/services/{$s->id}/modifier", $this->form(['price_xof' => '99999999999', 'scope' => 'Contactez-moi au 07 08 09 10 11 pour un devis.', 'title' => 'Mise en plan appel moi@exemple.ci']) + ['revision_no' => $v->revision_no]);
        $r->assertRedirect("/freelance/services/{$s->id}/modifier")->assertSessionHasErrors(['price_xof', 'scope', 'title']);
        $errs = session('errors')->getBag('default');
        $page = $this->actingAs($this->freelancer)->withSession(['errors' => $errs, '_old_input' => ['price_xof' => '99999999999', 'scope' => 'Contactez-moi au 07 08 09 10 11 pour un devis.']])->get("/freelance/services/{$s->id}/modifier");
        $page->assertOk()->assertSee('Contactez-moi au 07 08 09 10 11');       // saisie conservée
        $this->assertStringContainsString('coordonnées privées', $errs->first('scope'));
        $this->assertStringContainsString('prix doit être compris entre', $errs->first('price_xof'));
        $this->assertSame(trim($this->form()['scope']), $s->versions()->first()->scope, 'rien n’est écrit tant que la saisie est invalide');

        // la soumission exige un contenu complet
        $this->actingAs($this->freelancer)->post("/freelance/services/{$s->id}/modifier", $this->form(['scope' => 'trop court', 'deliverables' => '']) + ['revision_no' => $v->revision_no])->assertRedirect();
        $this->actingAs($this->freelancer)->get("/freelance/services/{$s->id}/soumettre")->assertOk()->assertSee('Le service n’est pas encore prêt')->assertSee('description du périmètre')->assertSee('livrable');
        $this->submit($s)->assertSessionHasErrors();
        $this->assertSame('draft', $s->versions()->first()->state);
    }

    // ---------- cycle de publication ----------

    public function test_draft_submission_moderation_publication_and_refusal_with_history(): void
    {
        $s = $this->draft();
        $this->submit($s)->assertRedirect()->assertSessionHas('status');
        $v = $s->versions()->first();
        $this->assertSame('in_review', $v->state);
        $this->assertSame('in_review', $s->fresh()->status->value);
        $this->get("/services/{$s->slug}")->assertNotFound();

        // en contrôle : plus modifiable ; double soumission impossible
        $this->actingAs($this->freelancer)->post("/freelance/services/{$s->id}/modifier", $this->form(['title' => 'Un autre titre assez long pour passer']) + ['revision_no' => $v->revision_no])->assertRedirect()->assertSessionHas('error');
        $this->assertSame($this->form()['title'], $v->fresh()->title);
        $this->submit($s)->assertSessionHas('error');

        // retrait de la soumission puis nouvelle soumission
        $this->actingAs($this->freelancer)->post("/freelance/services/{$s->id}/retirer-soumission")->assertRedirect()->assertSessionHas('status');
        $this->assertSame('draft', $v->fresh()->state);
        $this->submit($s)->assertRedirect()->assertSessionHas('status');

        // refus motivé : motif visible, rien de public
        $mod = app(ServiceModeration::class);
        try {
            $mod->requestChanges($this->admin, $v->id, 'court');
            $this->fail('motif obligatoire');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('motif', $e->getMessage());
        }
        $mod->requestChanges($this->admin, $v->id, 'Le périmètre ne précise pas les formats remis.');
        $this->assertSame('changes_requested', $v->fresh()->state);
        $this->assertSame('draft', $s->fresh()->status->value);
        $this->get("/services/{$s->slug}")->assertNotFound();
        $this->actingAs($this->freelancer)->get('/freelance/services')->assertOk()->assertSee('À corriger')->assertSee('Le périmètre ne précise pas les formats remis.');
        $this->actingAs($this->freelancer)->get('/freelance')->assertOk()->assertSee('Corriger votre service');
        $this->actingAs($this->freelancer)->get("/freelance/services/{$s->id}/modifier")->assertOk()->assertSee('La modération demande une correction');

        // correction puis nouvelle soumission, puis approbation : publié sous une adresse stable
        $this->actingAs($this->freelancer)->post("/freelance/services/{$s->id}/modifier", $this->form(['scope' => str_repeat('Formats remis : PDF et DWG AutoCAD 2018, relevés fournis par le client. ', 3)]) + ['revision_no' => $v->fresh()->revision_no])->assertRedirect()->assertSessionHas('status');
        $this->submit($s)->assertRedirect()->assertSessionHas('status');
        $mod->approve($this->admin, $v->id);
        $s->refresh();
        $this->assertSame('published', $s->status->value);
        $this->assertSame('mise-en-plan-2d-complete-dun-appartement', $s->slug);
        $this->assertSame(45000, $s->price_xof);
        $this->assertNotNull($s->published_at);
        $this->get("/services/{$s->slug}")->assertOk()->assertSee('Mise en plan 2D complète d’un appartement')->assertSee('45')->assertSee('Voir le profil complet');
        $this->get('/freelances/'.$this->freelancer->freelanceProfile->slug)->assertOk()->assertSee('Mise en plan 2D complète');
        $this->assertSame(['created', 'submitted', 'submission_withdrawn', 'submitted', 'changes_requested', 'submitted', 'approved'], DB::table('service_events')->where('service_id', $s->id)->orderBy('id')->pluck('type')->all());
        $this->assertSame($this->admin->id, DB::table('service_events')->where('type', 'approved')->value('actor_id'));
    }

    public function test_moderation_requires_an_active_administrator_and_never_the_owner(): void
    {
        $s = $this->draft();
        $this->submit($s);
        $v = $s->versions()->first();
        $mod = app(ServiceModeration::class);
        foreach ([$this->client, $this->freelancer] as $who) {
            try {
                $mod->approve($who, $v->id);
                $this->fail('modération sans habilitation');
            } catch (ModerationDenied) {
                $this->assertSame('in_review', $v->fresh()->state);
            }
        }
        // un administrateur propriétaire du service ne se modère pas lui-même
        app(GrantAdministrator::class)($this->freelancer, 'test');
        try {
            $mod->approve($this->freelancer, $v->id);
            $this->fail('auto-modération');
        } catch (ModerationDenied $e) {
            $this->assertStringContainsString('propre service', $e->getMessage());
        }
        $this->assertSame('in_review', $v->fresh()->state);
        // double approbation : la seconde est refusée
        $mod->approve($this->admin, $v->id);
        $this->expectException(ServiceStateConflict::class);
        $mod->approve($this->admin, $v->id);
    }

    public function test_a_modification_never_replaces_the_approved_public_version_until_approved(): void
    {
        $s = $this->publish($this->draft());
        $v1 = $s->versions()->where('state', 'published')->firstOrFail();

        $this->actingAs($this->freelancer)->post("/freelance/services/{$s->id}/nouvelle-version")->assertRedirect()->assertSessionHas('status');
        $v2 = $s->versions()->where('state', 'draft')->firstOrFail();
        $this->assertSame(2, $v2->number);
        $this->actingAs($this->freelancer)->post("/freelance/services/{$s->id}/nouvelle-version")->assertRedirect()->assertSessionHas('error');       // une seule version ouverte
        $this->actingAs($this->freelancer)->post("/freelance/services/{$s->id}/modifier", $this->form(['title' => 'Mise en plan 2D complète et cotée', 'price_xof' => '60000']) + ['revision_no' => $v2->revision_no])->assertRedirect()->assertSessionHas('status');
        $this->get("/services/{$s->slug}")->assertOk()->assertSee('Mise en plan 2D complète d’un appartement')->assertDontSee('Mise en plan 2D complète et cotée');
        $this->assertSame(45000, $s->fresh()->price_xof);

        $this->submit($s)->assertRedirect()->assertSessionHas('status');
        $this->assertSame('published', $s->fresh()->status->value, 'la version publiée reste en ligne pendant le contrôle');
        $this->get("/services/{$s->slug}")->assertOk()->assertDontSee('Mise en plan 2D complète et cotée');
        $this->actingAs($this->freelancer)->get('/freelance/services')->assertSee('Modification en contrôle');

        // le contenu soumis ou publié est immuable en base
        $this->dbRefuses(fn () => DB::table('service_versions')->where('id', $v2->id)->update(['title' => 'modifié en douce']));
        $this->dbRefuses(fn () => DB::table('service_versions')->where('id', $v1->id)->update(['price_xof' => 1]));
        $this->dbRefuses(fn () => DB::table('service_events')->where('service_id', $s->id)->delete());

        app(ServiceModeration::class)->approve($this->admin, $v2->id);
        $this->get("/services/{$s->slug}")->assertOk()->assertSee('Mise en plan 2D complète et cotée');
        $this->assertSame(60000, $s->fresh()->price_xof);
        $this->assertSame(['superseded', 'published'], $s->versions()->orderBy('number')->pluck('state')->all());
        $this->assertSame($s->slug, $s->fresh()->slug, 'adresse publique stable');
    }

    public function test_editor_saves_text_with_images_and_preserves_it_when_an_image_is_rejected(): void
    {
        $s = $this->draft();
        $url = "/freelance/services/{$s->id}/images";
        $input = $this->form(['title' => 'Une nouvelle prestation avec ses images']) + ['editor_form' => '1'];
        $this->actingAs($this->freelancer)->post($url, $input + [
            'revision_no' => $s->versions()->first()->revision_no,
            'image' => $this->pngUpload(), 'alt' => 'Première illustration',
        ])->assertRedirect()->assertSessionHas('status');
        $v = $s->versions()->first();
        $this->assertSame($input['title'], $v->title);
        $this->assertCount(1, $v->images);

        $failed = array_merge($input, ['title' => 'Texte à conserver en cas de refus']);
        $this->post($url, $failed + [
            'revision_no' => $v->revision_no,
            'image' => UploadedFile::fake()->createWithContent('image.png', 'not an image'), 'alt' => 'Image incorrecte',
        ])->assertSessionHas('error')->assertSessionHasInput('title', $failed['title']);
        $this->assertSame($v->revision_no, $v->fresh()->revision_no);
        $this->assertSame($input['title'], $v->fresh()->title, 'image et texte forment une transaction');

        $this->post("{$url}/{$v->images[0]['id']}/retirer", $failed + [
            'revision_no' => $v->revision_no, 'cover_image' => $v->images[0]['id'],
        ])->assertSessionHas('status');
        $this->assertSame($failed['title'], $v->fresh()->title);
        $this->assertSame([], $v->fresh()->images);
    }

    public function test_cover_must_belong_to_the_draft_and_becomes_the_public_catalog_image(): void
    {
        $s = $this->draft();
        foreach (['Première image', 'Seconde image'] as $alt) {
            $this->actingAs($this->freelancer)->post("/freelance/services/{$s->id}/images", [
                'revision_no' => $s->versions()->first()->revision_no, 'image' => $this->pngUpload(), 'alt' => $alt,
            ])->assertSessionHas('status');
        }
        $v = $s->versions()->first();
        $cover = $v->images[1]['id'];
        $this->post("/freelance/services/{$s->id}/modifier", $this->form() + [
            'revision_no' => $v->revision_no, 'cover_image' => 'foreign-image',
        ])->assertSessionHasErrors('cover_image');
        $this->assertSame($v->images, $v->fresh()->images);
        $this->post("/freelance/services/{$s->id}/modifier", $this->form() + [
            'revision_no' => $v->revision_no, 'cover_image' => $cover,
        ])->assertSessionHas('status');
        $this->assertSame($cover, $v->fresh()->images[0]['id']);
        $this->publish($s);
        $this->assertSame($cover, $s->fresh()->images[0]['id']);
        $this->get('/')->assertSee("/medias/{$cover}/card", false);
    }

    public function test_save_also_uploads_a_selected_image_without_a_separate_add_click(): void
    {
        $s = $this->draft();
        $this->actingAs($this->freelancer)->post("/freelance/services/{$s->id}/modifier", $this->form() + [
            'revision_no' => $s->versions()->first()->revision_no,
            'image' => $this->pngUpload(), 'alt' => 'Image de couverture',
        ])->assertSessionHas('status');
        $this->assertCount(1, $s->versions()->first()->images);
        $this->assertSame('draft', $s->versions()->first()->state);
    }

    private function dbRefuses(callable $attempt): void
    {
        try {
            DB::transaction($attempt);
        } catch (QueryException) {
            $this->assertTrue(true);

            return;
        }
        $this->fail('La base aurait dû refuser.');
    }

    // ---------- services déjà commandés ----------

    public function test_changing_withdrawing_or_suspending_a_service_never_alters_existing_orders(): void
    {
        $this->service->update(['delivery_requires_files' => false]);
        $this->enableSandbox();
        $s = $this->service->refresh();          // service existant sans version : créée à la volée
        $order = $this->inProgress();
        $agreement = $order->agreement->fresh()->only(['price_xof', 'scope', 'delivery_days', 'revisions_included', 'service_title']);

        // nouvelle version à prix différent, approuvée
        $this->actingAs($this->freelancer)->post("/freelance/services/{$s->id}/nouvelle-version")->assertRedirect()->assertSessionHas('status');
        $v = $s->versions()->where('state', 'draft')->firstOrFail();
        $this->actingAs($this->freelancer)->post("/freelance/services/{$s->id}/modifier", $this->form(['title' => 'Plans en DWG, nouvelle formule complète', 'price_xof' => '99000', 'delivery_days' => '2', 'revisions_included' => '0', 'delivery_mode' => 'message']) + ['revision_no' => $v->revision_no])->assertRedirect()->assertSessionHas('status');
        $this->submit($s)->assertRedirect();
        app(ServiceModeration::class)->approve($this->admin, $v->id);
        $this->assertSame(99000, $s->fresh()->price_xof);

        $this->assertEquals($agreement, $order->agreement->fresh()->only(array_keys($agreement)), 'l’accord figé ne bouge jamais');
        $page = $this->actingAs($this->client)->get("/commandes/{$order->reference}")->assertOk();
        $page->assertSee('35')->assertSee('Plans en DWG')->assertDontSee('nouvelle formule complète');

        // retrait du catalogue par le propriétaire, puis suspension par la modération : la commande continue
        $this->actingAs($this->freelancer)->post("/freelance/services/{$s->id}/retirer-du-catalogue", ['note' => 'Pause'])->assertRedirect()->assertSessionHas('status');
        $this->assertSame('archived', $s->fresh()->status->value);
        $this->get("/services/{$s->slug}")->assertStatus(410);
        $this->actingAs($this->freelancer)->post("/freelance/services/{$s->id}/remettre-en-ligne")->assertRedirect()->assertSessionHas('status');
        app(ServiceModeration::class)->suspend($this->admin, $s->id, 'Contenu à vérifier après signalement.');
        $this->assertSame('suspended', $s->fresh()->status->value);
        $this->actingAs($this->freelancer)->post("/freelance/services/{$s->id}/remettre-en-ligne")->assertRedirect()->assertSessionHas('error');       // le retrait de modération ne se lève pas soi-même
        $this->assertSame('suspended', $s->fresh()->status->value);

        $order->refresh();
        $this->assertSame('in_progress', $order->state->value, 'commande et obligations en cours inchangées');
        $this->actingAs($this->freelancer)->get("/commandes/{$order->reference}/livraison")->assertOk();
        $this->actingAs($this->freelancer)->post("/commandes/{$order->reference}/livraison/message", ['message' => 'Livraison complète des plans.'])->assertRedirect();
        $draft = Delivery::where('order_id', $order->id)->where('state', 'draft')->firstOrFail();
        $this->actingAs($this->freelancer)->post("/commandes/{$order->reference}/livraison/soumettre", ['delivery_id' => $draft->id, 'expected_version' => $order->row_version, 'operation_key' => 'k-liv'])->assertRedirect()->assertSessionHas('status');

        // plus aucune nouvelle demande sur un service retiré
        $this->actingAs($this->client)->get("/services/{$s->slug}/demande")->assertStatus(410);
        // remise en ligne par la modération
        app(ServiceModeration::class)->reinstate($this->admin, $s->id);
        $this->assertSame('published', $s->fresh()->status->value);
    }

    // ---------- commandes console ----------

    public function test_console_moderation_commands_are_restricted_and_leave_a_history(): void
    {
        $s = $this->draft();
        $this->submit($s);
        $v = $s->versions()->first();

        $this->assertSame(0, Artisan::call('freeci:moderation:queue'));
        $this->assertStringContainsString($v->id, Artisan::output());
        $this->assertSame(1, Artisan::call('freeci:moderation:approve', ['version' => $v->id, '--yes' => true]));              // sans --by
        $this->assertSame(1, Artisan::call('freeci:moderation:approve', ['version' => $v->id, '--by' => $this->client->email, '--yes' => true]));
        $this->assertStringContainsString('administrateur', Artisan::output());
        $this->assertSame(1, Artisan::call('freeci:moderation:refuse', ['version' => $v->id, '--by' => $this->admin->email, '--reason' => 'non', '--yes' => true]));
        $this->assertSame('in_review', $v->fresh()->state);

        $this->assertSame(0, Artisan::call('freeci:moderation:refuse', ['version' => $v->id, '--by' => $this->admin->email, '--reason' => 'Précisez les formats de fichiers remis.', '--yes' => true]));
        $this->assertSame('changes_requested', $v->fresh()->state);
        $this->actingAs($this->freelancer)->post("/freelance/services/{$s->id}/modifier", $this->form() + ['revision_no' => $v->fresh()->revision_no, 'intent' => 'submit'])->assertRedirect();
        $this->actingAs($this->freelancer)->post("/freelance/services/{$s->id}/soumettre", ['revision_no' => $v->fresh()->revision_no])->assertRedirect();
        $this->assertSame(0, Artisan::call('freeci:moderation:approve', ['version' => $v->id, '--by' => $this->admin->email, '--yes' => true]));
        $this->assertSame('published', $s->fresh()->status->value);
        $this->assertSame(0, Artisan::call('freeci:moderation:suspend', ['service' => $s->id, '--by' => $this->admin->email, '--reason' => 'Signalement en cours d’examen.', '--yes' => true]));
        $this->assertSame(0, Artisan::call('freeci:moderation:reinstate', ['service' => $s->id, '--by' => $this->admin->email, '--yes' => true]));
        $this->assertContains('suspended', DB::table('service_events')->where('service_id', $s->id)->pluck('type')->all());
        $this->assertSame('modération (console)', DB::table('service_events')->where('type', 'approved')->value('actor_label'));
    }

    // ---------- médias ----------

    public function test_images_are_validated_reencoded_stored_privately_and_served_only_through_the_controlled_route(): void
    {
        $s = $this->draft();
        $v = $s->versions()->first();
        $post = fn (UploadedFile $f, array $o = []) => $this->actingAs($this->freelancer)->post("/freelance/services/{$s->id}/images", ['image' => $f, 'alt' => 'Plan d’étage coté', 'revision_no' => $s->versions()->first()->revision_no] + $o);

        $post($this->pngUpload())->assertRedirect()->assertSessionHas('status');
        $img = $s->versions()->first()->images[0];
        $m = DB::table('service_media')->where('id', $img['id'])->first();
        Storage::disk('private_files')->assertExists($m->key_large);
        Storage::disk('private_files')->assertExists($m->key_card);
        $this->assertSame('image/webp', $m->mime);
        $this->assertSame('RIFF', substr(Storage::disk('private_files')->get($m->key_large), 0, 4), 'réencodé : le fichier d’origine n’est pas conservé');
        $this->assertFileDoesNotExist(public_path($m->key_large));
        $this->assertSame([], glob(public_path('m/*') ?: []) ?: [], 'rien dans public');

        $url = "/medias/{$img['id']}/large";
        auth()->forgetGuards();
        $this->get($url)->assertNotFound();                                              // brouillon : invisible du public
        $other = User::factory()->create();
        $this->actingAs($other)->get($url)->assertNotFound();
        $this->actingAs($this->admin)->get($url)->assertNotFound();
        $r = $this->actingAs($this->freelancer)->get($url)->assertOk();
        $this->assertSame('image/webp', $r->headers->get('content-type'));
        $this->assertSame('nosniff', $r->headers->get('x-content-type-options'));
        $this->assertStringContainsString('no-store', $r->headers->get('cache-control'));
        auth()->forgetGuards();
        $this->get('/medias/not-an-id/large')->assertNotFound();
        $this->get("/medias/{$img['id']}/original")->assertNotFound();

        // publication : l'image devient publique, par la route seulement
        $this->publish($s);
        auth()->forgetGuards();
        $this->get($url)->assertOk();
        $this->get("/medias/{$img['id']}/card")->assertOk();
        $this->get("/services/{$s->fresh()->slug}")->assertOk()->assertSee($url, false);
    }

    public function test_hostile_or_invalid_images_are_refused(): void
    {
        $s = $this->draft();
        $rev = fn () => $s->versions()->first()->revision_no;
        $try = fn (UploadedFile $f, string $alt = 'Une image') => $this->actingAs($this->freelancer)->post("/freelance/services/{$s->id}/images", ['image' => $f, 'alt' => $alt, 'revision_no' => $rev()]);
        $bad = [
            'php déguisé' => UploadedFile::fake()->createWithContent('photo.png', '<?php system($_GET["c"]); ?>'),
            'exécutable' => UploadedFile::fake()->createWithContent('outil.exe', 'MZ......'),
            'svg' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            'html' => UploadedFile::fake()->createWithContent('page.html', '<html></html>'),
            'png mensonger' => UploadedFile::fake()->createWithContent('faux.png', "GIF89a\x01\x00\x01\x00"),
            'trop petite' => $this->pngUpload('petite.png', 100, 100),
            'extension double' => UploadedFile::fake()->createWithContent('a.php.png', '<?php ?>'),
        ];
        foreach ($bad as $label => $f) {
            $try($f)->assertRedirect()->assertSessionHas('error');
            $this->assertSame(0, DB::table('service_media')->count(), $label);
        }
        $try($this->pngUpload(), 'x')->assertRedirect();                                    // texte alternatif trop court
        $this->assertSame(0, DB::table('service_media')->count());

        config(['freeci.catalog.image_max_mb' => 0]);
        $try($this->pngUpload())->assertRedirect()->assertSessionHas('error');
        config(['freeci.catalog.image_max_mb' => 5, 'freeci.catalog.images_max' => 1]);
        $try($this->pngUpload())->assertRedirect()->assertSessionHas('status');
        $try($this->pngUpload('b.png'))->assertRedirect()->assertSessionHas('error');
        $this->assertSame(1, DB::table('service_media')->count());
    }

    public function test_removed_images_are_pruned_and_public_images_stay_public_across_versions(): void
    {
        $s = $this->draft();
        $up = fn () => $this->actingAs($this->freelancer)->post("/freelance/services/{$s->id}/images", ['image' => $this->pngUpload(), 'alt' => 'Plan d’étage coté', 'revision_no' => $s->versions()->whereIn('state', ServiceVersion::OPEN)->first()->revision_no])->assertRedirect()->assertSessionHas('status');
        $up();
        $id = $s->versions()->first()->images[0]['id'];
        $this->publish($s);

        $this->actingAs($this->freelancer)->post("/freelance/services/{$s->id}/nouvelle-version");
        $v2 = $s->versions()->where('state', 'draft')->first();
        $this->actingAs($this->freelancer)->post("/freelance/services/{$s->id}/images/{$id}/retirer", ['revision_no' => $v2->revision_no])->assertRedirect()->assertSessionHas('status');
        $this->assertSame([], $s->versions()->where('state', 'draft')->first()->images);
        $up();                                                                           // une seconde image, jamais publiée
        $tmp = $s->versions()->where('state', 'draft')->first()->images[0]['id'];
        $this->actingAs($this->freelancer)->post("/freelance/services/{$s->id}/images/{$tmp}/retirer", ['revision_no' => $s->versions()->where('state', 'draft')->first()->revision_no])->assertRedirect();
        auth()->forgetGuards();
        $this->get("/medias/{$id}/large")->assertOk();                                   // la version publiée la référence encore

        DB::table('service_media')->update(['created_at' => now()->subDays(2)]);
        Artisan::call('freeci:media:prune');
        $this->assertSame([$id], DB::table('service_media')->pluck('id')->all(), 'seule l’image orpheline est supprimée');
        Storage::disk('private_files')->assertExists(DB::table('service_media')->value('key_large'));
    }

    public function test_concurrent_screens_cannot_overwrite_each_other_silently(): void
    {
        $s = $this->draft();
        $rev = $s->versions()->first()->revision_no;
        $this->actingAs($this->freelancer)->post("/freelance/services/{$s->id}/modifier", $this->form(['title' => 'Premier écran : titre assez long ici']) + ['revision_no' => $rev])->assertRedirect()->assertSessionHas('status');
        $this->actingAs($this->freelancer)->post("/freelance/services/{$s->id}/modifier", $this->form(['title' => 'Second écran périmé : titre long ici']) + ['revision_no' => $rev])->assertRedirect()->assertSessionHas('error');
        $this->assertSame('Premier écran : titre assez long ici', $s->versions()->first()->title);
    }

    public function test_legacy_services_get_a_version_lazily_and_catalog_data_is_preserved(): void
    {
        $legacy = Service::factory()->create(['freelance_profile_id' => $this->freelancer->freelanceProfile->id, 'title' => 'Service existant avant le lot 5', 'price_xof' => 30000]);
        $this->assertSame(0, $legacy->versions()->count());
        $this->actingAs($this->freelancer)->get('/freelance/services')->assertOk()->assertSee('Service existant avant le lot 5')->assertSee('Publié');
        $v = $legacy->versions()->first();
        $this->assertSame(['published', 1, 30000], [$v->state, $v->number, $v->price_xof]);
        $this->assertSame('published', $legacy->fresh()->status->value);
        $this->assertSame(30000, $legacy->fresh()->price_xof);
        $this->get("/services/{$legacy->slug}")->assertOk();
        app(ServiceAuthoring::class)->ensureVersions($legacy);          // idempotent
        $this->assertSame(1, $legacy->versions()->count());
    }

    public function test_submission_requires_a_published_profile(): void
    {
        $s = $this->draft();
        $this->freelancer->freelanceProfile->update(['published_at' => null]);
        $this->submit($s)->assertSessionHasErrors('profile');
        $this->assertSame('draft', $s->versions()->first()->state);
        $this->actingAs($this->freelancer)->get("/freelance/services/{$s->id}/modifier")->assertOk()->assertSee('Votre profil n’est pas publié');
    }

    public function test_a_service_cannot_be_submitted_without_a_cover_image(): void
    {
        if (! ImageProcessor::available()) {
            $this->markTestSkipped('Traitement d’images indisponible : la règle n’est pas appliquée.');
        }
        $s = $this->draft();
        $v = $s->versions()->first();
        $this->actingAs($this->freelancer)->post("/freelance/services/{$s->id}/soumettre", ['revision_no' => $v->revision_no])->assertSessionHasErrors('images');
        $this->assertSame('draft', $v->fresh()->state);

        $this->actingAs($this->freelancer)->post("/freelance/services/{$s->id}/modifier", $this->form() + [
            'revision_no' => $v->revision_no, 'image' => $this->pngUpload(), 'alt' => 'Image de couverture',
        ]);
        $this->submit($s)->assertRedirect()->assertSessionHas('status');
    }

    public function test_services_page_follows_the_home_visual_language(): void
    {
        $this->get('/services')->assertOk()->assertSee('service-directory', false)->assertSee('talent-filters', false)->assertSee('Des prestations à prix et délai annoncés');
    }
}

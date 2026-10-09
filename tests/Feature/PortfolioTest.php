<?php

namespace Tests\Feature;

use App\Modules\Accounts\Actions\ExportPersonalData;
use App\Modules\Accounts\Actions\Portfolio;
use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Support\ImageProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\AdminFixtures;
use Tests\Support\OrderFixtures;
use Tests\TestCase;

/** Lot 69 : réalisations (portfolio) du freelance — publication immédiate, signalement avec le profil, retrait par l'administration avec motif et journal. */
class PortfolioTest extends TestCase
{
    use AdminFixtures, OrderFixtures, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        if (! ImageProcessor::available()) {
            $this->markTestSkipped('Extension GD (WebP) absente.');
        }
        Storage::fake('private_files');
        $this->setUpParties();
        $this->admin = $this->readyAdmin(['name' => 'Admin Portfolio']);
    }

    private function img(string $name = 'realisation.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 900, 600);
    }

    private function add(?User $u = null, array $over = [], ?UploadedFile $f = null)
    {
        return $this->actingAs($u ?? $this->freelancer)->post('/freelance/realisations', $over + ['image' => $f ?? $this->img(), 'title' => 'Identité visuelle d’un café', 'description' => 'Logo, palette et déclinaisons.', 'year' => '2026']);
    }

    private function items(?User $u = null): Collection
    {
        return DB::table('portfolio_items')->where('user_id', ($u ?? $this->freelancer)->id)->where('state', 'active')->orderBy('created_at')->get();
    }

    public function test_a_freelancer_adds_an_item_which_is_public_and_reencoded(): void
    {
        $this->add()->assertRedirect()->assertSessionHas('status');
        $it = $this->items()->first();
        $this->assertSame('Identité visuelle d’un café', $it->title);
        Storage::disk('private_files')->assertExists([$it->key_large, $it->key_card]);
        $this->assertSame(IMAGETYPE_WEBP, getimagesizefromstring(Storage::disk('private_files')->get($it->key_card))[2]);
        auth()->forgetGuards();
        $this->get("/realisations/{$it->id}/card")->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->get("/realisations/{$it->id}/large")->assertOk();
        $this->get("/realisations/{$it->id}/huge")->assertNotFound();
        $slug = DB::table('freelance_profiles')->where('user_id', $this->freelancer->id)->value('slug');
        $this->get("/freelances/{$slug}")->assertOk()->assertSee('Réalisations')->assertSee('Identité visuelle d’un café')->assertSee('po-dlg', false)->assertSee("/realisations/{$it->id}/large", false);
    }

    public function test_limits_texts_and_files_are_enforced(): void
    {
        foreach (range(1, 8) as $n) {
            $this->add(null, ['title' => "Réalisation numéro {$n}"])->assertSessionHas('status');
        }
        $this->add(null, ['title' => 'Une de trop'])->assertSessionHas('error');
        $this->assertCount(8, $this->items());
        $first = $this->items()->first();
        $this->actingAs($this->freelancer)->post("/freelance/realisations/{$first->id}/supprimer")->assertSessionHas('status');
        $this->add(null, ['title' => 'Place libérée'])->assertSessionHas('status');

        $before = DB::table('portfolio_items')->count();
        $this->add(null, ['title' => 'ab'])->assertSessionHasErrors('title');
        $this->add(null, ['title' => 'Écrivez-moi : moi@exemple.ci'])->assertSessionHasErrors('title');
        $this->add(null, ['description' => 'Voir mon site www.monsite.com pour plus'])->assertSessionHasErrors('description');
        $this->add(null, ['description' => 'Appelez le 07 08 09 10 11'])->assertSessionHasErrors('description');
        $this->add(null, ['description' => str_repeat('a', 301)])->assertSessionHasErrors('description');
        $this->add(null, ['year' => '1850'])->assertSessionHasErrors('year');
        $this->add(null, [], UploadedFile::fake()->create('cv.pdf', 20, 'application/pdf'))->assertSessionHas('error');
        $this->add(null, [], UploadedFile::fake()->image('petite.jpg', 100, 100))->assertSessionHas('error');
        $this->assertSame($before, DB::table('portfolio_items')->count());
    }

    public function test_only_freelancers_manage_items_and_nobody_edits_someone_elses(): void
    {
        $this->add($this->client)->assertRedirect();
        $this->assertSame(0, DB::table('portfolio_items')->count(), 'un client sans espace freelance ne peut rien ajouter');
        $this->add();
        $it = $this->items()->first();
        $other = User::factory()->create();
        $this->actingAs($other)->post("/freelance/realisations/{$it->id}/supprimer")->assertRedirect();
        $this->assertCount(1, $this->items(), 'la réalisation d’un autre n’est pas supprimable');
        $this->actingAs($this->freelancer)->post("/freelance/realisations/{$it->id}", ['title' => 'Titre modifié', 'description' => '', 'year' => ''])->assertSessionHas('status');
        $this->assertSame('Titre modifié', DB::table('portfolio_items')->where('id', $it->id)->value('title'));
        $this->actingAs($this->freelancer)->post("/freelance/realisations/{$it->id}", ['title' => 'Mon mail a@b.ci', 'description' => ''])->assertSessionHasErrors('title');
        $this->actingAs($this->freelancer)->get('/freelance/profil')->assertOk()->assertSee('Réalisations')->assertSee('1 sur 8 réalisations')->assertSee('Titre modifié');
    }

    public function test_deleting_erases_the_files(): void
    {
        $this->add();
        $it = $this->items()->first();
        $this->actingAs($this->freelancer)->post("/freelance/realisations/{$it->id}/supprimer")->assertSessionHas('status');
        Storage::disk('private_files')->assertMissing([$it->key_large, $it->key_card]);
        $this->assertSame('deleted', DB::table('portfolio_items')->where('id', $it->id)->value('state'));
        $this->actingAs($this->freelancer)->post("/freelance/realisations/{$it->id}/supprimer")->assertSessionHas('status');
        auth()->forgetGuards();
        $this->get("/realisations/{$it->id}/card")->assertNotFound();
    }

    public function test_the_administrator_removes_an_item_with_reason_audit_and_notification(): void
    {
        $this->add();
        $it = $this->items()->first();
        $url = "/admin/utilisateurs/{$this->freelancer->id}/realisations/{$it->id}/retirer";
        $this->actingAs($this->client)->post($url, ['reason' => 'Motif suffisamment long'])->assertNotFound();
        $this->asAdmin($this->admin, recent: false)->post($url, ['reason' => 'Motif suffisamment long'])->assertRedirect(route('admin.reauth'));
        $this->assertCount(1, $this->items());
        $this->asAdmin($this->admin)->post($url, ['reason' => 'court'])->assertSessionHasErrors('reason');
        $this->asAdmin($this->admin)->post($url, ['reason' => 'Image qui n’est pas de la personne.'])->assertRedirect()->assertSessionHas('status');
        $this->assertCount(0, $this->items());
        $row = DB::table('portfolio_items')->where('id', $it->id)->first();
        $this->assertSame(['removed', $this->admin->id], [$row->state, $row->removed_by]);
        $this->assertNotNull($row->purge_after);
        auth()->forgetGuards();
        $this->get("/realisations/{$it->id}/card")->assertNotFound();
        $this->asAdmin($this->admin)->get("/realisations/{$it->id}/card")->assertOk();
        $this->assertSame(1, DB::table('admin_actions')->where('action', 'user.portfolio_remove')->where('result', 'done')->count());
        $n = DB::table('app_notifications')->where('user_id', $this->freelancer->id)->where('type', 'portfolio_removed')->first();
        $this->assertNotNull($n);
        $this->assertStringContainsString('Image qui n’est pas', (string) $n->body);
        $this->asAdmin($this->admin)->post($url, ['reason' => 'Deuxième tentative sur la même.'])->assertSessionHas('error');
        $this->asAdmin($this->admin)->get("/admin/utilisateurs/{$this->freelancer->id}")->assertOk()->assertSee('Réalisation retirée')->assertSee('Image qui n’est pas');
    }

    public function test_the_admin_user_page_lists_items_with_a_removal_form(): void
    {
        $this->add();
        $this->asAdmin($this->admin)->get("/admin/utilisateurs/{$this->freelancer->id}")->assertOk()->assertSee('Réalisations')->assertSee('Identité visuelle d’un café')->assertSee('Retirer cette réalisation');
    }

    public function test_removed_files_are_purged_after_the_delay_unless_a_report_is_open_and_closing_erases_everything(): void
    {
        $this->add();
        $it = $this->items()->first();
        $this->asAdmin($this->admin)->post("/admin/utilisateurs/{$this->freelancer->id}/realisations/{$it->id}/retirer", ['reason' => 'Contenu inapproprié constaté.']);
        $portfolio = app(Portfolio::class);
        $this->assertSame(0, $portfolio->purgeExpired());
        $this->travel(31)->days();
        $profile = DB::table('freelance_profiles')->where('user_id', $this->freelancer->id)->value('id');
        $case = (string) Str::uuid();
        DB::table('support_cases')->insert(['id' => $case, 'reference' => 'SUP-TEST-0009', 'kind' => 'report', 'requester_id' => $this->client->id, 'target_type' => 'profile', 'target_id' => $profile, 'status' => 'open',
            'subject' => 'Signalement', 'opened_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame(0, $portfolio->purgeExpired(), 'dossier ouvert : conservation prolongée');
        DB::table('support_cases')->where('id', $case)->update(['status' => 'closed']);
        $this->assertSame(1, $portfolio->purgeExpired());
        Storage::disk('private_files')->assertMissing([$it->key_large, $it->key_card]);
        $this->artisan('freeci:photos:purge')->assertSuccessful();

        $this->add(null, ['title' => 'À effacer à la fermeture']);
        $kept = $this->items()->first();
        $export = app(ExportPersonalData::class)->build($this->freelancer);
        $this->assertContains('À effacer à la fermeture', array_column($export['realisations'], 'titre'));
        $portfolio->purgeAll($this->freelancer->id);
        Storage::disk('private_files')->assertMissing([$kept->key_large, $kept->key_card]);
        $this->assertCount(0, $this->items());
        $this->assertSame('', DB::table('portfolio_items')->where('id', $kept->id)->value('title'));
    }
}

<?php

namespace Tests\Feature;

use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\AdminFixtures;
use Tests\Support\OrderFixtures;
use Tests\TestCase;

/** Lot 31 — les catégories se gèrent depuis l'administration. */
class AdminCategoriesTest extends TestCase
{
    use AdminFixtures, OrderFixtures, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private_files');
        $this->setUpParties();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->admin = $this->readyAdmin();
    }

    private function adminPost(string $uri, array $data = [], bool $recent = true)
    {
        return $this->asAdmin($this->admin, $recent)->post($uri, $data);
    }

    public function test_the_page_is_reserved_to_administrators_and_lists_categories(): void
    {
        $this->asAdmin($this->admin)->get('/admin/categories')->assertOk()->assertSee('Nouvelle catégorie')->assertSee($this->service->category->name);
        $this->actingAs($this->client)->get('/admin/categories')->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->get('/admin/categories')->assertRedirect();
    }

    public function test_creating_a_category_makes_it_available_and_is_audited(): void
    {
        $this->adminPost('/admin/categories', ['name' => 'Juridique et Comptabilité', 'icon' => 'shield'])->assertSessionHas('status');
        $c = Category::where('name', 'Juridique et Comptabilité')->firstOrFail();
        $this->assertSame('juridique-et-comptabilite', $c->slug);
        $this->assertSame('shield', $c->icon);
        $this->get('/missions')->assertSee('Juridique et Comptabilité');
        $this->assertSame(1, DB::table('admin_actions')->where('action', 'category.create')->where('result', 'done')->count());
        // même nom (casse différente) refusé ; icône hors liste refusée ; reconfirmation d'identité exigée
        $this->adminPost('/admin/categories', ['name' => 'JURIDIQUE ET COMPTABILITÉ', 'icon' => 'shield'])->assertSessionHas('error');
        $this->adminPost('/admin/categories', ['name' => 'Autre rubrique', 'icon' => 'inconnue'])->assertSessionHas('error');
        $this->adminPost('/admin/categories', ['name' => 'Sans reconfirmation', 'icon' => 'cog'], false)->assertRedirect();
        $this->assertNull(Category::where('name', 'Sans reconfirmation')->first());
    }

    public function test_renaming_keeps_the_address_and_reordering_swaps_positions(): void
    {
        $a = Category::factory()->create(['name' => 'Alpha rubrique', 'position' => 10]);
        $b = Category::factory()->create(['name' => 'Bravo rubrique', 'position' => 20]);
        $slug = $a->slug;
        $this->adminPost("/admin/categories/{$a->id}", ['name' => 'Alpha renommée', 'icon' => 'code'])->assertSessionHas('status');
        $this->assertSame([$slug, 'code', 'Alpha renommée'], [$a->fresh()->slug, $a->fresh()->icon, $a->fresh()->name]);
        $this->adminPost("/admin/categories/{$b->id}/deplacer/up")->assertSessionHas('status');
        $this->assertSame([20, 10], [$a->fresh()->position, $b->fresh()->position]);
    }

    public function test_an_archived_category_is_no_longer_offered_but_existing_services_remain(): void
    {
        $spare = Category::factory()->create(['name' => 'Rubrique de secours']);
        $cat = $this->service->category;
        $this->adminPost("/admin/categories/{$cat->id}/archiver", ['reason' => 'Rubrique remplacée par une autre.'])->assertSessionHas('status');
        $this->assertNotNull($cat->fresh()->archived_at);

        $this->get('/missions')->assertDontSee($cat->name)->assertSee('Rubrique de secours');
        $this->actingAs($this->freelancer)->get('/freelance/services/nouveau')->assertOk()->assertDontSee($cat->name);
        $this->actingAs($this->freelancer)->post('/freelance/services', ['title' => 'Un service dans une catégorie archivée', 'category_id' => $cat->id])->assertSessionHasErrors('category_id');
        $this->assertNotNull(Service::find($this->service->id), 'le service existant est conservé');
        $this->app['auth']->forgetGuards();
        $this->get("/services/{$this->service->slug}")->assertOk();

        $this->adminPost("/admin/categories/{$cat->id}/retablir")->assertSessionHas('status');
        $this->assertNull($cat->fresh()->archived_at);
        $this->assertGreaterThan($spare->position, $cat->fresh()->position);
    }

    public function test_the_last_active_category_cannot_be_archived(): void
    {
        $only = $this->service->category;
        Category::where('id', '!=', $only->id)->delete();
        $this->adminPost("/admin/categories/{$only->id}/archiver", ['reason' => 'Tentative sur la dernière catégorie.'])->assertSessionHas('error');
        $this->assertNull($only->fresh()->archived_at);
    }

    public function test_deletion_is_only_possible_for_an_unused_category(): void
    {
        $used = $this->service->category;
        $unused = Category::factory()->create(['name' => 'Rubrique inutilisée']);
        $this->adminPost("/admin/categories/{$used->id}/supprimer", ['reason' => 'Nettoyage de la liste.'])->assertSessionHas('error');
        $this->assertNotNull(Category::find($used->id));
        $this->adminPost("/admin/categories/{$unused->id}/supprimer", ['reason' => 'Nettoyage de la liste.'])->assertSessionHas('status');
        $this->assertNull(Category::find($unused->id));
        $this->assertSame(1, DB::table('admin_actions')->where('action', 'category.delete')->where('result', 'refused')->count());
    }

    public function test_the_home_never_lists_an_archived_category(): void
    {
        Category::factory()->create(['name' => 'Rubrique active unique']);
        $cat = $this->service->category;
        $cat->update(['archived_at' => now()]);
        $this->get('/')->assertOk()->assertDontSee("categorie={$cat->slug}", false)->assertSee('Rubrique active unique');
    }
}

<?php

namespace Tests\Feature;

use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Enums\ServiceStatus;
use App\Modules\Catalog\Models\FreelanceProfile;
use App\Modules\Catalog\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Lot 12 — favoris : privés, sans doublon, jamais un contournement de la visibilité publique. */
class FavoritesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function fav(User $u, string $kind, string $slug, string $intent = 'add')
    {
        return $this->actingAs($u)->post("/favoris/{$kind}/{$slug}", ['intent' => $intent]);
    }

    public function test_add_and_remove_without_duplicates_and_visitors_must_sign_in(): void
    {
        $s = Service::factory()->create(['title' => 'Plans en DWG']);
        $p = $s->freelanceProfile;
        $u = User::factory()->create();
        $this->post("/favoris/service/{$s->slug}", ['intent' => 'add'])->assertRedirect(route('login'));
        $this->fav($u, 'service', $s->slug)->assertRedirect()->assertSessionHas('status');
        $this->fav($u, 'service', $s->slug);                              // double clic : aucun doublon
        $this->fav($u, 'freelance', $p->slug);
        $this->assertSame(2, DB::table('favorites')->where('user_id', $u->id)->count());
        $this->actingAs($u)->get('/espace/favoris')->assertOk()->assertSee('Plans en DWG')->assertSee($p->display_name)->assertSee('Vos favoris sont privés');
        // le bouton reflète l'état
        $this->actingAs($u)->get("/services/{$s->slug}")->assertSee('Retirer des favoris');
        $this->fav($u, 'service', $s->slug, 'remove');
        $this->fav($u, 'service', $s->slug, 'remove');                    // retrait répété : sans effet
        $this->assertSame(1, DB::table('favorites')->where('user_id', $u->id)->count());
        $this->actingAs($u)->get("/services/{$s->slug}")->assertSee('Ajouter aux favoris');
        $this->fav($u, 'service', $s->slug, 'bogus')->assertSessionHasErrors('intent');
    }

    public function test_favorites_are_private_to_their_owner(): void
    {
        $s = Service::factory()->create(['title' => 'Service secret favori']);
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->fav($a, 'service', $s->slug);
        $this->actingAs($b)->get('/espace/favoris')->assertOk()->assertDontSee('Service secret favori')->assertSee('Aucun favori pour l’instant');
        $favId = DB::table('favorites')->value('id');
        $this->actingAs($b)->post("/espace/favoris/{$favId}/retirer")->assertRedirect();
        $this->assertSame(1, DB::table('favorites')->count(), 'un autre utilisateur ne peut pas retirer mon favori');
        // les favoris n'influencent aucun classement public : l'ordre du catalogue ne change pas
        $t = Service::factory()->create(['title' => 'Autre service', 'published_at' => now()->subDays(3)]);
        $this->get('/services')->assertSeeInOrder(['Service secret favori', 'Autre service']);
        $this->fav($b, 'service', $t->slug);
        $this->get('/services')->assertSeeInOrder(['Service secret favori', 'Autre service']);
        // aucune route publique ne liste les favoris d'autrui
        $this->app['auth']->forgetGuards();
        $this->get('/espace/favoris')->assertRedirect(route('login'));
    }

    public function test_withdrawn_or_suspended_content_is_unavailable_in_favorites_without_exposing_data_and_cannot_be_added(): void
    {
        $s = Service::factory()->create(['title' => 'Titre à ne pas exposer']);
        $p = FreelanceProfile::factory()->create(['display_name' => 'Nom retiré du public']);
        $u = User::factory()->create();
        $this->fav($u, 'service', $s->slug);
        $this->fav($u, 'freelance', $p->slug);
        $s->update(['status' => ServiceStatus::Suspended->value]);
        $p->update(['published_at' => null]);
        $page = $this->actingAs($u)->get('/espace/favoris')->assertOk();
        $page->assertSee('Contenu indisponible')->assertDontSee('Titre à ne pas exposer')->assertDontSee('Nom retiré du public');
        $this->assertSame(2, substr_count($page->getContent(), 'Contenu indisponible'));
        // ajout d'un contenu non public : refusé ; retrait toujours possible
        $this->fav($u, 'service', $s->slug)->assertSessionHas('error');
        $this->fav($u, 'freelance', $p->slug)->assertSessionHas('error');
        $id = DB::table('favorites')->value('id');
        $this->actingAs($u)->post("/espace/favoris/{$id}/retirer")->assertRedirect();
        $this->assertSame(1, DB::table('favorites')->count());
        // un vendeur suspendu : ses services publiés ne deviennent pas accessibles par les favoris
        $s2 = Service::factory()->create(['title' => 'Service du vendeur suspendu']);
        $this->fav($u, 'service', $s2->slug);
        DB::table('users')->where('id', $s2->freelanceProfile->user_id)->update(['suspended_at' => now()]);
        $this->actingAs($u)->get('/espace/favoris')->assertDontSee('Service du vendeur suspendu');
    }
}

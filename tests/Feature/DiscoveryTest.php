<?php

namespace Tests\Feature;

use App\Livewire\Catalog\ServiceSearch;
use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Actions\SearchServices;
use App\Modules\Catalog\Data\ServiceSearchCriteria;
use App\Modules\Catalog\Enums\ServiceStatus;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\FreelanceProfile;
use App\Modules\Catalog\Models\Service;
use App\Modules\Missions\Queries\PublicMissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/** Lot 12 — découverte : filtres prix / délai / compétence / catégorie, tris compréhensibles, pagination stable, URL, visibilité des résultats. */
class DiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private function search(array $c)
    {
        return app(SearchServices::class)(ServiceSearchCriteria::make($c['q'] ?? null, $c['cat'] ?? null, $c['sort'] ?? null, $c['pmin'] ?? null, $c['pmax'] ?? null, $c['dmax'] ?? null, $c['skill'] ?? null));
    }

    public function test_service_filters_by_price_delay_and_skill_and_only_published_services(): void
    {
        $cheap = Service::factory()->create(['title' => 'Petit logo', 'price_xof' => 10000, 'delivery_days' => 2]);
        Service::factory()->create(['title' => 'Site complet', 'price_xof' => 200000, 'delivery_days' => 20]);
        $cheap->freelanceProfile->update(['skills' => ['Illustrator', 'Branding']]);
        Service::factory()->status(ServiceStatus::Draft)->create(['title' => 'Brouillon pas cher', 'price_xof' => 5000, 'delivery_days' => 1]);
        Service::factory()->scheduled()->create(['title' => 'Programmé pas cher', 'price_xof' => 5000, 'delivery_days' => 1]);

        $this->assertSame(['Petit logo'], $this->search(['pmax' => 50000])->pluck('title')->all());
        $this->assertSame(['Site complet'], $this->search(['pmin' => 100000])->pluck('title')->all());
        $this->assertSame(['Petit logo'], $this->search(['dmax' => 3])->pluck('title')->all());
        $this->assertSame(['Petit logo'], $this->search(['skill' => 'branding'])->pluck('title')->all(), 'compétence sans casse');
        $this->assertSame(['Petit logo'], $this->search(['skill' => 'ILLUSTRATOR'])->pluck('title')->all());
        $this->assertCount(0, $this->search(['skill' => 'Inconnue']));
        $this->assertCount(0, $this->search(['pmin' => 20000, 'pmax' => 50000]));
        // bornes inversées corrigées, valeurs invalides ignorées
        $this->assertSame(['Petit logo'], $this->search(['pmin' => 50000, 'pmax' => 10000, 'dmax' => 'abc'])->pluck('title')->all());
        $this->assertSame(2, $this->search(['pmax' => '-5', 'dmax' => '0'])->total(), 'filtres invalides : ignorés, jamais « aucun résultat » trompeur');
        $this->assertTrue(ServiceSearchCriteria::make(null, null, null, 100)->hasFilters());
    }

    public function test_service_sorts_are_explicit_and_pagination_is_stable_even_with_ties(): void
    {
        foreach (range(1, 14) as $i) {
            Service::factory()->create(['title' => "Service {$i}", 'price_xof' => 10000, 'delivery_days' => 5, 'published_at' => now()->subDay()]);   // égalités totales
        }
        Service::factory()->create(['title' => 'Rapide', 'price_xof' => 99000, 'delivery_days' => 1]);
        foreach (['pertinence', 'prix-croissant', 'prix-decroissant', 'delai-court', 'recents', 'mieux-notes'] as $sort) {
            $one = $this->search(['sort' => $sort]);
            $this->assertSame(15, $one->total(), $sort);
            $seen = array_merge($one->pluck('slug')->all(), app(SearchServices::class)(ServiceSearchCriteria::make(null, null, $sort), 2)->pluck('slug')->all());
            $this->assertCount(15, array_unique($seen), "pagination stable sans doublon ni trou : {$sort}");
            $this->assertSame($seen, array_merge($this->search(['sort' => $sort])->pluck('slug')->all(), app(SearchServices::class)(ServiceSearchCriteria::make(null, null, $sort), 2)->pluck('slug')->all()), "ordre reproductible : {$sort}");
        }
        $this->assertSame('Rapide', $this->search(['sort' => 'delai-court'])->first()->title);
        $this->assertSame('Rapide', $this->search(['sort' => 'prix-decroissant'])->first()->title);
    }

    public function test_url_state_keeps_filters_and_the_empty_state_is_useful(): void
    {
        Service::factory()->create(['title' => 'Logo vert', 'price_xof' => 15000, 'delivery_days' => 3]);
        $this->get('/services?prix_max=20000&delai_max=5&tri=delai-court')->assertOk()->assertSee('Logo vert')->assertSee('jusqu’à 20')->assertSee('5 j max');
        $this->get('/services?prix_min=50000')->assertOk()->assertSee('Aucun service ne correspond')->assertSee('Retirez un filtre')->assertSee('Tout effacer')->assertDontSee('Logo vert');
        Livewire::withQueryParams(['prix_max' => 20000, 'competence' => 'AutoCAD', 'tri' => 'prix-croissant'])->test(ServiceSearch::class)
            ->assertSet('prixMax', '20000')->assertSet('competence', 'AutoCAD')->assertSet('tri', 'prix-croissant')
            ->call('removeFilter', 'prixMax')->assertSet('prixMax', '')->assertSet('competence', 'AutoCAD')->call('clear')->assertSet('competence', '');
        // les compétences proposées sont celles réellement déclarées par des profils publiés
        $this->get('/services')->assertSee('AutoCAD')->assertDontSee('Compétence inventée');
    }

    public function test_best_rated_sort_follows_an_explicit_rule_and_never_uses_favorites(): void
    {
        $a = Service::factory()->create(['title' => 'Sans avis']);
        $b = Service::factory()->create(['title' => 'Avis moyen']);
        $c = Service::factory()->create(['title' => 'Bien noté']);
        $client = User::factory()->create();
        $i = 0;
        foreach ([[$b, 3], [$c, 5], [$c, 5]] as [$svc, $rating]) {
            $i++;
            $orderId = $this->fakeOrder($svc, $client);
            DB::table('reviews')->insert(['id' => (string) Str::uuid(), 'order_id' => $orderId, 'author_id' => $client->id, 'subject_id' => $svc->freelanceProfile->user_id, 'origin' => 'service', 'service_id' => $svc->id,
                'rating' => $rating, 'comment' => 'Commentaire suffisamment long de test.', 'counts_public' => true, 'visible_at' => now()->subDay(), 'created_at' => now()->subDays(2)]);
        }
        DB::table('favorites')->insert(['user_id' => $client->id, 'kind' => 'service', 'target_id' => $a->id, 'created_at' => now()]);          // un favori ne change rien
        $this->assertSame(['Bien noté', 'Avis moyen', 'Sans avis'], $this->search(['sort' => 'mieux-notes'])->pluck('title')->all());
        $this->get('/services?tri=mieux-notes')->assertSee('Règle du tri')->assertSee('services sans avis sont placés en dernier');
        // un avis masqué ou de test ne compte pas dans le tri
        DB::table('reviews')->where('service_id', $c->id)->update(['hidden_at' => now()]);
        $this->assertSame('Avis moyen', $this->search(['sort' => 'mieux-notes'])->first()->title);
    }

    private function fakeOrder(Service $svc, User $client): string
    {
        // Commande minimale pour rattacher un avis : accord et événements ne sont pas nécessaires à ces lectures.
        $id = (string) Str::uuid();
        DB::statement('SET CONSTRAINTS ALL DEFERRED');
        DB::table('orders')->insert(['id' => $id, 'reference' => 'FC-T-'.Str::upper(Str::random(6)), 'client_id' => $client->id, 'freelancer_id' => $svc->freelanceProfile->user_id, 'service_id' => $svc->id, 'state' => 'closed',
            'closure_reason' => 'validated', 'requested_at' => now(), 'response_deadline_at' => now(), 'closed_at' => now(), 'started_at' => now()->subDays(3), 'due_at' => now()->subDay(), 'environment' => 'live', 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    public function test_freelance_directory_lists_only_public_profiles_with_working_filters_and_sorts(): void
    {
        $cat = Category::factory()->create(['slug' => 'btp']);
        $a = FreelanceProfile::factory()->create(['display_name' => 'Awa Dessin', 'headline' => 'Dessinatrice DAO', 'skills' => ['AutoCAD', 'Revit']]);
        $b = FreelanceProfile::factory()->create(['display_name' => 'Zoé Web', 'headline' => 'Développeuse', 'skills' => ['Laravel']]);
        FreelanceProfile::factory()->create(['display_name' => 'Brouillon Non Publié', 'published_at' => null]);
        $suspended = FreelanceProfile::factory()->create(['display_name' => 'Suspendue Cachée']);
        DB::table('users')->where('id', $suspended->user_id)->update(['suspended_at' => now()]);
        Service::factory()->create(['freelance_profile_id' => $a->id, 'category_id' => $cat->id, 'price_xof' => 30000, 'delivery_days' => 4, 'title' => 'Plan 2D']);
        Service::factory()->scheduled()->create(['freelance_profile_id' => $b->id, 'category_id' => $cat->id, 'price_xof' => 1000, 'delivery_days' => 1, 'title' => 'Programmé']);

        $this->get('/freelances')->assertOk()->assertSee('Awa Dessin')->assertSee('Zoé Web')->assertDontSee('Brouillon Non Publié')->assertDontSee('Suspendue Cachée');
        $this->get('/freelances?competence=autocad')->assertSee('Awa Dessin')->assertDontSee('Zoé Web');
        $this->get('/freelances?categorie=btp')->assertSee('Awa Dessin')->assertDontSee('Zoé Web');       // le service programmé n'est pas public
        $this->get('/freelances?prix_max=40000&delai_max=5')->assertSee('Awa Dessin')->assertDontSee('Zoé Web');
        $this->get('/freelances?prix_max=1000')->assertSee('Aucun freelance ne correspond')->assertSee('Tout effacer');
        $this->get('/freelances?q=developpeuse')->assertSee('Zoé Web')->assertDontSee('Awa Dessin');            // accents ignorés
        $this->get('/freelances?tri=nom')->assertSeeInOrder(['Awa Dessin', 'Zoé Web']);
        $this->get('/freelances?tri=mieux-notes')->assertOk()->assertSee('Règle du tri');
        $this->get('/freelances?prix_max=abc&tri=n_importe_quoi&page=-4')->assertOk()->assertSee('Awa Dessin');   // valeurs invalides ignorées
        $this->get('/freelances')->assertDontSee('★');                                                           // aucune note inventée
    }

    public function test_mission_filters_sorts_and_visibility(): void
    {
        $cat = Category::factory()->create(['slug' => 'trad']);
        $client = User::factory()->create();
        $mk = function (string $title, int $budget, int $days, ?string $status = 'open') use ($cat, $client) {
            $mid = (string) Str::uuid();
            $vid = (string) Str::uuid();
            DB::table('missions')->insert(['id' => $mid, 'client_id' => $client->id, 'slug' => Str::slug($title).'-'.Str::random(4), 'status' => $status, 'published_at' => now()->subDays(abs($days)), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('mission_versions')->insert(['id' => $vid, 'mission_id' => $mid, 'number' => 1, 'state' => 'published', 'category_id' => $cat->id, 'title' => $title, 'description' => 'Description de la mission '.$title.'.',
                'budget_xof' => $budget, 'application_deadline' => now()->addDays($days), 'published_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('missions')->where('id', $mid)->update(['published_version_id' => $vid]);
        };
        $mk('Mission courte', 20000, 3);
        $mk('Mission longue', 300000, 40);
        $mk('Mission fermée', 25000, 5, 'closed');
        $this->get('/missions')->assertSee('Mission courte')->assertSee('Mission longue')->assertDontSee('Mission fermée');
        $this->get('/missions?budget_max=50000')->assertSee('Mission courte')->assertDontSee('Mission longue');
        $this->get('/missions?budget_min=100000')->assertSee('Mission longue')->assertDontSee('Mission courte');
        $this->get('/missions?delai=7')->assertSee('Mission courte')->assertDontSee('Mission longue');
        $this->get('/missions?tri=budget-decroissant')->assertSeeInOrder(['Mission longue', 'Mission courte']);
        $this->get('/missions?tri=budget-croissant')->assertSeeInOrder(['Mission courte', 'Mission longue']);
        $this->get('/missions?budget_min=900000')->assertSee('Aucune mission ouverte ne correspond')->assertSee('Retirez un filtre');
        $this->get('/missions?budget_max=0')->assertSessionHasErrors('budget_max');
        $page = app(PublicMissions::class)->search(null, null, 1, ['tri' => 'echeance']);
        $this->assertSame(2, $page->total());
        $this->assertSame(['Mission courte'], $page->pluck('title')->all());
    }

    public function test_missions_page_follows_the_services_layout_and_pages_by_nine(): void
    {
        $cat = Category::factory()->create(['slug' => 'trad']);
        $client = User::factory()->create();
        foreach (range(1, 11) as $n) {
            $mid = (string) Str::uuid();
            $vid = (string) Str::uuid();
            DB::table('missions')->insert(['id' => $mid, 'client_id' => $client->id, 'slug' => 'mission-'.$n, 'status' => 'open', 'published_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('mission_versions')->insert(['id' => $vid, 'mission_id' => $mid, 'number' => 1, 'state' => 'published', 'category_id' => $cat->id, 'title' => 'Mission numéro '.$n, 'description' => 'Description '.$n,
                'budget_xof' => 10000 * $n, 'application_deadline' => now()->addDays($n)->addHours(2), 'published_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('missions')->where('id', $mid)->update(['published_version_id' => $vid]);
        }
        $page = $this->get('/missions')->assertOk()->assertSee('Trouvez votre prochain projet.')->assertSee('Accueil')->assertSee('Tout voir')->assertSee('11 missions ouvertes')
            ->assertSee('Vous avez un savoir-faire à proposer ?')->assertSee('Page 1 sur 2')->assertSee('J-1');
        $this->assertSame(9, substr_count($page->getContent(), 'class="card mission-card mc-new"'));
        $this->get('/missions?page=2')->assertOk()->assertSee('Page 2 sur 2');
        $this->get('/missions?budget_min=50000&budget_max=150000&tri=budget-croissant')->assertOk()->assertSee('Mission numéro 5')->assertDontSee('Mission numéro 4');
    }
}

<?php

namespace Tests\Feature;

use App\Livewire\Catalog\ServiceSearch;
use App\Modules\Catalog\Actions\SearchServices;
use App\Modules\Catalog\Data\ServiceSearchCriteria;
use App\Modules\Catalog\Enums\ServiceStatus;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogSearchTest extends TestCase
{
    use RefreshDatabase;

    private function search(?string $q = null, ?string $cat = null, ?string $sort = null, int $page = 1)
    {
        return app(SearchServices::class)(ServiceSearchCriteria::make($q, $cat, $sort), $page);
    }

    public function test_search_ignores_accents_and_case_and_matches_word_forms_and_prefixes(): void
    {
        Service::factory()->create(['title' => 'Créer un logo élégant', 'summary' => 'Identité visuelle']);
        Service::factory()->create(['title' => 'Traduction juridique', 'summary' => 'Documents']);

        $this->assertSame(['Créer un logo élégant'], $this->search('CREER logo')->pluck('title')->all());
        $this->assertSame(['Créer un logo élégant'], $this->search('elegant')->pluck('title')->all());
        $this->assertSame(['Créer un logo élégant'], $this->search('identite')->pluck('title')->all());   // texte du résumé, sans accent
        $this->assertSame(['Traduction juridique'], $this->search('trad')->pluck('title')->all());     // préfixe de titre
        $this->assertCount(0, $this->search('inexistant'));
    }

    public function test_search_never_returns_unpublished_services(): void
    {
        Service::factory()->status(ServiceStatus::Draft)->create(['title' => 'Logo brouillon']);
        Service::factory()->create(['title' => 'Logo publié']);

        $this->assertSame(['Logo publié'], $this->search('logo')->pluck('title')->all());
    }

    public function test_special_characters_are_treated_as_text_not_as_sql_or_wildcards(): void
    {
        Service::factory()->create(['title' => 'Logo']);

        foreach (['%', '_', "'", '"; DROP TABLE services; --', '\\', '"', '&|!', str_repeat('a', 500)] as $q) {
            $this->assertCount(0, $this->search($q), $q);
        }
        $this->assertSame(1, Service::count());
    }

    public function test_category_filter(): void
    {
        $a = Category::factory()->create();
        $b = Category::factory()->create();
        Service::factory()->create(['title' => 'Dans A', 'category_id' => $a->id]);
        Service::factory()->create(['title' => 'Dans B', 'category_id' => $b->id]);

        $this->assertSame(['Dans A'], $this->search(null, $a->slug)->pluck('title')->all());
        $this->assertCount(0, $this->search(null, 'categorie-inconnue'));
        $this->assertCount(2, $this->search());
    }

    public function test_sort_orders(): void
    {
        Service::factory()->create(['title' => 'Moyen', 'price_xof' => 20000, 'published_at' => now()->subDays(2)]);
        Service::factory()->create(['title' => 'Cher', 'price_xof' => 90000, 'published_at' => now()->subDays(3)]);
        Service::factory()->create(['title' => 'Bon marché', 'price_xof' => 5000, 'published_at' => now()->subDay()]);

        $this->assertSame(['Bon marché', 'Moyen', 'Cher'], $this->search(null, null, 'prix-croissant')->pluck('title')->all());
        $this->assertSame(['Cher', 'Moyen', 'Bon marché'], $this->search(null, null, 'prix-decroissant')->pluck('title')->all());
        $this->assertSame(['Bon marché', 'Moyen', 'Cher'], $this->search(null, null, 'recents')->pluck('title')->all());
        $this->assertSame(['Bon marché', 'Moyen', 'Cher'], $this->search(null, null, 'tri-invalide')->pluck('title')->all());
    }

    public function test_pagination_is_capped_and_stable(): void
    {
        Service::factory()->count(15)->create();

        $p1 = $this->search();
        $p2 = $this->search(page: 2);
        $this->assertSame(ServiceSearchCriteria::PER_PAGE, $p1->count());
        $this->assertLessThanOrEqual(20, ServiceSearchCriteria::PER_PAGE);
        $this->assertSame(3, $p2->count());
        $this->assertSame(15, $p1->total());
        $this->assertEmpty(array_intersect($p1->pluck('slug')->all(), $p2->pluck('slug')->all()));

        $this->get('/services?page=2')->assertOk()->assertSee('Page 2 sur 2');
    }

    public function test_url_state_drives_the_page_without_javascript(): void
    {
        $c = Category::factory()->create();
        Service::factory()->create(['title' => 'Logo vert', 'category_id' => $c->id]);
        Service::factory()->create(['title' => 'Site bleu']);

        $this->get('/services?q=logo&categorie='.$c->slug)->assertOk()->assertSee('Logo vert')->assertDontSee('Site bleu');
        $this->get('/services?q=zzz')->assertOk()->assertSee('Aucun service ne correspond')->assertSee('Tout effacer');
    }

    public function test_livewire_component_filters_and_clears(): void
    {
        $c = Category::factory()->create();
        Service::factory()->create(['title' => 'Logo vert', 'category_id' => $c->id]);
        Service::factory()->create(['title' => 'Site bleu']);

        Livewire::test(ServiceSearch::class)
            ->assertSee('Logo vert')->assertSee('Site bleu')
            ->set('q', 'logo')->assertSee('Logo vert')->assertDontSee('Site bleu')
            ->call('clear')->assertSee('Site bleu')
            ->set('categorie', $c->slug)->assertSee('Logo vert')->assertDontSee('Site bleu')
            ->set('tri', 'valeur-invalide')->assertSee('Logo vert');
    }
}

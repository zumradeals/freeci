<?php

namespace Tests\Feature;

use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Enums\ServiceStatus;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Service;
use Database\Seeders\DemoCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Accueil : décisions de présentation du lot 30 (8 services, catégories utiles avec leur nombre, entrées « Devenir freelance » et « Publier une mission »). */
class HomeShowcaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_shows_up_to_eight_recent_services(): void
    {
        Service::factory()->count(10)->create();
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertSame(8, preg_match_all('/class="svc[ "]/', $html), 'huit cartes de service exactement');
    }

    public function test_empty_categories_stay_visible_and_counts_appear_only_when_there_are_services(): void
    {
        $used = Category::factory()->create(['name' => 'Catégorie utilisée']);
        $empty = Category::factory()->create(['name' => 'Catégorie vide']);
        $archived = Category::factory()->create(['name' => 'Catégorie archivée', 'archived_at' => now()]);
        Service::factory()->count(2)->create(['category_id' => $used->id]);
        Service::factory()->status(ServiceStatus::Draft)->create(['category_id' => $empty->id]);

        $this->get('/')->assertOk()->assertSee('Catégorie utilisée')->assertSee('2 services')->assertSee('Catégorie vide')->assertDontSee('0 service')->assertDontSee('Catégorie archivée');
    }

    public function test_visitors_see_become_freelance_and_publish_a_mission_as_a_button(): void
    {
        $page = $this->get('/')->assertOk();
        $page->assertSee('Devenir freelance')->assertSee('btn btn-secondary btn-lg', false)->assertSee('Publier une mission');
        $page->assertDontSee('brouillons tant qu’elles ne sont pas adoptées');

        $this->actingAs(User::factory()->create())->get('/')->assertOk()->assertDontSee('Devenir freelance');
    }

    public function test_the_demo_seeder_fills_the_home_with_the_eight_cards_of_the_validated_mockup(): void
    {
        $this->seed(DemoCatalogSeeder::class);
        $page = $this->get('/')->assertOk();
        foreach (['Convertir vos plans PDF en fichiers AutoCAD (DWG)', 'Créer votre logo et une mini charte graphique', 'Site vitrine de 5 pages, prêt à publier', 'Traduire un document du français vers l’anglais (10 pages)',
            'Affiche d’événement au format A3, prête à imprimer', 'Note de calcul de structure pour une dalle béton', 'Montage d’une vidéo de présentation de 90 secondes', 'Calendrier de publications pour vos réseaux sociaux'] as $title) {
            $page->assertSee($title);
        }
        $this->assertSame(8, preg_match_all('/class="svc[ "]/', $page->getContent()));
    }
}

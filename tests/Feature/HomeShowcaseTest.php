<?php

namespace Tests\Feature;

use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Enums\ServiceStatus;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Service;
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

    public function test_categories_without_published_service_are_hidden_and_counts_are_shown(): void
    {
        $used = Category::factory()->create(['name' => 'Catégorie utilisée']);
        $empty = Category::factory()->create(['name' => 'Catégorie vide']);
        Service::factory()->count(2)->create(['category_id' => $used->id]);
        Service::factory()->status(ServiceStatus::Draft)->create(['category_id' => $empty->id]);

        $this->get('/')->assertOk()->assertSee('Catégorie utilisée')->assertSee('2 services')->assertDontSee('Catégorie vide');
    }

    public function test_when_no_service_exists_all_categories_remain_visible_without_a_count(): void
    {
        Category::factory()->create(['name' => 'Catégorie de lancement']);

        $this->get('/')->assertOk()->assertSee('Catégorie de lancement')->assertDontSee('0 service');
    }

    public function test_visitors_see_become_freelance_and_publish_a_mission_as_a_button(): void
    {
        $page = $this->get('/')->assertOk();
        $page->assertSee('Devenir freelance')->assertSee('btn btn-secondary btn-lg', false)->assertSee('Publier une mission');
        $page->assertDontSee('brouillons tant qu’elles ne sont pas adoptées');

        $this->actingAs(User::factory()->create())->get('/')->assertOk()->assertDontSee('Devenir freelance');
    }
}

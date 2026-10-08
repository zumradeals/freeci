<?php

namespace Tests\Feature;

use App\Modules\Catalog\Enums\ServiceStatus;
use App\Modules\Catalog\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_published_services_are_listed_and_viewable(): void
    {
        $pub = Service::factory()->create(['title' => 'Visible publiée']);
        $draft = Service::factory()->status(ServiceStatus::Draft)->create(['title' => 'Brouillon secret']);
        $review = Service::factory()->status(ServiceStatus::InReview)->create(['title' => 'En contrôle secret']);
        $sched = Service::factory()->scheduled()->create(['title' => 'Programmée secrète']);

        $this->get('/services')->assertOk()->assertSee('Visible publiée')
            ->assertDontSee('Brouillon secret')->assertDontSee('En contrôle secret')->assertDontSee('Programmée secrète');
        $this->get('/')->assertOk()->assertSee('Visible publiée')->assertDontSee('Brouillon secret');

        $this->get('/services/'.$pub->slug)->assertOk()->assertSee('Visible publiée');
        foreach ([$draft, $review, $sched] as $hidden) {
            $this->get('/services/'.$hidden->slug)->assertNotFound();
        }
    }

    public function test_withdrawn_services_show_a_neutral_page_without_private_data(): void
    {
        foreach ([ServiceStatus::Suspended, ServiceStatus::Archived] as $status) {
            $s = Service::factory()->status($status)->create(['title' => 'Titre retiré']);
            $r = $this->get('/services/'.$s->slug);
            $r->assertStatus(410)->assertSee('Ce service n’est plus disponible')->assertDontSee('Titre retiré');
            $this->get('/services')->assertDontSee('Titre retiré');
        }
    }

    public function test_unknown_slug_is_404(): void
    {
        $this->get('/services/inexistant')->assertNotFound();
    }

    public function test_public_pages_never_expose_account_data(): void
    {
        $s = Service::factory()->create();
        $email = $s->freelanceProfile->user->email;

        $this->get('/services/'.$s->slug)->assertDontSee($email);
        $this->get('/services')->assertDontSee($email);
    }

    public function test_service_page_announces_that_ordering_is_not_available(): void
    {
        $s = Service::factory()->create();

        $this->get('/services/'.$s->slug)->assertSee('Demander cette prestation')->assertSee('Aucun paiement à cette étape');
    }

    public function test_service_page_shows_the_offer_card_assurances_and_other_services_of_the_category(): void
    {
        $s = Service::factory()->create();
        $other = Service::factory()->create(['category_id' => $s->category_id]);

        $page = $this->get('/services/'.$s->slug)->assertOk()->assertSee('Accueil')->assertSee('Prix, délai et périmètre figés dès l’accord')->assertSee('Vous validez la livraison')
            ->assertSee('À propos du freelance')->assertSee('Autres services :')->assertSee($other->title);
        $this->assertSame(1, substr_count($page->getContent(), 'class="svc"'));
    }

    public function test_empty_catalog_is_honest(): void
    {
        $this->get('/services')->assertOk()->assertSee('Les premiers services seront publiés ici.');
        $this->get('/')->assertOk()->assertSee('Les premiers services seront publiés ici.');
    }
}

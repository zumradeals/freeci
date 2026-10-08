<?php

namespace Tests\Feature;

use App\Modules\Accounts\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Lot 61 : pages d'erreur en français, même langage visuel ; 500 et 503 autonomes (sans dépendre du gabarit du site). */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_unknown_page_answers_404_with_the_shared_layout_and_shortcuts(): void
    {
        $this->get('/une-page-qui-n-existe-pas')->assertNotFound()->assertSee('Page introuvable')->assertSee('Erreur 404')
            ->assertSee('Cette page est introuvable, ou vous n’y avez pas accès.')->assertSee('er-links', false)->assertSee('Parcourir les services');
    }

    public function test_expired_and_throttled_pages_are_french_and_say_what_to_do(): void
    {
        $this->assertStringContainsString('Votre page a expiré', view('errors.419')->render());
        $this->assertStringContainsString('Rien n’a été enregistré', view('errors.419')->render());
        $this->assertStringContainsString('Trop de tentatives', view('errors.429')->render());
        $this->assertStringContainsString('Patientez une minute', view('errors.429')->render());
    }

    public function test_the_500_and_503_pages_are_standalone_and_make_no_unverifiable_promise_on_500(): void
    {
        $e500 = view('errors.500')->render();
        $this->assertStringContainsString('Une erreur est survenue', $e500);
        $this->assertStringContainsString('Vos données ne sont pas modifiées par cette erreur.', $e500);
        $this->assertStringNotContainsString('aucune commande ni aucun paiement', mb_strtolower($e500));
        $this->assertStringNotContainsString('<link', $e500);
        $e503 = view('errors.503')->render();
        $this->assertStringContainsString('Service momentanément indisponible', $e503);
        $this->assertStringContainsString('aucune commande ni aucun paiement n’est perdu', $e503);
        $this->assertStringNotContainsString('<link', $e503);
    }

    public function test_the_order_error_keeps_its_message_and_back_link(): void
    {
        $this->actingAs(User::factory()->create());
        $html = view('errors.order', ['title' => 'Demande expirée', 'message' => 'Le délai est dépassé.', 'back' => '/commandes/X', 'backLabel' => 'Voir la commande'])->render();
        $this->assertStringContainsString('Demande expirée', $html);
        $this->assertStringContainsString('Le délai est dépassé.', $html);
        $this->assertStringContainsString('href="/commandes/X"', $html);
    }

    public function test_public_footer_shows_the_call_to_action_to_guests_only_and_keeps_legal_links(): void
    {
        $this->get('/une-page-qui-n-existe-pas')->assertSee('class="ft"', false)->assertSee('Un projet à confier, un talent à proposer ?')->assertSee('Proposer mes services')
            ->assertSee('Côte d’Ivoire')->assertSee('© '.now()->year.' FreeCI')->assertSee('Haut de page')->assertSee('Mentions légales');
        $this->actingAs(User::factory()->create())->get('/services')->assertOk()->assertSee('class="ft"', false)->assertDontSee('Un projet à confier, un talent à proposer ?');
    }
}

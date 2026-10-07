<?php

namespace Tests\Feature;

use App\Modules\Accounts\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Tests\Support\AdminFixtures;
use Tests\Support\OrderFixtures;
use Tests\TestCase;

/** Lot 31 — les textes de l'accueil et du pied de page se modifient dans Paramètres › Accueil et vitrine. */
class HomeContentSettingsTest extends TestCase
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

    private function save(array $v)
    {
        return $this->asAdmin($this->admin)->post('/admin/parametres/vitrine', ['v' => $this->settingsGroup('vitrine', $v), 'reason' => 'Textes de la vitrine validés.']);
    }

    public function test_the_settings_page_offers_the_showcase_group_with_the_starting_texts(): void
    {
        $this->asAdmin($this->admin)->get('/admin/parametres')->assertOk()->assertSee('Accueil et vitrine')->assertSee('Un freelance pour votre prochain projet');
    }

    public function test_saved_texts_replace_the_starting_ones_on_the_home_and_the_footer(): void
    {
        $this->save(['home_title' => 'Le bon freelance, près de chez vous', 'home_chips' => 'Plomberie, Traduction ,  Logo', 'home_tagline' => 'Une phrase de pied de page.', 'home_freelance_title' => 'Proposez vos talents'])->assertSessionHas('status');
        $this->app['auth']->forgetGuards();
        $page = $this->get('/')->assertOk()->assertSee('Le bon freelance, près de chez vous')->assertSee('Une phrase de pied de page.')->assertSee('Proposez vos talents')
            ->assertSee('Plomberie')->assertDontSee('Plan AutoCAD')->assertDontSee('Un freelance pour votre prochain projet');
        $page->assertSee('q=Traduction', false)->assertSee('q=Logo', false);
        $this->get('/services')->assertSee('Une phrase de pied de page.');
    }

    public function test_an_empty_field_falls_back_to_the_starting_text_and_html_is_escaped(): void
    {
        $this->save(['home_title' => '', 'home_lede' => '<script>alert(1)</script> Nouvelle accroche'])->assertSessionHas('status');
        $this->app['auth']->forgetGuards();
        $this->get('/')->assertOk()->assertSee('Un freelance pour votre prochain projet')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt; Nouvelle accroche', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_the_chip_list_is_limited_to_six_terms(): void
    {
        $this->save(['home_chips' => 'A1, B2, C3, D4, E5, F6, G7, H8'])->assertSessionHas('status');
        $this->app['auth']->forgetGuards();
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertSame(6, preg_match_all('/class="chip"/', $html));
        $this->assertStringNotContainsString('q=G7', $html);
    }
}

<?php

namespace Tests\Feature;

use App\Modules\Accounts\Models\User;
use App\Modules\Admin\Navigation\Destinations;
use App\Modules\Admin\Settings\SettingDefinitions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\AdminFixtures;
use Tests\Support\OrderFixtures;
use Tests\TestCase;

/** Lot 33 — menus du site, boutons de l'accueil et sujets d'assistance modifiables depuis l'administration. */
class AdminNavigationTest extends TestCase
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

    private function go(string $uri, array $data = [], bool $recent = true)
    {
        return $this->asAdmin($this->admin, $recent)->post($uri, $data);
    }

    public function test_origin_menus_apply_until_customized(): void
    {
        $this->asAdmin($this->admin)->get('/admin/navigation')->assertOk()->assertSee('Menu d’origine')->assertSee('Personnaliser ce menu');
        $this->app['auth']->forgetGuards();
        $this->get('/')->assertSee('Devenir freelance')->assertSee('Centre d’aide')->assertSee('Mentions légales');
    }

    public function test_the_header_menu_can_be_customized_reordered_hidden_and_reset(): void
    {
        $this->go('/admin/navigation/header/personnaliser')->assertSessionHas('status');
        $items = DB::table('menu_items')->where('area', 'header')->orderBy('position')->get();
        $this->assertCount(5, $items);
        $first = $items[0];
        // renommer + changer la destination, masquer un lien
        $this->go("/admin/navigation/liens/{$first->id}", ['label' => 'Catalogue', 'destination' => 'services', 'visible' => '1'])->assertSessionHas('status');
        $guestOnly = $items[4];
        $this->go("/admin/navigation/liens/{$guestOnly->id}", ['label' => 'Devenir freelance', 'destination' => 'freelance_activate'])->assertSessionHas('status');
        $this->app['auth']->forgetGuards();
        $html = $this->get('/')->getContent();
        preg_match('/<nav class="main-nav".*?<\/nav>/s', $html, $nav);
        $this->assertStringContainsString('Catalogue</a>', $nav[0]);
        $this->assertStringNotContainsString('freelance/activer', $nav[0], 'le lien masqué n’apparaît plus dans le menu');
        // ajout, déplacement, suppression
        $this->go('/admin/navigation/header/ajouter', ['label' => 'Aide', 'destination' => 'help'])->assertSessionHas('status');
        $added = DB::table('menu_items')->where('label', 'Aide')->first();
        $this->go("/admin/navigation/liens/{$added->id}/deplacer/up")->assertSessionHas('status');
        $this->assertSame(5, DB::table('menu_items')->where('id', $added->id)->value('position'));
        $this->go("/admin/navigation/liens/{$added->id}/supprimer")->assertSessionHas('status');
        $this->assertNull(DB::table('menu_items')->where('id', $added->id)->first());
        // retour à l'origine
        $this->go('/admin/navigation/header/retablir')->assertSessionHas('status');
        $this->assertSame(0, DB::table('menu_items')->where('area', 'header')->count());
        $this->app['auth']->forgetGuards();
        $this->get('/')->assertSee('>Services</a>', false);
    }

    public function test_destinations_are_a_closed_list_and_labels_are_bounded(): void
    {
        $this->go('/admin/navigation/footer_help/personnaliser')->assertSessionHas('status');
        $this->go('/admin/navigation/footer_help/ajouter', ['label' => 'Piège', 'destination' => 'javascript:alert(1)'])->assertSessionHas('error');
        $this->go('/admin/navigation/footer_help/ajouter', ['label' => 'Piège', 'destination' => 'https://exemple.test'])->assertSessionHas('error');
        $this->go('/admin/navigation/footer_help/ajouter', ['label' => 'x', 'destination' => 'help'])->assertSessionHas('error');
        $this->assertNull(DB::table('menu_items')->where('label', 'Piège')->first());
        $this->assertNull(Destinations::url('javascript:alert(1)'));
        $this->assertNull(Destinations::url(null));
        $this->go('/admin/navigation/zone-inconnue/personnaliser')->assertSessionHas('error');
    }

    public function test_the_main_menu_keeps_at_least_one_visible_link_and_writes_need_a_recent_identity(): void
    {
        $this->go('/admin/navigation/header/personnaliser');
        $ids = DB::table('menu_items')->where('area', 'header')->orderBy('position')->pluck('id')->all();
        foreach (array_slice($ids, 1) as $id) {
            $this->go("/admin/navigation/liens/{$id}/supprimer")->assertSessionHas('status');
        }
        $this->go("/admin/navigation/liens/{$ids[0]}/supprimer")->assertSessionHas('error');
        $this->go("/admin/navigation/liens/{$ids[0]}", ['label' => 'Seul lien', 'destination' => 'services', 'visible' => '1'])->assertSessionHas('status');
        $this->go('/admin/navigation/header/ajouter', ['label' => 'Autre', 'destination' => 'missions'], false)->assertRedirect();
        $this->assertSame(1, DB::table('menu_items')->where('area', 'header')->count());
    }

    /** Valeurs actuelles d'un groupe de paramètres, comme le formulaire les envoie, avec quelques remplacements. */
    private function group(string $group, array $override): array
    {
        $v = [];
        foreach (SettingDefinitions::groups()[$group]['keys'] as $key) {
            $def = SettingDefinitions::all()[$key];
            $value = config($def['path']);
            $v[str_replace('.', '_', $key)] = is_bool($value) ? ($value ? '1' : '0') : (string) ($value ?? '');
        }

        return array_merge($v, $override);
    }

    public function test_home_buttons_and_support_subjects_are_editable(): void
    {
        $this->asAdmin($this->admin)->post('/admin/parametres/vitrine', ['v' => $this->group('vitrine', ['home_mission_btn_label' => 'Décrire mon besoin', 'home_mission_btn_dest' => 'missions', 'home_freelance_btn_label' => 'Rejoindre FreeCI', 'home_freelance_btn_dest' => 'register']), 'reason' => 'Boutons de la vitrine validés.'])->assertSessionHas('status');
        $this->app['auth']->forgetGuards();
        $this->get('/')->assertSee('Décrire mon besoin')->assertSee('Rejoindre FreeCI')->assertSee('href="'.route('register').'"', false);
        $this->asAdmin($this->admin)->post('/admin/parametres/vitrine', ['v' => $this->group('vitrine', ['home_mission_btn_dest' => 'javascript:alert(1)']), 'reason' => 'Tentative de lien libre.'])->assertSessionHas('error');

        $this->asAdmin($this->admin)->post('/admin/parametres/echanges', ['v' => $this->group('echanges', ['support_subjects_order' => 'Ma commande en cours', 'support_subjects_other' => '']), 'reason' => 'Sujets d’assistance validés.'])->assertSessionHas('status');
        $this->actingAs($this->client)->get('/espace/assistance/nouvelle')->assertOk()->assertSee('Ma commande en cours')->assertSee('Un paiement')->assertSee('Autre');
    }
}

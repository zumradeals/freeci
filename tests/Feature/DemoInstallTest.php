<?php

namespace Tests\Feature;

use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\AdminFixtures;
use Tests\Support\OrderFixtures;
use Tests\TestCase;

/** Lot 35 — installation volontaire de la démonstration depuis l'administration, sans variable d'environnement. */
class DemoInstallTest extends TestCase
{
    use AdminFixtures, OrderFixtures, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private_files');
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->admin = $this->readyAdmin();
    }

    public function test_the_admin_installs_the_eight_demo_cards_without_the_environment_flag(): void
    {
        config(['freeci.allow_demo_seed' => false]);
        $this->asAdmin($this->admin)->post('/admin/exploitation/demo/installer', ['phrase' => 'oui'])->assertSessionHas('error');
        $this->assertSame(0, DB::table('services')->count());
        $this->asAdmin($this->admin, false)->post('/admin/exploitation/demo/installer', ['phrase' => 'INSTALLER LA DEMO'])->assertRedirect();
        $this->assertSame(0, DB::table('services')->count(), 'identité à reconfirmer');

        $this->asAdmin($this->admin)->post('/admin/exploitation/demo/installer', ['phrase' => 'installer la demo'])->assertSessionHas('status');
        $this->app['auth']->forgetGuards();
        $html = $this->get('/')->assertOk()->assertSee('Note de calcul de structure pour une dalle béton')->getContent();
        $this->assertSame(8, preg_match_all('/class="svc[ "]/', $html));
        $this->assertSame(1, DB::table('admin_actions')->where('action', 'demo.install')->where('result', 'done')->count());
        // aucun compte de connexion créé : seuls les vendeurs d'exemple (non connectables)
        $this->assertNull(User::where('email', 'client@demo.freeci.invalid')->first());
        $this->assertSame(0, User::where('is_demo', true)->whereNotNull('email_verified_at')->count());
    }

    public function test_existing_categories_are_never_renamed_by_the_installation(): void
    {
        Category::factory()->create(['slug' => 'btp-et-architecture', 'name' => 'Bâtiment (nom choisi par l’administrateur)', 'position' => 7]);
        $this->asAdmin($this->admin)->post('/admin/exploitation/demo/installer', ['phrase' => 'INSTALLER LA DEMO'])->assertSessionHas('status');
        $c = Category::where('slug', 'btp-et-architecture')->first();
        $this->assertSame(['Bâtiment (nom choisi par l’administrateur)', 7], [$c->name, $c->position]);
        $this->assertSame(1, Category::where('slug', 'btp-et-architecture')->count());
    }

    public function test_the_console_command_installs_and_reports_the_visible_cards(): void
    {
        $this->artisan('freeci:demo-install', ['--yes' => true])->expectsOutputToContain('L\'accueil affiche 8 carte(s)')->assertSuccessful();
    }

    public function test_the_installation_repairs_archived_services_and_suspended_vendors_and_the_page_explains_the_cause(): void
    {
        $this->asAdmin($this->admin)->post('/admin/exploitation/demo/installer', ['phrase' => 'INSTALLER LA DEMO'])->assertSessionHas('status');
        // état observé après un retrait partiel : services archivés, un vendeur suspendu, un profil dépublié
        DB::table('services')->where('is_demo', true)->update(['status' => 'archived']);
        $vendor = User::where('is_demo', true)->first();
        $vendor->forceFill(['suspended_at' => now()])->save();
        DB::table('freelance_profiles')->where('is_demo', true)->update(['published_at' => null]);
        $this->asAdmin($this->admin)->get('/admin/exploitation')->assertOk()->assertSee('0</strong> carte(s) sur 8', false)->assertSee('archivé(s)')->assertSee('Installer ou remettre en ligne la démonstration');

        $this->asAdmin($this->admin)->post('/admin/exploitation/demo/installer', ['phrase' => 'INSTALLER LA DEMO'])->assertSessionHas('status');
        $this->assertNull($vendor->fresh()->suspended_at);
        $this->assertSame(0, DB::table('freelance_profiles')->where('is_demo', true)->whereNull('published_at')->count());
        $this->app['auth']->forgetGuards();
        $this->assertSame(8, preg_match_all('/class="svc[ "]/', $this->get('/')->getContent()));
        $this->asAdmin($this->admin)->get('/admin/exploitation')->assertSee('8</strong> carte(s) sur 8', false);
    }

    public function test_demo_profiles_are_published_so_the_directory_and_profile_pages_are_not_empty(): void
    {
        $this->asAdmin($this->admin)->post('/admin/exploitation/demo/installer', ['phrase' => 'INSTALLER LA DEMO'])->assertSessionHas('status');
        $this->app['auth']->forgetGuards();
        $this->get('/freelances')->assertOk()->assertSee('9 freelances')->assertSee('Kader Soro')->assertSee('Voir le profil');
        $this->get('/freelances/kader-soro-demo')->assertOk()->assertSee('Présentation')->assertSee('AutoCAD')->assertSee('profil d’exemple');
    }
}

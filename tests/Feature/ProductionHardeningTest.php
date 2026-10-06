<?php

namespace Tests\Feature;

use App\Modules\Catalog\Models\Category;
use App\Providers\AppServiceProvider;
use Dotenv\Dotenv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductionHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        DB::prohibitDestructiveCommands(false);
        parent::tearDown();
    }

    public function test_security_headers_are_sent_and_hsts_only_over_https(): void
    {
        config(['freeci.hsts_max_age' => 3600]);

        $http = $this->get('/');
        $http->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertFalse($http->headers->has('Strict-Transport-Security'));

        $https = $this->get('https://freeci.example/');
        $https->assertHeader('Strict-Transport-Security', 'max-age=3600');
        $this->assertStringNotContainsString('includeSubDomains', $https->headers->get('Strict-Transport-Security'));
        $this->assertStringNotContainsString('preload', $https->headers->get('Strict-Transport-Security'));
    }

    public function test_hsts_can_be_disabled(): void
    {
        config(['freeci.hsts_max_age' => 0]);

        $this->assertFalse($this->get('https://freeci.example/')->headers->has('Strict-Transport-Security'));
    }

    public function test_noindex_is_configurable(): void
    {
        config(['freeci.noindex' => true]);
        $this->get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSee('content="noindex, nofollow"', false);

        config(['freeci.noindex' => false]);
        $r = $this->get('/');
        $this->assertFalse($r->headers->has('X-Robots-Tag'));
        $r->assertDontSee('noindex', false);
        // les pages privées restent non indexables dans tous les cas
        $this->get('/connexion')->assertSee('noindex', false);
    }

    public function test_the_test_mode_banner_follows_the_payment_mode(): void
    {
        config(['freeci.payments.mode' => 'sandbox']);
        $this->get('/')->assertSee('Mode test — aucun argent réel');

        config(['freeci.payments.mode' => 'live']);
        $this->get('/')->assertDontSee('Mode test — aucun argent réel');

        config(['freeci.payments.mode' => 'nimportequoi']);              // valeur inconnue : jamais interprétée comme live
        $this->get('/')->assertSee('Mode test — aucun argent réel');
    }

    public function test_public_catalog_has_no_global_restriction(): void
    {
        foreach (['/', '/services'] as $u) {
            $this->get($u)->assertOk();
        }
    }

    public function test_robots_txt_hides_private_paths(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));
        foreach (['/espace', '/connexion', '/inscription', '/reinitialisation', '/livewire'] as $p) {
            $this->assertStringContainsString("Disallow: {$p}", $robots);
        }
        $this->assertStringNotContainsString("Disallow: /\n", $robots);
    }

    public function test_destructive_database_commands_are_prohibited_in_production(): void
    {
        $this->app['env'] = 'production';
        (new AppServiceProvider($this->app))->boot();

        foreach (['migrate:fresh', 'migrate:refresh', 'migrate:reset', 'db:wipe'] as $command) {
            $this->assertSame(1, Artisan::call($command), $command);
            $this->assertStringContainsString('prohibited', Artisan::output(), $command);
        }
        $this->assertSame(1, Category::query()->count() + 1, 'la base est intacte');
    }

    public function test_https_app_url_forces_https_links(): void
    {
        config(['app.url' => 'https://freeci.example']);
        (new AppServiceProvider($this->app))->boot();

        $this->assertStringStartsWith('https://', route('services.index'));
    }

    public function test_preflight_fails_on_unsafe_configuration_and_passes_on_a_safe_one(): void
    {
        $this->app['env'] = 'production';
        config(['app.debug' => true, 'app.url' => 'http://localhost']);
        $this->assertSame(1, Artisan::call('freeci:preflight'));
        $this->assertStringContainsString('ÉCHEC', Artisan::output());

        config([
            'app.debug' => false, 'app.url' => 'https://freeci.example', 'session.secure' => true,
            'session.http_only' => true, 'session.same_site' => 'lax', 'freeci.allow_demo_seed' => false,
            'freeci.demo_client_password' => null,
        ]);
        $code = Artisan::call('freeci:preflight');
        $this->assertSame(0, $code, Artisan::output());
    }

    public function test_preflight_refuses_a_demo_reseed_flag_with_a_warning(): void
    {
        $this->app['env'] = 'production';
        config(['freeci.allow_demo_seed' => true]);
        Artisan::call('freeci:preflight');

        $this->assertMatchesRegularExpression('/non réinjectables\s*\|\s*AVERT\./u', Artisan::output());
    }

    public function test_env_templates_are_valid_dotenv_files(): void
    {
        foreach ([base_path('.env.example'), base_path('deploy/env.production.example')] as $file) {
            $vars = Dotenv::parse(file_get_contents($file));
            $this->assertArrayHasKey('APP_URL', $vars, $file);
            $this->assertSame('', $vars['APP_KEY'] ?? '', 'aucune clé dans le modèle : '.$file);
        }
    }
}

<?php

namespace Tests\Feature;

use App\Modules\Accounts\Actions\GrantAdministrator;
use App\Modules\Accounts\Models\User;
use App\Modules\Missions\Actions\MissionModeration;
use App\Modules\Missions\Models\Mission;
use App\Shared\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\AdminFixtures;
use Tests\Support\OrderFixtures;
use Tests\TestCase;

/** F-16 — robots.txt, plan du site, aperçus de partage et données structurées. */
class SeoTest extends TestCase
{
    use AdminFixtures, OrderFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://freeci.example']);
        $this->setUpParties();
        $this->makeReal();
    }

    /** Les fabriques créent des données de démonstration : le plan du site les exclut, donc on les « réalise » pour ces tests. */
    private function makeReal(): void
    {
        DB::table('services')->update(['is_demo' => false]);
        DB::table('freelance_profiles')->update(['is_demo' => false]);
    }

    private function open(): void
    {
        config(['freeci.noindex' => false]);
    }

    private function jsonld(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

        return array_map(fn ($j) => json_decode($j, true, 512, JSON_THROW_ON_ERROR), $m[1]);
    }

    private function meta(string $html, string $attr, string $name): ?string
    {
        return preg_match('#<meta '.$attr.'="'.preg_quote($name, '#').'" content="([^"]*)"#', $html, $m) ? html_entity_decode($m[1]) : null;
    }

    // ---- robots.txt et plan du site -----------------------------------------------------------------------------------------------------------------

    public function test_robots_follow_the_hide_from_search_engines_setting(): void
    {
        config(['freeci.noindex' => true]);
        $r = $this->get('/robots.txt')->assertOk();
        $this->assertStringContainsString('text/plain', $r->headers->get('Content-Type'));
        $this->assertSame("User-agent: *\nDisallow: /\n", $r->getContent());
        $this->get('/sitemap.xml')->assertNotFound();
        $this->get('/sitemap-services.xml')->assertNotFound();

        $this->open();
        $body = $this->get('/robots.txt')->assertOk()->getContent();
        foreach (['Disallow: /admin', 'Disallow: /espace', 'Disallow: /commandes', 'Disallow: /services/*/demande', 'Disallow: /missions/*/proposition', 'Sitemap: https://freeci.example/sitemap.xml'] as $line) {
            $this->assertStringContainsString($line, $body);
        }
        $this->assertStringNotContainsString("Disallow: /\n", $body);
        $this->assertFileDoesNotExist(public_path('robots.txt'), 'le fichier statique ne doit plus masquer la route');
    }

    public function test_sitemap_lists_only_public_current_content(): void
    {
        $this->open();
        $admin = User::factory()->create();
        app(GrantAdministrator::class)($admin, 'test');
        $this->actingAs($this->client)->post('/espace/missions', ['title' => 'Plans d’une villa', 'category_id' => $this->service->category_id])->assertRedirect();
        $m = Mission::firstOrFail();
        $v = $m->versions()->first();
        $this->actingAs($this->client)->post("/espace/missions/{$m->id}/modifier", ['title' => $v->title, 'category_id' => $this->service->category_id, 'description' => str_repeat('Villa R+1, plans complets à produire en plusieurs étapes. ', 4),
            'budget_xof' => '120000', 'application_deadline' => now()->addDays(5)->format('Y-m-d'), 'client_inputs' => 'Surface', 'revision_no' => $v->revision_no])->assertRedirect();
        $this->actingAs($this->client)->post("/espace/missions/{$m->id}/soumettre", ['revision_no' => $v->fresh()->revision_no])->assertRedirect();
        app(MissionModeration::class)->approve($admin, $v->id);
        $m->refresh();
        DB::table('missions')->where('id', $m->id)->update(['is_demo' => false]);

        $page = $this->get('/missions/'.$m->slug)->assertOk()->getContent();
        $ld = $this->jsonld($page);
        $this->assertCount(1, $ld, 'une mission n’est pas une offre d’emploi : fil d’Ariane seulement');
        $this->assertSame('BreadcrumbList', $ld[0]['@type']);
        $this->assertStringContainsString('120', (string) $this->meta($page, 'name', 'description'));
        $this->assertStringNotContainsString($this->client->email, $page);

        $index = simplexml_load_string($this->get('/sitemap.xml')->assertOk()->getContent());
        $this->assertCount(4, $index->sitemap);
        $this->assertStringStartsWith('https://freeci.example/sitemap-', (string) $index->sitemap[0]->loc);

        $load = function (string $k): array {
            $out = [];
            foreach (simplexml_load_string($this->get("/sitemap-{$k}.xml")->assertOk()->getContent())->url as $u) {
                $out[] = ['loc' => (string) $u->loc, 'lastmod' => (string) $u->lastmod];
            }

            return $out;
        };
        $svc = $load('services');
        $this->assertSame('https://freeci.example/services/'.$this->service->slug, $svc[0]['loc']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $svc[0]['lastmod']);
        $this->assertSame('https://freeci.example/freelances/'.$this->freelancer->freelanceProfile->slug, $load('freelances')[0]['loc']);
        $this->assertSame(['https://freeci.example/missions/'.$m->slug], array_column($load('missions'), 'loc'));
        $pages = array_column($load('pages'), 'loc');
        foreach (['https://freeci.example/', 'https://freeci.example/services', 'https://freeci.example/freelances', 'https://freeci.example/missions'] as $u) {
            $this->assertContains($u, $pages);
        }
        $cat = DB::table('categories')->where('id', $this->service->category_id)->value('slug');
        $this->assertContains('https://freeci.example/services?categorie='.$cat, $pages);
        $this->assertContains('https://freeci.example/missions?categorie='.$cat, $pages);
        $this->assertNotContains('https://freeci.example/informations/mentions-legales', $pages, 'une page légale non adoptée est exclue');

        // exclusions : démonstration, brouillon, vendeur suspendu, mission expirée, profil non publié
        DB::table('services')->where('id', $this->service->id)->update(['is_demo' => true]);
        $this->assertSame([], $load('services'));
        DB::table('services')->update(['is_demo' => false, 'status' => 'draft']);
        $this->assertSame([], $load('services'));
        DB::table('services')->update(['status' => 'published']);
        DB::table('users')->where('id', $this->freelancer->id)->update(['suspended_at' => now()]);
        $this->assertSame([], $load('services'));
        $this->assertSame([], $load('freelances'));
        DB::table('users')->where('id', $this->freelancer->id)->update(['suspended_at' => null]);
        DB::table('freelance_profiles')->update(['published_at' => null]);
        $this->assertSame([], $load('freelances'));
        DB::table('missions')->where('id', $m->id)->update(['status' => 'expired']);
        $this->assertSame([], $load('missions'));
        foreach (array_merge($load('pages'), $svc) as $u) {
            $this->assertStringNotContainsString('/espace', $u['loc']);
            $this->assertStringNotContainsString('/admin', $u['loc']);
            $this->assertStringNotContainsString('/commandes', $u['loc']);
        }
        $this->get('/sitemap-inconnu.xml')->assertNotFound();
    }

    // ---- aperçus de partage et canonique ------------------------------------------------------------------------------------------------------------

    public function test_service_page_has_share_tags_canonical_and_valid_structured_data(): void
    {
        $this->open();
        $this->service->update(['title' => 'Plans </script><script>alert(1)</script> en DWG']);
        $html = $this->get('/services/'.$this->service->slug)->assertOk()->getContent();
        $url = 'https://freeci.example/services/'.$this->service->slug;

        $this->assertStringContainsString('<link rel="canonical" href="'.$url.'">', $html);
        $this->assertSame($url, $this->meta($html, 'property', 'og:url'));
        $this->assertSame('website', $this->meta($html, 'property', 'og:type'));
        $this->assertSame('summary_large_image', $this->meta($html, 'name', 'twitter:card'));
        $this->assertSame('FreeCI', $this->meta($html, 'property', 'og:site_name'));
        $this->assertStringStartsWith('https://freeci.example/', (string) $this->meta($html, 'property', 'og:image'));
        $this->assertLessThanOrEqual(160, mb_strlen((string) $this->meta($html, 'name', 'description')));
        $this->assertStringContainsString('FCFA', (string) $this->meta($html, 'name', 'description'));
        $this->assertStringNotContainsString('index', (string) $this->meta($html, 'name', 'robots'));
        $this->assertStringNotContainsString('</script><script>alert(1)', $html, 'aucune balise injectable dans les données structurées ni les métadonnées');

        $ld = $this->jsonld($html);
        $this->assertCount(2, $ld);
        $this->assertSame('Service', $ld[0]['@type']);
        $this->assertSame('XOF', $ld[0]['offers']['priceCurrency']);
        $this->assertSame((string) $this->service->price_xof, $ld[0]['offers']['price']);
        $this->assertSame('Kader Freelance', $ld[0]['provider']['name']);
        $this->assertArrayNotHasKey('aggregateRating', $ld[0], 'sans avis public, aucune note');
        $this->assertSame('BreadcrumbList', $ld[1]['@type']);
        $this->assertSame($url, $ld[1]['itemListElement'][2]['item']);
        $this->assertStringNotContainsString($this->freelancer->email, $html);
        $this->assertStringNotContainsString('"email"', $html);
    }

    public function test_profile_mission_and_home_structured_data(): void
    {
        $this->open();
        $slug = $this->freelancer->freelanceProfile->slug;
        $html = $this->get("/freelances/{$slug}")->assertOk()->getContent();
        $ld = $this->jsonld($html);
        $this->assertSame('Person', $ld[0]['@type']);
        $this->assertSame('Kader Freelance', $ld[0]['name']);
        $this->assertSame('profile', $this->meta($html, 'property', 'og:type'));
        $this->assertStringContainsString('"jobTitle":"Dessinateur"', $html);
        $this->assertStringNotContainsString($this->freelancer->email, $html);

        $home = $this->get('/')->assertOk()->getContent();
        $g = $this->jsonld($home)[0]['@graph'];
        $this->assertSame(['Organization', 'WebSite'], array_column($g, '@type'));
        $this->assertStringEndsWith('/services?q={terme}', $g[1]['potentialAction']['target']['urlTemplate']);
        $this->assertStringContainsString('/images/og-default.png', (string) $this->meta($home, 'property', 'og:image'));
        $this->assertFileExists(public_path('images/og-default.png'));
        $this->assertSame('PNG', strtoupper(pathinfo(public_path('images/og-default.png'), PATHINFO_EXTENSION)));
        [$w, $h] = getimagesize(public_path('images/og-default.png'));
        $this->assertSame([1200, 630], [$w, $h]);
    }

    public function test_share_tags_stay_on_while_indexing_is_blocked(): void
    {
        config(['freeci.noindex' => true]);
        $html = $this->get('/services/'.$this->service->slug)->assertOk()->getContent();
        $this->assertSame('noindex, nofollow', $this->meta($html, 'name', 'robots'));
        $this->assertNotNull($this->meta($html, 'property', 'og:title'), 'les aperçus de partage restent actifs');
        $this->assertStringContainsString('application/ld+json', $html);
    }

    public function test_filtered_lists_are_not_indexable_and_point_to_the_clean_list(): void
    {
        $this->open();
        $clean = $this->get('/services')->assertOk()->getContent();
        $this->assertNull($this->meta($clean, 'name', 'robots'));
        $this->assertStringContainsString('<link rel="canonical" href="https://freeci.example/services">', $clean);

        foreach (['/services?q=plans', '/services?tri=prix', '/missions?budget_min=1000', '/freelances?q=dessin'] as $u) {
            $h = $this->get($u)->assertOk()->getContent();
            $this->assertSame('noindex, follow', $this->meta($h, 'name', 'robots'), $u);
            $this->assertStringContainsString('<link rel="canonical" href="https://freeci.example/'.explode('?', ltrim($u, '/'))[0].'">', $h, $u);
        }
        $cat = DB::table('categories')->where('id', $this->service->category_id)->value('slug');
        $h = $this->get("/services?categorie={$cat}&page=2&utm_source=x")->getContent();
        $this->assertStringContainsString('<link rel="canonical" href="https://freeci.example/services?categorie='.$cat.'&amp;page=2">', $h);
        $this->assertNull($this->meta($h, 'name', 'robots'), 'catégorie et pagination restent indexables, sans paramètre de suivi');
    }

    public function test_private_pages_carry_no_share_tags_or_structured_data(): void
    {
        $this->open();
        foreach (['/connexion', '/inscription'] as $u) {
            $h = $this->get($u)->assertOk()->getContent();
            $this->assertStringNotContainsString('og:title', $h, $u);
            $this->assertStringNotContainsString('rel="canonical"', $h, $u);
            $this->assertStringNotContainsString('ld+json', $h, $u);
            $this->assertStringContainsString('noindex', (string) $this->meta($h, 'name', 'robots'));
        }
        $h = $this->actingAs($this->client)->get('/espace')->getContent();
        $this->assertStringNotContainsString('og:title', $h);
    }

    // ---- aides ----------------------------------------------------------------------------------------------------------------------------------------

    public function test_helpers(): void
    {
        $this->assertSame('Court.', Seo::trim("  Court.\n\n", 50));
        $long = str_repeat('mot ', 80);
        $t = Seo::trim($long, 60);
        $this->assertLessThanOrEqual(60, mb_strlen($t));
        $this->assertStringEndsWith('…', $t);
        $this->assertStringNotContainsString('  ', $t);
        $this->assertSame('Texte propre', Seo::trim('<b>Texte</b> <i>propre</i>', 50));
        $this->assertStringContainsString('\\u003C', Seo::json(['a' => '</script>']));
        $this->assertSame('https://freeci.example/images/og-default.png', Seo::absolute(null));
        $this->assertSame('https://freeci.example/medias/x/large', Seo::absolute('/medias/x/large'));
        $this->assertNull(Seo::raster('/img/demo/plan.svg'));
        $this->assertSame('/medias/x/large', Seo::raster('/medias/x/large'));
        $this->assertSame('https://cdn.exemple/a.png', Seo::absolute('https://cdn.exemple/a.png'));

        $s = Seo::service(['title' => 'T', 'summary' => 'S', 'url' => 'https://x/y', 'price' => 25000, 'seller' => 'A', 'image' => null, 'city' => 'Abidjan', 'category' => 'C', 'rating' => ['avg' => '4,6', 'count' => 12]]);
        $this->assertSame('4.6', $s['aggregateRating']['ratingValue']);
        $this->assertSame(12, $s['aggregateRating']['reviewCount']);
        $this->assertSame('Abidjan', $s['provider']['address']['addressLocality']);
        $this->assertArrayNotHasKey('aggregateRating', Seo::person(['name' => 'N', 'headline' => 'H', 'url' => 'u', 'city' => null, 'image' => null, 'rating' => ['avg' => '5', 'count' => 0]]));
    }

    public function test_operations_page_reports_the_seo_state(): void
    {
        $admin = $this->readyAdmin();
        config(['freeci.noindex' => true]);
        $this->asAdmin($admin)->get('/admin/exploitation')->assertOk()->assertSee('Référencement')->assertSee('Site masqué aux moteurs');
        config(['freeci.noindex' => false, 'app.url' => 'http://localhost']);
        $this->asAdmin($admin)->get('/admin/exploitation')->assertSee('n’est pas l’adresse publique en https');
        config(['app.url' => 'https://freeci.net']);
        $this->asAdmin($admin)->get('/admin/exploitation')->assertSee('Indexation ouverte : robots.txt et plan du site publiés');
    }
}

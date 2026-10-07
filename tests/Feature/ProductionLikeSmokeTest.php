<?php

namespace Tests\Feature;

use App\Modules\Accounts\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Support\AdminFixtures;
use Tests\Support\OrderFixtures;
use Tests\TestCase;

/**
 * Filet de sécurité : parcourt TOUTES les pages (GET) de l'application avec un cache et des réglages proches de la production, deux fois de suite
 * (la seconde requête lit ce que la première a mis en cache), avant et après des enregistrements de paramètres et de textes légaux.
 * Une page répondant 5xx fait échouer le test. Né d'un défaut réel : des objets mis en cache revenaient « incomplets » avec le cache « database »
 * (cache.serializable_classes = false), page 500 invisible avec le cache en mémoire des autres tests.
 */
class ProductionLikeSmokeTest extends TestCase
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
        // Réglages de production : cache en base (sérialisé), file en base, courrier journalisé, débogage désactivé.
        config(['cache.default' => 'database', 'queue.default' => 'database', 'app.debug' => false]);
        Cache::flush();
    }

    /** @return list<array{0: string, 1: string}> [chemin, acteur] */
    private function pages(string $orderRef): array
    {
        $fixed = [
            "/commandes/{$orderRef}", "/commandes/{$orderRef}/livraison", "/commandes/{$orderRef}/litige", "/commandes/{$orderRef}/avis", "/commandes/{$orderRef}/paiement",
            "/services/{$this->service->slug}", '/freelances/'.$this->freelancer->freelanceProfile->slug, "/freelance/services/{$this->service->id}/modifier",
            '/informations/conditions', '/informations/mentions-legales', '/informations/contact', '/admin/pages/conditions',
        ];
        $out = [];
        foreach ($fixed as $path) {
            $out[] = [$path, str_starts_with($path, '/admin') ? 'admin' : (str_starts_with($path, '/freelance/') ? 'freelancer' : (str_starts_with($path, '/commandes') ? 'client' : 'guest'))];
        }
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uri = '/'.ltrim($route->uri(), '/');
            if (! in_array('GET', $route->methods(), true) || str_contains($uri, '{') || str_starts_with($uri, '/webhooks') || str_starts_with($uri, '/livewire') || str_contains($uri, 'telescope') || str_starts_with($uri, '/up') || str_starts_with($uri, '/storage')) {
                continue;
            }
            $out[] = [$uri, str_starts_with($uri, '/admin') ? 'admin' : (str_starts_with($uri, '/freelance') ? 'freelancer' : (str_starts_with($uri, '/espace') ? 'client' : 'guest'))];
        }

        return $out;
    }

    private function visit(string $path, string $actor)
    {
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        return match ($actor) {
            'admin' => $this->asAdmin($this->admin)->get($path),
            'freelancer' => $this->actingAs($this->freelancer)->get($path),
            'client' => $this->actingAs($this->client)->get($path),
            default => $this->get($path),
        };
    }

    private function sweep(string $label, array $pages): void
    {
        $failures = [];
        foreach ($pages as [$path, $actor]) {
            $status = $this->visit($path, $actor)->getStatusCode();
            if ($status >= 500) {
                $failures[] = "{$status} {$path} ({$actor})";
            }
        }
        $this->assertSame([], $failures, "{$label} : pages en erreur serveur");
    }

    public function test_every_page_survives_a_database_cache_before_and_after_settings_are_saved(): void
    {
        $order = $this->inProgress();
        $pages = $this->pages($order->reference);
        $this->assertGreaterThan(50, count($pages), 'le parcours doit couvrir l’ensemble des pages');

        $this->sweep('1re passe (cache vide)', $pages);
        $this->sweep('2e passe (cache rempli)', $pages);

        // Enregistrements représentatifs : paramètres (avec approbation), texte légal publié, paramètre secret.
        $form = ['orders_response_hours' => '36', 'orders_payment_hours' => '24', 'orders_review_days' => '7', 'orders_extension_max_days' => '30', 'missions_selection_days' => '14', 'reviews_publication_days' => '14', 'account_closure_grace_days' => '14'];
        $this->asAdmin($this->admin)->post('/admin/parametres/delais', ['v' => $form, 'reason' => 'Réglage de recette.', 'approve' => '1'])->assertSessionHas('status');
        $this->asAdmin($this->admin)->post('/admin/parametres/courrier', ['v' => ['mail_default' => 'log', 'mail_host' => 'smtp.example.test', 'mail_port' => '587', 'mail_scheme' => '', 'mail_username' => 'u', 'mail_password' => 'secret-value-123', 'mail_from_address' => 'a@example.test', 'mail_from_name' => 'FreeCI'], 'reason' => 'Réglage de recette.'])->assertSessionHas('status');
        $this->asAdmin($this->admin)->post('/admin/pages/conditions/brouillon', ['body' => "## Article 1\n\nTexte."])->assertSessionHas('status');
        $this->asAdmin($this->admin)->post('/admin/pages/conditions/publier', ['reason' => 'Relu et adopté.', 'confirm' => '1'])->assertSessionHas('status');

        $this->sweep('3e passe (après enregistrements)', $pages);
        $this->sweep('4e passe (cache relu)', $pages);
        $this->assertSame('36', (string) config('freeci.orders.response_hours'));
        $this->assertSame(1, DB::table('legal_pages')->where('slug', 'conditions')->count());
    }
}

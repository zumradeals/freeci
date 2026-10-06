<?php

namespace Tests\Feature;

use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Models\Service;
use App\Modules\Orders\Models\Order;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\OrderFixtures;
use Tests\TestCase;

class OrderSpacesTest extends TestCase
{
    use OrderFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpParties();
    }

    public function test_dashboards_have_honest_empty_states(): void
    {
        $this->actingAs($this->client)->get('/espace')->assertOk()->assertSee('Par où commencer ?')->assertSee('Choisir un service')->assertSee('Activer l’espace freelance')->assertDontSee('Autres informations');
        $this->actingAs($this->freelancer)->get('/freelance')->assertOk()->assertSee('Aucune demande à traiter.')->assertSee('Vos services');
        $this->actingAs($this->client)->get('/espace/commandes')->assertSee('Aucune commande pour l’instant.');
    }

    public function test_freelance_tasks_are_ranked_by_real_deadline(): void
    {
        $second = User::factory()->create(['name' => 'Second Client']);
        $first = $this->placeOrder();                 // échéance de réponse : T + 48 h
        $this->travel(5)->hours();
        $later = $this->placeOrder($second);          // échéance : T + 53 h

        $html = $this->actingAs($this->freelancer)->get('/freelance')->getContent();
        $this->assertLessThan(strpos($html, $later->reference), strpos($html, $first->reference), 'la demande dont l’échéance est la plus proche passe d’abord');
        $this->assertStringContainsString('Répondre avant le', $html);
    }

    public function test_undated_client_tasks_never_precede_dated_ones(): void
    {
        $this->enableSandbox();
        $a = $this->placeOrder();
        $this->accept($a);
        $this->travel(1)->minute();
        $b = $this->placeOrder();
        $this->accept($b);
        DB::table('orders')->where('id', $b->id)->update(['payment_deadline_at' => now()->addHours(5)]);   // b a une échéance, a n'en a pas

        $html = $this->actingAs($this->client)->get('/espace')->getContent();
        $pos = fn ($ref) => strpos($html, $ref);
        $taskA = strpos(substr($html, 0, strpos($html, 'id="h-orders"')), $a->reference);
        $taskB = strpos(substr($html, 0, strpos($html, 'id="h-orders"')), $b->reference);
        $this->assertNotFalse($taskA);
        $this->assertLessThan($taskA, $taskB, 'la tâche datée précède la tâche sans échéance');
        $this->assertNotFalse($pos($a->reference));
    }

    public function test_dashboard_shows_real_orders_and_counts(): void
    {
        $order = $this->placeOrder();
        $this->actingAs($this->client)->get('/espace')->assertSee($order->reference)->assertSee('En attente de réponse')->assertSee('Réponse attendue avant le');
        $this->actingAs($this->freelancer)->get('/freelance')->assertSee('Répondre à la demande')->assertSee('Fanta Client');
        $this->actingAs($this->freelancer)->get('/freelance/commandes')->assertSee('À accepter');
        $this->accept($order);
        $this->actingAs($this->client)->get('/espace')->assertSee('En attente de paiement')->assertSee('Paiement non ouvert pour le moment');
        $this->actingAs($this->freelancer)->get('/freelance/commandes')->assertSee('En attente de paiement')->assertDontSee('À accepter');
    }

    public function test_freelance_space_needs_activation_with_a_minimal_profile(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/freelance')->assertRedirect(route('freelance.activate'));
        $this->actingAs($user)->get('/freelance/activer')->assertOk()->assertSee('Activer l’espace freelance');

        $this->actingAs($user)->post('/freelance/profil', ['display_name' => 'A', 'headline' => 'x'])->assertSessionHasErrors(['display_name', 'headline']);
        $this->assertFalse($user->fresh()->hasRole('freelance'));

        $this->actingAs($user)->post('/freelance/profil', ['display_name' => 'Awa Test', 'headline' => 'Traductrice', 'city' => 'Abidjan'])->assertRedirect(route('freelance.dashboard'));
        $this->assertTrue($user->fresh()->hasRole('freelance'));
        $this->assertSame('Traductrice', $user->fresh()->freelanceProfile->headline);
        $this->actingAs($user)->get('/freelance')->assertOk()->assertSee('Créer un service');
        $this->actingAs($user)->get('/freelance/activer')->assertRedirect(route('freelance.dashboard'));

        $this->actingAs($user)->post('/freelance/profil', ['display_name' => 'Awa Test', 'headline' => 'Traductrice FR-EN']);
        $this->assertSame(1, $user->fresh()->freelanceProfile()->count());
        $this->assertSame('Traductrice FR-EN', $user->fresh()->freelanceProfile->headline);
    }

    public function test_recette_command_builds_a_two_account_journey_without_touching_existing_accounts_or_publishing_secrets(): void
    {
        $existing = User::factory()->create(['email' => 'recette.client@demo.freeci.invalid', 'password' => 'Mot-de-passe-existant-1']);
        $hash = $existing->password;

        $this->assertSame(0, Artisan::call('freeci:demo:recette', ['--yes' => true]));
        $out = Artisan::output();
        preg_match('/recette\.freelance@demo\.freeci\.invalid — mot de passe généré[^:]*: (\S+)/u', $out, $m);
        $freelancePassword = $m[1] ?? '';
        $this->assertGreaterThanOrEqual(18, strlen($freelancePassword));
        $this->assertStringNotContainsString('recette.client@demo.freeci.invalid — mot de passe', $out, 'compte existant : ni modifié ni affiché');
        $this->assertSame($hash, $existing->fresh()->password);
        $this->assertTrue(Hash::check($freelancePassword, User::where('email', 'recette.freelance@demo.freeci.invalid')->first()->password));

        // idempotent : relancer ne recrée rien et n'affiche aucun mot de passe
        Artisan::call('freeci:demo:recette', ['--yes' => true]);
        $this->assertStringNotContainsString('mot de passe généré', Artisan::output());
        $this->assertSame(1, DB::table('services')->where('slug', 'service-de-recette-mise-en-plan')->count());

        // parcours complet avec les deux comptes de recette
        $this->post('/connexion', ['email' => 'recette.freelance@demo.freeci.invalid', 'password' => $freelancePassword])->assertRedirect();
        $this->post('/deconnexion');
        $client = User::where('email', 'recette.client@demo.freeci.invalid')->first();
        $service = Service::where('slug', 'service-de-recette-mise-en-plan')->first();
        $this->actingAs($client)->post('/services/'.$service->slug.'/demande', [
            'service_version' => $service->row_version, 'operation_key' => 'recette-1', 'answers' => ['60 m²', '3', 'Plan de mon appartement'], 'conditions' => '1',
        ])->assertRedirect();
        $order = Order::firstOrFail();
        $this->assertTrue($order->is_demo);
        $freelancer = User::where('email', 'recette.freelance@demo.freeci.invalid')->first();
        $this->actingAs($freelancer)->post("/commandes/{$order->reference}/accept", ['expected_version' => 1, 'operation_key' => 'recette-2'])->assertRedirect();
        $this->assertSame('awaiting_payment', $order->fresh()->state->value);
    }

    public function test_recette_command_refuses_production_without_the_explicit_flag(): void
    {
        $this->app['env'] = 'production';
        config(['freeci.allow_demo_seed' => false]);

        $this->assertSame(1, Artisan::call('freeci:demo:recette', ['--yes' => true]));
        $this->assertSame(0, User::where('email', 'like', 'recette.%')->count());
        $this->assertDoesNotMatchRegularExpression('/Hash::make\([\'"][^\'"]+[\'"]\)|[\'"]password[\'"]\s*=>\s*[\'"][^\'"]+[\'"]/', file_get_contents(app_path('Console/Commands/DemoRecette.php')));
    }

    public function test_demo_catalog_services_do_not_accept_requests_and_purge_keeps_ordered_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->assertFalse(Service::where('slug', 'convertir-plans-pdf-en-dwg')->value('accepts_requests'));
        $this->assertSame(0, Service::where('slug', '!=', $this->service->slug)->where('is_demo', true)->where('accepts_requests', true)->count(), 'seul le service de recette/fixture accepte des demandes');

        $this->placeOrder();                                               // commande réelle sur le service du freelance (non démo)
        Artisan::call('freeci:demo-purge', ['--force' => true]);
        $this->assertSame(1, Order::count());
        $this->assertTrue($this->service->fresh()->exists);
        $this->assertTrue($this->client->fresh()->exists);
    }
}

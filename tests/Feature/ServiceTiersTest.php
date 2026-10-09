<?php

namespace Tests\Feature;

use App\Modules\Accounts\Actions\GrantAdministrator;
use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\ServiceVersion;
use App\Modules\Catalog\Moderation\ServiceModeration;
use App\Modules\Catalog\Support\ServiceTiers;
use App\Modules\Orders\Models\Order;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\OrderFixtures;
use Tests\TestCase;

/** F-08 — offres à niveaux (2 ou 3 formules) et options payantes : rédaction, contrôle, affichage, demande, accord figé. */
class ServiceTiersTest extends TestCase
{
    use OrderFixtures, RefreshDatabase;

    private const TIERS = [
        ['name' => 'Essentiel', 'price_xof' => 25000, 'delivery_days' => 3, 'revisions_included' => 1, 'includes' => ['Plan 2D coté d’une pièce', 'Export PDF']],
        ['name' => 'Standard', 'price_xof' => 45000, 'delivery_days' => 5, 'revisions_included' => 2, 'includes' => ['Plans 2D cotés (3 pièces)', 'Export PDF et DWG']],
        ['name' => 'Complet', 'price_xof' => 80000, 'delivery_days' => 8, 'revisions_included' => 3, 'includes' => ['Plans 2D cotés (6 pièces)', 'Coupe et façade simples']],
    ];

    private const OPTIONS = [
        ['label' => 'Livraison express', 'price_xof' => 8000, 'delivery_days' => -2],
        ['label' => 'Une correction supplémentaire', 'price_xof' => 5000, 'delivery_days' => 0],
        ['label' => 'Plan de mobilier', 'price_xof' => 10000, 'delivery_days' => 2],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        Storage::fake('private_files');
        $this->setUpParties();
    }

    private function tiered(bool $withOptions = true): void
    {
        $this->service->update(['tiers' => self::TIERS, 'options' => $withOptions ? self::OPTIONS : null, 'price_xof' => 25000, 'delivery_days' => 3, 'revisions_included' => 1]);
    }

    private function request(array $o = [])
    {
        return $this->actingAs($this->client)->post('/services/'.$this->service->slug.'/demande', $this->requestPayload(null, $o));
    }

    public function test_the_public_page_shows_the_tiers_the_options_and_a_from_price(): void
    {
        $this->tiered();
        $this->actingAs($this->client)->get('/services/'.$this->service->slug)->assertOk()->assertSee('Choisissez une formule')->assertSee('Essentiel')->assertSee('Standard')->assertSee('Complet')
            ->assertSee('Plan 2D coté d’une pièce')->assertSee('Options payantes')->assertSee('Plan de mobilier')->assertSee('À partir de')->assertDontSee('populaire');
        $this->get('/services')->assertOk()->assertSee('3 formules')->assertSee('À partir de');
        // une offre unique sans option : aucune section ni mention
        $other = Service::factory()->create(['freelance_profile_id' => $this->service->freelance_profile_id, 'title' => 'Service à offre unique']);
        $this->get('/services/'.$other->slug)->assertOk()->assertDontSee('Choisissez une formule')->assertDontSee('Options payantes');
    }

    public function test_the_request_page_needs_a_valid_tier_and_shows_the_server_computed_summary(): void
    {
        $this->tiered();
        $url = '/services/'.$this->service->slug.'/demande';
        $this->actingAs($this->client)->get($url)->assertRedirect('/services/'.$this->service->slug);
        $this->actingAs($this->client)->get($url.'?formule=9')->assertRedirect('/services/'.$this->service->slug);
        $this->actingAs($this->client)->get($url.'?formule=2&options[]=1&options[]=3')->assertOk()->assertSee('Votre sélection')->assertSee('Standard')->assertSee('Livraison express')
            ->assertSee('Plan de mobilier')->assertSee('Total');
        $this->actingAs($this->client)->get($url.'?formule=2&options[]=9')->assertRedirect('/services/'.$this->service->slug);
    }

    public function test_the_agreement_freezes_the_tier_the_options_and_the_totals_computed_by_the_server(): void
    {
        $this->enableSandbox();
        $this->tiered();
        // le client n'envoie AUCUN montant : seulement des numéros ; tout est recalculé
        $this->request(['tier' => 2, 'options' => [1, 3]])->assertRedirect();
        $a = DB::table('order_agreements')->first();
        $this->assertSame('Standard', $a->tier_name);
        $this->assertSame([45000 + 8000 + 10000, 5 - 2 + 2, 2, 45000, 5], [(int) $a->price_xof, (int) $a->delivery_days, (int) $a->revisions_included, (int) $a->base_price_xof, (int) $a->base_delivery_days]);
        $this->assertSame([['label' => 'Livraison express', 'price_xof' => 8000, 'delivery_days' => -2], ['label' => 'Plan de mobilier', 'price_xof' => 10000, 'delivery_days' => 2]], json_decode($a->selected_options, true));
        $deliverables = json_decode($a->deliverables, true);
        $this->assertContains('Export PDF et DWG', $deliverables);
        $this->assertContains('Option : Plan de mobilier', $deliverables);

        // le service change ensuite : l'accord ne bouge pas
        $this->service->update(['tiers' => array_map(fn ($t) => ['price_xof' => $t['price_xof'] * 2] + $t, self::TIERS)]);
        $this->assertSame(63000, (int) DB::table('order_agreements')->value('price_xof'));

        // la commande acceptée puis payée est facturée au total
        $order = Order::query()->firstOrFail();
        $this->actingAs($this->client)->get('/commandes/'.$order->reference)->assertOk()->assertSee('Standard')->assertSee('Options retenues')->assertSee('Livraison express');
    }

    public function test_a_missing_or_invalid_choice_is_refused_and_creates_nothing(): void
    {
        $this->tiered();
        $this->request()->assertSessionHasErrors('tier');
        $this->request(['tier' => 7])->assertSessionHasErrors();
        $this->request(['tier' => 1, 'options' => [8]])->assertSessionHasErrors();
        $this->assertSame(0, DB::table('orders')->count());
        // un montant glissé dans la requête est ignoré
        $this->request(['tier' => 1, 'price_xof' => 1])->assertSessionHasNoErrors();
        $this->assertSame(25000, (int) DB::table('order_agreements')->value('price_xof'));
    }

    public function test_a_single_offer_with_options_adds_them_to_the_price_and_without_options_nothing_changes(): void
    {
        $this->service->update(['options' => self::OPTIONS]);
        $base = (int) $this->service->fresh()->price_xof;
        $this->request(['options' => [2]])->assertRedirect();
        $a = DB::table('order_agreements')->first();
        $this->assertSame([$base + 5000, null], [(int) $a->price_xof, $a->tier_name]);
    }

    public function test_the_total_delay_never_goes_below_one_day(): void
    {
        $r = ServiceTiers::resolve([['name' => 'A', 'price_xof' => 10000, 'delivery_days' => 1, 'revisions_included' => 0, 'includes' => ['x']], ['name' => 'B', 'price_xof' => 20000, 'delivery_days' => 2, 'revisions_included' => 0, 'includes' => ['y']]],
            [['label' => 'Express', 'price_xof' => 5000, 'delivery_days' => -5]], 1, [1], 10000, 1, 0);
        $this->assertSame([15000, 1], [$r['price'], $r['days']]);
        $this->expectException(ValidationException::class);
        ServiceTiers::resolve(self::TIERS, null, null, [], 0, 0, 0);
    }

    public function test_the_rules_refuse_decreasing_prices_over_cap_totals_private_contacts_and_too_many_items(): void
    {
        $n = fn (array $in) => ServiceTiers::normalize(['pricing_mode' => 'tiers'] + $in);
        $row = fn (string $name, $price, $days = 3) => ['name' => $name, 'price_xof' => (string) $price, 'delivery_days' => (string) $days, 'revisions_included' => '1', 'includes' => 'élément'];
        $errors = fn (array $in) => ServiceTiers::errors($n($in)['tiers'], $n($in)['options'], true);

        $this->assertSame([], $errors(['tiers' => [$row('Essentiel', 25000), $row('Standard', 45000)]]));
        $this->assertArrayHasKey('tiers.1.price_xof', $errors(['tiers' => [$row('Essentiel', 45000), $row('Standard', 25000)]]));
        $this->assertArrayHasKey('tiers.1.price_xof', $errors(['tiers' => [$row('Essentiel', 25000), $row('Standard', 25000)]]));
        $this->assertArrayHasKey('tiers', $errors(['tiers' => [$row('Essentiel', 25000)]]));
        $this->assertArrayHasKey('tiers.1.name', $errors(['tiers' => [$row('Essentiel', 25000), $row('essentiel', 30000)]]));
        $this->assertArrayHasKey('tiers.0.price_xof', $errors(['tiers' => [$row('Essentiel', 100), $row('Standard', 30000)]]));
        $this->assertArrayHasKey('tiers.0.name', $errors(['tiers' => [$row('Écrire à moi@exemple.ci', 25000), $row('Standard', 30000)]]));
        $this->assertArrayHasKey('options', $errors(['tiers' => [$row('Essentiel', 25000), $row('Standard', 450000)], 'options' => [['label' => 'Option longue', 'price_xof' => '100000', 'delivery_days' => '0']]]));
        $opts = fn (int $k) => array_map(fn ($i) => ['label' => "Option numéro $i", 'price_xof' => '1000', 'delivery_days' => '0'], range(1, $k));
        $this->assertSame([], ServiceTiers::errors(null, $n(['options' => $opts(5)])['options'], true));
        $this->assertArrayHasKey('options', ServiceTiers::errors(null, $n(['options' => $opts(6)])['options'], true));
        $this->assertArrayHasKey('options.0.price_xof', ServiceTiers::errors(null, $n(['options' => [['label' => 'Trop chère', 'price_xof' => '900000', 'delivery_days' => '0']]])['options'], true));
        $this->assertArrayHasKey('options.0.delivery_days', ServiceTiers::errors(null, $n(['options' => [['label' => 'Délai fou', 'price_xof' => '1000', 'delivery_days' => '99']]])['options'], true));
    }

    public function test_the_editor_saves_tiers_derives_price_and_delay_and_the_published_service_exposes_them(): void
    {
        $category = Category::query()->first() ?? $this->service->category;
        $admin = User::factory()->create();
        app(GrantAdministrator::class)($admin, 'test');
        $this->freelancer->freelanceProfile->update(['published_at' => now(), 'slug' => 'kader-soro', 'bio' => str_repeat('Dessinateur. ', 6), 'skills' => ['AutoCAD']]);

        $this->actingAs($this->freelancer)->post('/freelance/services', ['title' => 'Mise en plan 2D complète d’un appartement', 'category_id' => $category->id])->assertRedirect();
        $s = Service::query()->orderByDesc('created_at')->orderByDesc('id')->firstOrFail();
        $v = $s->versions()->first();
        $form = ['title' => 'Mise en plan 2D complète d’un appartement', 'category_id' => $category->id, 'summary' => 'Plans cotés au format PDF et DWG à partir de vos relevés.', 'scope' => str_repeat('Un logement jusqu’à 120 m², relevés fournis. ', 5),
            'pricing_mode' => 'tiers', 'price_xof' => '1', 'delivery_days' => '1', 'revisions_included' => '0', 'deliverables' => 'Un plan coté', 'client_inputs' => 'Surface', 'delivery_mode' => 'message', 'revision_no' => $v->revision_no, 'intent' => 'save',
            'tiers' => [
                ['name' => 'Standard', 'price_xof' => '45 000', 'delivery_days' => '5', 'revisions_included' => '2', 'includes' => "Plans cotés\nExport DWG"],
                ['name' => 'Essentiel', 'price_xof' => '25000', 'delivery_days' => '3', 'revisions_included' => '1', 'includes' => 'Plan coté'],
                ['name' => '', 'price_xof' => '', 'delivery_days' => '', 'revisions_included' => '', 'includes' => ''],
            ],
            'options' => [['label' => 'Plan de mobilier', 'price_xof' => '10 000', 'delivery_days' => '2'], ['label' => '', 'price_xof' => '', 'delivery_days' => '']]];
        // brouillon : les prix décroissants sont signalés
        $this->actingAs($this->freelancer)->post("/freelance/services/{$s->id}/modifier", $form)->assertSessionHasErrors('tiers.1.price_xof');
        $form['tiers'] = [array_reverse($form['tiers'])[1], array_reverse($form['tiers'])[2], $form['tiers'][2]];
        $form['tiers'] = [['name' => 'Essentiel', 'price_xof' => '25000', 'delivery_days' => '3', 'revisions_included' => '1', 'includes' => 'Plan coté'], ['name' => 'Standard', 'price_xof' => '45 000', 'delivery_days' => '5', 'revisions_included' => '2', 'includes' => "Plans cotés\nExport DWG"], ['name' => '', 'price_xof' => '', 'delivery_days' => '', 'revisions_included' => '', 'includes' => '']];
        $this->actingAs($this->freelancer)->post("/freelance/services/{$s->id}/modifier", $form)->assertSessionHasNoErrors()->assertSessionHas('status');

        $v = $s->versions()->first()->refresh();
        $this->assertSame([25000, 3, 1], [(int) $v->price_xof, (int) $v->delivery_days, (int) $v->revisions_included], 'dérivés de la formule la moins chère et la plus rapide');
        $this->assertCount(2, $v->tiers);
        $this->assertSame([['label' => 'Plan de mobilier', 'price_xof' => 10000, 'delivery_days' => 2]], $v->options);
        $this->actingAs($this->freelancer)->get("/freelance/services/{$s->id}/modifier")->assertOk()->assertSee('Formules et options')->assertSee('Essentiel')->assertSee('Plan de mobilier');

        // soumission : la modération voit les formules ; l'approbation les publie
        $v->forceFill(['images' => [['id' => (string) Str::uuid(), 'alt' => 'Plan d’étage coté', 'caption' => '']]])->save();
        $this->actingAs($this->freelancer)->post("/freelance/services/{$s->id}/soumettre", ['revision_no' => $v->revision_no])->assertRedirect()->assertSessionHas('status');
        $this->actingAs($admin)->get('/admin');
        $in = $s->versions()->where('state', 'in_review')->firstOrFail();
        try {
            DB::transaction(fn () => DB::table('service_versions')->where('id', $in->id)->update(['tiers' => json_encode([])]));       // une version soumise est gelée, formules comprises
            $this->fail('la version soumise a été modifiée');
        } catch (QueryException $e) {
            $this->assertStringContainsString('ne se modifie pas', $e->getMessage());
        }
        app(ServiceModeration::class)->approve($admin, $in->id);
        $pub = $s->refresh();
        $this->assertCount(2, $pub->tiers);
        $this->assertSame(25000, (int) $pub->price_xof);
        $this->get('/services/'.$pub->slug)->assertOk()->assertSee('Essentiel')->assertSee('Plan de mobilier');
    }

    public function test_single_offer_mode_clears_the_tiers_and_keeps_entered_values(): void
    {
        $category = $this->service->category;
        $this->freelancer->freelanceProfile->update(['published_at' => now()]);
        $this->actingAs($this->freelancer)->post('/freelance/services', ['title' => 'Mise en plan 2D complète d’un appartement', 'category_id' => $category->id])->assertRedirect();
        $s = Service::query()->orderByDesc('created_at')->orderByDesc('id')->firstOrFail();
        $v = $s->versions()->first();
        $form = ['title' => 'Mise en plan 2D complète d’un appartement', 'category_id' => $category->id, 'summary' => 'Plans cotés au format PDF et DWG à partir de vos relevés.', 'scope' => str_repeat('Un logement jusqu’à 120 m². ', 8),
            'pricing_mode' => 'single', 'price_xof' => '45000', 'delivery_days' => '6', 'revisions_included' => '2', 'deliverables' => 'Un plan', 'client_inputs' => '', 'delivery_mode' => 'message', 'revision_no' => $v->revision_no, 'intent' => 'save',
            'tiers' => [['name' => 'Ignorée', 'price_xof' => '1000', 'delivery_days' => '1', 'revisions_included' => '0', 'includes' => 'x']]];
        $this->actingAs($this->freelancer)->post("/freelance/services/{$s->id}/modifier", $form)->assertSessionHasNoErrors();
        $v->refresh();
        $this->assertNull($v->tiers);
        $this->assertSame([45000, 6], [(int) $v->price_xof, (int) $v->delivery_days]);
        $this->assertTrue(ServiceVersion::query()->whereKey($v->id)->exists());
    }
}

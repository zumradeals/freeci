<?php

namespace Tests\Feature;

use App\Modules\Accounts\Models\User;
use App\Modules\Admin\Actions\CsvExports;
use App\Modules\Admin\Queries\Statistics;
use App\Modules\Admin\Support\StatsPeriod;
use App\Modules\Orders\Models\Delivery;
use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\AdminFixtures;
use Tests\Support\OrderFixtures;
use Tests\TestCase;

/** F-17 — statistiques d'administration et exports CSV. */
class StatisticsExportsTest extends TestCase
{
    use AdminFixtures, OrderFixtures, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private_files');
        $this->setUpParties();
        $this->service->update(['delivery_requires_files' => false]);
        $this->useFakeScanner();
        $this->enableSandbox();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->admin = $this->readyAdmin(['email' => 'admin-secret@exemple.test']);
    }

    /** Commande de service payée puis validée par le client (commission figée dans l'accord). */
    private function closedOrder(): Order
    {
        $order = $this->placeOrder();
        $this->accept($order);
        $this->settle($order->fresh());
        $order->refresh();
        $this->actingAs($this->freelancer)->post("/commandes/{$order->reference}/livraison/message", ['message' => 'Voici la livraison complète, formats DWG et PDF, prête à examiner.'])->assertRedirect();
        $draft = Delivery::query()->where('order_id', $order->id)->where('state', 'draft')->firstOrFail();
        $this->actingAs($this->freelancer)->post("/commandes/{$order->reference}/livraison/soumettre", ['delivery_id' => $draft->id, 'expected_version' => $order->fresh()->row_version, 'operation_key' => (string) Str::uuid()])->assertSessionHas('status');
        $d = Delivery::query()->where('order_id', $order->id)->where('state', 'submitted')->firstOrFail();
        $this->actingAs($this->client)->post("/commandes/{$order->reference}/validation", ['delivery_id' => $d->id, 'expected_version' => $order->fresh()->row_version, 'operation_key' => (string) Str::uuid(), 'confirm' => '1'])->assertSessionHas('status');

        return $order->fresh();
    }

    private function card(array $s, string $group, string $label): array
    {
        return collect($s[$group])->firstWhere('label', $label);
    }

    // ---- période -----------------------------------------------------------------------------------------------------------------------------------

    public function test_period_defaults_presets_and_refusals_never_fail_silently(): void
    {
        $now = Carbon::parse('2026-10-10 15:00', 'UTC');
        $d = StatsPeriod::fromInput([], $now);
        $this->assertSame('30j', $d->key);
        $this->assertSame('2026-09-11', $d->from->format('Y-m-d'));
        $this->assertSame('2026-10-10', $d->lastDay()->format('Y-m-d'));
        $this->assertSame(30, $d->days());
        $this->assertSame('2026-08-12', $d->previous()[0]->format('Y-m-d'));
        $this->assertSame(365, StatsPeriod::fromInput(['periode' => '12m'], $now)->days());
        $this->assertSame('30j', StatsPeriod::fromInput(['periode' => 'nimporte'], $now)->key);

        $ok = StatsPeriod::fromInput(['periode' => 'perso', 'du' => '2026-10-01', 'au' => '2026-10-05'], $now);
        $this->assertSame('perso', $ok->key);
        $this->assertSame(5, $ok->days());
        $this->assertNull($ok->notice);
        foreach ([['2026-10-05', '2026-10-01'], ['2026-10-01', '2026-10-11'], ['2025-01-01', '2026-10-10'], ['x', 'y']] as [$du, $au]) {
            $bad = StatsPeriod::fromInput(['periode' => 'perso', 'du' => $du, 'au' => $au], $now);
            $this->assertSame('30j', $bad->key);
            $this->assertNotNull($bad->notice, "$du → $au");
        }
    }

    // ---- accès -------------------------------------------------------------------------------------------------------------------------------------

    public function test_pages_are_for_administrators_only(): void
    {
        $this->get('/admin/statistiques')->assertRedirect();
        $this->actingAs($this->client)->get('/admin/statistiques')->assertNotFound();
        $this->actingAs($this->client)->post('/admin/statistiques/exports/commandes')->assertNotFound();
        $this->asAdmin($this->admin)->get('/admin/statistiques')->assertOk()->assertSee('Statistiques')->assertSee('Commandes créées')->assertSee('Réel');
        $this->asAdmin($this->admin)->get('/admin/statistiques?periode=perso&du=2099-01-01&au=2099-01-02')->assertOk()->assertSee('Période refusée');
        $this->asAdmin($this->admin)->get('/admin/statistiques/exports')->assertOk()->assertSee('Exports CSV')->assertSee('Remboursements et reversements');
        $this->asAdmin($this->admin)->get('/admin/')->assertOk();
    }

    // ---- statistiques ------------------------------------------------------------------------------------------------------------------------------

    public function test_real_and_test_are_never_mixed_and_commission_uses_each_agreement(): void
    {
        $test = $this->closedOrder();                                                 // commande de test : validée
        $paid = (int) $test->agreement->price_xof;
        $bp = (int) $test->agreement->commission_bp;
        $p = StatsPeriod::fromInput([]);

        $real = app(Statistics::class)($p, true);
        $sandbox = app(Statistics::class)($p, false);
        $this->assertSame('0 FCFA', $this->card($real, 'finance', 'Encaissé')['value']);
        $this->assertSame('0', $this->card($real, 'activity', 'Commandes créées')['value']);
        $this->assertSame('1', $this->card($sandbox, 'activity', 'Commandes créées')['value']);
        $this->assertSame('1', $this->card($sandbox, 'activity', 'Commandes clôturées')['value']);
        $this->assertStringContainsString(number_format($paid, 0, ',', ' '), str_replace("\u{202F}", ' ', str_replace("\u{00A0}", ' ', $this->card($sandbox, 'finance', 'Encaissé')['value'])));
        $commission = intdiv($paid * $bp + 5000, 10000);
        $this->assertStringContainsString(number_format($commission, 0, ',', ' '), str_replace(["\u{202F}", "\u{00A0}"], ' ', $this->card($sandbox, 'finance', 'Commissions')['value']));
        $this->assertSame('100 %', $this->card($sandbox, 'activity', 'Taux de paiement')['value']);
        $this->assertSame('100 %', $this->card($sandbox, 'activity', 'Taux d’acceptation')['value']);

        // la même commande devenue « réelle » bascule de côté, rien n'est compté deux fois
        DB::statement('ALTER TABLE orders DISABLE TRIGGER orders_environment_fixed');        // l'environnement est immuable en production : seul ce test le force
        DB::table('orders')->where('id', $test->id)->update(['environment' => 'live']);
        DB::statement('ALTER TABLE orders ENABLE TRIGGER orders_environment_fixed');
        $real = app(Statistics::class)($p, true);
        $sandbox = app(Statistics::class)($p, false);
        $this->assertSame('1', $this->card($real, 'activity', 'Commandes créées')['value']);
        $this->assertSame('0', $this->card($sandbox, 'activity', 'Commandes créées')['value']);
        $this->assertSame('0 FCFA', $this->card($sandbox, 'finance', 'Encaissé')['value']);
        $this->assertNotSame('0 FCFA', $this->card($real, 'finance', 'Encaissé')['value']);

        // séries : un seul jour non nul, total cohérent avec les cartes
        $this->assertSame(1, array_sum(array_column($real['series']['orders']['points'], 'value')));
        $this->assertSame($paid, array_sum(array_column($real['series']['collected']['points'], 'value')));
        $this->assertCount(30, $real['series']['orders']['points']);

        // comptes de démonstration : exclus du réel
        User::factory()->create(['is_demo' => true]);
        $before = (int) $this->card(app(Statistics::class)($p, true), 'activity', 'Inscriptions')['value'];
        User::factory()->create(['is_demo' => false]);
        $this->assertSame($before + 1, (int) $this->card(app(Statistics::class)($p, true), 'activity', 'Inscriptions')['value']);

        $page = $this->asAdmin($this->admin)->get('/admin/statistiques?env=test')->assertOk();
        $page->assertSee('Test et anciennes')->assertSee('Tableau équivalent');
    }

    // ---- exports -----------------------------------------------------------------------------------------------------------------------------------

    public function test_export_requires_recent_identity_confirmation_and_is_audited(): void
    {
        $this->closedOrder();
        $url = '/admin/statistiques/exports/commandes';
        $this->asAdmin($this->admin, false)->from('/admin/statistiques/exports')->post($url, ['periode' => '30j', 'env' => 'test'])->assertRedirect(route('admin.reauth'));
        $this->assertSame(0, DB::table('admin_actions')->where('action', 'export.csv')->count());

        $r = $this->asAdmin($this->admin)->post($url, ['periode' => '30j', 'env' => 'test'])->assertOk();
        $this->assertStringContainsString('text/csv', $r->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $r->headers->get('Content-Disposition'));
        $this->assertStringContainsString('freeci-commandes-', $r->headers->get('Content-Disposition'));
        $this->assertStringContainsString('-test.csv', $r->headers->get('Content-Disposition'));
        $csv = $r->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $lines = array_values(array_filter(explode("\n", substr($csv, 3))));
        $this->assertCount(2, $lines, 'en-tête + une commande');
        $this->assertStringStartsWith('référence;origine;état;environnement;', $lines[0]);
        $cells = str_getcsv($lines[1], ';');
        $this->assertStringStartsWith('FC-', $cells[0]);
        $this->assertSame('closed', $cells[2]);
        $this->assertSame('test', $cells[3]);
        $this->assertSame('validated', $cells[6]);
        $this->assertSame('35000', $cells[8]);

        $a = DB::table('admin_actions')->where('action', 'export.csv')->first();
        $this->assertSame($this->admin->id, $a->actor_id);
        $this->assertSame('commandes', $a->target_id);
        $this->assertSame('done', $a->result);
        $this->assertSame('1 ligne', $a->detail);
        $this->assertStringContainsString('test et anciennes', $a->target_label);

        // le réel est vide : en-tête seul
        $csv = $this->asAdmin($this->admin)->post($url, ['periode' => '30j', 'env' => 'reel'])->streamedContent();
        $this->assertCount(1, array_values(array_filter(explode("\n", substr($csv, 3)))));
    }

    public function test_every_dataset_exports_and_private_data_never_leaves(): void
    {
        $o = $this->closedOrder();
        foreach (array_keys(CsvExports::catalogue()) as $k) {
            $r = $this->asAdmin($this->admin)->post("/admin/statistiques/exports/{$k}", ['periode' => '12m', 'env' => 'test'])->assertOk();
            $csv = $r->streamedContent();
            $header = str_getcsv(explode("\n", substr($csv, 3))[0], ';');
            $this->assertSame(CsvExports::catalogue()[$k]['cols'], $header, $k);
            foreach (['@exemple.test', 'admin-secret', $this->client->email, $this->freelancer->email, 'Voici la livraison', '12 plans', 'AutoCAD 2018', 'Villa R+1'] as $private) {
                $this->assertStringNotContainsString((string) $private, $csv, "$k ne doit pas contenir « $private »");
            }
        }
        $this->assertSame(count(CsvExports::catalogue()), DB::table('admin_actions')->where('action', 'export.csv')->where('result', 'done')->count());
        $this->asAdmin($this->admin)->post('/admin/statistiques/exports/inconnu', ['periode' => '30j'])->assertNotFound();

        // paiements : la commande validée apparaît avec son montant
        $csv = $this->asAdmin($this->admin)->post('/admin/statistiques/exports/paiements', ['periode' => '30j', 'env' => 'test'])->streamedContent();
        $this->assertStringContainsString($o->reference.';35000;confirmed;', $csv);
        $csv = $this->asAdmin($this->admin)->post('/admin/statistiques/exports/commissions', ['periode' => '30j', 'env' => 'test'])->streamedContent();
        $bp = (int) $o->agreement->commission_bp;
        $c = intdiv(35000 * $bp + 5000, 10000);
        $this->assertStringContainsString($o->reference.';', $csv);
        $this->assertStringContainsString(';35000;'.$bp.';'.$c.';'.(35000 - $c), $csv);
    }

    public function test_cells_are_protected_against_formula_injection(): void
    {
        foreach (['=HYPERLINK("http://x")', '+1+1', '-2+3', '@SUM(A1)', "\tcmd"] as $evil) {
            $this->assertStringStartsWith("'", CsvExports::cell($evil), $evil);
        }
        $this->assertSame('-12', CsvExports::cell(-12));
        $this->assertSame('12,5', CsvExports::cell('12,5'));
        $this->assertSame('oui', CsvExports::cell(true));
        $this->assertSame('', CsvExports::cell(null));
        $this->assertSame('ligne un ligne deux', CsvExports::cell("ligne un\nligne deux"));

        DB::table('services')->where('id', $this->service->id)->update(['title' => '=cmd|calc']);
        $csv = $this->asAdmin($this->admin)->post('/admin/statistiques/exports/services', ['periode' => '12m', 'env' => 'test'])->streamedContent();
        $this->assertStringContainsString(";'=cmd|calc;", $csv);
        $this->assertStringNotContainsString(';=cmd|calc;', $csv);
    }

    public function test_export_above_the_limit_is_refused_and_logged(): void
    {
        $this->closedOrder();
        $this->placeOrder();
        config(['freeci.exports.max_rows' => 1]);
        $this->asAdmin($this->admin)->from('/admin/statistiques/exports')->post('/admin/statistiques/exports/commandes', ['periode' => '30j', 'env' => 'test'])
            ->assertRedirect()->assertSessionHas('error');
        $a = DB::table('admin_actions')->where('action', 'export.csv')->first();
        $this->assertSame('refused', $a->result);
        $this->assertStringContainsString('réduisez la période', $a->detail);
        $this->asAdmin($this->admin)->get(route('admin.audit'))->assertOk()->assertSee('Export CSV de gestion');
    }
}

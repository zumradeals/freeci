<?php

namespace Tests\Feature;

use App\Modules\Accounts\Models\User;
use App\Modules\Admin\Settings\AppSettings;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\AdminFixtures;
use Tests\Support\OrderFixtures;
use Tests\TestCase;

/** Lot 16 — paramètres administrables et textes légaux éditables. */
class AdminSettingsTest extends TestCase
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

    private function save(string $group, array $v, array $extra = [], bool $recent = true)
    {
        return $this->asAdmin($this->admin, $recent)->post("/admin/parametres/{$group}", ['v' => $v, 'reason' => 'Décision du porteur, validée.'] + $extra);
    }

    public function test_settings_screens_are_reserved_to_administrators_and_need_recent_identity_to_write(): void
    {
        $this->actingAs($this->client)->get('/admin/parametres')->assertNotFound();
        $this->actingAs($this->client)->get('/admin/pages')->assertNotFound();
        $this->asAdmin($this->admin)->get('/admin/parametres')->assertOk()->assertSee('Commission')->assertSee('Provisoire')->assertSee('Valeur par défaut');
        $this->save('commission', ['finance_commission_bp' => '8'], ['confirm' => '1'], false)->assertRedirect(route('admin.reauth'));
        $this->assertSame(0, DB::table('app_settings')->count());
    }

    public function test_commission_applies_only_to_new_orders_and_requires_an_explicit_confirmation(): void
    {
        $this->enableSandbox();
        $old = $this->placeOrder();
        $this->assertSame(1000, $old->agreement->commission_bp);

        $this->save('commission', ['finance_commission_bp' => '8'])->assertSessionHas('error');                    // sans confirmation : refusé
        $this->assertSame(1000, (int) config('freeci.finance.commission_bp'));
        $this->save('commission', ['finance_commission_bp' => '45'], ['confirm' => '1'])->assertSessionHas('error');   // hors bornes (0 à 30 %)
        $this->save('commission', ['finance_commission_bp' => 'abc'], ['confirm' => '1'])->assertSessionHas('error');
        $this->save('commission', ['finance_commission_bp' => '7,5'], ['confirm' => '1'])->assertSessionHas('status');
        $this->assertSame(750, (int) config('freeci.finance.commission_bp'));
        $this->assertSame('proposition-non-validee', config('freeci.finance.commission_policy'), 'non approuvée : la mention provisoire demeure');

        $new = $this->placeOrder(User::factory()->create(), ['operation_key' => (string) Str::uuid()]);
        $this->assertSame(750, $new->agreement->commission_bp);
        $this->assertSame(1000, $old->fresh()->agreement->commission_bp, 'jamais de rétroactivité sur une commande existante');

        $this->save('commission', ['finance_commission_bp' => '7,5'], ['confirm' => '1', 'approve' => '1'])->assertSessionHas('status');   // approbation sans changement de valeur
        $this->assertSame('approuvee', config('freeci.finance.commission_policy'));
        $this->assertSame('approved', DB::table('app_settings')->where('key', 'finance.commission_bp')->value('status'));
        $this->assertSame($this->admin->id, DB::table('app_settings')->where('key', 'finance.commission_bp')->value('approved_by'));
        $this->save('commission', ['finance_commission_bp' => '9'], ['confirm' => '1'])->assertSessionHas('status');                     // modifier = redevient provisoire
        $this->assertSame('provisional', DB::table('app_settings')->where('key', 'finance.commission_bp')->value('status'));
        $this->assertSame('proposition-non-validee', config('freeci.finance.commission_policy'));
    }

    public function test_history_is_append_only_and_audited_and_an_unchanged_save_is_refused(): void
    {
        $this->save('delais', ['orders_response_hours' => '12', 'orders_payment_hours' => '24', 'orders_review_days' => '7', 'orders_extension_max_days' => '30', 'missions_selection_days' => '14', 'reviews_publication_days' => '14', 'account_closure_grace_days' => '14'])->assertSessionHas('status');
        $this->assertSame(12, config('freeci.orders.response_hours'));
        $c = DB::table('app_setting_changes')->first();
        $this->assertSame('orders.response_hours', $c->key);
        $this->assertSame(48, json_decode($c->old_value, true));
        $this->assertSame($this->admin->id, $c->actor_id);
        $this->assertSame(1, DB::table('admin_actions')->where('action', 'settings.update')->where('result', 'done')->count());
        $this->expectException(QueryException::class);
        DB::table('app_setting_changes')->update(['reason' => 'réécriture']);
    }

    public function test_unchanged_save_and_invalid_prices_are_refused_with_a_clear_message(): void
    {
        $this->save('prix', ['catalog_price_xof_0' => '5000', 'catalog_price_xof_1' => '500000'])->assertSessionHas('error');
        $this->assertSame(0, DB::table('app_settings')->count());
        $this->save('prix', ['catalog_price_xof_0' => '9000', 'catalog_price_xof_1' => '8000'])->assertSessionHas('error');
        $this->save('prix', ['catalog_price_xof_0' => '2 000', 'catalog_price_xof_1' => '900000'])->assertSessionHas('status');
        $this->assertSame([2000, 900000], config('freeci.catalog.price_xof'));
        $this->asAdmin($this->admin)->post('/admin/parametres/prix', ['v' => ['catalog_price_xof_0' => '2000', 'catalog_price_xof_1' => '900000'], 'reason' => 'court'])->assertSessionHas('error');
    }

    public function test_saved_values_are_applied_at_boot_and_defaults_return_when_nothing_is_saved(): void
    {
        DB::table('app_settings')->insert(['key' => 'orders.payment_hours', 'value' => json_encode(6), 'status' => 'approved', 'updated_at' => now()]);
        Cache::flush();
        AppSettings::apply();
        $this->assertSame(6, config('freeci.orders.payment_hours'));
        DB::table('app_settings')->delete();
        Cache::flush();
        AppSettings::apply();
        $this->assertSame(24, config('freeci.orders.payment_hours'));
    }

    public function test_operator_identity_and_site_toggles_are_editable_and_drive_public_pages(): void
    {
        $this->get('/')->assertSee('noindex', false);
        $this->save('site', ['noindex' => '0', 'notifications_emails' => '1'])->assertSessionHas('status');
        $this->assertFalse(config('freeci.noindex'));
        $this->get('/')->assertDontSee('content="noindex', false);
        $this->save('exploitant', ['legal_operator_name' => 'Société Test SARL', 'legal_operator_address' => 'Abidjan, Cocody', 'legal_operator_registration' => '', 'legal_publication_director' => '', 'legal_host' => '', 'legal_contact_email' => 'pas-un-mail'])->assertSessionHas('error');
        $this->save('exploitant', ['legal_operator_name' => 'Société Test SARL', 'legal_operator_address' => 'Abidjan, Cocody', 'legal_operator_registration' => '', 'legal_publication_director' => '', 'legal_host' => '', 'legal_contact_email' => 'contact@example.test'])->assertSessionHas('status');
        $this->get('/informations/mentions-legales')->assertSee('Société Test SARL')->assertSee('contact@example.test')->assertSee('Brouillon — texte non adopté');
    }

    public function test_legal_pages_draft_is_private_publishing_adopts_it_and_withdrawal_restores_the_banner(): void
    {
        $this->get('/informations/conditions')->assertSee('Brouillon — texte non adopté');
        $this->asAdmin($this->admin)->get('/admin/pages/conditions')->assertOk()->assertSee('Publier comme adopté');

        $body = "## Article 1\n\nNos conditions rédigées par l’exploitant. {exploitant}\n\n<script>alert(1)</script>\n\n- point A";
        $this->asAdmin($this->admin)->post('/admin/pages/conditions/brouillon', ['body' => $body])->assertSessionHas('status');
        $this->get('/informations/conditions')->assertDontSee('Nos conditions rédigées')->assertSee('Brouillon — texte non adopté');        // brouillon : jamais public
        $this->asAdmin($this->admin)->get('/admin/pages/conditions')->assertSee('Nos conditions rédigées')->assertDontSee('<script>alert', false);

        $this->asAdmin($this->admin)->post('/admin/pages/conditions/publier', ['reason' => 'Relu et adopté.'])->assertSessionHasErrors('confirm');
        $this->asAdmin($this->admin, false)->post('/admin/pages/conditions/publier', ['reason' => 'Relu et adopté.', 'confirm' => '1'])->assertRedirect(route('admin.reauth'));
        $this->asAdmin($this->admin)->post('/admin/pages/conditions/publier', ['reason' => 'court', 'confirm' => '1'])->assertSessionHas('error');
        $this->asAdmin($this->admin)->post('/admin/pages/conditions/publier', ['reason' => 'Relu et adopté.', 'confirm' => '1'])->assertSessionHas('status');

        $this->get('/informations/conditions')->assertOk()->assertSee('Nos conditions rédigées')->assertSee('à renseigner')->assertDontSee('Brouillon — texte non adopté')->assertDontSee('<script>alert', false);
        $this->assertSame(1, DB::table('legal_pages')->where('slug', 'conditions')->value('published_version'));
        $this->assertSame(1, DB::table('legal_page_history')->count());

        $this->asAdmin($this->admin)->post('/admin/pages/conditions/retirer', ['reason' => 'Retrait pour révision.'])->assertSessionHas('status');
        $this->get('/informations/conditions')->assertSee('Brouillon — texte non adopté')->assertDontSee('Nos conditions rédigées');
        $this->assertSame(2, DB::table('legal_page_history')->count());
        $this->actingAs($this->client)->post('/admin/pages/conditions/publier', ['reason' => 'Relu et adopté.', 'confirm' => '1'])->assertNotFound();
    }

    public function test_readiness_points_to_the_settings_screens(): void
    {
        $this->asAdmin($this->admin)->get('/admin/exploitation')->assertSee('Compléter')->assertSee(route('admin.settings'), false)->assertSee(route('admin.legal'), false);
        $this->artisan('freeci:readiness')->assertSuccessful();
    }
}

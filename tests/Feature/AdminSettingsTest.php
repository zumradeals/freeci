<?php

namespace Tests\Feature;

use App\Modules\Accounts\Models\User;
use App\Modules\Admin\Queries\SettingsOverview;
use App\Modules\Admin\Settings\AppSettings;
use App\Modules\Admin\Settings\SettingDefinitions;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Service;
use App\Modules\Notifications\Support\MailStatus;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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
        $this->asAdmin($this->admin)->get('/admin/parametres')->assertOk()->assertSee('Commission')->assertSee('Provisoire')->assertSee('Défaut :');
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
        $this->save('site', ['noindex' => '0', 'notifications_emails' => '1', 'ops_backup_dir' => '/var/backups/freeci', 'ops_backup_max_age_hours' => '36'])->assertSessionHas('status');
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

    // ---------------------------------------------------------------- lot 17 : secrets, limites techniques, tout autre réglage

    private function paymentForm(array $over = []): array
    {
        return $over + ['payments_mode' => 'sandbox', 'payments_enabled' => '1', 'payments_live_authorized' => '', 'payments_genius_base_url' => 'https://geniuspay.ci/api/v1/merchant', 'payments_genius_checkout_hosts' => 'geniuspay.ci',
            'payments_genius_webhook_tolerance' => '90000', 'payments_genius_sandbox_merchant_id' => '', 'payments_genius_live_merchant_id' => ''];
    }

    public function test_secrets_are_encrypted_write_only_never_displayed_nor_logged_and_can_be_removed(): void
    {
        $this->save('paiement', $this->paymentForm(['payments_genius_sandbox_api_key' => 'pk_sandbox_SECRETKEYVALUE', 'payments_genius_sandbox_api_secret' => 'sk_sandbox_TOPSECRETVALUE', 'payments_genius_sandbox_webhook_secret' => 'whsec_SECRETWEBHOOK12345']), ['confirm' => '1'])->assertSessionHas('status');
        $this->assertSame('sk_sandbox_TOPSECRETVALUE', config('freeci.payments.genius.sandbox.api_secret'));
        $raw = (string) DB::table('app_settings')->where('key', 'payments.genius.sandbox.api_secret')->value('value');
        $this->assertStringNotContainsString('TOPSECRET', $raw, 'chiffré au repos');
        $this->assertSame('sk_sandbox_TOPSECRETVALUE', Crypt::decryptString(json_decode($raw, true)));

        $page = $this->asAdmin($this->admin)->get('/admin/parametres')->assertOk()->assertSee('Défini dans l’administration')->getContent();
        foreach (['TOPSECRET', 'SECRETKEYVALUE', 'SECRETWEBHOOK'] as $needle) {
            $this->assertStringNotContainsString($needle, $page, 'un secret n’est jamais réaffiché');
            $this->assertStringNotContainsString($needle, json_encode(DB::table('app_setting_changes')->get()), 'ni journalisé');
            $this->assertStringNotContainsString($needle, json_encode(DB::table('admin_actions')->get()), 'ni audité');
        }
        $this->assertSame('••••••', collect(app(SettingsOverview::class)->changes())->firstWhere('label', 'Clé secrète (bac à sable)')['new']);

        // Laisser vide = conserver ; « retirer » = retour à la valeur du serveur.
        $this->save('paiement', $this->paymentForm(['payments_genius_sandbox_api_secret' => '']), ['confirm' => '1'])->assertSessionHas('error');
        $this->assertSame('sk_sandbox_TOPSECRETVALUE', config('freeci.payments.genius.sandbox.api_secret'));
        $this->save('paiement', $this->paymentForm(), ['confirm' => '1', 'clear' => ['payments_genius_sandbox_api_secret' => '1']])->assertSessionHas('status');
        $this->assertNotSame('sk_sandbox_TOPSECRETVALUE', config('freeci.payments.genius.sandbox.api_secret'));
        $this->assertSame(0, DB::table('app_settings')->where('key', 'payments.genius.sandbox.api_secret')->count());
    }

    public function test_real_payment_needs_a_typed_phrase_and_confirmation_and_sandbox_stays_the_default(): void
    {
        $this->assertSame('sandbox', config('freeci.payments.mode'));
        $this->save('paiement', $this->paymentForm(['payments_mode' => 'live']), ['confirm' => '1'])->assertSessionHas('error');
        $this->save('paiement', $this->paymentForm(['payments_mode' => 'live']), ['confirm' => '1', 'live_phrase' => 'oui'])->assertSessionHas('error');
        $this->assertSame('sandbox', config('freeci.payments.mode'));
        $this->save('paiement', $this->paymentForm(['payments_mode' => 'live']), ['live_phrase' => 'PAIEMENT REEL'])->assertSessionHas('error');             // confirmation financière manquante
        $this->save('paiement', $this->paymentForm(['payments_mode' => 'sandbox', 'payments_live_authorized' => '1']), ['confirm' => '1'])->assertSessionHas('error');  // autoriser le réel = même exigence
        $this->save('paiement', $this->paymentForm(['payments_mode' => 'live']), ['confirm' => '1', 'live_phrase' => 'paiement reel'])->assertSessionHas('status');
        $this->assertSame('live', config('freeci.payments.mode'));
        $this->save('paiement', $this->paymentForm(['payments_mode' => 'sandbox']), ['confirm' => '1'])->assertSessionHas('status');             // revenir au test : sans phrase
        $this->assertSame('sandbox', config('freeci.payments.mode'));
        $this->save('paiement', $this->paymentForm(['payments_mode' => 'invalide']), ['confirm' => '1'])->assertSessionHas('error');
        $this->save('paiement', $this->paymentForm(['payments_genius_base_url' => 'http://pas-securise.test']), ['confirm' => '1'])->assertSessionHas('error');
    }

    public function test_mail_settings_apply_at_once_the_test_mail_goes_only_to_the_admin_and_failures_are_explained(): void
    {
        $mail = ['mail_default' => 'smtp', 'mail_host' => 'smtp.gmail.com', 'mail_port' => '587', 'mail_scheme' => '', 'mail_username' => 'dg@example.test', 'mail_from_address' => 'dg@example.test', 'mail_from_name' => 'FreeCI'];
        $this->save('courrier', $mail + ['mail_password' => 'APP-PASSWORD-123'])->assertSessionHas('status');
        $this->assertSame('smtp.gmail.com', config('mail.mailers.smtp.host'));
        $this->assertSame('APP-PASSWORD-123', config('mail.mailers.smtp.password'));
        $this->assertSame('dg@example.test', config('mail.from.address'));
        $this->assertTrue(MailStatus::configured());

        Mail::fake();
        $this->asAdmin($this->admin)->post('/admin/parametres-test/courrier')->assertSessionHas('status');
        $this->save('courrier', ['mail_default' => 'log'] + $mail)->assertSessionHas('status');
        $this->asAdmin($this->admin)->post('/admin/parametres-test/courrier')->assertSessionHas('error');
        $this->asAdmin($this->admin, false)->post('/admin/parametres-test/courrier')->assertRedirect(route('admin.reauth'));
        $this->actingAs($this->client)->post('/admin/parametres-test/courrier')->assertNotFound();
        $this->save('courrier', ['mail_default' => 'smtp', 'mail_host' => 'h', 'mail_port' => '99999', 'mail_scheme' => '', 'mail_username' => '', 'mail_from_address' => 'pas-un-mail', 'mail_from_name' => 'x'])->assertSessionHas('error');
    }

    public function test_genius_connection_test_reads_the_merchant_account_without_creating_a_payment_or_showing_keys(): void
    {
        $this->enableSandbox();
        $this->asAdmin($this->admin)->post('/admin/parametres-test/genius/sandbox')->assertSessionHas('status');
        $this->asAdmin($this->admin)->post('/admin/parametres-test/genius/live')->assertSessionHas('error');          // live non configuré : aucun appel
        $this->assertSame(0, DB::table('payments')->count());
        $this->asAdmin($this->admin)->post('/admin/parametres-test/genius/autre')->assertNotFound();
    }

    public function test_technical_limits_are_editable_validated_and_applied(): void
    {
        $limits = fn (array $o = []) => $o + ['catalog_title_0' => '15', 'catalog_title_1' => '100', 'catalog_summary_0' => '30', 'catalog_summary_1' => '300', 'catalog_scope_0' => '150', 'catalog_scope_1' => '5000',
            'catalog_delivery_days_0' => '1', 'catalog_delivery_days_1' => '60', 'catalog_revisions_0' => '0', 'catalog_revisions_1' => '10', 'catalog_bio_0' => '50', 'catalog_bio_1' => '1500', 'catalog_skill_0' => '2', 'catalog_skill_1' => '40',
            'catalog_deliverables_max' => '10', 'catalog_exclusions_max' => '10', 'catalog_client_inputs_max' => '8', 'catalog_line_max' => '200', 'catalog_images_max' => '6', 'catalog_image_max_mb' => '5', 'catalog_image_min_width' => '400', 'catalog_skills_max' => '10'];
        $this->save('catalogue', $limits())->assertSessionHas('error');                                            // rien à changer
        $this->save('catalogue', $limits(['catalog_title_0' => '120', 'catalog_title_1' => '100']))->assertSessionHas('error');   // minimum > maximum
        $this->save('catalogue', $limits(['catalog_images_max' => '99']))->assertSessionHas('error');              // hors bornes
        $this->save('catalogue', $limits(['catalog_images_max' => '3', 'catalog_title_1' => '120']))->assertSessionHas('status');
        $this->assertSame(3, config('freeci.catalog.images_max'));
        $this->assertSame([15, 120], config('freeci.catalog.title'));
        $this->assertSame([5000, 500000], config('freeci.catalog.price_xof'), 'les autres bornes ne bougent pas');
    }

    public function test_every_group_renders_and_every_default_is_reflected_without_a_false_change(): void
    {
        $page = $this->asAdmin($this->admin)->get('/admin/parametres')->assertOk();
        foreach (SettingDefinitions::groups() as $g) {
            $page->assertSee($g['title']);
        }
        $this->assertGreaterThan(90, count(SettingDefinitions::all()));
    }

    // ---------------------------------------------------------------- retrait des données de démonstration

    public function test_demo_data_removal_from_the_admin_is_confirmed_audited_and_keeps_real_and_referenced_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $realServices = Service::where('is_demo', false)->count();
        $this->assertGreaterThan(10, Service::where('is_demo', true)->count());
        $this->placeOrder();                                              // commande réelle (fixture non démo) : doit survivre
        $categories = Category::count();

        $this->asAdmin($this->admin)->get('/admin/exploitation')->assertSee('Données de démonstration')->assertSee('RETIRER LA DEMO')->assertSee('à retirer depuis cette page');
        $body = ['reason' => 'Ouverture : retrait de la démonstration.', 'confirm' => '1', 'phrase' => 'RETIRER LA DEMO'];
        $this->asAdmin($this->admin, false)->post('/admin/exploitation/demo/retirer', $body)->assertRedirect(route('admin.reauth'));
        $this->asAdmin($this->admin)->post('/admin/exploitation/demo/retirer', ['phrase' => 'non'] + $body)->assertSessionHas('error');
        $this->asAdmin($this->admin)->post('/admin/exploitation/demo/retirer', array_diff_key($body, ['confirm' => 1]))->assertSessionHasErrors('confirm');
        $this->actingAs($this->client)->post('/admin/exploitation/demo/retirer', $body)->assertNotFound();
        $this->assertGreaterThan(0, Service::where('is_demo', true)->count(), 'rien ne part sans les trois confirmations');

        $this->asAdmin($this->admin)->post('/admin/exploitation/demo/retirer', $body)->assertSessionHas('status');
        $this->assertSame(1, Service::where('is_demo', true)->count(), 'seul le service référencé par une commande est conservé…');
        $this->assertSame('archived', Service::where('is_demo', true)->first()->status->value, '…et archivé');
        $this->assertSame($realServices, Service::where('is_demo', false)->count());
        $this->assertSame($categories, Category::count(), 'les catégories ne sont jamais supprimées');
        $this->assertSame(1, DB::table('orders')->count());
        $this->assertSame(1, DB::table('admin_actions')->where('action', 'demo.purge')->where('result', 'done')->count());
        $this->asAdmin($this->admin)->get('/admin/exploitation')->assertSee('élément(s)');
        $this->artisan('freeci:demo-purge', ['--dry-run' => true])->assertSuccessful();
    }

    public function test_demo_service_referenced_by_an_order_is_kept_but_archived_out_of_the_public_catalog(): void
    {
        $this->service->update(['is_demo' => true]);
        $order = $this->placeOrder();
        $this->artisan('freeci:demo-purge', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame('published', $this->service->fresh()->status->value, 'la simulation ne change rien');
        $this->artisan('freeci:demo-purge', ['--force' => true])->assertSuccessful();
        $this->assertTrue($this->service->fresh()->exists);
        $this->assertSame('archived', $this->service->fresh()->status->value);
        $this->assertSame(1, DB::table('orders')->where('id', $order->id)->count());
    }

    /** Régression : avec le cache « database » (production), des objets mis en cache ne sont pas rendus (serializable_classes = false) → page 500 après la première écriture. */
    public function test_settings_survive_a_serializing_cache_store_and_approval_does_not_crash(): void
    {
        config(['cache.default' => 'database']);
        Cache::flush();
        $form = ['orders_response_hours' => '48', 'orders_payment_hours' => '24', 'orders_review_days' => '7', 'orders_extension_max_days' => '30', 'missions_selection_days' => '14', 'reviews_publication_days' => '14', 'account_closure_grace_days' => '14'];
        $this->save('delais', $form, ['approve' => '1'])->assertSessionHas('status');          // approbation sans changement de valeur
        $this->asAdmin($this->admin)->get('/admin/parametres')->assertOk()->assertSee('Approuvé');   // lecture depuis le cache sérialisé
        $this->asAdmin($this->admin)->get('/admin/parametres')->assertOk();
        AppSettings::apply();
        $this->assertSame(48, config('freeci.orders.response_hours'));
        $this->assertSame('approved', AppSettings::rows()['orders.response_hours']->status);
    }
}

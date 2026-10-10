<?php

namespace Tests\Feature;

use App\Modules\Accounts\Models\User;
use App\Modules\Accounts\Referrals\ReferralCodes;
use App\Modules\Accounts\Referrals\Referrals;
use App\Modules\Admin\Actions\PromoCampaigns;
use App\Modules\Admin\Queries\Statistics;
use App\Modules\Admin\Support\StatsPeriod;
use App\Modules\Finance\Commission\CommissionGrants;
use App\Modules\Finance\Commission\CommissionTerms;
use App\Modules\Missions\Exceptions\MissionConflict;
use App\Modules\Orders\Actions\ExpireOverdueOrders;
use App\Modules\Orders\Models\Delivery;
use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\AdminFixtures;
use Tests\Support\OrderFixtures;
use Tests\TestCase;

/** F-14 — parrainage et codes promotionnels : la récompense est une commission offerte, jamais une remise sur le prix payé. */
class ReferralTest extends TestCase
{
    use AdminFixtures, OrderFixtures, RefreshDatabase;

    private User $parrain;

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
        $this->parrain = User::factory()->create(['name' => 'Koffi Adou', 'email' => 'koffi@exemple.test']);
        $this->admin = $this->readyAdmin();
    }

    /** Nouvel acheteur : les aides de test paient et valident toujours en tant que `$this->client`. */
    private function buyer(): User
    {
        return $this->client = User::factory()->create(['name' => 'Acheteur '.Str::random(4)]);
    }

    private function refer(User $referee, ?User $referrer = null): void
    {
        $code = app(ReferralCodes::class)->forUser((string) ($referrer ?? $this->parrain)->id);
        $this->assertTrue(app(Referrals::class)->attach($referee, $code));
    }

    private function live(Order $o): void
    {
        DB::statement('ALTER TABLE orders DISABLE TRIGGER orders_environment_fixed');          // l'environnement est immuable en production : seul ce test le force
        DB::table('orders')->where('id', $o->id)->update(['environment' => 'live']);
        DB::statement('ALTER TABLE orders ENABLE TRIGGER orders_environment_fixed');
    }

    /** Commande payée puis validée par le client ; $real la fait passer en « live » avant la validation. */
    private function validated(?User $client = null, bool $real = true): Order
    {
        $this->client = $client ?? $this->buyer();
        $o = $this->placeOrder($this->client);
        $this->accept($o)->assertRedirect();
        $this->settle($o->fresh());
        $o->refresh();
        $this->actingAs($this->freelancer)->post("/commandes/{$o->reference}/livraison/message", ['message' => 'Voici la livraison complète, formats DWG et PDF, prête à examiner.'])->assertRedirect();
        $d = Delivery::query()->where('order_id', $o->id)->where('state', 'draft')->firstOrFail();
        $this->actingAs($this->freelancer)->post("/commandes/{$o->reference}/livraison/soumettre", ['delivery_id' => $d->id, 'expected_version' => $o->fresh()->row_version, 'operation_key' => (string) Str::uuid()])->assertSessionHas('status');
        if ($real) {
            $this->live($o);
        }
        $sub = Delivery::query()->where('order_id', $o->id)->where('state', 'submitted')->firstOrFail();
        $this->actingAs(User::find($o->client_id))->post("/commandes/{$o->reference}/validation", ['delivery_id' => $sub->id, 'expected_version' => $o->fresh()->row_version, 'operation_key' => (string) Str::uuid(), 'confirm' => '1'])->assertSessionHas('status');

        return $o->fresh();
    }

    private function grants(User $u)
    {
        return DB::table('commission_grants')->where('user_id', $u->id)->get();
    }

    // ---- codes et inscription ---------------------------------------------------------------------------------------------------------------------

    public function test_codes_are_stable_unguessable_and_normalised(): void
    {
        $c = app(ReferralCodes::class);
        $code = $c->forUser((string) $this->parrain->id);
        $this->assertMatchesRegularExpression('/^[A-HJKMNP-Z2-9]{8}$/', $code);
        $this->assertSame($code, $c->forUser((string) $this->parrain->id));
        $this->assertNotSame($code, $c->forUser((string) $this->client->id));
        $this->assertSame($code, ReferralCodes::normalize('  '.strtolower(substr($code, 0, 4)).'-'.strtolower(substr($code, 4)).' '));
        $this->assertNull(ReferralCodes::normalize('ABC'));
        $this->assertNull(ReferralCodes::normalize('0O1IL000'));
        $this->assertSame((string) $this->parrain->id, (string) $c->ownerOf(strtolower($code))->user_id);
        DB::table('users')->where('id', $this->parrain->id)->update(['suspended_at' => now()]);
        $this->assertNull($c->ownerOf($code), 'le code d’un compte suspendu est inconnu');
        $this->assertSame('Koffi A.', ReferralCodes::shortName('Koffi Adou'));
        $this->assertSame('Fatou', ReferralCodes::shortName('Fatou'));
        $this->assertSame('Moussa T.', ReferralCodes::shortName('  Moussa  Ben Traoré '));
    }

    public function test_email_variants_of_one_mailbox_are_recognised(): void
    {
        $n = fn ($e) => Referrals::normalizeEmail($e);
        $this->assertSame($n('moussa.traore+x@gmail.com'), $n('MoussaTraore@googlemail.com'));
        $this->assertSame($n('a+promo@exemple.ci'), $n('A@exemple.ci'));
        $this->assertNotSame($n('a.b@exemple.ci'), $n('ab@exemple.ci'), 'les points ne comptent que chez Gmail');
    }

    public function test_registration_link_remembers_the_code_and_attaches_the_referral_once(): void
    {
        $code = app(ReferralCodes::class)->forUser((string) $this->parrain->id);
        $page = $this->get('/inscription?parrain='.strtolower($code))->assertOk()->assertSee('Code reconnu')->assertSee('Koffi A.')->assertCookie('fc_ref');
        $this->withCookie('fc_ref', $code)->get('/inscription')->assertOk()->assertSee($code);                                                  // le cookie remplit le champ

        $this->post('/inscription', ['name' => 'Fatou Diallo', 'email' => 'fatou@exemple.test', 'password' => 'MotDePasse-2026x', 'password_confirmation' => 'MotDePasse-2026x', 'referral_code' => $code])->assertRedirect();
        $fatou = User::where('email', 'fatou@exemple.test')->firstOrFail();
        $r = DB::table('referrals')->where('referee_id', $fatou->id)->first();
        $this->assertSame((string) $this->parrain->id, (string) $r->referrer_id);
        $this->assertSame('registered', $r->state);
        $this->assertFalse(app(Referrals::class)->attach($fatou, app(ReferralCodes::class)->forUser((string) $this->client->id)), 'un compte n’est parrainé qu’une fois');
        $this->assertSame(1, DB::table('referrals')->where('referee_id', $fatou->id)->count());
    }

    public function test_bad_codes_and_same_mailbox_are_refused_without_creating_the_account(): void
    {
        $pw = ['password' => 'MotDePasse-2026x', 'password_confirmation' => 'MotDePasse-2026x'];
        $this->post('/inscription', ['name' => 'Aïcha K', 'email' => 'aicha@exemple.test', 'referral_code' => 'ZZZZZZZZ'] + $pw)->assertSessionHasErrors('referral_code');
        $this->assertNull(User::where('email', 'aicha@exemple.test')->first());
        $code = app(ReferralCodes::class)->forUser((string) $this->parrain->id);
        $this->post('/inscription', ['name' => 'Koffi Bis', 'email' => 'Koffi+bis@exemple.test', 'referral_code' => $code] + $pw)->assertSessionHasErrors('referral_code');
        $this->assertNull(User::where('email', 'Koffi+bis@exemple.test')->first());
        config(['freeci.referral.enabled' => false]);
        $this->get('/inscription')->assertOk()->assertDontSee('Code de parrainage');
        $this->post('/inscription', ['name' => 'Autre', 'email' => 'autre@exemple.test', 'referral_code' => $code] + $pw)->assertRedirect();
        $this->assertSame(0, DB::table('referrals')->count(), 'parrainage désactivé : le code est ignoré');
    }

    // ---- récompense -------------------------------------------------------------------------------------------------------------------------------

    public function test_first_real_validated_order_rewards_both_sides_and_test_orders_never_do(): void
    {
        $this->refer($this->freelancer);
        $this->validated(null, false);                                                                              // commande de test : ne qualifie jamais
        $this->assertSame('registered', DB::table('referrals')->value('state'));
        $this->assertSame(0, DB::table('commission_grants')->count());

        $o = $this->validated();                                                                                    // première commande RÉELLE validée
        $r = DB::table('referrals')->first();
        $this->assertSame('qualified', $r->state);
        $this->assertSame($o->id, $r->qualifying_order_id);
        $this->assertTrue((bool) $r->referrer_rewarded);
        $mine = $this->grants($this->freelancer)->first();
        $theirs = $this->grants($this->parrain)->first();
        foreach ([$mine, $theirs] as $g) {
            $this->assertSame(3, (int) $g->total);
            $this->assertSame(0, (int) $g->rate_bp);
            $this->assertSame('active', $g->state);
        }
        $this->assertSame('referral_referee', $mine->source);
        $this->assertSame('referral_referrer', $theirs->source);
        $this->assertSame(1, DB::table('app_notifications')->where('user_id', $this->parrain->id)->where('type', 'referral_reward')->count());
        $this->assertSame(1, DB::table('app_notifications')->where('user_id', $this->freelancer->id)->where('type', 'referral_reward')->count());

        $this->validated();                                                                                         // une seule récompense par filleul
        $this->assertSame(1, $this->grants($this->freelancer)->count());
        $this->assertSame(1, $this->grants($this->parrain)->count());
    }

    public function test_no_reward_when_the_referrer_is_the_other_party_or_accounts_are_suspended_or_feature_is_off(): void
    {
        $this->refer($this->freelancer);
        $this->validated($this->parrain);                                                                           // le parrain est le client de la même commande
        $this->assertSame('registered', DB::table('referrals')->value('state'));

        DB::table('users')->where('id', $this->parrain->id)->update(['suspended_at' => now()]);
        $this->validated();
        $this->assertSame('registered', DB::table('referrals')->value('state'));
        DB::table('users')->where('id', $this->parrain->id)->update(['suspended_at' => null]);

        config(['freeci.referral.enabled' => false]);
        $this->validated();
        $this->assertSame('registered', DB::table('referrals')->value('state'));
        $this->assertSame(0, DB::table('commission_grants')->count());
    }

    public function test_referrer_cap_still_rewards_the_referee_but_not_the_referrer(): void
    {
        config(['freeci.referral.max_qualified' => 1]);
        $second = User::factory()->create();
        $second->roles()->firstOrCreate(['role' => 'freelance']);
        $this->refer($this->freelancer);
        $this->refer($second);
        $this->validated();
        $this->assertSame(1, DB::table('referrals')->where('referrer_rewarded', true)->count());
        $this->assertSame(1, $this->grants($this->parrain)->count());
        // le second filleul qualifie à son tour (service du second freelance)
        DB::table('referrals')->where('referee_id', $second->id)->update(['state' => 'registered']);
        app(Referrals::class)->onOrderValidated($this->liveOrderWith($second)->id);
        $r = DB::table('referrals')->where('referee_id', $second->id)->first();
        $this->assertSame('qualified', $r->state);
        $this->assertFalse((bool) $r->referrer_rewarded, 'plafond atteint : pas de seconde récompense de parrain');
        $this->assertSame(1, $this->grants($this->parrain)->count());
        $this->assertSame(1, $this->grants($second)->count(), 'le filleul est récompensé quand même');
    }

    private function liveOrderWith(User $freelancer): Order
    {
        $o = $this->validated();
        DB::statement('ALTER TABLE orders DISABLE TRIGGER USER');
        DB::table('orders')->where('id', $o->id)->update(['freelancer_id' => $freelancer->id]);
        DB::statement('ALTER TABLE orders ENABLE TRIGGER USER');

        return $o->fresh();
    }

    // ---- taux, réservation, consommation ----------------------------------------------------------------------------------------------------------

    public function test_offered_rate_is_frozen_reserved_consumed_at_payment_and_returned_when_unpaid(): void
    {
        $this->grantTo($this->freelancer, 0, 2);
        $this->assertSame(2, CommissionTerms::available((string) $this->freelancer->id));

        $a = $this->placeOrder($this->buyer());
        $ag = $a->agreement;
        $this->assertSame(0, (int) $ag->commission_bp);
        $this->assertSame(1000, (int) $ag->commission_base_bp);
        $this->assertSame('Parrainage', $ag->commission_reason);
        $this->assertSame(35000, (int) $ag->price_xof, 'le prix payé par le client ne change jamais');
        $this->assertSame(1, CommissionTerms::available((string) $this->freelancer->id), 'une unité est réservée dès la création');

        $b = $this->placeOrder($this->buyer());
        $this->assertSame(0, CommissionTerms::available((string) $this->freelancer->id));
        $c = $this->placeOrder($this->buyer());
        $this->assertSame(1000, (int) $c->agreement->commission_bp, 'plus d’unité disponible : taux normal');
        $this->assertNull($c->agreement->commission_reason);

        // paiement : consommée
        $this->client = User::find($a->client_id);
        $this->accept($a)->assertRedirect();
        $this->settle($a->fresh());
        $g = $this->grants($this->freelancer)->first();
        $this->assertSame(1, (int) $g->consumed);
        $this->assertSame('consumed', DB::table('commission_grant_uses')->where('order_id', $a->id)->value('state'));

        // refus du freelance avant paiement : l'unité réservée est rendue
        $this->actingAs($this->freelancer)->post("/commandes/{$b->reference}/decline", ['reason' => 'Je ne suis pas disponible pour cette commande.', 'expected_version' => $b->fresh()->row_version, 'operation_key' => (string) Str::uuid()]);
        $this->assertSame(1, CommissionTerms::available((string) $this->freelancer->id));
        $this->assertSame('returned', DB::table('commission_grant_uses')->where('order_id', $b->id)->value('state'));
        $this->assertSame(1, (int) $this->grants($this->freelancer)->first()->consumed, 'le compteur consommé ne bouge pas');
    }

    private function grantTo(User $u, int $rate, int $total, string $source = 'referral_referee'): string
    {
        $parrain = User::factory()->create();
        $dummy = User::factory()->create();
        $ref = (string) Str::uuid();
        DB::table('referrals')->insert(['id' => $ref, 'referrer_id' => $parrain->id, 'referee_id' => $dummy->id, 'code' => 'AAAAAAAA', 'state' => 'qualified', 'created_at' => now()]);

        return app(CommissionGrants::class)->create((string) $u->id, $source, ['referral_id' => $ref], $rate, $total);
    }

    public function test_unpaid_expiry_returns_the_unit_and_paid_cancellation_does_not(): void
    {
        $this->grantTo($this->freelancer, 0, 1);
        $o = $this->placeOrder($this->buyer());
        $this->accept($o)->assertRedirect();
        DB::table('orders')->where('id', $o->id)->update(['payment_deadline_at' => now()->subMinute()]);
        app(ExpireOverdueOrders::class)();
        $this->assertSame('returned', DB::table('commission_grant_uses')->where('order_id', $o->id)->value('state'));
        $this->assertSame(1, CommissionTerms::available((string) $this->freelancer->id));
        app(CommissionGrants::class)->onEnded($o->id);                                // idempotent
        $this->assertSame(1, CommissionTerms::available((string) $this->freelancer->id));
    }

    public function test_the_most_favourable_grant_applies_and_rates_not_below_the_normal_one_are_ignored(): void
    {
        $this->grantTo($this->freelancer, 500, 3);                                                                    // 5 %
        $this->grantTo($this->freelancer, 1000, 3, 'referral_referrer');                                              // = taux normal : sans effet
        $o = $this->placeOrder($this->buyer());
        $this->assertSame(500, (int) $o->agreement->commission_bp);
        $this->assertSame(1, DB::table('commission_grant_uses')->count(), 'une seule unité par commande, d’une seule attribution');
        $zero = $this->grantTo($this->freelancer, 0, 1, 'referral_referrer');
        $o2 = $this->placeOrder($this->buyer());
        $this->assertSame(0, (int) $o2->agreement->commission_bp, 'le taux le plus favorable passe devant');
        $this->assertSame($zero, DB::table('commission_grant_uses')->where('order_id', $o2->id)->value('grant_id'));
    }

    public function test_existing_agreements_never_change_when_a_grant_is_revoked_or_settings_change(): void
    {
        $g = $this->grantTo($this->freelancer, 0, 3);
        $o = $this->placeOrder($this->buyer());
        $this->assertSame(0, (int) $o->agreement->commission_bp);
        $this->actingAs($this->admin);
        app(CommissionGrants::class)->revoke($this->admin, $g, 'Abus constaté : comptes liés entre eux.');
        config(['freeci.referral.free_orders' => 9, 'freeci.finance.commission_bp' => 2000]);
        $this->assertSame(0, (int) $o->fresh()->agreement->commission_bp);
        $this->accept($o)->assertRedirect();
        $this->settle($o->fresh());
        $this->assertSame('consumed', DB::table('commission_grant_uses')->where('order_id', $o->id)->value('state'), 'l’unité déjà réservée suit son accord');
        $o2 = $this->placeOrder($this->buyer());
        $this->assertSame(2000, (int) $o2->agreement->commission_bp, 'après révocation : taux normal (alors de 20 %)');
    }

    // ---- campagnes --------------------------------------------------------------------------------------------------------------------------------

    private function campaign(array $o = []): string
    {
        return app(PromoCampaigns::class)->create($this->admin, array_merge(['code' => 'lancement', 'rate' => '0', 'free_orders' => '3', 'starts_on' => now()->subDay()->format('Y-m-d'), 'ends_on' => now()->addMonth()->format('Y-m-d'), 'max_uses' => '2', 'note' => 'Lancement'], $o));
    }

    public function test_campaign_codes_follow_dates_limits_and_one_per_account(): void
    {
        $id = $this->campaign();
        $this->assertSame('LANCEMENT', DB::table('promo_campaigns')->where('id', $id)->value('code'));
        app(PromoCampaigns::class)->redeem($this->freelancer, ' lancement ');
        $g = $this->grants($this->freelancer)->first();
        $this->assertSame('campaign', $g->source);
        $this->assertSame(3, (int) $g->total);
        $this->assertSame(1, (int) DB::table('promo_campaigns')->where('id', $id)->value('uses'));
        $this->assertThrows(fn () => app(PromoCampaigns::class)->redeem($this->freelancer, 'LANCEMENT'), ValidationException::class);          // un seul code par compte

        $o = $this->placeOrder($this->buyer());
        $this->assertSame('Code LANCEMENT', $o->agreement->commission_reason);

        $u2 = User::factory()->create();
        $u3 = User::factory()->create();
        app(PromoCampaigns::class)->redeem($u2, 'LANCEMENT');
        $this->assertThrows(fn () => app(PromoCampaigns::class)->redeem($u3, 'LANCEMENT'), ValidationException::class);          // épuisé
        foreach (['', 'INCONNU', 'lancemen'] as $bad) {
            try {
                app(PromoCampaigns::class)->redeem($u3, $bad);
                $this->fail('refusé attendu');
            } catch (ValidationException $e) {
                $this->assertSame('Code inconnu, expiré ou épuisé.', $e->errors()['promo_code'][0], 'message neutre');
            }
        }

        $future = $this->campaign(['code' => 'PLUSTARD', 'starts_on' => now()->addDays(3)->format('Y-m-d'), 'ends_on' => now()->addDays(9)->format('Y-m-d')]);
        $past = $this->campaign(['code' => 'EXPIRE25', 'starts_on' => now()->subDays(9)->format('Y-m-d'), 'ends_on' => now()->subDays(3)->format('Y-m-d')]);
        $this->campaign(['code' => 'PAUSE000']);
        app(PromoCampaigns::class)->setState($this->admin, DB::table('promo_campaigns')->where('code', 'PAUSE000')->value('id'), false);
        foreach (['PLUSTARD', 'EXPIRE25', 'PAUSE000'] as $c) {
            $this->assertThrows(fn () => app(PromoCampaigns::class)->redeem($u3, $c), ValidationException::class);
        }
        $this->assertSame(0, $this->grants($u3)->count());
        $this->assertNotNull($future.$past);
    }

    public function test_campaign_validation_update_and_audit(): void
    {
        $bad = fn (array $o) => $this->assertThrows(fn () => $this->campaign($o), ValidationException::class);
        $bad(['code' => 'abc']);
        $bad(['code' => 'CODE AVEC ESPACE ÉÉ']);
        $bad(['rate' => '10']);                                                                                          // = commission normale
        $bad(['rate' => '-1']);
        $bad(['free_orders' => '0']);
        $bad(['max_uses' => '0']);
        $bad(['ends_on' => now()->subDays(5)->format('Y-m-d')]);
        $id = $this->campaign(['code' => 'ARCHI25', 'rate' => '5', 'free_orders' => '5']);
        $this->assertSame(500, (int) DB::table('promo_campaigns')->where('id', $id)->value('rate_bp'));
        $this->assertThrows(fn () => $this->campaign(['code' => 'archi25']), ValidationException::class);                 // doublon
        app(PromoCampaigns::class)->update($this->admin, $id, ['code' => 'ARCHI26', 'rate' => '2,5', 'free_orders' => '4', 'starts_on' => now()->format('Y-m-d'), 'ends_on' => now()->addDays(20)->format('Y-m-d'), 'max_uses' => '10']);
        $this->assertSame(250, (int) DB::table('promo_campaigns')->where('id', $id)->value('rate_bp'));
        app(PromoCampaigns::class)->redeem($this->freelancer, 'ARCHI26');
        $this->assertThrows(fn () => app(PromoCampaigns::class)->update($this->admin, $id, ['code' => 'ARCHI27', 'rate' => '1', 'free_orders' => '1', 'starts_on' => now()->format('Y-m-d'), 'ends_on' => now()->addDays(5)->format('Y-m-d'), 'max_uses' => '5']), MissionConflict::class);   // déjà utilisé
        $this->assertGreaterThanOrEqual(2, DB::table('admin_actions')->whereIn('action', ['campaign.create', 'campaign.update'])->where('result', 'done')->count());
    }

    public function test_freelance_profile_form_applies_a_code_and_a_refused_code_keeps_the_profile(): void
    {
        $this->campaign(['code' => 'BIENVENUE']);
        $data = ['display_name' => 'Kader Freelance', 'headline' => 'Dessinateur DAO', 'city' => 'Abidjan'];
        $this->actingAs($this->freelancer)->post('/freelance/profil', $data + ['promo_code' => 'nimporte'])->assertRedirect(route('freelance.profile'))->assertSessionHasErrors('promo_code')->assertSessionHas('status');
        $this->assertSame(0, $this->grants($this->freelancer)->count());
        $this->actingAs($this->freelancer)->get('/freelance/profil')->assertOk()->assertSee('Code promotionnel');
        $this->actingAs($this->freelancer)->post('/freelance/profil', $data + ['promo_code' => 'bienvenue'])->assertRedirect()->assertSessionHas('status');
        $this->assertSame(1, $this->grants($this->freelancer)->count());
        $this->actingAs($this->freelancer)->get('/freelance/profil')->assertOk()->assertDontSee('Code promotionnel <span');
    }

    // ---- pages ------------------------------------------------------------------------------------------------------------------------------------

    public function test_referral_page_shows_only_first_name_and_initial_and_the_admin_page_is_protected(): void
    {
        $u = User::factory()->create(['name' => 'Fatou Diallo', 'email' => 'secret-fatou@exemple.test']);
        $this->refer($u);
        $page = $this->actingAs($this->parrain)->get('/espace/parrainage')->assertOk()->assertSee('Fatou D.')->assertSee('Commandes à commission offerte');
        $page->assertDontSee('secret-fatou')->assertDontSee('Fatou Diallo');
        $code = app(ReferralCodes::class)->forUser((string) $this->parrain->id);
        $page->assertSee($code)->assertSee('inscription?parrain='.$code, false);
        config(['freeci.referral.enabled' => false]);
        $this->actingAs($this->parrain)->get('/espace/parrainage')->assertNotFound();
        config(['freeci.referral.enabled' => true]);

        $this->actingAs($this->client)->get('/admin/parrainage')->assertNotFound();
        $this->campaign();
        $g = $this->grantTo($this->freelancer, 0, 3);
        $this->asAdmin($this->admin)->get('/admin/parrainage')->assertOk()->assertSee('LANCEMENT')->assertSee('Attributions actives');
        // écritures : confirmation récente exigée
        $this->asAdmin($this->admin, false)->from('/admin/parrainage')->post("/admin/parrainage/attributions/{$g}/revoquer", ['reason' => 'Abus constaté sur ce compte.'])->assertRedirect(route('admin.reauth'));
        $this->assertSame('active', DB::table('commission_grants')->where('id', $g)->value('state'));
        $this->asAdmin($this->admin)->post("/admin/parrainage/attributions/{$g}/revoquer", ['reason' => 'court'])->assertSessionHas('error');
        $this->asAdmin($this->admin)->post("/admin/parrainage/attributions/{$g}/revoquer", ['reason' => 'Abus constaté sur ce compte.'])->assertSessionHas('status');
        $this->assertSame('revoked', DB::table('commission_grants')->where('id', $g)->value('state'));
        $this->assertSame(1, DB::table('app_notifications')->where('user_id', $this->freelancer->id)->where('type', 'commission_grant_revoked')->count());
        $this->asAdmin($this->admin)->post("/admin/parrainage/attributions/{$g}/revoquer", ['reason' => 'Une seconde fois, déjà fait.'])->assertSessionHas('error');
        $this->assertSame(1, DB::table('admin_actions')->where('action', 'grant.revoke')->where('result', 'done')->count());

        $this->asAdmin($this->admin)->post('/admin/parrainage/campagnes', ['code' => 'RENTREE', 'rate' => '0', 'free_orders' => '3', 'starts_on' => '2026-11-01', 'ends_on' => '2026-12-31', 'max_uses' => '100'])->assertSessionHas('status');
        $this->assertNotNull(DB::table('promo_campaigns')->where('code', 'RENTREE')->first());
        $cid = DB::table('promo_campaigns')->where('code', 'RENTREE')->value('id');
        $this->asAdmin($this->admin)->post("/admin/parrainage/campagnes/{$cid}/suspendre")->assertSessionHas('status');
        $this->assertSame('suspended', DB::table('promo_campaigns')->where('id', $cid)->value('state'));
        $this->asAdmin($this->admin)->get('/admin/parametres')->assertOk()->assertSee('Parrainage');
    }

    public function test_statistics_show_offered_commissions_separately(): void
    {
        $this->grantTo($this->freelancer, 0, 1);
        $o = $this->validated(null, false);
        $s = app(Statistics::class)(StatsPeriod::fromInput([]), false);
        $card = collect($s['finance'])->firstWhere('label', 'Commissions offertes');
        $this->assertStringContainsString('3 500', str_replace(["\u{202F}", "\u{00A0}"], ' ', $card['value']));          // 35 000 × 10 %
        $this->assertSame('0', (string) DB::table('order_agreements')->where('order_id', $o->id)->value('commission_bp'));
        $com = collect($s['finance'])->firstWhere('label', 'Commissions');
        $this->assertStringStartsWith('0 ', str_replace(["\u{202F}", "\u{00A0}"], ' ', $com['value']), 'la commission perçue est nulle, l’offerte est séparée');
        $csv = $this->asAdmin($this->admin)->post('/admin/statistiques/exports/commandes', ['periode' => '30j', 'env' => 'test'])->streamedContent();
        $this->assertStringContainsString(';35000;5;0;0;3500', $csv);
    }
}

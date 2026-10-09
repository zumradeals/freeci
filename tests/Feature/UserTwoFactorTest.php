<?php

namespace Tests\Feature;

use App\Modules\Accounts\Models\User;
use App\Modules\Accounts\Security\SecondFactorGate;
use App\Modules\Accounts\Security\Totp;
use App\Modules\Accounts\Security\TwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\AdminFixtures;
use Tests\TestCase;

/** F-18 — double authentification FACULTATIVE pour tous : activation, connexion en deux temps, actes sensibles, désactivation, réinitialisation par l'équipe. */
class UserTwoFactorTest extends TestCase
{
    use AdminFixtures, RefreshDatabase;

    private const PASSWORD = 'MotDePasse-2026x';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function person(array $attrs = []): User
    {
        return User::factory()->create($attrs + ['password' => Hash::make(self::PASSWORD), 'email_verified_at' => now()]);
    }

    /** Code valide : on repart d'un pas libre (la protection contre le rejeu est testée à part). */
    private function code(User $u): string
    {
        DB::table('users')->where('id', $u->id)->update(['two_factor_last_step' => null]);

        return Totp::code($this->secretOf($u), Totp::step());
    }

    /** Personne avec la double authentification active ; @return array{0: User, 1: list<string>} */
    private function enrolled(array $attrs = []): array
    {
        $u = $this->person($attrs);
        $mfa = app(TwoFactor::class);
        $secret = $mfa->begin($u)['secret'];
        $codes = $mfa->confirm($u, Totp::code($secret, Totp::step()));

        return [$u->fresh(), $codes];
    }

    public function test_enrolment_needs_the_password_and_a_valid_code_and_shows_the_backup_codes_once(): void
    {
        $u = $this->person();
        $this->actingAs($u)->get('/espace/compte')->assertOk()->assertSee('Non activée')->assertSee('Activer la double authentification');

        $r = $this->actingAs($u)->get('/espace/compte/double-authentification')->assertOk();
        $r->assertSee('<svg', false)->assertSee('Saisissez le code affiché');
        $this->assertFalse($u->fresh()->hasTwoFactor(), 'rien n\'est actif avant un code valide');
        $secret = $this->secretOf($u);
        $this->assertStringContainsString(substr($secret, 0, 4), (string) $r->getContent());

        $this->actingAs($u)->post('/espace/compte/double-authentification/activer', ['code' => Totp::code($secret, Totp::step()), 'current_password' => 'mauvais'])->assertSessionHasErrors('current_password');
        $this->actingAs($u)->post('/espace/compte/double-authentification/activer', ['code' => '000000', 'current_password' => self::PASSWORD])->assertSessionHasErrors('code');
        $this->assertFalse($u->fresh()->hasTwoFactor());

        DB::table('sessions')->insert(['id' => 'autre-session', 'user_id' => $u->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);
        $old = $u->fresh()->remember_token;
        $ok = $this->actingAs($u)->post('/espace/compte/double-authentification/activer', ['code' => Totp::code($secret, Totp::step()), 'current_password' => self::PASSWORD]);
        $ok->assertOk()->assertSee('Ces codes ne seront plus jamais affichés');
        $this->assertSame(TwoFactor::CODES, substr_count((string) $ok->getContent(), 'class="tf-codes"') === 1 ? preg_match_all('/<li>[a-z0-9]{5}-[a-z0-9]{5}<\/li>/', (string) $ok->getContent()) : -1);
        $fresh = $u->fresh();
        $this->assertTrue($fresh->hasTwoFactor());
        $this->assertNotSame($old, $fresh->remember_token, 'les connexions mémorisées sont invalidées');
        $this->assertSame(0, DB::table('sessions')->where('id', 'autre-session')->count(), 'les autres sessions sont fermées');
        $this->assertSame(0, DB::table('two_factor_recovery_codes')->where('code_hash', 'like', '%-%')->count(), 'aucun code de secours en clair');
        $this->actingAs($fresh)->get('/espace/compte')->assertSee('Activée')->assertSee('Codes de secours : 8 sur 8');
        $this->actingAs($fresh)->get('/espace/compte/double-authentification')->assertRedirect();
    }

    public function test_the_password_alone_does_not_open_a_session_and_a_valid_code_does(): void
    {
        [$u, $codes] = $this->enrolled();

        $r = $this->post('/connexion', ['email' => $u->email, 'password' => self::PASSWORD, 'remember' => '1']);
        $r->assertRedirect('/connexion/verification');
        $this->assertGuest();
        $this->assertNull($r->getCookie(Auth::guard('web')->getRecallerName()), 'jamais de connexion mémorisée');
        $this->get('/espace')->assertRedirect('/connexion');

        $this->get('/connexion/verification')->assertOk()->assertSee('Vérification en deux étapes');
        $this->post('/connexion/verification', ['code' => '111111'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post('/connexion/verification', ['code' => $this->code($u)])->assertRedirect('/espace');
        $this->assertAuthenticatedAs($u);
    }

    public function test_a_backup_code_logs_in_once_and_a_wrong_password_never_reaches_the_second_step(): void
    {
        [$u, $codes] = $this->enrolled();
        $this->post('/connexion', ['email' => $u->email, 'password' => 'faux'])->assertSessionHasErrors('email');
        $this->get('/connexion/verification')->assertRedirect('/connexion');

        $this->post('/connexion', ['email' => $u->email, 'password' => self::PASSWORD]);
        $this->post('/connexion/verification', ['code' => $codes[0]])->assertRedirect('/espace');
        $this->assertAuthenticatedAs($u);
        $this->post('/deconnexion');

        $this->post('/connexion', ['email' => $u->email, 'password' => self::PASSWORD]);
        $this->post('/connexion/verification', ['code' => $codes[0]])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_the_pending_step_expires_and_repeated_wrong_codes_lock_it(): void
    {
        [$u] = $this->enrolled();
        $this->post('/connexion', ['email' => $u->email, 'password' => self::PASSWORD]);
        $this->withSession(['login_2fa' => ['user' => $u->id, 'at' => time() - 3600]])->get('/connexion/verification')->assertRedirect('/connexion');

        RateLimiter::clear('mfa:'.$u->id);
        $this->post('/connexion', ['email' => $u->email, 'password' => self::PASSWORD]);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/connexion/verification', ['code' => '222222']);
        }
        $this->post('/connexion/verification', ['code' => $this->code($u)])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertStringContainsString('Trop de tentatives', (string) session('errors')->first('code'));
    }

    public function test_staff_with_the_second_factor_also_passes_the_administration_challenge_at_login(): void
    {
        $admin = $this->readyAdmin();
        DB::table('users')->where('id', $admin->id)->update(['password' => Hash::make(self::PASSWORD)]);
        $this->post('/connexion', ['email' => $admin->email, 'password' => self::PASSWORD]);
        $this->post('/connexion/verification', ['code' => $this->code($admin)])->assertRedirect();
        $this->get('/admin')->assertOk();
    }

    public function test_sensitive_acts_need_a_code_when_the_second_factor_is_active_and_only_then(): void
    {
        [$u] = $this->enrolled();
        $pw = ['current_password' => self::PASSWORD, 'password' => 'NouveauMotDePasse-77', 'password_confirmation' => 'NouveauMotDePasse-77'];
        $this->actingAs($u)->post('/espace/compte/mot-de-passe', $pw)->assertSessionHasErrors('code');
        $this->assertTrue(Hash::check(self::PASSWORD, $u->fresh()->password));
        $this->actingAs($u)->post('/espace/compte/mot-de-passe', $pw + ['code' => '000000'])->assertSessionHasErrors('code');
        $this->actingAs($u)->post('/espace/compte/mot-de-passe', $pw + ['code' => $this->code($u)])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('NouveauMotDePasse-77', $u->fresh()->password));

        $this->actingAs($u)->post('/espace/compte/adresse', ['email' => 'nouvelle@exemple.ci', 'current_password' => 'NouveauMotDePasse-77'])->assertSessionHasErrors('code');

        // sans double authentification : aucune exigence supplémentaire
        $plain = $this->person();
        $this->actingAs($plain)->post('/espace/compte/mot-de-passe', $pw)->assertSessionHasNoErrors();
        $this->assertTrue(app(SecondFactorGate::class) instanceof SecondFactorGate);
    }

    public function test_declaring_payout_details_needs_a_code_when_the_second_factor_is_active(): void
    {
        [$f] = $this->enrolled();
        $f->roles()->firstOrCreate(['role' => 'freelance']);
        $d = ['method' => 'mobile_money', 'holder' => 'Awa Test', 'destination' => '+2250700000000'];
        $this->actingAs($f)->post('/freelance/revenus/destination', $d)->assertSessionHasErrors('code');
        $this->assertSame(0, DB::table('payout_beneficiaries')->count());
        $this->actingAs($f)->post('/freelance/revenus/destination', $d + ['code' => $this->code($f)])->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('payout_beneficiaries')->count());
    }

    public function test_regenerating_codes_needs_password_and_code_and_kills_the_old_codes(): void
    {
        [$u, $old] = $this->enrolled();
        $this->actingAs($u)->post('/espace/compte/double-authentification/codes', ['current_password' => self::PASSWORD])->assertSessionHasErrors('code');
        $r = $this->actingAs($u)->post('/espace/compte/double-authentification/codes', ['current_password' => self::PASSWORD, 'code' => $this->code($u)]);
        $r->assertOk()->assertSee('Nouveaux codes de secours');
        $this->assertSame(TwoFactor::CODES, app(TwoFactor::class)->remaining($u));
        $this->assertFalse(app(TwoFactor::class)->challenge($u->fresh(), $old[0]), 'un ancien code ne fonctionne plus');
    }

    public function test_a_person_can_disable_it_with_password_and_code_but_staff_cannot(): void
    {
        [$u] = $this->enrolled();
        DB::table('sessions')->insert(['id' => 'autre', 'user_id' => $u->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);
        $this->actingAs($u)->post('/espace/compte/double-authentification/desactiver', ['current_password' => 'faux', 'code' => $this->code($u)])->assertSessionHasErrors('current_password');
        $this->assertTrue($u->fresh()->hasTwoFactor());
        $this->actingAs($u)->post('/espace/compte/double-authentification/desactiver', ['current_password' => self::PASSWORD, 'code' => $this->code($u)])->assertRedirect('/espace/compte');
        $this->assertFalse($u->fresh()->hasTwoFactor());
        $this->assertSame(0, DB::table('two_factor_recovery_codes')->where('user_id', $u->id)->count());
        $this->assertSame(0, DB::table('sessions')->where('id', 'autre')->count());

        $admin = $this->readyAdmin();
        DB::table('users')->where('id', $admin->id)->update(['password' => Hash::make(self::PASSWORD)]);
        $this->actingAs($admin)->get('/espace/compte')->assertSee('obligatoire pour l’équipe')->assertDontSee('Désactiver la double authentification');
        $this->actingAs($admin)->post('/espace/compte/double-authentification/desactiver', ['current_password' => self::PASSWORD, 'code' => $this->code($admin)])->assertSessionHas('error');
        $this->assertTrue($admin->fresh()->hasTwoFactor());
    }

    public function test_an_administrator_resets_a_persons_second_factor_with_a_reason_and_it_is_audited_and_notified(): void
    {
        [$u] = $this->enrolled(['name' => 'Fanta Perdue']);
        $admin = $this->readyAdmin();
        DB::table('sessions')->insert(['id' => 'sess-u', 'user_id' => $u->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);
        $url = "/admin/utilisateurs/{$u->id}/double-authentification/reinitialiser";

        $this->asAdmin($admin)->get("/admin/utilisateurs/{$u->id}")->assertOk()->assertSee('Réinitialiser la double authentification');
        $this->asAdmin($admin)->post($url, ['reason' => 'court'])->assertSessionHasErrors('reason');
        $this->asAdmin($admin, recent: false)->post($url, ['reason' => 'Identité vérifiée par appel au numéro du compte.'])->assertRedirect();
        $this->assertTrue($u->fresh()->hasTwoFactor(), 'sans confirmation récente d\'identité : rien');

        $this->asAdmin($admin)->post($url, ['reason' => 'Identité vérifiée par appel au numéro du compte.'])->assertRedirect("/admin/utilisateurs/{$u->id}");
        $this->assertFalse($u->fresh()->hasTwoFactor());
        $this->assertSame(0, DB::table('sessions')->where('user_id', $u->id)->count());
        $this->assertSame(1, DB::table('admin_actions')->where('action', 'user.mfa_reset')->where('result', 'done')->count());
        $n = DB::table('app_notifications')->where('user_id', $u->id)->where('type', 'two_factor_reset')->first();
        $this->assertNotNull($n);
        $this->assertStringContainsString('Identité vérifiée', (string) $n->body);

        // refus : compte sans double authentification, soi-même, personnel
        $this->asAdmin($admin)->post($url, ['reason' => 'Identité vérifiée par appel au numéro du compte.'])->assertSessionHas('error');
        $this->asAdmin($admin)->post("/admin/utilisateurs/{$admin->id}/double-authentification/reinitialiser", ['reason' => 'Identité vérifiée par appel au numéro.'])->assertSessionHas('error');
        $other = $this->readyAdmin();
        $this->asAdmin($admin)->post("/admin/utilisateurs/{$other->id}/double-authentification/reinitialiser", ['reason' => 'Identité vérifiée par appel au numéro.'])->assertSessionHas('error');
        $this->assertTrue($other->fresh()->hasTwoFactor());
    }

    public function test_an_ordinary_person_cannot_reset_anyone(): void
    {
        [$u] = $this->enrolled();
        $v = $this->person();
        $this->actingAs($v)->post("/admin/utilisateurs/{$u->id}/double-authentification/reinitialiser", ['reason' => 'Je veux entrer dans ce compte.'])->assertStatus(404);
        $this->assertTrue($u->fresh()->hasTwoFactor());
    }
}

<?php

namespace Tests\Feature;

use App\Modules\Accounts\Actions\GrantAdministrator;
use App\Modules\Accounts\Models\AccountRole;
use App\Modules\Accounts\Models\StaffGrant;
use App\Modules\Accounts\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Finder\Finder;
use Tests\Support\AdminFixtures;
use Tests\TestCase;

class AdministratorTest extends TestCase
{
    use AdminFixtures, RefreshDatabase;

    private const EMAIL = 'chef@example.test';

    private function generatedPassword(): string
    {
        preg_match('/UNE seule fois[^:]*: (\S+)/', Artisan::output(), $m);

        return $m[1] ?? '';
    }

    public function test_grant_with_create_makes_an_admin_with_all_three_spaces_and_a_one_time_password(): void
    {
        $this->assertSame(0, Artisan::call('freeci:admin:grant', ['email' => 'Chef@Example.test', '--create' => true, '--yes' => true]));
        $password = $this->generatedPassword();

        $user = User::where('email', self::EMAIL)->firstOrFail();
        $this->assertTrue($user->isAdministrator());
        $this->assertTrue($user->hasRole(AccountRole::CLIENT));
        $this->assertTrue($user->hasRole(AccountRole::FREELANCE));
        $this->assertGreaterThanOrEqual(20, strlen($password));
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertNotSame($password, $user->password);
        $this->assertSame('console', $user->staffGrants()->first()->granted_by);
        $this->assertNotEmpty($user->staffGrants()->first()->reason);

        $this->post('/connexion', ['email' => self::EMAIL, 'password' => $password])->assertRedirect(route('account.dashboard'));
    }

    public function test_grant_never_promotes_an_unknown_or_existing_account_without_an_explicit_flag(): void
    {
        $this->assertSame(1, Artisan::call('freeci:admin:grant', ['email' => self::EMAIL, '--yes' => true]));
        $this->assertSame(0, User::count());

        $user = User::factory()->create(['email' => self::EMAIL]);
        $this->assertSame(1, Artisan::call('freeci:admin:grant', ['email' => self::EMAIL, '--yes' => true]));
        $this->assertFalse($user->isAdministrator());
        $this->assertStringContainsString('--existing', Artisan::output());
    }

    public function test_existing_account_can_be_confirmed_and_its_password_reset(): void
    {
        $user = User::factory()->create(['email' => self::EMAIL, 'password' => 'Ancien-mdp-2026']);

        $this->assertSame(0, Artisan::call('freeci:admin:grant', ['email' => self::EMAIL, '--existing' => true, '--yes' => true]));
        $this->assertTrue($user->fresh()->isAdministrator());
        $this->assertTrue(Hash::check('Ancien-mdp-2026', $user->fresh()->password), 'mot de passe inchangé sans --reset-password');
        $this->assertSame('', $this->generatedPassword());

        Artisan::call('freeci:admin:grant', ['email' => self::EMAIL, '--existing' => true, '--reset-password' => true, '--yes' => true]);
        $new = $this->generatedPassword();
        $this->assertNotSame('', $new);
        $this->assertFalse(Hash::check('Ancien-mdp-2026', $user->fresh()->password));
        $this->assertTrue(Hash::check($new, $user->fresh()->password));
        $this->assertSame(1, $user->staffGrants()->count(), 'idempotent : une seule habilitation active');
    }

    public function test_admin_area_is_hidden_from_everyone_but_active_administrators(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));

        $client = User::factory()->create();
        $this->actingAs($client)->get('/admin')->assertNotFound();
        $this->actingAs($client)->get('/espace')->assertDontSee('Administration');

        // habilitation seule : l'administration reste fermée (activation requise), jamais ouverte silencieusement
        $bare = User::factory()->create();
        app(GrantAdministrator::class)($bare, 'test');
        $this->actingAs($bare)->get('/admin')->assertRedirect(route('admin.activation'));

        $admin = $this->readyAdmin();
        $r = $this->asAdmin($admin)->get('/admin');
        $r->assertOk()->assertSee('Tableau de bord');
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $this->actingAs($admin)->get('/espace')->assertSee('Administration');
    }

    public function test_revoked_and_expired_grants_stop_working(): void
    {
        $admin = $this->readyAdmin(['email' => self::EMAIL]);
        StaffGrant::query()->update(['expires_at' => now()->addDay()]);
        $this->asAdmin($admin)->get('/admin')->assertOk();

        StaffGrant::query()->update(['expires_at' => now()->subMinute()]);
        $this->asAdmin($admin->fresh())->get('/admin')->assertNotFound();

        StaffGrant::query()->update(['expires_at' => null]);
        $this->asAdmin($admin->fresh())->get('/admin')->assertOk();

        $this->assertSame(0, Artisan::call('freeci:admin:revoke', ['email' => self::EMAIL]));
        $this->asAdmin($admin->fresh())->get('/admin')->assertNotFound();
        $this->assertTrue($admin->hasRole(AccountRole::CLIENT), 'les rôles client et freelance sont conservés');

        // une nouvelle habilitation reste possible après révocation (index unique partiel)
        app(GrantAdministrator::class)($admin->fresh(), 'retour');
        $this->asAdmin($admin->fresh())->get('/admin')->assertOk();
    }

    public function test_listing_shows_state_without_secrets(): void
    {
        Artisan::call('freeci:admin:grant', ['email' => self::EMAIL, '--create' => true, '--yes' => true]);
        $password = $this->generatedPassword();
        Artisan::call('freeci:admin:list');

        $out = Artisan::output();
        $this->assertStringContainsString(self::EMAIL, $out);
        $this->assertStringContainsString('ACTIVE', $out);
        $this->assertStringNotContainsString($password, $out);
    }

    public function test_registration_gives_the_client_role_and_never_admin_rights(): void
    {
        $this->post('/inscription', ['name' => 'Aya Test', 'email' => 'aya@example.test', 'password' => 'Motdepasse-2026', 'password_confirmation' => 'Motdepasse-2026']);
        $user = User::where('email', 'aya@example.test')->firstOrFail();

        $this->assertTrue($user->hasRole(AccountRole::CLIENT));
        $this->assertFalse($user->hasRole(AccountRole::FREELANCE));
        $this->assertFalse($user->isAdministrator());
        // pas d'auto-élévation par la requête d'inscription
        $this->post('/deconnexion');
        $this->post('/inscription', ['name' => 'Eve', 'email' => 'eve@example.test', 'password' => 'Motdepasse-2026', 'password_confirmation' => 'Motdepasse-2026', 'is_admin' => 1, 'role' => 'administrator']);
        $this->assertFalse(User::where('email', 'eve@example.test')->firstOrFail()->isAdministrator());
    }

    public function test_no_administrator_address_or_password_is_hardcoded_in_the_code(): void
    {
        foreach ((new Finder)->files()->in([app_path(), database_path(), base_path('config'), base_path('routes')])->name('*.php') as $f) {
            $this->assertStringNotContainsString('admin@', $f->getContents(), $f->getRelativePathname());
        }
    }
}

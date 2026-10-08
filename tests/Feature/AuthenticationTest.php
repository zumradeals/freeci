<?php

namespace Tests\Feature;

use App\Modules\Accounts\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Motdepasse-2026';

    public function test_registration_creates_a_hashed_account_and_logs_in(): void
    {
        $r = $this->post('/inscription', ['name' => 'Aya Test', 'email' => 'Aya@Example.test', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD]);

        $r->assertRedirect(route('account.dashboard'));
        $this->assertAuthenticated();
        $user = User::where('email', 'aya@example.test')->firstOrFail();
        $this->assertNotSame(self::PASSWORD, $user->password);
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));
        $this->assertFalse($user->is_demo);
    }

    public function test_registration_rejects_weak_or_unconfirmed_passwords_and_duplicates(): void
    {
        User::factory()->create(['email' => 'dup@example.test']);

        $this->post('/inscription', ['name' => 'A', 'email' => 'x@example.test', 'password' => 'court1', 'password_confirmation' => 'court1'])->assertSessionHasErrors(['name', 'password']);
        $this->post('/inscription', ['name' => 'Aya', 'email' => 'x@example.test', 'password' => self::PASSWORD, 'password_confirmation' => 'autre'])->assertSessionHasErrors('password');
        $this->post('/inscription', ['name' => 'Aya', 'email' => 'dup@example.test', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_logout_cycle(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);

        $this->post('/connexion', ['email' => $user->email, 'password' => self::PASSWORD])->assertRedirect(route('account.dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->post('/deconnexion')->assertRedirect(route('home'));
        $this->assertGuest();
        $this->get('/espace')->assertRedirect(route('login'));
    }

    public function test_wrong_credentials_give_one_generic_message_for_unknown_and_known_accounts(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);

        $a = $this->from('/connexion')->post('/connexion', ['email' => $user->email, 'password' => 'faux-mot-de-passe']);
        $b = $this->from('/connexion')->post('/connexion', ['email' => 'inconnu@example.test', 'password' => 'faux-mot-de-passe']);

        $this->assertGuest();
        $a->assertSessionHasErrors(['email' => __('auth.failed')]);
        $b->assertSessionHasErrors(['email' => __('auth.failed')]);
    }

    public function test_login_is_throttled_after_repeated_failures(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);
        RateLimiter::clear('x');

        for ($i = 0; $i < 5; $i++) {
            $this->post('/connexion', ['email' => $user->email, 'password' => 'faux'.$i]);
        }
        // même avec le bon mot de passe, le compte est verrouillé temporairement
        $this->post('/connexion', ['email' => $user->email, 'password' => self::PASSWORD])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_private_area_requires_authentication_and_is_not_cacheable(): void
    {
        $this->get('/espace')->assertRedirect(route('login'));

        $user = User::factory()->create();
        $r = $this->actingAs($user)->get('/espace');
        $r->assertOk()->assertSee('Par où commencer ?');
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $r->headers->get('Cache-Control'));
    }

    public function test_after_login_the_visitor_returns_to_the_private_page_he_asked_for_only(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);

        $this->get('/espace');
        $this->post('/connexion', ['email' => $user->email, 'password' => self::PASSWORD, 'redirect' => 'https://evil.example/'])
            ->assertRedirect(route('account.dashboard'));
    }

    public function test_logged_in_users_are_redirected_away_from_guest_pages(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/connexion')->assertRedirect(route('account.dashboard'));
        $this->get('/inscription')->assertRedirect(route('account.dashboard'));
    }

    public function test_password_reset_request_does_not_reveal_whether_an_account_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $known = $this->post('/mot-de-passe-oublie', ['email' => $user->email]);
        $unknown = $this->post('/mot-de-passe-oublie', ['email' => 'personne@example.test']);

        $known->assertSessionHas('status');
        $unknown->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertCount(1);
    }

    public function test_password_can_be_reset_once_with_a_valid_token_only(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);
        $token = Password::createToken($user);
        $new = 'Nouveau-mdp-2026';

        $this->post('/reinitialisation', ['token' => 'mauvais', 'email' => $user->email, 'password' => $new, 'password_confirmation' => $new])->assertSessionHasErrors('email');

        $this->post('/reinitialisation', ['token' => $token, 'email' => $user->email, 'password' => $new, 'password_confirmation' => $new])->assertRedirect(route('login'));
        $this->assertTrue(Hash::check($new, $user->fresh()->password));

        // un jeton déjà utilisé est refusé
        $this->post('/reinitialisation', ['token' => $token, 'email' => $user->email, 'password' => 'Autre-mdp-2026', 'password_confirmation' => 'Autre-mdp-2026'])->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check($new, $user->fresh()->password));
    }

    public function test_reset_form_is_reachable_from_the_emailed_link(): void
    {
        $this->get('/reinitialisation/abc?email=a@example.test')->assertOk()->assertSee('Nouveau mot de passe');
    }

    public function test_access_pages_use_the_two_pane_layout_and_no_longer_announce_orders_as_future(): void
    {
        $this->get('/connexion')->assertOk()->assertSee('Content de vous revoir.')->assertSee('Un accord clair')->assertSee('Mot de passe oublié ?')->assertSee('data-reveal="f-password"', false);
        $register = $this->get('/inscription')->assertOk()->assertSee('Créer un compte')->assertSee('vous pourrez activer l’espace freelance ensuite')->assertSee('data-strength-for="f-password"', false)
            ->assertSee('conditions d’utilisation')->assertSee('politique de confidentialité')->assertDontSee('prochain lot');
        $this->assertStringNotContainsString('type="checkbox" name="terms"', $register->getContent());
        $this->get('/mot-de-passe-oublie')->assertOk()->assertSee('Envoyer le lien');
    }
}

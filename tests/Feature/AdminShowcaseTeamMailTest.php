<?php

namespace Tests\Feature;

use App\Mail\NotificationMail;
use App\Mail\VerifyEmailMail;
use App\Modules\Accounts\Models\User;
use App\Modules\Admin\Actions\ManageAdministrators;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\AdminFixtures;
use Tests\Support\OrderFixtures;
use Tests\TestCase;

/** Lot 32 — bandeau d'annonce, catégorie à la une, textes des courriels, équipe d'assistance : tout se gère depuis l'administration. */
class AdminShowcaseTeamMailTest extends TestCase
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

    private function save(string $group, array $v)
    {
        return $this->asAdmin($this->admin)->post("/admin/parametres/{$group}", ['v' => $this->settingsGroup($group, $v), 'reason' => 'Réglage de la vitrine validé.']);
    }

    public function test_the_announcement_banner_is_shown_only_when_enabled_with_a_safe_internal_link(): void
    {
        $this->get('/')->assertDontSee('class="announce"', false);
        $this->save('vitrine', ['home_announce_enabled' => '1', 'home_announce_text' => 'Nouveauté : la messagerie est ouverte.', 'home_announce_link' => 'services', 'home_announce_link_label' => 'Voir le catalogue'])->assertSessionHas('status');
        $this->app['auth']->forgetGuards();
        $this->get('/')->assertSee('class="announce"', false)->assertSee('Nouveauté : la messagerie est ouverte.')->assertSee('Voir le catalogue')->assertSee(parse_url(route('services.index'), PHP_URL_PATH).'"', false);
        // un lien hors liste est refusé (aucune adresse libre possible)
        $this->save('vitrine', ['home_announce_enabled' => '1', 'home_announce_text' => 'x', 'home_announce_link' => 'javascript:alert(1)'])->assertSessionHas('error');
        // désactivé : plus de bandeau
        $this->save('vitrine', ['home_announce_enabled' => '0', 'home_announce_text' => 'Nouveauté : la messagerie est ouverte.'])->assertSessionHas('status');
        $this->app['auth']->forgetGuards();
        $this->get('/')->assertDontSee('class="announce"', false);
    }

    public function test_one_category_can_be_featured_on_the_home_and_only_one(): void
    {
        $a = Category::factory()->create(['name' => 'Rubrique A à la une']);
        $b = Category::factory()->create(['name' => 'Rubrique B à la une']);
        Service::factory()->create(['category_id' => $a->id]);
        Service::factory()->create(['category_id' => $b->id]);
        $this->asAdmin($this->admin)->post("/admin/categories/{$a->id}/mettre-en-avant")->assertSessionHas('status');
        $this->asAdmin($this->admin)->post("/admin/categories/{$b->id}/mettre-en-avant")->assertSessionHas('status');
        $this->assertNull($a->fresh()->featured_at);
        $this->assertNotNull($b->fresh()->featured_at);
        $this->app['auth']->forgetGuards();
        $this->get('/')->assertSee('À la une')->assertSee('Voir cette catégorie')->assertSee("categorie={$b->slug}", false);
        $this->asAdmin($this->admin)->post("/admin/categories/{$b->id}/retirer-mise-en-avant")->assertSessionHas('status');
        $this->app['auth']->forgetGuards();
        $this->get('/')->assertDontSee('Voir cette catégorie');
        // une catégorie archivée ne peut pas être mise en avant
        $a->update(['archived_at' => now()]);
        $this->asAdmin($this->admin)->post("/admin/categories/{$a->id}/mettre-en-avant")->assertSessionHas('error');
    }

    public function test_email_texts_are_editable_and_fall_back_to_the_starting_text(): void
    {
        $url = 'https://freeci.test/verifier/abc';
        $default = (new VerifyEmailMail($url))->render();
        $this->assertStringContainsString('Pour confirmer votre adresse e-mail sur FreeCI, ouvrez ce lien :', $default);
        $this->assertStringContainsString('Ce lien est valable 60 minutes.', $default);

        $this->save('courriels', ['mailtpl_subject_prefix' => 'MonMarché', 'mailtpl_greeting' => 'Salut,', 'mailtpl_verify_intro' => 'Cliquez pour confirmer votre adresse :', 'mailtpl_signature' => 'L’équipe MonMarché'])->assertSessionHas('status');
        $mail = new VerifyEmailMail($url);
        $text = $mail->render();
        $this->assertStringContainsString('Salut,', $text);
        $this->assertStringContainsString('Cliquez pour confirmer votre adresse :', $text);
        $this->assertStringContainsString('L’équipe MonMarché', $text);
        $this->assertStringContainsString($url, $text);
        $this->assertStringContainsString('Ce lien est valable 60 minutes.', $text, 'la durée de validité reste fixée par l’application');
        $this->assertStringContainsString('Si vous n’êtes pas à l’origine de cette demande', $text, 'champ vide : texte de départ');
        $this->assertSame('MonMarché : confirmez votre adresse e-mail', $mail->envelope()->subject);
        $this->assertSame('MonMarché : Livraison à examiner', (new NotificationMail('Livraison à examiner'))->envelope()->subject);
        $this->assertStringContainsString('ni message privé ni pièce jointe', (new NotificationMail('Titre'))->render());
    }

    public function test_the_support_team_is_managed_from_the_administration_without_granting_administrator(): void
    {
        $agent = User::factory()->create(['email' => 'agent@example.test']);
        $this->asAdmin($this->admin)->get('/admin/equipe')->assertOk()->assertSee('Aucune personne');
        $this->asAdmin($this->admin)->post('/admin/equipe', ['email' => 'inconnu@example.test', 'reason' => 'Renfort pour l’assistance.'])->assertSessionHas('error');
        $this->asAdmin($this->admin)->post('/admin/equipe', ['email' => 'AGENT@example.test', 'reason' => 'Renfort pour l’assistance.'])->assertSessionHas('status');
        $this->assertTrue($agent->fresh()->isSupport());
        $this->assertFalse($agent->fresh()->isAdministrator(), 'jamais d’habilitation d’administrateur depuis l’interface');
        $this->asAdmin($this->admin)->get('/admin/equipe')->assertOk()->assertSee('agent@example.test')->assertSee('Activation en attente');
        $this->asAdmin($this->admin)->post('/admin/equipe', ['email' => $this->admin->email, 'reason' => 'Tentative sur un administrateur.'])->assertSessionHas('error');
        // sans reconfirmation récente, aucune écriture
        $this->asAdmin($this->admin, false)->post("/admin/equipe/{$agent->id}/retirer", ['reason' => 'Fin de mission du renfort.'])->assertRedirect();
        $this->assertTrue($agent->fresh()->isSupport());
        $this->asAdmin($this->admin)->post("/admin/equipe/{$agent->id}/retirer", ['reason' => 'Fin de mission du renfort.'])->assertSessionHas('status');
        $this->assertFalse($agent->fresh()->isSupport());
        $this->assertSame(['support.grant', 'support.revoke'], DB::table('admin_actions')->where('result', 'done')->whereIn('action', ['support.grant', 'support.revoke'])->orderBy('id')->pluck('action')->all());
        // le personnel d'assistance lui-même n'accède pas à cette page
        $this->actingAs($agent)->get('/admin/equipe')->assertNotFound();
    }

    public function test_administrator_rights_can_be_granted_and_revoked_from_the_administration_under_safeguards(): void
    {
        $phrase = ManageAdministrators::PHRASE;
        $second = User::factory()->create(['email' => 'second@example.test']);
        $base = ['email' => 'second@example.test', 'reason' => 'Désignation d’un second administrateur.', 'phrase' => $phrase, 'confirm' => '1'];

        $this->asAdmin($this->admin)->get('/admin/equipe')->assertOk()->assertSee('Administrateurs')->assertSee('Un administrateur peut tout faire')->assertSee('tm-m', false)->assertSee('Ce que permet chaque habilitation');
        // phrase fausse, case non cochée, compte inconnu ou non vérifié : refus
        $this->asAdmin($this->admin)->post('/admin/equipe/administrateurs', ['phrase' => 'oui'] + $base)->assertSessionHas('error');
        $this->asAdmin($this->admin)->post('/admin/equipe/administrateurs', array_diff_key($base, ['confirm' => 1]))->assertSessionHasErrors('confirm');
        $this->asAdmin($this->admin)->post('/admin/equipe/administrateurs', ['email' => 'nobody@example.test'] + $base)->assertSessionHas('error');
        $second->forceFill(['email_verified_at' => null])->save();
        $this->asAdmin($this->admin)->post('/admin/equipe/administrateurs', $base)->assertSessionHas('error');
        $this->assertFalse($second->fresh()->isAdministrator());
        // sans reconfirmation récente : aucune écriture
        $second->forceFill(['email_verified_at' => now()])->save();
        $this->asAdmin($this->admin, false)->post('/admin/equipe/administrateurs', $base)->assertRedirect();
        $this->assertFalse($second->fresh()->isAdministrator());
        // octroi correct (apostrophe droite acceptée)
        $this->asAdmin($this->admin)->post('/admin/equipe/administrateurs', ['phrase' => str_replace('’', "'", mb_strtolower($phrase))] + $base)->assertSessionHas('status');
        $this->assertTrue($second->fresh()->isAdministrator());
        $this->assertSame('admin:'.$this->admin->id, DB::table('staff_grants')->where('user_id', $second->id)->where('capability', 'administrator')->value('granted_by'));
        // on ne retire pas sa propre habilitation ; un autre administrateur peut retirer ; jamais le dernier
        $this->asAdmin($this->admin)->post("/admin/equipe/administrateurs/{$this->admin->id}/retirer", ['reason' => 'Je me retire moi-même.', 'confirm' => '1'])->assertSessionHas('error');
        $this->assertTrue($this->admin->fresh()->isAdministrator());
        $this->asAdmin($this->admin)->post("/admin/equipe/administrateurs/{$second->id}/retirer", ['reason' => 'Fin de la mission du second.', 'confirm' => '1'])->assertSessionHas('status');
        $this->assertFalse($second->fresh()->isAdministrator());
        $this->assertSame(['admin.grant', 'admin.revoke'], DB::table('admin_actions')->where('result', 'done')->whereIn('action', ['admin.grant', 'admin.revoke'])->orderBy('id')->pluck('action')->all());
        $this->assertGreaterThanOrEqual(2, DB::table('admin_actions')->where('result', 'refused')->whereIn('action', ['admin.grant', 'admin.revoke'])->count());
    }
}

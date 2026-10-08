<?php

namespace Tests\Feature;

use App\Modules\Accounts\Actions\AccountClosure;
use App\Modules\Accounts\Actions\ExportPersonalData;
use App\Modules\Accounts\Actions\ProfilePhotos;
use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Support\ImageProcessor;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\AdminFixtures;
use Tests\Support\OrderFixtures;
use Tests\TestCase;

/** Lot 67 : photo de profil (publication immédiate, signalement, retrait par l'administration avec motif et journal). */
class ProfilePhotoTest extends TestCase
{
    use AdminFixtures, OrderFixtures, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        if (! ImageProcessor::available()) {
            $this->markTestSkipped('Extension GD (WebP) absente.');
        }
        Storage::fake('private_files');
        $this->setUpParties();
        $this->admin = $this->readyAdmin(['name' => 'Admin Photo']);
    }

    private function jpg(int $w = 400, int $h = 300, string $name = 'moi.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, $w, $h);
    }

    private function upload(User $u, ?UploadedFile $f = null)
    {
        return $this->actingAs($u)->post('/espace/compte/photo', ['photo' => $f ?? $this->jpg()]);
    }

    private function active(User $u): ?object
    {
        return DB::table('profile_photos')->where('user_id', $u->id)->where('state', 'active')->first();
    }

    public function test_a_freelancer_photo_is_public_at_once_square_webp_and_replaced_without_leaving_files(): void
    {
        $this->upload($this->freelancer)->assertRedirect()->assertSessionHas('status');
        $p = $this->active($this->freelancer);
        $this->assertNotNull($p);
        Storage::disk('private_files')->assertExists([$p->key_large, $p->key_small]);
        [$w, $h, $type] = getimagesizefromstring(Storage::disk('private_files')->get($p->key_large));
        $this->assertSame([512, 512, IMAGETYPE_WEBP], [$w, $h, $type]);
        auth()->forgetGuards();
        $this->get("/photos/{$p->id}/large")->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->get("/photos/{$p->id}/small")->assertOk();
        $this->get("/photos/{$p->id}/huge")->assertNotFound();

        $this->upload($this->freelancer, $this->jpg(300, 600, 'autre.png'))->assertSessionHas('status');
        $q = $this->active($this->freelancer);
        $this->assertNotSame($p->id, $q->id);
        $this->assertSame(1, DB::table('profile_photos')->where('user_id', $this->freelancer->id)->where('state', 'active')->count());
        Storage::disk('private_files')->assertMissing([$p->key_large, $p->key_small]);
        auth()->forgetGuards();
        $this->get("/photos/{$p->id}/large")->assertNotFound();
    }

    public function test_bad_files_are_refused_and_nothing_is_stored(): void
    {
        $this->upload($this->freelancer, UploadedFile::fake()->create('cv.pdf', 20, 'application/pdf'))->assertSessionHas('error');
        $this->upload($this->freelancer, $this->jpg(120, 120))->assertSessionHas('error');
        $this->upload($this->freelancer, UploadedFile::fake()->createWithContent('faux.jpg', '<?php echo 1;'))->assertSessionHas('error');
        $this->assertSame(0, DB::table('profile_photos')->count());
        $this->actingAs($this->freelancer)->post('/espace/compte/photo', [])->assertSessionHasErrors('photo');
    }

    public function test_a_client_photo_is_visible_only_to_the_owner_the_counterparty_and_staff(): void
    {
        $this->placeOrder();
        $this->upload($this->client);
        $p = $this->active($this->client);
        auth()->forgetGuards();
        $this->get("/photos/{$p->id}/small")->assertNotFound();
        $this->actingAs($this->client)->get("/photos/{$p->id}/small")->assertOk();
        $this->actingAs($this->freelancer)->get("/photos/{$p->id}/small")->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $stranger = User::factory()->create();
        $this->actingAs($stranger)->get("/photos/{$p->id}/small")->assertNotFound();
        $this->asAdmin($this->admin)->get("/photos/{$p->id}/small")->assertOk();
    }

    public function test_the_user_can_delete_the_photo_and_initials_come_back(): void
    {
        $this->upload($this->freelancer);
        $p = $this->active($this->freelancer);
        $this->actingAs($this->freelancer)->post('/espace/compte/photo/supprimer')->assertRedirect()->assertSessionHas('status');
        $this->assertNull($this->active($this->freelancer));
        Storage::disk('private_files')->assertMissing([$p->key_large, $p->key_small]);
        $this->actingAs($this->freelancer)->post('/espace/compte/photo/supprimer')->assertSessionHas('status');
    }

    public function test_the_photo_is_shown_on_public_pages_and_forms_are_available(): void
    {
        $this->upload($this->freelancer);
        $p = $this->active($this->freelancer);
        auth()->forgetGuards();
        $slug = DB::table('freelance_profiles')->where('user_id', $this->freelancer->id)->value('slug');
        $this->get("/freelances/{$slug}")->assertOk()->assertSee("/photos/{$p->id}/large", false);
        $this->get('/services')->assertOk()->assertSee("/photos/{$p->id}/small", false);
        $this->actingAs($this->freelancer)->get('/freelance/profil')->assertOk()->assertSee('Photo de profil')->assertSee('Supprimer la photo');
        $this->actingAs($this->client)->get('/espace/compte')->assertOk()->assertSee('Choisir une photo')->assertSee('Facultative');
    }

    public function test_the_administrator_removes_a_photo_with_a_reason_audit_trail_and_notification(): void
    {
        $this->upload($this->freelancer);
        $p = $this->active($this->freelancer);
        $url = "/admin/utilisateurs/{$this->freelancer->id}/photo/retirer";
        $this->actingAs($this->client)->post($url, ['reason' => 'Motif suffisamment long'])->assertNotFound();
        $this->asAdmin($this->admin, recent: false)->post($url, ['reason' => 'Motif suffisamment long'])->assertRedirect(route('admin.reauth'));
        $this->assertNotNull($this->active($this->freelancer), 'sans confirmation récente : rien ne change');
        $this->asAdmin($this->admin)->post($url, ['reason' => 'court'])->assertSessionHasErrors('reason');
        $this->asAdmin($this->admin)->post($url, ['reason' => 'Photo qui n’est pas celle de la personne.'])->assertRedirect()->assertSessionHas('status');

        $this->assertNull($this->active($this->freelancer));
        $row = DB::table('profile_photos')->where('id', $p->id)->first();
        $this->assertSame('removed', $row->state);
        $this->assertSame($this->admin->id, $row->removed_by);
        $this->assertNotNull($row->purge_after);
        auth()->forgetGuards();
        $this->get("/photos/{$p->id}/large")->assertNotFound();
        $this->asAdmin($this->admin)->get("/photos/{$p->id}/large")->assertOk();        // conservée pour un éventuel litige
        $this->assertSame(1, DB::table('admin_actions')->where('action', 'user.photo_remove')->where('result', 'done')->count());
        $n = DB::table('app_notifications')->where('user_id', $this->freelancer->id)->where('type', 'photo_removed')->first();
        $this->assertNotNull($n);
        $this->assertStringContainsString('Photo qui n’est pas celle', (string) $n->body);
        $this->asAdmin($this->admin)->get('/admin/journal')->assertOk()->assertSee('Photo de profil retirée');
        $this->asAdmin($this->admin)->post($url, ['reason' => 'Deuxième tentative sans photo.'])->assertSessionHas('error');
        $this->assertSame(1, DB::table('admin_actions')->where('action', 'user.photo_remove')->where('result', 'refused')->count());
        // la personne peut en déposer une autre
        $this->upload($this->freelancer)->assertSessionHas('status');
        $this->assertNotNull($this->active($this->freelancer));
    }

    public function test_the_admin_user_page_shows_the_photo_and_the_removal_form_but_not_for_oneself(): void
    {
        $this->upload($this->freelancer);
        $this->asAdmin($this->admin)->get("/admin/utilisateurs/{$this->freelancer->id}")->assertOk()->assertSee('Photo de profil')->assertSee('Retirer la photo')->assertSee('Photo en ligne');
        $this->upload($this->admin);
        $this->asAdmin($this->admin)->get("/admin/utilisateurs/{$this->admin->id}")->assertOk()->assertDontSee('Retirer la photo');
    }

    public function test_removed_photo_files_are_purged_after_the_delay_unless_a_report_is_open(): void
    {
        $this->upload($this->freelancer);
        $p = $this->active($this->freelancer);
        $this->asAdmin($this->admin)->post("/admin/utilisateurs/{$this->freelancer->id}/photo/retirer", ['reason' => 'Photo inappropriée constatée.']);
        $photos = app(ProfilePhotos::class);
        $this->assertSame(0, $photos->purgeExpired(), 'délai de conservation en cours');
        $this->travel(31)->days();
        $profile = DB::table('freelance_profiles')->where('user_id', $this->freelancer->id)->value('id');
        $case = (string) Str::uuid();
        DB::table('support_cases')->insert(['id' => $case, 'reference' => 'SUP-TEST-0001', 'kind' => 'report', 'requester_id' => $this->client->id, 'target_type' => 'profile', 'target_id' => $profile, 'status' => 'open',
            'subject' => 'Signalement', 'opened_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame(0, $photos->purgeExpired(), 'dossier ouvert : conservation prolongée');
        Storage::disk('private_files')->assertExists($p->key_large);
        DB::table('support_cases')->where('id', $case)->update(['status' => 'closed']);
        $this->assertSame(1, $photos->purgeExpired());
        Storage::disk('private_files')->assertMissing([$p->key_large, $p->key_small]);
        $this->assertNull(DB::table('profile_photos')->where('id', $p->id)->value('key_large'));
        $this->artisan('freeci:photos:purge')->assertSuccessful();
    }

    public function test_profile_report_accepts_the_photo_reason_and_other_targets_do_not_offer_it(): void
    {
        $slug = DB::table('freelance_profiles')->where('user_id', $this->freelancer->id)->value('slug');
        $this->actingAs($this->client)->get("/espace/assistance/signaler/profile/{$slug}")->assertOk()->assertSee('Photo de profil inappropriée');
        $this->actingAs($this->client)->get('/espace/assistance/signaler/service/'.$this->service->slug)->assertOk()->assertDontSee('Photo de profil inappropriée');
        $this->actingAs($this->client)->post("/espace/assistance/signaler/profile/{$slug}", ['reason' => 'photo', 'body' => 'Cette photo n’est pas celle du freelance.', 'operation_key' => (string) Str::uuid()])->assertRedirect();
        $this->assertSame(1, DB::table('support_cases')->where('kind', 'report')->where('target_type', 'profile')->count());
    }

    public function test_export_lists_photos_and_closing_the_account_erases_the_files(): void
    {
        $this->upload($this->client);
        $p = $this->active($this->client);
        $export = app(ExportPersonalData::class)->build($this->client);
        $this->assertSame('active', $export['photos_de_profil'][0]['etat']);
        $this->assertArrayNotHasKey('key_large', $export['photos_de_profil'][0]);
        app(ProfilePhotos::class)->purgeAll($this->client->id);
        Storage::disk('private_files')->assertMissing([$p->key_large, $p->key_small]);
        $this->assertSame('closed', DB::table('profile_photos')->where('id', $p->id)->value('state'));
        $this->assertNull($this->active($this->client));
        $this->assertTrue(method_exists(AccountClosure::class, 'blockers'));
    }

    public function test_only_one_active_photo_per_person_even_in_the_database(): void
    {
        $this->upload($this->freelancer);
        $this->expectException(QueryException::class);
        DB::table('profile_photos')->insert(['id' => (string) Str::uuid(), 'user_id' => $this->freelancer->id, 'state' => 'active', 'mime' => 'image/webp', 'sha256' => str_repeat('a', 64), 'created_at' => now()]);
    }

    public function test_the_photo_is_shown_in_orders_and_messages_to_the_other_party(): void
    {
        $o = $this->placeOrder();
        $this->upload($this->client);
        $cp = $this->active($this->client);
        $this->upload($this->freelancer);
        $fp = $this->active($this->freelancer);
        $this->actingAs($this->freelancer)->get("/commandes/{$o->reference}")->assertOk()->assertSee("/photos/{$cp->id}/large", false);
        $this->actingAs($this->client)->get("/commandes/{$o->reference}")->assertOk()->assertSee("/photos/{$fp->id}/large", false);
        $this->actingAs($this->freelancer)->get('/freelance/commandes')->assertOk()->assertSee("/photos/{$cp->id}/small", false);
        $this->actingAs($this->client)->get('/espace/commandes')->assertOk()->assertSee("/photos/{$fp->id}/small", false);
    }
}

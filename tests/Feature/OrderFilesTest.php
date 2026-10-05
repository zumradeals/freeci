<?php

namespace Tests\Feature;

use App\Integrations\FileScan\FileScanner;
use App\Integrations\FileScan\UnavailableScanner;
use App\Modules\Accounts\Models\User;
use App\Modules\Files\Models\FileAsset;
use App\Modules\Orders\Enums\OrderState;
use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\Support\FakeScanner;
use Tests\Support\OrderFixtures;
use Tests\TestCase;

class OrderFilesTest extends TestCase
{
    use OrderFixtures, RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private_files');
        $this->setUpParties();
        $this->useFakeScanner();
        $this->enableSandbox();
        $this->order = $this->placeOrder();
    }

    private function pdf(string $name = 'plan.pdf', string $extra = ''): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<<>>endobj\n{$extra}trailer<<>>\n%%EOF\n");
    }

    private function upload(UploadedFile $file, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->client)->post("/commandes/{$this->order->reference}/brief/fichiers", ['file' => $file]);
    }

    private function asset(): ?FileAsset
    {
        return FileAsset::query()->orderByDesc('id')->first();
    }

    public function test_a_clean_file_is_stored_privately_under_an_opaque_key_and_downloadable_by_both_parties_only(): void
    {
        $this->upload($this->pdf())->assertRedirect()->assertSessionHas('status');
        $f = $this->asset();
        $this->assertSame('clean', $f->state->value);
        $this->assertStringNotContainsString('plan', $f->storage_key, 'clé opaque');
        Storage::disk('private_files')->assertExists($f->storage_key);

        $page = $this->actingAs($this->freelancer)->get("/commandes/{$this->order->reference}")->assertOk();
        preg_match('~href="([^"]*/fichiers/[^"]+)"~', $page->getContent(), $m);
        $url = html_entity_decode($m[1] ?? '');
        $this->assertNotSame('', $url, 'lien signé présent pour le freelance');

        $r = $this->actingAs($this->freelancer)->get($url)->assertOk();
        $this->assertStringContainsString('attachment', $r->headers->get('content-disposition'));
        $this->assertSame('nosniff', $r->headers->get('x-content-type-options'));
        $this->assertSame('application/octet-stream', $r->headers->get('content-type'));

        // un tiers : 404 même avec un lien signé valide d'une partie
        $other = User::factory()->create();
        $this->actingAs($other)->get($url)->assertNotFound();
        // anonyme : redirigé vers la connexion
        auth()->forgetGuards();
        $this->get($url)->assertRedirect();
        // lien non signé / altéré
        $this->actingAs($this->client)->get("/commandes/{$this->order->reference}/fichiers/{$f->id}")->assertForbidden();
        $this->actingAs($this->client)->get($url.'x')->assertForbidden();
    }

    public function test_an_unscanned_or_rejected_file_is_never_downloadable(): void
    {
        FakeScanner::$unavailable = true;
        $this->upload($this->pdf())->assertRedirect();
        $f = $this->asset();
        $this->assertSame('quarantined', $f->state->value);

        $page = $this->actingAs($this->client)->get("/commandes/{$this->order->reference}")->assertOk();
        $this->assertStringContainsString('Non téléchargeable', $page->getContent());
        $this->assertStringContainsString('Non téléchargeable avant la fin du contrôle', $page->getContent());

        // même un lien signé fabriqué à la main est refusé tant que le fichier n'est pas propre
        $url = URL::temporarySignedRoute('orders.files.download', now()->addMinutes(5), ['reference' => $this->order->reference, 'file' => $f->id, 'u' => $this->client->id]);
        $this->actingAs($this->client)->get($url)->assertNotFound();
        $this->actingAs($this->freelancer)->get(str_replace((string) $this->client->id, (string) $this->freelancer->id, $url))->assertForbidden();

        // infecté : refusé, supprimé du stockage, jamais téléchargeable
        FakeScanner::$unavailable = false;
        $this->upload($this->pdf('virus.pdf', 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE'))->assertRedirect();
        $bad = $this->asset();
        $this->assertSame('rejected', $bad->state->value);
        Storage::disk('private_files')->assertMissing($bad->storage_key);
        $url = URL::temporarySignedRoute('orders.files.download', now()->addMinutes(5), ['reference' => $this->order->reference, 'file' => $bad->id, 'u' => $this->client->id]);
        $this->actingAs($this->client)->get($url)->assertNotFound();
    }

    public function test_the_default_scanner_is_unavailable_and_uploads_are_refused_without_it(): void
    {
        $this->app->bind(FileScanner::class, UnavailableScanner::class);
        $this->assertFalse(app(FileScanner::class)->isOperational());
        $this->upload($this->pdf())->assertRedirect()->assertSessionHas('error');
        $this->assertSame(0, FileAsset::count());
    }

    public function test_only_the_client_of_the_order_can_upload_or_remove(): void
    {
        $this->upload($this->pdf(), $this->freelancer)->assertNotFound();
        $this->upload($this->pdf(), User::factory()->create())->assertNotFound();
        auth()->forgetGuards();
        $this->post("/commandes/{$this->order->reference}/brief/fichiers", ['file' => $this->pdf()])->assertRedirect();
        $this->assertSame(0, FileAsset::count());

        $this->upload($this->pdf())->assertRedirect();
        $f = $this->asset();
        $this->actingAs($this->freelancer)->post("/commandes/{$this->order->reference}/brief/fichiers/{$f->id}/retirer")->assertNotFound();
        $this->assertSame('clean', $f->fresh()->state->value);
        $this->actingAs($this->client)->post("/commandes/{$this->order->reference}/brief/fichiers/{$f->id}/retirer")->assertRedirect();
        $this->assertSame('removed', $f->fresh()->state->value);
    }

    public function test_format_and_size_limits_and_real_content_type(): void
    {
        config(['freeci.files.max_mb' => 1]);
        $bad = [
            'exe' => UploadedFile::fake()->createWithContent('outil.exe', 'MZ....'),
            'php déguisé' => UploadedFile::fake()->createWithContent('image.php.png', "\x89PNG\r\n\x1a\n<?php echo 1;"),
            'double extension' => $this->pdf('facture.pdf.php'),
            'pdf mensonger' => UploadedFile::fake()->createWithContent('faux.pdf', 'ceci est du texte'),
            'png mensonger' => UploadedFile::fake()->createWithContent('faux.png', '%PDF-1.4 pas une image'),
            'trop gros' => UploadedFile::fake()->createWithContent('gros.pdf', "%PDF-1.4\n".str_repeat('a', 1024 * 1024 + 10)),
        ];
        foreach ($bad as $label => $file) {
            $this->upload($file)->assertRedirect()->assertSessionHas('error');
            $this->assertSame(0, FileAsset::count(), $label);
        }
    }

    public function test_files_are_frozen_once_the_work_has_started_and_the_agreement_is_untouched(): void
    {
        $this->upload($this->pdf())->assertRedirect();
        $f = $this->asset();
        $agreement = $this->order->agreement->only(['price_xof', 'scope', 'brief_requires_files', 'delivery_days']);

        $this->accept($this->order);
        $this->payableOrderStarted();
        $this->assertSame($agreement, $this->order->agreement->fresh()->only(['price_xof', 'scope', 'brief_requires_files', 'delivery_days']), 'compléter le brief ne touche pas l’accord');
        $this->assertSame('clean', $f->fresh()->state->value);
    }

    private function payableOrderStarted(): void
    {
        $this->startPayment($this->order)->assertRedirect();
        $ref = $this->currentPayment($this->order)->provider_reference;
        $this->resolve($ref, 'succeeded', ['--notify' => true]);
        $this->assertSame(OrderState::InProgress, $this->order->fresh()->state);

        $this->upload($this->pdf('apres.pdf'))->assertRedirect()->assertSessionHas('error');
        $this->actingAs($this->client)->post("/commandes/{$this->order->reference}/brief/fichiers/{$this->asset()->id}/retirer")->assertRedirect()->assertSessionHas('error');
        $this->assertSame('clean', $this->asset()->fresh()->state->value);
        Artisan::call('freeci:files:scan');
    }
}

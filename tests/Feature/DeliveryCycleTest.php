<?php

namespace Tests\Feature;

use App\Modules\Accounts\Actions\GrantAdministrator;
use App\Modules\Accounts\Models\User;
use App\Modules\Files\Models\FileAsset;
use App\Modules\Orders\Enums\OrderState;
use App\Modules\Orders\Models\CorrectionRequest;
use App\Modules\Orders\Models\Delivery;
use App\Modules\Orders\Models\Order;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\Support\FakeScanner;
use Tests\Support\OrderFixtures;
use Tests\TestCase;

/** Lot 4 : livraison versionnée, corrections, report d'échéance, validation, silence du client. */
class DeliveryCycleTest extends TestCase
{
    use OrderFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private_files');
        $this->setUpParties();
        $this->service->update(['delivery_requires_files' => false]);      // la plupart des scénarios livrent par message seul
        $this->useFakeScanner();
    }

    private function pdf(string $name = 'livraison.pdf', string $extra = ''): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<<>>endobj\n{$extra}trailer<<>>\n%%EOF\n");
    }

    private function draft(Order $o, string $message = 'Voici la livraison complète des plans, formats DWG et PDF.', bool $withFile = true): void
    {
        $this->actingAs($this->freelancer)->post("/commandes/{$o->reference}/livraison/message", ['message' => $message])->assertRedirect();
        if ($withFile) {
            $this->actingAs($this->freelancer)->post("/commandes/{$o->reference}/livraison/fichiers", ['file' => $this->pdf('plans-v'.(Delivery::count() + 1).'.pdf')])->assertRedirect()->assertSessionMissing('error');
        }
    }

    private function submit(Order $o, ?string $key = null, ?int $version = null)
    {
        $d = Delivery::query()->where('order_id', $o->id)->where('state', 'draft')->first();

        return $this->actingAs($this->freelancer)->post("/commandes/{$o->reference}/livraison/soumettre", [
            'delivery_id' => $d?->id ?? (string) Str::uuid(), 'expected_version' => $version ?? $o->fresh()->row_version, 'operation_key' => $key ?? (string) Str::uuid(),
        ]);
    }

    private function deliver(Order $o): Delivery
    {
        $this->draft($o);
        $this->submit($o)->assertRedirect()->assertSessionHas('status');

        return Delivery::query()->where('order_id', $o->id)->where('state', 'submitted')->orderByDesc('version')->firstOrFail();
    }

    private function correct(Order $o, Delivery $d, string $reason = 'Le calque COTATION manque sur les plans 3 et 7.', ?string $key = null)
    {
        return $this->actingAs($this->client)->post("/commandes/{$o->reference}/correction", ['delivery_id' => $d->id, 'reason' => $reason, 'expected_version' => $o->fresh()->row_version, 'operation_key' => $key ?? (string) Str::uuid()]);
    }

    private function validateIt(Order $o, Delivery $d, ?string $key = null, bool $confirm = true)
    {
        return $this->actingAs($this->client)->post("/commandes/{$o->reference}/validation", ['delivery_id' => $d->id, 'expected_version' => $o->fresh()->row_version, 'operation_key' => $key ?? (string) Str::uuid()] + ($confirm ? ['confirm' => '1'] : []));
    }

    /** Une violation d'intégrité dans la base doit être refusée ; le point de sauvegarde garde la transaction du test utilisable. */
    private function dbRefuses(callable $attempt, string $contains = ''): void
    {
        try {
            DB::transaction($attempt);
        } catch (QueryException $e) {
            $this->assertStringContainsString($contains, $e->getMessage());

            return;
        }
        $this->fail('La base aurait dû refuser cette modification.');
    }

    // ---------- cycle complet ----------

    public function test_full_cycle_delivery_correction_new_version_validation_and_commercial_closure(): void
    {
        $order = $this->inProgress();
        $paymentsBefore = DB::table('payments')->count();
        $ledgerBefore = DB::table('ledger_batches')->count();

        $v1 = $this->deliver($order);
        $this->assertSame(1, $v1->version);
        $this->assertSame(OrderState::Delivered, $order->fresh()->state);

        $this->correct($order, $v1)->assertRedirect()->assertSessionHas('status');
        $order->refresh();
        $this->assertSame(OrderState::RevisionRequested, $order->state);
        $c = CorrectionRequest::firstOrFail();
        $this->assertSame([1, $v1->id], [$c->number, $c->delivery_id]);

        $v2 = $this->deliver($order);
        $this->assertSame(2, $v2->version);
        $this->assertSame($c->id, $v2->correction_request_id, 'la nouvelle version répond à la demande de correction');
        $this->assertNotSame($v1->id, $v2->id);
        $this->assertSame('submitted', $v1->fresh()->state);
        $this->assertSame($v1->message, $v1->fresh()->message, 'la v1 est conservée telle quelle');

        $page = $this->actingAs($this->client)->get("/commandes/{$order->reference}")->assertOk();
        $page->assertSee('Livraison v2')->assertSee('Remplacée par la v2')->assertSee('Répond à la correction n° 1')->assertSee('1 utilisée sur 2')->assertSee('Examiner la livraison v2');

        $this->validateIt($order, $v2)->assertRedirect()->assertSessionHas('status');
        $order->refresh();
        $this->assertSame(OrderState::Closed, $order->state);
        $this->assertSame($v2->id, $order->validated_delivery_id);
        $this->assertSame('validated', $order->closure_reason->value);
        $this->assertNotNull($order->closed_at);
        $this->assertSame(['validated', 'closed'], DB::table('order_events')->where('order_id', $order->id)->whereIn('type', ['validated', 'closed'])->orderBy('id')->pluck('type')->all());
        $this->assertSame($paymentsBefore, DB::table('payments')->count(), 'aucun mouvement de paiement');
        $this->assertSame($ledgerBefore, DB::table('ledger_batches')->count(), 'aucun reversement ni écriture : clôture commerciale seulement');

        $this->actingAs($this->client)->get("/commandes/{$order->reference}")->assertOk()->assertSee('commande clôturée')->assertSee('ne confirme ni ne déclenche aucun reversement');
        $this->actingAs($this->freelancer)->get("/commandes/{$order->reference}")->assertOk()->assertSee('Clôturée');
    }

    // ---------- autorisations ----------

    public function test_only_the_right_party_can_deliver_review_and_nobody_else_sees_anything(): void
    {
        $order = $this->inProgress();
        $this->draft($order);
        $stranger = User::factory()->create();
        $admin = User::factory()->create();
        app(GrantAdministrator::class)($admin, 'test');
        $draftFile = FileAsset::firstOrFail();

        // le client ne dépose, ne soumet ni ne voit le brouillon
        $this->actingAs($this->client)->get("/commandes/{$order->reference}/livraison")->assertRedirect();
        $this->actingAs($this->client)->post("/commandes/{$order->reference}/livraison/message", ['message' => 'x'])->assertNotFound();
        $this->actingAs($this->client)->post("/commandes/{$order->reference}/livraison/fichiers", ['file' => $this->pdf()])->assertNotFound();
        $this->submit($order)->assertRedirect();      // le freelance, lui, peut
        $v = Delivery::where('state', 'submitted')->firstOrFail();

        // le freelance ne se valide pas lui-même ni ne demande de correction
        $this->actingAs($this->freelancer)->post("/commandes/{$order->reference}/validation", ['delivery_id' => $v->id, 'expected_version' => $order->fresh()->row_version, 'operation_key' => 'k1', 'confirm' => '1'])->assertNotFound();
        $this->actingAs($this->freelancer)->post("/commandes/{$order->reference}/correction", ['delivery_id' => $v->id, 'reason' => str_repeat('a', 30), 'expected_version' => $order->fresh()->row_version, 'operation_key' => 'k2'])->assertNotFound();
        $this->assertSame(OrderState::Delivered, $order->fresh()->state);

        // tiers et administrateur : rien, ni pages, ni actions, ni fichiers
        foreach ([$stranger, $admin] as $who) {
            foreach (['livraison', 'livraison/soumettre', 'correction', 'validation', 'report'] as $path) {
                $this->actingAs($who)->get("/commandes/{$order->reference}/{$path}")->assertNotFound();
            }
            $this->actingAs($who)->post("/commandes/{$order->reference}/validation", ['delivery_id' => $v->id, 'expected_version' => 1, 'operation_key' => 'k3', 'confirm' => '1'])->assertNotFound();
            $url = URL::temporarySignedRoute('orders.files.download', now()->addMinutes(5), ['reference' => $order->reference, 'file' => $draftFile->id, 'u' => $who->id]);
            $this->actingAs($who)->get($url)->assertNotFound();
        }
        $this->assertSame(OrderState::Delivered, $order->fresh()->state);
    }

    public function test_a_draft_stays_private_and_delivered_files_download_only_for_the_parties(): void
    {
        $order = $this->inProgress();
        $this->draft($order);
        $f = FileAsset::firstOrFail();
        $linkFor = fn (User $u) => URL::temporarySignedRoute('orders.files.download', now()->addMinutes(5), ['reference' => $order->reference, 'file' => $f->id, 'u' => $u->id]);

        $this->actingAs($this->client)->get("/commandes/{$order->reference}")->assertOk()->assertDontSee('plans-v')->assertDontSee('Brouillon');
        $this->actingAs($this->client)->get($linkFor($this->client))->assertNotFound();       // brouillon : jamais pour le client
        $this->actingAs($this->freelancer)->get($linkFor($this->freelancer))->assertOk();
        $this->assertSame(0, DB::table('order_events')->where('order_id', $order->id)->whereIn('type', ['brief_file_clean', 'brief_file_added'])->count(), 'un brouillon ne laisse aucune trace dans l’historique partagé');

        $this->submit($order)->assertRedirect();
        $this->actingAs($this->client)->get($linkFor($this->client))->assertOk();
        $this->actingAs($this->client)->get("/commandes/{$order->reference}")->assertSee('plans-v');
        // un fichier de livraison ne peut pas être retiré après soumission : ni par l'application, ni par la base
        $this->actingAs($this->freelancer)->post("/commandes/{$order->reference}/livraison/fichiers/{$f->id}/retirer")->assertStatus(409);
        $this->dbRefuses(fn () => DB::table('file_assets')->where('id', $f->id)->update(['state' => 'removed']), 'conservés');
    }

    // ---------- fichiers obligatoires et contrôle ----------

    public function test_delivery_is_blocked_until_required_files_are_available_and_scanned(): void
    {
        $order = $this->inProgress(['delivery_requires_files' => true]);
        $this->assertSame('files', $order->agreement->fresh()->delivery_mode);

        // message seul : refusé, avec la raison en clair
        $this->draft($order, withFile: false);
        $this->submit($order)->assertRedirect()->assertSessionHas('error');
        $this->assertSame(OrderState::InProgress, $order->fresh()->state);
        $this->actingAs($this->freelancer)->get("/commandes/{$order->reference}/livraison")->assertOk()->assertSee('au moins un fichier ayant passé le contrôle');

        // fichier non contrôlé (scanner indisponible) : la livraison ne devient pas examinable
        FakeScanner::$unavailable = true;
        $this->actingAs($this->freelancer)->post("/commandes/{$order->reference}/livraison/fichiers", ['file' => $this->pdf()])->assertRedirect();
        $f = FileAsset::firstOrFail();
        $this->assertSame('quarantined', $f->state->value);
        $this->actingAs($this->freelancer)->get("/commandes/{$order->reference}/livraison")->assertOk()->assertSee('en cours de contrôle de sécurité');
        $this->submit($order)->assertRedirect()->assertSessionHas('error');
        $this->assertSame(0, Delivery::where('state', 'submitted')->count());

        // contrôle réussi : soumissible
        FakeScanner::$unavailable = false;
        Artisan::call('freeci:files:scan');
        $this->assertSame('clean', $f->fresh()->state->value);
        $this->submit($order)->assertRedirect()->assertSessionHas('status');
        $this->assertSame(OrderState::Delivered, $order->fresh()->state);
    }

    public function test_an_infected_delivery_file_is_refused_and_blocks_submission_until_removed(): void
    {
        $order = $this->inProgress(['delivery_requires_files' => true]);
        $this->draft($order, withFile: false);
        $this->actingAs($this->freelancer)->post("/commandes/{$order->reference}/livraison/fichiers", ['file' => $this->pdf('virus.pdf', 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE')])->assertRedirect();
        $bad = FileAsset::firstOrFail();
        $this->assertSame('rejected', $bad->state->value);
        $this->submit($order)->assertRedirect()->assertSessionHas('error');
        $this->assertSame(0, Delivery::where('state', 'submitted')->count());
        $url = URL::temporarySignedRoute('orders.files.download', now()->addMinutes(5), ['reference' => $order->reference, 'file' => $bad->id, 'u' => $this->freelancer->id]);
        $this->actingAs($this->freelancer)->get($url)->assertNotFound();
    }

    public function test_without_a_scanner_a_file_requiring_delivery_is_blocked_with_a_clear_message(): void
    {
        $order = $this->inProgress(['delivery_requires_files' => true]);
        FakeScanner::$operational = false;
        $this->actingAs($this->freelancer)->post("/commandes/{$order->reference}/livraison/fichiers", ['file' => $this->pdf()])->assertRedirect()->assertSessionHas('error');
        $this->actingAs($this->freelancer)->get("/commandes/{$order->reference}/livraison")->assertOk()->assertSee('désactivé')->assertSee('bloquée tant que ce service n’est pas installé');
    }

    public function test_a_message_is_required_and_an_empty_draft_cannot_be_submitted(): void
    {
        $order = $this->inProgress();
        $this->submit($order)->assertStatus(409);          // aucun brouillon : rien à soumettre
        $this->assertSame(OrderState::InProgress, $order->fresh()->state);
        $this->draft($order, '   ', false);
        $this->submit($order)->assertRedirect()->assertSessionHas('error');
        $this->assertSame(0, Delivery::where('state', 'submitted')->count());
    }

    public function test_legacy_agreements_are_never_rewritten_and_do_not_silently_waive_promised_files(): void
    {
        $order = $this->inProgress();
        $this->assertSame('message', $order->agreement->fresh()->delivery_mode, 'accord récent : choix explicite figé');
        // accord antérieur à la règle : indicateur ambigu, conservé tel quel (la base interdit de le réécrire)
        $this->dbRefuses(fn () => DB::table('order_agreements')->where('order_id', $order->id)->update(['delivery_mode' => 'files']), 'ajout seul');
        DB::statement('ALTER TABLE order_agreements DISABLE TRIGGER order_agreements_append_only');
        DB::table('order_agreements')->where('order_id', $order->id)->update(['delivery_mode' => 'legacy', 'delivery_requires_files' => false]);
        DB::statement('ALTER TABLE order_agreements ENABLE TRIGGER order_agreements_append_only');
        $this->assertSame('unspecified', $order->agreement->fresh()->deliveryMode());

        $this->actingAs($this->client)->get("/commandes/{$order->reference}")->assertOk()->assertSee('Accord antérieur à cette règle')->assertSee('pas contrôlée automatiquement');
        $this->draft($order, withFile: false);
        $this->submit($order)->assertRedirect()->assertSessionHas('status');      // non bloquant : l'obligation n'est pas appliquée rétroactivement

        // un ancien accord qui exigeait déjà des fichiers (indicateur vrai) continue de les exiger
        DB::statement('ALTER TABLE order_agreements DISABLE TRIGGER order_agreements_append_only');
        DB::table('order_agreements')->where('order_id', $order->id)->update(['delivery_requires_files' => true]);
        DB::statement('ALTER TABLE order_agreements ENABLE TRIGGER order_agreements_append_only');
        $this->assertSame('files', $order->agreement->fresh()->deliveryMode());
    }

    public function test_exhausted_corrections_never_force_validation_and_a_disagreement_only_records_a_follow_up(): void
    {
        $order = $this->inProgress(['revisions_included' => 1]);
        $v1 = $this->deliver($order);
        $this->correct($order, $v1)->assertRedirect();
        $v2 = $this->deliver($order);

        $page = $this->actingAs($this->client)->get("/commandes/{$order->reference}")->assertOk();
        $page->assertSee('Vous n’êtes pas obligé de valider')->assertSee('non validée')->assertSee('Signaler un désaccord');
        $payload = fn ($note, $key = null) => ['delivery_id' => $v2->id, 'note' => $note, 'expected_version' => $order->fresh()->row_version, 'operation_key' => $key ?? (string) Str::uuid()];

        $this->actingAs($this->client)->post("/commandes/{$order->reference}/desaccord", $payload('court'))->assertRedirect()->assertSessionHas('error');
        $this->actingAs($this->freelancer)->post("/commandes/{$order->reference}/desaccord", $payload(str_repeat('a', 30)))->assertNotFound();
        $key = (string) Str::uuid();
        $p = $payload('Le calque COTATION manque toujours sur le plan 3.', $key);
        $this->actingAs($this->client)->post("/commandes/{$order->reference}/desaccord", $p)->assertRedirect()->assertSessionHas('status');
        $this->actingAs($this->client)->post("/commandes/{$order->reference}/desaccord", $p)->assertRedirect()->assertSessionHas('status', 'Cette action avait déjà été enregistrée.');
        $this->actingAs($this->client)->post("/commandes/{$order->reference}/desaccord", $payload('Un second signalement identique.'))->assertRedirect()->assertSessionHas('error');

        $order->refresh();
        $this->assertSame(OrderState::Delivered, $order->state, 'rien n’est validé, clôturé ni forcé');
        $this->assertNull($order->validated_delivery_id);
        $this->assertSame(1, DB::table('order_follow_ups')->where('delivery_id', $v2->id)->where('kind', 'client_disagreement')->count());
        $this->assertSame(1, DB::table('order_events')->where('order_id', $order->id)->where('type', 'disagreement_reported')->count());
        $this->assertSame(0, DB::table('ledger_batches')->where('kind', '!=', 'payment_confirmed')->count());
        $this->actingAs($this->freelancer)->get("/commandes/{$order->reference}")->assertOk()->assertSee('Le client a signalé un désaccord')->assertSee('aucun support n’a été contacté automatiquement');
        $this->actingAs($this->client)->get("/commandes/{$order->reference}")->assertOk()->assertSee('Désaccord signalé le')->assertDontSee('Signaler un désaccord</a>', false);
        // il peut toujours valider plus tard : aucun automatisme ne tranche à sa place
        $this->validateIt($order, $v2)->assertRedirect()->assertSessionHas('status');
    }

    public function test_disagreement_is_refused_while_included_corrections_remain(): void
    {
        $order = $this->inProgress();
        $v1 = $this->deliver($order);
        $this->actingAs($this->client)->post("/commandes/{$order->reference}/desaccord", ['delivery_id' => $v1->id, 'note' => str_repeat('b', 30), 'expected_version' => $order->fresh()->row_version, 'operation_key' => (string) Str::uuid()])->assertRedirect()->assertSessionHas('error');
        $this->assertSame(0, DB::table('order_follow_ups')->where('kind', 'client_disagreement')->count());
        $this->actingAs($this->client)->get("/commandes/{$order->reference}/desaccord")->assertRedirect();
    }

    // ---------- corrections ----------

    public function test_corrections_are_limited_by_the_frozen_agreement_and_a_double_submit_consumes_one(): void
    {
        $order = $this->inProgress(['revisions_included' => 1]);
        $this->assertSame(1, $order->agreement->fresh()->revisions_included);
        $this->service->update(['revisions_included' => 9]);       // le service change : l'accord ne bouge pas
        $v1 = $this->deliver($order);

        // double soumission : même clé = rejouée ; autre clé sur la même version = refusée
        $key = (string) Str::uuid();
        $version = $order->fresh()->row_version;
        $payload = ['delivery_id' => $v1->id, 'reason' => 'Corriger la cotation des plans 3 et 7.', 'expected_version' => $version, 'operation_key' => $key];
        $this->actingAs($this->client)->post("/commandes/{$order->reference}/correction", $payload)->assertRedirect();
        $this->actingAs($this->client)->post("/commandes/{$order->reference}/correction", $payload)->assertRedirect()->assertSessionHas('status', 'Cette action avait déjà été enregistrée.');
        $this->actingAs($this->client)->post("/commandes/{$order->reference}/correction", ['operation_key' => (string) Str::uuid()] + $payload)->assertStatus(409);
        $this->assertSame(1, CorrectionRequest::count());

        // la base elle-même refuse une seconde demande pour la même version
        $this->dbRefuses(fn () => CorrectionRequest::create(['order_id' => $order->id, 'delivery_id' => $v1->id, 'number' => 2, 'reason' => 'x', 'requested_by' => $this->client->id]));
        $this->assertSame(1, CorrectionRequest::count());

        // nouvelle version ; la limite de l'accord (1) est atteinte : aucune seconde correction
        $v2 = $this->deliver($order);
        $this->correct($order, $v2)->assertRedirect()->assertSessionHas('error');
        $this->assertSame(OrderState::Delivered, $order->fresh()->state);
        $this->assertSame(1, CorrectionRequest::count());
        $this->actingAs($this->client)->get("/commandes/{$order->reference}")->assertSee('Aucune correction incluse restante')->assertSee('1 utilisée sur 1');
        $this->actingAs($this->client)->get("/commandes/{$order->reference}/correction")->assertOk()->assertSee('toutes utilisées');
    }

    public function test_a_correction_needs_a_real_reason_and_targets_the_latest_version_only(): void
    {
        $order = $this->inProgress();
        $v1 = $this->deliver($order);
        $this->correct($order, $v1, 'trop court')->assertRedirect()->assertSessionHas('error');
        $this->assertSame(0, CorrectionRequest::count());
        $this->correct($order, $v1)->assertRedirect();
        $v2 = $this->deliver($order);
        // version périmée : la demande vise v1 alors que v2 est la dernière
        $this->correct($order, $v1)->assertStatus(409);
        $this->assertSame(1, CorrectionRequest::count());
        $this->assertSame(OrderState::Delivered, $order->fresh()->state);
        $this->assertNotNull($v2);
    }

    // ---------- validation ----------

    public function test_validation_needs_explicit_confirmation_is_single_effect_and_refuses_stale_versions(): void
    {
        $order = $this->inProgress();
        $v1 = $this->deliver($order);
        $this->validateIt($order, $v1, confirm: false)->assertSessionHasErrors('confirm');
        $this->assertSame(OrderState::Delivered, $order->fresh()->state);

        $key = (string) Str::uuid();
        $ver = $order->fresh()->row_version;
        $p = ['delivery_id' => $v1->id, 'expected_version' => $ver, 'operation_key' => $key, 'confirm' => '1'];
        $this->actingAs($this->client)->post("/commandes/{$order->reference}/validation", $p)->assertRedirect()->assertSessionHas('status');
        $this->actingAs($this->client)->post("/commandes/{$order->reference}/validation", $p)->assertRedirect()->assertSessionHas('status', 'Cette action avait déjà été enregistrée.');
        $this->actingAs($this->client)->post("/commandes/{$order->reference}/validation", ['operation_key' => (string) Str::uuid()] + $p)->assertStatus(409);
        $this->assertSame(1, DB::table('order_events')->where('order_id', $order->id)->where('type', 'validated')->count());
        // plus aucune correction, plus de livraison
        $this->correct($order, $v1)->assertStatus(409);
        $this->actingAs($this->freelancer)->get("/commandes/{$order->reference}/livraison")->assertRedirect();
    }

    public function test_the_validated_version_must_be_the_latest(): void
    {
        $order = $this->inProgress();
        $v1 = $this->deliver($order);
        $this->correct($order, $v1)->assertRedirect();
        $this->deliver($order);
        $this->validateIt($order, $v1)->assertStatus(409);
        $this->assertSame(OrderState::Delivered, $order->fresh()->state);
    }

    // ---------- immutabilité ----------

    public function test_submitted_deliveries_and_corrections_are_immutable_in_the_database(): void
    {
        $order = $this->inProgress();
        $v1 = $this->deliver($order);
        $this->correct($order, $v1)->assertRedirect();
        foreach ([
            fn () => DB::table('deliveries')->where('id', $v1->id)->update(['message' => 'modifiée en douce']),
            fn () => DB::table('deliveries')->where('id', $v1->id)->delete(),
            fn () => DB::table('correction_requests')->where('delivery_id', $v1->id)->update(['reason' => 'autre']),
            fn () => DB::table('correction_requests')->where('delivery_id', $v1->id)->delete(),
        ] as $attempt) {
            $this->dbRefuses($attempt);
        }
        $this->assertSame($v1->message, $v1->fresh()->message);
    }

    // ---------- report d'échéance ----------

    public function test_extension_changes_the_due_date_only_when_the_client_accepts_and_keeps_history(): void
    {
        $order = $this->inProgress();
        $due = $order->due_at->copy();
        $day = fn (int $n) => $due->copy()->addDays($n)->format('Y-m-d');
        $req = fn (string $date, string $reason = 'Le client a fourni des plans incomplets, il me faut trois jours.', ?string $who = null) => $this->actingAs($who ? $this->client : $this->freelancer)
            ->post("/commandes/{$order->reference}/report", ['proposed_date' => $date, 'reason' => $reason, 'expected_version' => $order->fresh()->row_version, 'operation_key' => (string) Str::uuid()]);

        // le client ne propose pas ; dates et motifs invalides refusés
        $req($day(3), who: 'client')->assertNotFound();
        $req($day(-1))->assertRedirect()->assertSessionHas('error');
        $req($day(45))->assertRedirect()->assertSessionHas('error');
        $req($day(3), 'court')->assertRedirect()->assertSessionHas('error');
        $this->assertSame(0, DB::table('extension_requests')->count());

        $req($day(3))->assertRedirect()->assertSessionHas('status');
        $this->assertEquals($due->toIso8601String(), $order->fresh()->due_at->toIso8601String(), 'une proposition ne change rien');
        $e = DB::table('extension_requests')->first();
        $this->assertSame('pending', $e->state);
        $req($day(4))->assertRedirect()->assertSessionHas('error');       // une seule proposition en attente

        // le freelance ne répond pas à sa propre proposition
        $ans = fn (string $decision, User $u) => $this->actingAs($u)->post("/commandes/{$order->reference}/report/{$decision}", ['extension_id' => $e->id, 'note' => 'ok', 'expected_version' => $order->fresh()->row_version, 'operation_key' => (string) Str::uuid()]);
        $ans('accepter', $this->freelancer)->assertNotFound();
        $this->assertEquals($due->toIso8601String(), $order->fresh()->due_at->toIso8601String());

        // refus : échéance inchangée, décision conservée
        $ans('refuser', $this->client)->assertRedirect();
        $this->assertSame('declined', DB::table('extension_requests')->where('id', $e->id)->value('state'));
        $this->assertEquals($due->toIso8601String(), $order->fresh()->due_at->toIso8601String());

        // nouvelle proposition acceptée : seule l'acceptation modifie l'échéance
        $req($day(2))->assertRedirect()->assertSessionHas('status');
        $e2 = DB::table('extension_requests')->where('state', 'pending')->first();
        $this->actingAs($this->client)->post("/commandes/{$order->reference}/report/accepter", ['extension_id' => $e2->id, 'note' => null, 'expected_version' => $order->fresh()->row_version, 'operation_key' => (string) Str::uuid()])->assertRedirect();
        $order->refresh();
        $this->assertSame($day(2), $order->due_at->format('Y-m-d'));
        $row = DB::table('extension_requests')->where('id', $e2->id)->first();
        $this->assertSame('accepted', $row->state);
        $this->assertEquals($due->toIso8601String(), Carbon::parse($row->previous_due_at)->toIso8601String(), 'ancienne échéance conservée');
        $this->assertSame($order->started_at->toIso8601String(), $order->fresh()->started_at->toIso8601String());
        $this->actingAs($this->client)->get("/commandes/{$order->reference}")->assertSee('Report accepté (initialement le')->assertSee('Report d’échéance accepté');

        // une décision rendue ne se modifie plus (base)
        $this->dbRefuses(fn () => DB::table('extension_requests')->where('id', $e2->id)->update(['state' => 'declined']), 'ne se modifie plus');
    }

    public function test_no_unilateral_due_date_change_even_directly_in_the_database(): void
    {
        $order = $this->inProgress();
        $this->dbRefuses(fn () => DB::table('orders')->where('id', $order->id)->update(['due_at' => $order->due_at->copy()->addDays(10)]), 'report accepté');
        $v = $this->deliver($order);
        $this->correct($order, $v)->assertRedirect();
        $this->assertEquals($order->due_at->toIso8601String(), $order->fresh()->due_at->toIso8601String(), 'ni la livraison ni la correction ne remettent le délai à zéro');
    }

    public function test_a_pending_extension_is_withdrawn_when_the_delivery_is_submitted_and_can_be_withdrawn_by_its_author(): void
    {
        $order = $this->inProgress();
        $date = $order->due_at->copy()->addDays(3)->format('Y-m-d');
        $go = fn () => $this->actingAs($this->freelancer)->post("/commandes/{$order->reference}/report", ['proposed_date' => $date, 'reason' => 'Besoin de trois jours de plus pour finir.', 'expected_version' => $order->fresh()->row_version, 'operation_key' => (string) Str::uuid()])->assertRedirect();
        $go();
        $id = DB::table('extension_requests')->value('id');
        $this->actingAs($this->freelancer)->post("/commandes/{$order->reference}/report/retirer", ['extension_id' => $id, 'expected_version' => $order->fresh()->row_version, 'operation_key' => (string) Str::uuid()])->assertRedirect();
        $this->assertSame('withdrawn', DB::table('extension_requests')->where('id', $id)->value('state'));

        $go();
        $this->deliver($order);
        $this->assertSame(0, DB::table('extension_requests')->where('state', 'pending')->count());
        $this->assertSame(2, DB::table('extension_requests')->where('state', 'withdrawn')->count());
    }

    // ---------- silence du client ----------

    public function test_client_silence_never_validates_closes_or_contacts_support_it_only_records_a_follow_up_need(): void
    {
        $order = $this->inProgress();
        $v1 = $this->deliver($order);
        $this->travel(8)->days();

        $this->actingAs($this->freelancer)->get("/commandes/{$order->reference}")->assertOk()->assertSee('Le délai d’examen est dépassé')->assertSee('aucun support n’a été contacté automatiquement');
        $this->artisan('freeci:orders:expire')->assertSuccessful();
        $this->actingAs($this->client)->get('/espace')->assertOk()->assertSee('Examiner la livraison v1')->assertSee('Délai d’examen dépassé');
        $order->refresh();
        $this->assertSame(OrderState::Delivered, $order->state, 'la commande reste ouverte');
        $this->assertNull($order->validated_delivery_id);
        $this->assertNull($order->closed_at);
        $this->assertSame(1, DB::table('order_follow_ups')->where('delivery_id', $v1->id)->count(), 'besoin de suivi enregistré une seule fois');
        $this->assertSame(1, DB::table('order_events')->where('order_id', $order->id)->where('type', 'review_overdue')->count());
        $this->assertStringContainsString('aucun support n’a été contacté', (string) DB::table('order_events')->where('type', 'review_overdue')->value('note'));
        $this->assertSame(0, DB::table('ledger_batches')->where('kind', '!=', 'payment_confirmed')->count());

        // le client peut encore valider ou demander une correction après le délai
        $this->validateIt($order, $v1)->assertRedirect()->assertSessionHas('status');
        $this->assertSame(OrderState::Closed, $order->fresh()->state);
    }

    // ---------- tableaux de bord et dossier ----------

    public function test_overviews_follow_the_spaces_layout_with_metrics_revenue_and_profile(): void
    {
        $this->inProgress();
        $free = $this->actingAs($this->freelancer)->get('/freelance')->assertOk()->assertSee('Espace freelance')->assertSee('Nouveau service')->assertSee('Demandes à accepter')->assertSee('Services publiés')
            ->assertSee('Revenus')->assertSee('Votre profil public')->assertSee('Voir mes revenus');
        $this->assertSame(4, substr_count($free->getContent(), 'class="sx-metric '));
        $client = $this->actingAs($this->client)->get('/espace')->assertOk()->assertSee('Espace client')->assertSee('Choisir un service')->assertSee('Besoin d’aide ?')->assertSee('Commandes récentes');
        $this->assertSame(4, substr_count($client->getContent(), 'class="sx-metric '));
    }

    public function test_dashboards_show_the_real_actions_and_due_dates(): void
    {
        $order = $this->inProgress();
        $this->actingAs($this->freelancer)->get('/freelance')->assertOk()->assertSee('Livrer la commande')->assertSee('Préparer la livraison')->assertSee($order->reference);
        $this->actingAs($this->client)->get('/espace')->assertOk()->assertDontSee('Examiner la livraison');

        $v1 = $this->deliver($order);
        $this->actingAs($this->client)->get('/espace')->assertOk()->assertSee('Examiner la livraison v1')->assertSee('À décider avant le');
        $this->actingAs($this->freelancer)->get('/freelance')->assertOk()->assertDontSee('Livrer la commande')->assertSee('En attente du client')->assertSee('en attente d’examen');

        $this->correct($order, $v1)->assertRedirect();
        $this->actingAs($this->freelancer)->get('/freelance')->assertOk()->assertSee('Répondre à la correction demandée')->assertSee('Préparer la nouvelle version');
        $this->actingAs($this->freelancer)->get("/commandes/{$order->reference}")->assertOk()->assertSee('Répondre à la correction n° 1')->assertSee('Le calque COTATION manque');
    }

    public function test_the_dossier_explains_every_state_with_the_right_actions(): void
    {
        $order = $this->inProgress();
        $this->actingAs($this->freelancer)->get("/commandes/{$order->reference}")->assertOk()->assertSee('Déposer la livraison')->assertSee('Proposer un report')->assertDontSee('Valider la livraison');
        $this->actingAs($this->client)->get("/commandes/{$order->reference}")->assertOk()->assertDontSee('Préparer la livraison')->assertDontSee('Proposer un report');
        $this->deliver($order);
        $this->actingAs($this->client)->get("/commandes/{$order->reference}")->assertOk()->assertSee('Demander une correction')->assertSee('Valider la livraison')->assertSee('2 sur 2 restante')->assertSee('Contrôle de sécurité ≠ qualité du travail');
        $this->actingAs($this->freelancer)->get("/commandes/{$order->reference}")->assertOk()->assertDontSee('Valider la livraison')->assertSee('en attente d’examen');
    }
}

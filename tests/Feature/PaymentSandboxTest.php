<?php

namespace Tests\Feature;

use App\Integrations\Payments\SandboxPaymentProvider;
use App\Modules\Accounts\Models\User;
use App\Modules\Finance\Actions\ConfirmPayment;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\SandboxGate;
use App\Modules\Orders\Enums\OrderState;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\FakeScanner;
use Tests\Support\OrderFixtures;
use Tests\TestCase;

class PaymentSandboxTest extends TestCase
{
    use OrderFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpParties();
    }

    // ---------- cloisonnement : démonstration / réel ----------

    public function test_the_simulator_is_off_by_default_and_a_real_order_can_never_be_paid(): void
    {
        $this->assertFalse(config('freeci.payments.sandbox_enabled'), 'désactivé par défaut');
        $order = $this->placeOrder();
        $this->accept($order);

        $this->actingAs($this->client)->get("/commandes/{$order->reference}")->assertDontSee('Aller au paiement simulé')->assertSee('Le paiement n’est pas ouvert pour cette commande');
        $this->actingAs($this->client)->get("/commandes/{$order->reference}/paiement")->assertOk()->assertSee('Le paiement n’est pas ouvert pour cette commande')->assertDontSee('Payer 35');
        $this->startPayment($order)->assertStatus(409);
        $this->assertSame(0, Payment::count());

        // Activé mais commande réelle (aucun élément de démonstration) : toujours refusé.
        config(['freeci.payments.sandbox_enabled' => true]);
        $this->client->forceFill(['sandbox_payments' => true])->save();      // même un drapeau posé à tort
        $this->service->update(['is_demo' => false]);
        $this->service->freelanceProfile->update(['is_demo' => false]);
        $real = $this->placeOrder();
        $this->accept($real);
        $this->startPayment($real)->assertStatus(409);
        $this->assertSame(0, Payment::count());
        $this->assertNull($real->fresh()->payment_deadline_at, 'paiement jamais ouvert : aucune échéance');
    }

    public function test_every_condition_of_the_gate_is_required(): void
    {
        $order = $this->payableOrder();
        $gate = app(SandboxGate::class);
        $this->assertNull($gate->denial($order->fresh()));

        $cases = [
            'sandbox_disabled' => fn () => config(['freeci.payments.sandbox_enabled' => false]),
            'order_not_demo' => fn () => DB::table('orders')->where('id', $order->id)->update(['is_demo' => false]),
            'party_not_demo' => fn () => $this->client->forceFill(['is_demo' => false])->save(),
            'service_not_demo' => fn () => DB::table('services')->where('id', $order->service_id)->update(['is_demo' => false]),
            'account_not_authorized' => fn () => $this->client->forceFill(['sandbox_payments' => false])->save(),
        ];
        foreach ($cases as $expected => $break) {
            $this->enableSandbox();
            DB::table('orders')->where('id', $order->id)->update(['is_demo' => true]);
            DB::table('services')->where('id', $order->service_id)->update(['is_demo' => true]);
            $break();
            $this->assertSame($expected, $gate->denial($order->fresh()), $expected);
        }
        $this->enableSandbox();
        $this->freelancer->forceFill(['is_demo' => false])->save();
        $this->assertSame('party_not_demo', $gate->denial($order->fresh()));
    }

    public function test_only_demo_accounts_can_be_authorized_for_the_simulator(): void
    {
        $real = User::factory()->create(['email' => 'reel@example.test', 'is_demo' => false]);
        $this->assertSame(1, Artisan::call('freeci:sandbox:authorize', ['email' => 'reel@example.test']));
        $this->assertFalse($real->fresh()->sandbox_payments);

        $demo = User::factory()->create(['email' => 'demo@demo.freeci.invalid', 'is_demo' => true]);
        $this->assertSame(0, Artisan::call('freeci:sandbox:authorize', ['email' => 'demo@demo.freeci.invalid']));
        $this->assertTrue($demo->fresh()->sandbox_payments);
        Artisan::call('freeci:sandbox:authorize', ['email' => 'demo@demo.freeci.invalid', '--revoke' => true]);
        $this->assertFalse($demo->fresh()->sandbox_payments);
    }

    public function test_no_button_or_request_parameter_can_confirm_a_payment(): void
    {
        $order = $this->payableOrder();
        $this->startPayment($order)->assertRedirect();
        $payment = $this->currentPayment($order);

        foreach (["?status=succeeded&paid=1&payment=confirmed&ref={$payment->provider_reference}", '?success=true', '?state=confirmed'] as $query) {
            $this->actingAs($this->client)->get("/commandes/{$order->reference}/paiement{$query}")->assertOk()->assertSee('Vérification du paiement en cours')->assertDontSee('Paiement confirmé');
        }
        // un POST de « confirmation » forgé ne fait rien : il n'existe aucune route de confirmation
        $this->actingAs($this->client)->post("/commandes/{$order->reference}/paiement", ['operation_key' => 'x', 'conditions' => '1', 'state' => 'confirmed', 'status' => 'succeeded', 'amount_xof' => 1]);
        $this->actingAs($this->client)->post("/commandes/{$order->reference}/paiement/actualiser", ['state' => 'confirmed', 'status' => 'succeeded']);
        $this->assertSame('pending', $payment->fresh()->state->value);
        $this->assertSame(OrderState::AwaitingPayment, $order->fresh()->state);
        $this->assertNull($order->fresh()->started_at);
        $this->assertSame(1, Payment::count());
    }

    public function test_the_webhook_is_closed_unless_enabled_and_always_requires_a_valid_signature(): void
    {
        $body = json_encode(['id' => 'e1', 'reference' => 'SBX-X', 'status' => 'succeeded']);
        config(['freeci.payments.sandbox_enabled' => false, 'freeci.payments.sandbox_webhook_secret' => 's3cret-de-test-0123456789']);
        $this->call('POST', '/webhooks/sandbox-payments', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)->assertNotFound();

        config(['freeci.payments.sandbox_enabled' => true]);
        $post = fn (array $headers) => $this->call('POST', '/webhooks/sandbox-payments', [], [], [], array_merge(['CONTENT_TYPE' => 'application/json'], $headers), $body);
        $post([])->assertStatus(400);
        $post(['HTTP_X_SANDBOX_SIGNATURE' => 't='.time().',v1='.str_repeat('0', 64)])->assertStatus(400);
        $post(['HTTP_X_SANDBOX_SIGNATURE' => SandboxPaymentProvider::signatureHeader('autre-secret', $body)])->assertStatus(400);
        $post(['HTTP_X_SANDBOX_SIGNATURE' => SandboxPaymentProvider::signatureHeader('s3cret-de-test-0123456789', $body, time() - 3600)])->assertStatus(400);   // rejeu ancien
        $post(['HTTP_X_SANDBOX_SIGNATURE' => SandboxPaymentProvider::signatureHeader('s3cret-de-test-0123456789', $body.' ')])->assertStatus(400);                // corps altéré
        $post(['HTTP_X_SANDBOX_SIGNATURE' => SandboxPaymentProvider::signatureHeader('s3cret-de-test-0123456789', $body)])->assertOk()->assertJson(['outcome' => 'ignored']);

        config(['freeci.payments.sandbox_webhook_secret' => null]);
        $post(['HTTP_X_SANDBOX_SIGNATURE' => SandboxPaymentProvider::signatureHeader('', $body)])->assertNotFound();
    }

    // ---------- parcours et états ----------

    public function test_payment_attempt_is_separate_from_order_and_amount_comes_from_the_agreement(): void
    {
        $order = $this->payableOrder();
        $this->service->update(['price_xof' => 1]);                                   // le service change : l'accord non
        $this->actingAs($this->client)->post("/commandes/{$order->reference}/paiement", ['operation_key' => 'k1', 'conditions' => '1', 'amount_xof' => 1, 'amount' => 1])->assertRedirect(route('orders.payment', $order->reference));

        $p = $this->currentPayment($order);
        $this->assertSame(35000, $p->amount_xof);
        $this->assertSame('pending', $p->state->value);
        $this->assertTrue($p->is_simulated);
        $this->assertSame('sandbox', $p->provider);
        $this->assertSame(OrderState::AwaitingPayment, $order->fresh()->state, 'tentative en attente : la commande ne bouge pas');
        $this->assertSame(0, DB::table('ledger_batches')->count(), 'aucun état financier avant confirmation');
        $this->actingAs($this->client)->get("/commandes/{$order->reference}")->assertSee('Vérification du paiement en cours')->assertSee('Ne payez pas une seconde fois');
    }

    public function test_double_submission_and_a_second_attempt_while_uncertain_are_refused(): void
    {
        $order = $this->payableOrder();
        $this->startPayment($order, 'meme-cle')->assertRedirect();
        $this->startPayment($order, 'meme-cle')->assertRedirect();                    // double clic : rejoué
        $this->assertSame(1, Payment::count());

        $this->startPayment($order)->assertStatus(409)->assertSee('Paiement déjà en cours');   // autre clé, tentative ouverte
        $this->assertSame(1, Payment::count());
        $this->actingAs($this->client)->get("/commandes/{$order->reference}/paiement")->assertDontSee('Payer 35');

        // rempart de base de données : une seule tentative ouverte par commande
        $this->expectException(QueryException::class);
        Payment::create(['order_id' => $order->id, 'amount_xof' => 35000, 'provider' => 'sandbox', 'provider_reference' => 'SBX-DUP', 'state' => 'pending']);
    }

    public function test_confirmed_payment_starts_the_order_once_with_one_ledger_batch(): void
    {
        $order = $this->payableOrder();
        $this->startPayment($order);
        $ref = $this->currentPayment($order)->provider_reference;

        $this->travel(1)->minute();
        $out = $this->resolve($ref, 'succeeded', ['--notify' => true, '--prefix' => 'evt']);
        $this->assertStringContainsString('200', $out);

        $order->refresh();
        $this->assertSame('confirmed', $this->currentPayment($order)->state->value);
        $this->assertSame(OrderState::InProgress, $order->state);
        $this->assertNotNull($order->started_at);
        $this->assertEqualsWithDelta(5 * 86400, $order->started_at->diffInSeconds($order->due_at), 2, 'échéance = départ + délai de l’accord');
        $startedAt = $order->started_at->toIso8601String();

        // un seul lot, équilibré
        $this->assertSame(1, DB::table('ledger_batches')->count());
        $this->assertSame(0, (int) DB::table('ledger_lines')->sum('amount_xof'));
        $this->assertSame(2, DB::table('ledger_lines')->count());

        // rejeu du même événement et nouvelles confirmations : aucun second effet
        $this->resolve($ref, 'succeeded', ['--notify' => true, '--prefix' => 'evt']);
        $this->resolve($ref, 'succeeded', ['--notify' => true, '--prefix' => 'autre']);
        $this->assertSame('already', app(ConfirmPayment::class)($this->currentPayment($order)->id));
        $this->assertSame(1, DB::table('ledger_batches')->count());
        $this->assertSame(1, DB::table('order_events')->where('type', 'work_started')->count());
        $this->assertSame($startedAt, $order->fresh()->started_at->toIso8601String(), 'départ enregistré une seule fois');

        // la base interdit toute modification du départ et de l'échéance
        try {
            DB::transaction(fn () => DB::table('orders')->where('id', $order->id)->update(['started_at' => now()->addDay(), 'due_at' => now()->addDays(9)]));
            $this->fail('départ modifiable');
        } catch (QueryException $e) {
            $this->assertStringContainsString('une seule fois', $e->getMessage());
        }
        // et aucune annulation simple après un paiement confirmé
        $this->actingAs($this->client)->post("/commandes/{$order->reference}/cancel", ['expected_version' => $order->fresh()->row_version, 'operation_key' => 'c1'])->assertStatus(409);
        $this->actingAs($this->client)->get("/commandes/{$order->reference}")->assertSee('Le travail a démarré')->assertSee('Paiement simulé confirmé');
    }

    public function test_duplicate_and_disordered_events_have_a_single_effect(): void
    {
        $order = $this->payableOrder();
        $this->startPayment($order);
        $ref = $this->currentPayment($order)->provider_reference;

        // 3 notifications dont une doublon et un « échec » tardif : un seul effet, aucune régression
        $out = $this->resolve($ref, 'succeeded', ['--events' => 'succeeded,succeeded,failed', '--prefix' => 'seq']);
        $this->assertStringContainsString('Notification 1 (succeeded) : 200 {"outcome":"applied"}', $out);
        $this->assertStringContainsString('Notification 2 (succeeded) : 200 {"outcome":"applied"}', str_replace('ignored', 'applied', $out)); // 2e : ignorée ou dédoublonnée
        $this->resolve($ref, 'succeeded', ['--events' => 'succeeded', '--prefix' => 'seq']);                // même identifiant : doublon
        $this->assertSame(1, DB::table('payment_events')->where('provider_event_id', 'seq-1')->value('duplicate_count'));

        $p = $this->currentPayment($order);
        $this->assertSame('confirmed', $p->state->value, 'un échec tardif ne défait jamais une confirmation');
        $this->assertSame(OrderState::InProgress, $order->fresh()->state);
        $this->assertSame(1, DB::table('ledger_batches')->count());
        $this->assertSame(1, DB::table('order_events')->where('type', 'work_started')->count());
    }

    public function test_a_late_confirmation_after_a_failure_never_reopens_anything(): void
    {
        $order = $this->payableOrder();
        $this->startPayment($order);
        $ref = $this->currentPayment($order)->provider_reference;

        $this->resolve($ref, 'succeeded', ['--events' => 'failed,succeeded', '--prefix' => 'late']);
        $this->assertSame('failed', $this->currentPayment($order)->state->value);
        $this->assertSame(OrderState::AwaitingPayment, $order->fresh()->state);
        $this->assertNull($order->fresh()->started_at);
        $this->assertSame(1, DB::table('reconciliation_cases')->where('reason', 'confirmed_after_failed')->count(), 'cas à examiner par le support');
        $this->assertSame(0, DB::table('ledger_batches')->count());
    }

    public function test_failed_payment_allows_a_retry_and_only_one_payment_is_ever_confirmed(): void
    {
        $order = $this->payableOrder();
        $this->startPayment($order);
        $first = $this->currentPayment($order)->provider_reference;
        $this->resolve($first, 'failed', ['--notify' => true]);
        $this->actingAs($this->client)->get("/commandes/{$order->reference}/paiement")->assertSee('Paiement non abouti')->assertSee('Réessayer le paiement')->assertSee('Aucun montant n’a été confirmé');

        $this->startPayment($order)->assertRedirect();
        $second = $this->currentPayment($order);
        $this->assertNotSame($first, $second->provider_reference);
        $this->resolve($second->provider_reference, 'succeeded', ['--notify' => true]);
        $this->assertSame(OrderState::InProgress, $order->fresh()->state);
        $this->assertSame(1, Payment::where('state', 'confirmed')->count());
        $this->startPayment($order)->assertStatus(409);
    }

    public function test_an_uncertain_payment_blocks_retries_until_the_server_verifies_it(): void
    {
        $order = $this->payableOrder();
        $this->startPayment($order);
        $ref = $this->currentPayment($order)->provider_reference;

        $this->resolve($ref, 'indeterminate', ['--notify' => true]);
        $this->assertSame('unknown', $this->currentPayment($order)->state->value);
        $this->actingAs($this->client)->get("/commandes/{$order->reference}/paiement")->assertSee('Vérification du paiement en cours')->assertDontSee('Réessayer le paiement')->assertDontSee('Payer 35');
        $this->startPayment($order)->assertStatus(409);
        $this->assertSame(1, Payment::count());

        // notification perdue : l'issue n'est connue que du prestataire ; l'actualisation côté serveur la rapproche
        $this->resolve($ref, 'succeeded');                                                         // sans --notify
        $this->assertSame('unknown', $this->currentPayment($order)->state->value);
        $this->actingAs($this->client)->post("/commandes/{$order->reference}/paiement/actualiser")->assertRedirect();
        $this->assertSame('confirmed', $this->currentPayment($order)->state->value);
        $this->assertSame(OrderState::InProgress, $order->fresh()->state);
    }

    public function test_refresh_is_rate_limited_and_changes_nothing_while_the_provider_is_still_pending(): void
    {
        $order = $this->payableOrder();
        $this->startPayment($order);
        $this->actingAs($this->client)->post("/commandes/{$order->reference}/paiement/actualiser")->assertRedirect()->assertSessionHas('status');
        $this->actingAs($this->client)->post("/commandes/{$order->reference}/paiement/actualiser")->assertRedirect()->assertSessionHas('error');   // trop fréquent
        $this->assertSame('pending', $this->currentPayment($order)->state->value);
    }

    public function test_a_notification_is_never_trusted_without_server_verification(): void
    {
        $order = $this->payableOrder();
        $this->startPayment($order);
        $p = $this->currentPayment($order);

        // le prestataire (simulé) dit « en attente » alors qu'une notification affirme « réussi » : rien n'est confirmé
        $this->resolve($p->provider_reference, 'pending', ['--events' => 'succeeded', '--prefix' => 'liar']);
        $this->assertSame('pending', $p->fresh()->state->value);
        $this->assertSame(1, DB::table('reconciliation_cases')->where('reason', 'verification_mismatch')->count());

        // montant discordant côté prestataire
        DB::table('sandbox_transactions')->where('reference', $p->provider_reference)->update(['status' => 'succeeded', 'amount_xof' => 10]);
        $this->resolve($p->provider_reference, 'succeeded', ['--events' => 'succeeded', '--prefix' => 'amount']);
        $this->assertSame('pending', $p->fresh()->state->value);
        $this->assertSame(OrderState::AwaitingPayment, $order->fresh()->state);
    }

    public function test_events_are_rejected_when_the_order_is_no_longer_eligible_and_unknown_references_are_ignored(): void
    {
        $order = $this->payableOrder();
        $this->startPayment($order);
        $ref = $this->currentPayment($order)->provider_reference;
        $this->client->forceFill(['sandbox_payments' => false])->save();               // autorisation retirée entre-temps

        $this->resolve($ref, 'succeeded', ['--notify' => true]);
        $this->assertSame('pending', $this->currentPayment($order)->state->value);
        $this->assertSame('rejected', DB::table('payment_events')->orderByDesc('id')->value('outcome'));

        $this->client->forceFill(['sandbox_payments' => true])->save();
        DB::table('sandbox_transactions')->insert(['reference' => 'SBX-ORPHELIN', 'amount_xof' => 5, 'currency' => 'XOF', 'status' => 'succeeded', 'order_reference' => 'FC-0', 'created_at' => now(), 'updated_at' => now()]);
        $this->assertStringContainsString('"outcome":"ignored"', $this->resolve('SBX-ORPHELIN', 'succeeded', ['--notify' => true]));
    }

    public function test_only_the_client_of_the_order_can_reach_the_payment_pages(): void
    {
        $order = $this->payableOrder();
        $outsider = User::factory()->create();
        foreach ([$this->freelancer, $outsider] as $user) {
            $this->actingAs($user)->get("/commandes/{$order->reference}/paiement")->assertNotFound();
            $this->actingAs($user)->post("/commandes/{$order->reference}/paiement", ['operation_key' => 'z', 'conditions' => '1'])->assertNotFound();
            $this->actingAs($user)->post("/commandes/{$order->reference}/paiement/actualiser")->assertNotFound();
        }
        $this->assertSame(0, Payment::count());
        $this->app['auth']->forgetGuards();
        $this->get("/commandes/{$order->reference}/paiement")->assertRedirect(route('login'));
    }

    public function test_simulator_commands_do_nothing_when_the_simulator_is_disabled(): void
    {
        $order = $this->payableOrder();
        $this->startPayment($order);
        $ref = $this->currentPayment($order)->provider_reference;
        config(['freeci.payments.sandbox_enabled' => false]);

        $this->assertSame(1, Artisan::call('freeci:sandbox:resolve', ['reference' => $ref, 'outcome' => 'succeeded', '--notify' => true]));
        $this->assertSame('pending', $this->currentPayment($order)->state->value);
        $this->assertSame('pending', DB::table('sandbox_transactions')->where('reference', $ref)->value('status'));
    }

    public function test_confirmed_payment_with_an_incomplete_brief_waits_for_the_brief_then_starts_once(): void
    {
        $this->useFakeScanner();
        $order = $this->payableOrder(['brief_requires_files' => true]);
        $this->assertTrue($order->agreement->brief_requires_files, 'exigence figée dans l’accord');
        $this->startPayment($order);
        $ref = $this->currentPayment($order)->provider_reference;
        $this->resolve($ref, 'succeeded', ['--notify' => true]);

        $order->refresh();
        $this->assertSame(OrderState::AwaitingBrief, $order->state, 'paiement confirmé mais brief incomplet : rien ne démarre');
        $this->assertNull($order->started_at);
        $this->assertNull($order->due_at);

        // un fichier non contrôlé ne complète pas le brief
        FakeScanner::$unavailable = true;
        $this->actingAs($this->client)->post("/commandes/{$order->reference}/brief/fichiers", ['file' => UploadedFile::fake()->createWithContent('plan.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n")])->assertRedirect();
        $this->assertSame(OrderState::AwaitingBrief, $order->fresh()->state);

        // le contrôle réussit : le brief devient complet et le travail démarre, une seule fois
        FakeScanner::$unavailable = false;
        Artisan::call('freeci:files:scan');
        $order->refresh();
        $this->assertSame(OrderState::InProgress, $order->state);
        $this->assertNotNull($order->started_at);
        $startedAt = $order->started_at->toIso8601String();
        Artisan::call('freeci:files:scan');
        $this->assertSame($startedAt, $order->fresh()->started_at->toIso8601String());
        $this->assertSame(1, DB::table('order_events')->where('type', 'work_started')->count());
    }

    public function test_an_order_accepted_before_payments_existed_never_expires_for_non_payment_and_stays_closed_to_payment(): void
    {
        $order = $this->placeOrder();
        $this->accept($order)->assertRedirect();
        // Simule une commande acceptée au lot 2 : aucune échéance de paiement, simulateur absent.
        DB::table('orders')->where('id', $order->id)->update(['payment_deadline_at' => null]);
        $this->travel(30)->days();

        $this->actingAs($this->client)->get("/commandes/{$order->reference}")->assertOk();
        $this->assertSame(OrderState::AwaitingPayment, $order->fresh()->state, 'jamais expirée faute de paiement');
        $this->artisan('freeci:orders:expire')->assertSuccessful();
        $this->assertSame(OrderState::AwaitingPayment, $order->fresh()->state);
        $this->startPayment($order)->assertStatus(409);
        $this->assertSame(0, Payment::count());
    }

    public function test_the_service_changing_its_brief_requirement_does_not_alter_an_accepted_agreement(): void
    {
        $order = $this->payableOrder(['brief_requires_files' => true]);
        $this->service->update(['brief_requires_files' => false, 'price_xof' => 99000]);
        $this->assertTrue($order->agreement->fresh()->brief_requires_files);
        $this->assertSame(35000, $order->agreement->fresh()->price_xof);
    }

    public function test_completing_the_brief_never_changes_the_commercial_scope(): void
    {
        $order = $this->payableOrder();
        $before = $order->agreement->fresh()->only(['price_xof', 'delivery_days', 'revisions_included', 'scope', 'deliverables', 'exclusions', 'client_inputs', 'brief_requires_files']);
        $this->startPayment($order);
        $this->resolve($this->currentPayment($order)->provider_reference, 'succeeded', ['--notify' => true]);

        $this->assertSame($before, $order->agreement->fresh()->only(array_keys($before)));
        $this->actingAs($this->client)->get("/commandes/{$order->reference}")->assertSee('ne modifie');
    }
}

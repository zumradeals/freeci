<?php

namespace App\Console\Commands;

use App\Integrations\Payments\GeniusPayConfig;
use App\Integrations\Payments\PaymentMode;
use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Enums\ServiceStatus;
use App\Modules\Catalog\Models\Service;
use App\Modules\Finance\Actions\Beneficiaries;
use App\Modules\Finance\Actions\FinancialOperations;
use App\Modules\Finance\Actions\InitiatePayment;
use App\Modules\Finance\Actions\RefreshPaymentStatus;
use App\Modules\Finance\Enums\PaymentState;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Support\OrderFunds;
use App\Modules\Orders\Actions\AcceptServiceRequest;
use App\Modules\Orders\Actions\DeliveryDraft;
use App\Modules\Orders\Actions\RequestService;
use App\Modules\Orders\Actions\SubmitDelivery;
use App\Modules\Orders\Actions\ValidateDelivery;
use App\Modules\Orders\Models\Order;
use App\Modules\Support\Actions\OpenDispute;
use App\Modules\Support\Actions\StaffCases;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Recette financière AUTOMATISÉE (docs/27) en BAC À SABLE uniquement : elle déroule, avec les actions applicatives, les scénarios A à F sur des commandes
 * de test créées entre les comptes de démonstration, règle les paiements par la page du simulateur Genius Pay (docs/39 : deux requêtes HTTP) et imprime le
 * compte rendu du §10. Elle n'invente aucun résultat : un écart est consigné « ÉCHEC », un comportement incertain « À VOIR ».
 * Refuse de tourner si le mode actif n'est pas le bac à sable. Ne supprime rien. Ne tente JAMAIS un second remboursement par API.
 * Reste MANUEL : écrans (F5 à F8 : double clic, identité récente, accès refusés), actions dans le tableau de bord Genius Pay, question §8.4, signature.
 */
class RecetteFinance extends Command
{
    protected $signature = 'freeci:recette:finance
        {--admin= : e-mail de l\'administrateur qui agit (ni client ni freelance de la recette)}
        {--only= : scénarios à jouer, séparés par des virgules (A,B,C,D,E,F,P ; défaut A à F)}
        {--open-requests : ouvre TEMPORAIREMENT les demandes du service de recette s\'il les refuse, puis rétablit la valeur d\'origine}
        {--yes : ne pas demander de confirmation}';

    protected $description = 'Recette financière du bac à sable Genius Pay (docs/27) : scénarios A à F + sonde P, compte rendu §10. Refuse tout autre mode que le bac à sable.';

    private const CLIENT = 'recette.client@demo.freeci.invalid';

    private const FREELANCE = 'recette.freelance@demo.freeci.invalid';

    private const SERVICE = 'service-de-recette-mise-en-plan';

    private const PAD = 'Motif de recette automatisée en bac à sable (aucun argent réel).';

    /** @var list<array{0: string, 1: string, 2: string, 3: string, 4: string}> [étape, libellé, statut, référence, remarque] */
    private array $rows = [];

    private User $client;

    private User $freelance;

    private User $admin;

    private FinancialOperations $ops;

    public function handle(FinancialOperations $ops): int
    {
        $this->ops = $ops;
        if (! $this->preflight()) {
            return self::FAILURE;
        }
        $only = array_filter(array_map('trim', explode(',', strtoupper((string) ($this->option('only') ?: 'A,B,C,D,E,F')))));
        $this->line('Scénarios : '.implode(', ', $only).' · administrateur : '.$this->admin->email.' · mode : '.PaymentMode::environment().' (aucun argent réel).');
        if (! $this->option('yes') && ! $this->confirm('Créer des commandes de TEST entre les comptes de démonstration et lancer la recette en bac à sable ?')) {
            $this->warn('Annulé.');

            return self::FAILURE;
        }

        $service = Service::query()->where('slug', self::SERVICE)->firstOrFail();
        $restore = null;
        $published = $service->status === ServiceStatus::Published && $service->published_at !== null && $service->published_at <= now();
        if (! $service->accepts_requests || ! $published) {
            if (! $this->option('open-requests')) {
                $this->error('Le service de recette n\'est pas ouvert aux demandes (publié : '.($published ? 'oui' : 'NON, état « '.$service->status->value.' »').' ; demandes : '.($service->accepts_requests ? 'oui' : 'NON').'). Relancez avec --open-requests : il est rouvert pour la recette, puis remis EXACTEMENT dans son état d\'origine.');

                return self::FAILURE;
            }
            $restore = ['id' => $service->getKey(), 'accepts_requests' => $service->accepts_requests, 'status' => $service->status->value, 'published_at' => $service->published_at];
            DB::table('services')->where('id', $service->getKey())->update(['accepts_requests' => true, 'status' => 'published', 'published_at' => $published ? $service->published_at : now()->subDay()]);
            $this->warn('Service de recette rouvert temporairement à '.now()->format('H:i:s').' (état d\'origine : '.$restore['status'].').');
        }
        $startedAt = now();

        try {
            foreach (['A', 'B', 'C', 'D', 'E', 'F', 'P'] as $s) {
                if (in_array($s, $only, true)) {
                    $this->line("— Scénario {$s}…");
                    try {
                        $this->{'scenario'.$s}();
                    } catch (Throwable $e) {
                        $this->row($s.'*', 'Scénario interrompu', 'ÉCHEC', '', $this->msg($e));
                    }
                }
            }
            $this->journal($startedAt);
        } finally {
            if ($restore !== null) {
                DB::table('services')->where('id', $restore['id'])->update(['accepts_requests' => $restore['accepts_requests'], 'status' => $restore['status'], 'published_at' => $restore['published_at']]);
                $this->warn('Service de recette remis dans son état d\'origine à '.now()->format('H:i:s').'.');
            }
        }

        return $this->report();
    }

    // ------------------------------------------------------------------ garde-fous

    private function preflight(): bool
    {
        if (PaymentMode::environment() !== 'sandbox' || PaymentMode::isLive()) {
            $this->error('Refusé : le mode de paiement actif n\'est pas le bac à sable.');

            return false;
        }
        if (! GeniusPayConfig::ready('sandbox')) {
            $this->error('Refusé : configuration Genius Pay « sandbox » incomplète ou non conforme (freeci:genius:status).');

            return false;
        }
        if (! PaymentMode::creationOpen()) {
            $this->error('Refusé : les nouveaux paiements ne sont pas ouverts ('.PaymentMode::adminMessage(PaymentMode::blocker()).').');

            return false;
        }
        $client = User::where('email', self::CLIENT)->first();
        $freelance = User::where('email', self::FREELANCE)->first();
        if ($client === null || $freelance === null || ! Service::query()->where('slug', self::SERVICE)->exists()) {
            $this->error('Comptes ou service de recette absents : lancez d\'abord freeci:demo:recette.');

            return false;
        }
        $email = (string) $this->option('admin');
        $admin = $email === '' ? null : User::where('email', $email)->first();
        if ($admin === null || ! $admin->isAdministrator() || in_array($admin->getKey(), [$client->getKey(), $freelance->getKey()], true) || str_ends_with($admin->email, '@demo.freeci.invalid')) {
            $this->error('--admin doit désigner un administrateur réel, distinct du client et du freelance de la recette.');

            return false;
        }
        [$this->client, $this->freelance, $this->admin] = [$client, $freelance, $admin];

        return true;
    }

    // ------------------------------------------------------------------ scénarios

    /** A — désaccord, la prestation continue : aucun argent ne bouge. */
    private function scenarioA(): void
    {
        $o = $this->delivered('A');
        if ($o === null) {
            return;
        }
        $case = $this->guard('A2', 'Litige ouvert par le client', fn () => $this->openCase($o, 'dispute'), $o->reference);
        if ($case === null) {
            return;
        }
        $this->check('A2', 'La commande passe « en litige »', $o->reference, fn () => $this->state($o) === 'disputed' || $this->state($o) === 'in_dispute' ? null : 'état : '.$this->state($o));
        $this->guard('A3', 'Dossier affecté et ouvert par l\'administrateur', fn () => $this->takeCase($case), $case);
        $dec = $this->guard('A4', 'Décision « poursuite », aucune suite financière', fn () => $this->decide($case, 'continue', 'none'), $case);
        $this->check('A5', 'La commande revient à l\'état d\'avant (livrée)', $o->reference, fn () => $this->state($o) === 'delivered' ? null : 'état : '.$this->state($o));
        $this->check('A6', 'Aucune opération financière créée', $o->reference, fn () => DB::table('financial_operations')->where('order_id', $o->getKey())->exists() ? 'une opération existe' : null);
        unset($dec);
    }

    /** B — annulation après paiement, remboursement total par API Genius Pay. */
    private function scenarioB(): void
    {
        $o = $this->paid('B');
        if ($o === null) {
            return;
        }
        $case = $this->guard('B2', 'Demande d\'annulation ouverte par le client', fn () => $this->openCase($o, 'cancellation'), $o->reference);
        if ($case === null) {
            return;
        }
        $this->guard('B3a', 'Dossier affecté et ouvert', fn () => $this->takeCase($case), $case);
        $dec = $this->guard('B3', 'Décision « annulation » + remboursement à traiter', fn () => $this->decide($case, 'cancel', 'refund'), $case);
        $this->check('B3b', 'Commande annulée ; rien n\'est remboursé à ce stade', $o->reference, fn () => $this->state($o) === 'cancelled' && ! DB::table('financial_operations')->where('order_id', $o->getKey())->exists() ? null : 'état : '.$this->state($o));
        if ($dec === null) {
            return;
        }
        $op = $this->guard('B4', 'Remboursement préparé (fonds réservés)', fn () => $this->ops->requestRefund($this->admin, (string) $dec, null, self::PAD, (string) Str::uuid()), $o->reference);
        if ($op === null) {
            return;
        }
        $this->check('B4b', 'Plafond remboursable à 0 après réservation', $o->reference, fn () => OrderFunds::escrow($o->getKey(), true) === 0 ? null : 'escrow : '.OrderFunds::escrow($o->getKey(), true));
        $this->guard('B6', 'Récapitulatif confirmé (sans second approbateur)', fn () => $this->ops->approve($this->admin, $op, true), $op);
        // B7 : UN SEUL appel d'exécution par API ; jamais de renvoi automatique.
        try {
            $res = $this->ops->executeRefundViaApi($this->admin, $op);
            $note = 'retour : '.$res;
        } catch (Throwable $e) {
            $note = 'exception : '.$this->msg($e);
        }
        $row = DB::table('financial_operations')->where('id', $op)->first(['state', 'provider_refund_reference', 'execution_mode']);
        $state = (string) ($row->state ?? '?');
        $this->row('B7/B8', 'Exécution du remboursement total par API (une seule fois)', match ($state) {
            'confirmed' => ($row->provider_refund_reference ?? null) ? 'RÉUSSI' : 'À VOIR',
            'to_verify' => 'À VOIR',
            default => 'ÉCHEC',
        }, (string) DB::table('financial_operations')->where('id', $op)->value('reference'), "état « {$state} », référence prestataire ".(($row->provider_refund_reference ?? null) ? 'présente' : 'absente').'. '.$note.($state === 'to_verify' ? ' → rapprochement manuel à faire dans l\'administration (B9).' : ''));
        $p = Payment::query()->where('order_id', $o->getKey())->latest('id')->first();
        $this->row('B10', 'Libellés côté client', 'MANUEL', $o->reference, 'À regarder à l\'écran : remboursement « confirmé » seulement s\'il l\'est vraiment, commande marquée test (paiement '.($p?->provider_reference ?? '?').').');
        $this->check('B11', 'Montant dans les totaux « Test », jamais « Réel »', $o->reference, fn () => (bool) DB::table('financial_operations')->where('id', $op)->value('is_simulated') ? null : 'opération non marquée simulée');
    }

    /** C — remboursement partiel enregistré à la main. */
    private function scenarioC(): void
    {
        $o = $this->paid('C');
        if ($o === null) {
            return;
        }
        $case = $this->guard('C1', 'Litige ouvert par le client', fn () => $this->openCase($o, 'dispute'), $o->reference);
        if ($case === null) {
            return;
        }
        $this->guard('C2a', 'Dossier affecté et ouvert', fn () => $this->takeCase($case), $case);
        $dec = $this->guard('C2', 'Décision « annulation » + répartition à traiter', fn () => $this->decide($case, 'cancel', 'partial', 'Remboursement de 10 000 FCFA au client'), $case);
        if ($dec === null) {
            return;
        }
        $price = (int) ($o->agreement->price_xof ?? 0);
        $this->refused('C4', 'Montant supérieur aux fonds disponibles refusé', $o->reference, fn () => $this->ops->requestRefund($this->admin, (string) $dec, $price + 1, self::PAD, (string) Str::uuid()));
        $op = $this->guard('C3', 'Remboursement partiel de 10 000 préparé', fn () => $this->ops->requestRefund($this->admin, (string) $dec, 10000, self::PAD, (string) Str::uuid()), $o->reference);
        if ($op === null) {
            return;
        }
        $this->guard('C5', 'Opération confirmée (approuvée)', fn () => $this->ops->approve($this->admin, $op, true), $op);
        $this->refused('C6', 'Exécution par API refusée pour un remboursement partiel', $o->reference, fn () => $this->ops->executeRefundViaApi($this->admin, $op));
        $this->row('C7', 'Remboursement partiel dans le tableau de bord Genius Pay', 'MANUEL', $o->reference, 'Point §8.3 : à tenter dans le tableau de bord sandbox. Ici, l\'exécution est simulée par un constat manuel enregistré (C8).');
        $ref = 'RECETTE-'.strtoupper(Str::random(8));
        $this->guard('C8', 'Exécution manuelle enregistrée (retaper 10 000)', fn () => $this->ops->recordManual($this->admin, $op, ['external_reference' => $ref, 'proof_note' => 'Constat de recette automatisée (bac à sable).', 'amount_confirm' => 10000, 'confirm' => '1']), $op);
        $this->check('C8b', 'Opération « confirmée », mode manuel', $op, fn () => DB::table('financial_operations')->where('id', $op)->value('state') === 'confirmed' ? null : 'état : '.DB::table('financial_operations')->where('id', $op)->value('state'));
    }

    /** D — reversement au freelance, parcours nominal. */
    private function scenarioD(): void
    {
        $o = $this->delivered('D');
        if ($o === null) {
            return;
        }
        $d = $this->latestDelivery($o);
        $this->guard('D2', 'Livraison validée par le client', fn () => app(ValidateDelivery::class)($this->client, $o->reference, $d, $o->fresh()->row_version, (string) Str::uuid()), $o->reference);
        $this->check('D2b', 'Commande clôturée', $o->reference, fn () => $this->state($o) === 'closed' ? null : 'état : '.$this->state($o));
        $this->beneficiary();
        $op = $this->guard('D6', 'Reversement préparé (fonds réservés)', fn () => $this->ops->requestPayout($this->admin, $o->reference, self::PAD, (string) Str::uuid()), $o->reference);
        if ($op === null) {
            return;
        }
        $row = DB::table('financial_operations')->where('id', $op)->first();
        $price = (int) DB::table('payments')->where('order_id', $o->getKey())->where('state', PaymentState::Confirmed->value)->value('amount_xof');
        $this->check('D6b', 'Montant = base moins commission (taux figé)', $op, fn () => $row->amount_xof > 0 && $row->amount_xof < $price ? null : 'montant : '.$row->amount_xof.' pour '.$price);
        $this->guard('D8', 'Récapitulatif confirmé', fn () => $this->ops->approve($this->admin, $op, true), $op);
        $this->refused('D9', 'Exécution par API refusée pour un reversement', $op, fn () => $this->ops->executeRefundViaApi($this->admin, $op));
        $this->guard('D10', 'Transfert enregistré comme effectué (référence unique)', fn () => $this->ops->recordManual($this->admin, $op, ['external_reference' => 'RECETTE-'.strtoupper(Str::random(8)), 'proof_note' => 'Constat de recette automatisée (bac à sable).', 'amount_confirm' => (int) $row->amount_xof, 'confirm' => '1']), $op);
        $this->check('D10b', 'Opération « confirmée »', $op, fn () => DB::table('financial_operations')->where('id', $op)->value('state') === 'confirmed' ? null : 'état : '.DB::table('financial_operations')->where('id', $op)->value('state'));
        $this->refused('D12', 'Second reversement sur la même commande refusé', $o->reference, fn () => $this->ops->requestPayout($this->admin, $o->reference, self::PAD, (string) Str::uuid()));
        $this->row('D3/D11', 'Écran « Revenus » du freelance', 'MANUEL', $o->reference, 'À regarder à l\'écran : disponible/versé en montants de test, numéro jamais réaffiché (D4).');
    }

    /** E — litige qui valide la livraison, puis reversement. */
    private function scenarioE(): void
    {
        $o = $this->delivered('E');
        if ($o === null) {
            return;
        }
        $this->beneficiary();
        $this->refused('E3', 'Le silence du client n\'ouvre pas de reversement (commande livrée non validée)', $o->reference, fn () => $this->ops->requestPayout($this->admin, $o->reference, self::PAD, (string) Str::uuid()));
        $case = $this->guard('E1', 'Litige ouvert par le client', fn () => $this->openCase($o, 'dispute'), $o->reference);
        if ($case === null) {
            return;
        }
        $this->refused('F3', 'Reversement refusé pendant un litige ouvert', $o->reference, fn () => $this->ops->requestPayout($this->admin, $o->reference, self::PAD, (string) Str::uuid()));
        $this->guard('E2a', 'Dossier affecté et ouvert', fn () => $this->takeCase($case), $case);
        $this->guard('E2', 'Décision « livraison conforme » + reversement à autoriser', fn () => $this->decide($case, 'validate_delivery', 'release'), $case);
        $this->check('E2b', 'Commande clôturée, validée', $o->reference, fn () => $this->state($o) === 'closed' ? null : 'état : '.$this->state($o));
        $op = $this->guard('E3b', 'Reversement possible après la décision', fn () => $this->ops->requestPayout($this->admin, $o->reference, self::PAD, (string) Str::uuid()), $o->reference);
        if ($op !== null) {
            $this->guard('E3c', 'Récapitulatif confirmé', fn () => $this->ops->approve($this->admin, $op, true), $op);
            $this->row('E3d', 'Reversement laissé « approuvé » (non exécuté)', 'MANUEL', (string) DB::table('financial_operations')->where('id', $op)->value('reference'), 'Volontaire : à enregistrer vous-même dans l\'administration si vous voulez voir ce parcours.');
        }
    }

    /** F — garde-fous : refus, annulation, doublon, journal. */
    private function scenarioF(): void
    {
        $o = $this->paid('F');
        if ($o === null) {
            return;
        }
        $case = $this->guard('F0', 'Annulation ouverte', fn () => $this->openCase($o, 'cancellation'), $o->reference);
        if ($case === null) {
            return;
        }
        $this->takeCase($case);
        $dec = $this->guard('F0b', 'Décision annulation + remboursement', fn () => $this->decide($case, 'cancel', 'refund'), $case);
        if ($dec === null) {
            return;
        }
        $full = (int) DB::table('payments')->where('order_id', $o->getKey())->where('state', PaymentState::Confirmed->value)->value('amount_xof');
        $op = $this->guard('F2a', 'Opération préparée', fn () => $this->ops->requestRefund($this->admin, (string) $dec, null, self::PAD, (string) Str::uuid()), $o->reference);
        if ($op === null) {
            return;
        }
        $this->refused('F4', 'Second remboursement refusé tant qu\'un est ouvert', $o->reference, fn () => $this->ops->requestRefund($this->admin, (string) $dec, null, self::PAD, (string) Str::uuid()));
        $this->guard('F2', 'Annulation de l\'opération', fn () => $this->ops->cancel($this->admin, $op, self::PAD), $op);
        $this->check('F2b', 'Fonds libérés après annulation', $op, fn () => OrderFunds::escrow($o->getKey(), true) === $full ? null : 'escrow : '.OrderFunds::escrow($o->getKey(), true));
        $op2 = $this->guard('F1a', 'Nouvelle opération préparée', fn () => $this->ops->requestRefund($this->admin, (string) $dec, null, self::PAD, (string) Str::uuid()), $o->reference);
        if ($op2 !== null) {
            $this->guard('F1', 'Refus de l\'opération avec motif', fn () => $this->ops->reject($this->admin, $op2, self::PAD), $op2);
            $this->check('F1b', 'Fonds libérés après refus', $op2, fn () => OrderFunds::escrow($o->getKey(), true) === $full ? null : 'escrow : '.OrderFunds::escrow($o->getKey(), true));
        }
        $this->row('F5-F8', 'Double clic, identité récente, accès refusés aux non-autorisés', 'MANUEL', '', 'Écrans : à vérifier à la main (docs/27 §6).');
    }

    /** P — sonde : comportements inconnus (échec, attente, délai dépassé). Aucune assertion : observation consignée. */
    private function scenarioP(): void
    {
        foreach (['failure', 'pending', 'timeout'] as $scenario) {
            $o = $this->payable('P-'.$scenario);
            if ($o === null) {
                continue;
            }
            try {
                [$payment] = app(InitiatePayment::class)($this->client, $o->reference, (string) Str::uuid());
                $http = $this->settleHttp((string) $payment->fresh()->checkout_url, $scenario);
                sleep(8);
                $after = app(RefreshPaymentStatus::class)->forPayment($payment->fresh());
                $this->row('P-'.$scenario, 'Scénario « '.$scenario.' » du simulateur', 'À VOIR', $o->reference, "HTTP {$http} ; état FreeCI du paiement : ".($after->state->value ?? '?').' ; commande : '.$this->state($o).'. Observation seulement (comportement non documenté).');
            } catch (Throwable $e) {
                $this->row('P-'.$scenario, 'Scénario « '.$scenario.' » du simulateur', 'À VOIR', $o->reference, $this->msg($e));
            }
        }
    }

    // ------------------------------------------------------------------ éléments de parcours

    private function payable(string $step): ?Order
    {
        $service = Service::query()->where('slug', self::SERVICE)->firstOrFail();
        $order = $this->guard($step.'.1', 'Demande de prestation', fn () => app(RequestService::class)($this->client, self::SERVICE, $service->row_version,
            array_fill(0, max(1, count((array) $service->client_inputs)), 'Réponse de recette'), 'Commande de recette automatisée (bac à sable).', true, (string) Str::uuid())[0], '');
        if ($order === null) {
            return null;
        }
        $accepted = $this->guard($step.'.2', 'Demande acceptée par le freelance', fn () => app(AcceptServiceRequest::class)($this->freelance, $order->reference, $order->fresh()->row_version, (string) Str::uuid())[0], $order->reference);

        return $accepted === null ? null : Order::findOrFail($order->getKey());
    }

    /** Commande payée (paiement confirmé côté serveur), en cours. */
    private function paid(string $step): ?Order
    {
        $o = $this->payable($step);
        if ($o === null) {
            return null;
        }
        $payment = $this->guard($step.'.3', 'Paiement démarré (lien Genius Pay sandbox)', fn () => app(InitiatePayment::class)($this->client, $o->reference, (string) Str::uuid())[0], $o->reference);
        if ($payment === null) {
            return null;
        }
        $http = $this->guard($step.'.4', 'Paiement réglé sur la page du simulateur', fn () => $this->settleHttp((string) $payment->fresh()->checkout_url, 'success'), $o->reference);
        if ($http === null) {
            return null;
        }
        $deadline = time() + 90;
        $confirmed = false;
        while (time() < $deadline) {
            $p = app(RefreshPaymentStatus::class)->forPayment(Payment::findOrFail($payment->getKey()));
            if ($p->state === PaymentState::Confirmed) {
                $confirmed = true;
                break;
            }
            sleep(4);
        }
        $this->row($step.'.5', 'Paiement confirmé côté serveur (délai maximal 90 s)', $confirmed ? 'RÉUSSI' : 'ÉCHEC', $o->reference, $confirmed ? '' : 'état : '.Payment::find($payment->getKey())?->state->value);
        if (! $confirmed) {
            return null;
        }
        $order = Order::findOrFail($o->getKey());
        if ($this->state($order) !== 'in_progress') {
            $this->row($step.'.6', 'Commande en cours après paiement', 'ÉCHEC', $o->reference, 'état : '.$this->state($order));

            return null;
        }

        return $order;
    }

    /** Commande payée puis livrée par le freelance. */
    private function delivered(string $step): ?Order
    {
        $o = $this->paid($step);
        if ($o === null) {
            return null;
        }
        $ok = $this->guard($step.'.7', 'Livraison déposée par le freelance', function () use ($o) {
            app(DeliveryDraft::class)->saveMessage($this->freelance, $o->reference, 'Livraison de recette automatisée.');
            $id = (string) DB::table('deliveries')->where('order_id', $o->getKey())->where('state', 'draft')->value('id');
            app(SubmitDelivery::class)($this->freelance, $o->reference, $id, $o->fresh()->row_version, (string) Str::uuid());

            return true;
        }, $o->reference);

        return $ok === null ? null : Order::findOrFail($o->getKey());
    }

    private function beneficiary(): void
    {
        if (DB::table('payout_beneficiaries')->where('user_id', $this->freelance->getKey())->where('status', 'verified')->exists()) {
            return;
        }
        $this->guard('D4', 'Coordonnées de versement déclarées par le freelance', fn () => app(Beneficiaries::class)->declare($this->freelance, 'mobile_money', 'Awa Recette', '+2250700000000'), '');
        $id = DB::table('payout_beneficiaries')->where('user_id', $this->freelance->getKey())->where('status', 'pending')->value('id');
        if ($id !== null) {
            $this->guard('D5', 'Destination vérifiée par l\'administrateur', fn () => app(Beneficiaries::class)->verify($this->admin, (string) $id, 'Destination de démonstration vérifiée pour la recette.'), '');
        }
    }

    private function openCase(Order $o, string $kind): string
    {
        return app(OpenDispute::class)($this->client, $o->reference, $kind, 'Motif de recette automatisée : vérification du parcours en bac à sable.', (string) Str::uuid())[0];
    }

    private function takeCase(string $ref): void
    {
        $staff = app(StaffCases::class);
        $staff->claim($this->admin, $ref);
        $staff->openAccess($this->admin, $ref, 'Examen du dossier dans le cadre de la recette.');
    }

    /** @return int identifiant de la décision */
    private function decide(string $ref, string $outcome, string $need, string $note = ''): int
    {
        $c = DB::table('support_cases')->where('reference', $ref)->first(['id', 'row_version']);
        app(StaffCases::class)->decide($this->admin, $ref, ['outcome' => $outcome, 'financial_need' => $need, 'reason' => 'Décision de recette automatisée, motivée (bac à sable).', 'financial_note' => $note], (int) $c->row_version, (string) Str::uuid());

        return (int) DB::table('support_decisions')->where('case_id', $c->id)->value('id');
    }

    private function latestDelivery(Order $o): string
    {
        return (string) DB::table('deliveries')->where('order_id', $o->getKey())->where('state', 'submitted')->orderByDesc('version')->value('id');
    }

    /**
     * Règle un paiement du SIMULATEUR (docs/39, hors documentation du prestataire) : GET de la page pour le cookie de session et le jeton CSRF, puis POST.
     * L'adresse n'est suivie que si elle vient de l'application (champ enregistré à la création du paiement).
     */
    private function settleHttp(string $checkoutUrl, string $scenario): int
    {
        if (! str_starts_with($checkoutUrl, 'https://')) {
            throw new \RuntimeException('Lien de paiement absent ou non sécurisé.');
        }
        $jar = new CookieJar;
        $page = Http::withOptions(['cookies' => $jar])->timeout(20)->get($checkoutUrl);
        if (! preg_match('/<meta[^>]+name="csrf-token"[^>]+content="([^"]+)"/i', $page->body(), $m)) {
            throw new \RuntimeException('Page du simulateur : jeton introuvable (le mécanisme a peut-être changé). HTTP '.$page->status());
        }
        $r = Http::withOptions(['cookies' => $jar])->timeout(30)->acceptJson()->withHeaders(['X-CSRF-TOKEN' => $m[1]])
            ->post(rtrim($checkoutUrl, '/').'/sandbox/process', ['scenario' => $scenario, 'gateway' => 'orange_money']);
        if (! $r->successful() && $scenario === 'success') {
            throw new \RuntimeException('Le simulateur a répondu HTTP '.$r->status().'.');
        }

        return $r->status();
    }

    // ------------------------------------------------------------------ consignation

    private function state(Order $o): string
    {
        return (string) DB::table('orders')->where('id', $o->getKey())->value('state');
    }

    private function msg(Throwable $e): string
    {
        return Str::limit(class_basename($e).' : '.$e->getMessage(), 220);
    }

    /** Exécute une étape ; consigne RÉUSSI/ÉCHEC ; renvoie le résultat (non nul) ou null en cas d'échec. */
    private function guard(string $step, string $label, callable $fn, string $ref): mixed
    {
        try {
            $r = $fn();
            $this->row($step, $label, 'RÉUSSI', $ref, '');

            return $r ?? true;
        } catch (Throwable $e) {
            $this->row($step, $label, 'ÉCHEC', $ref, $this->msg($e));

            return null;
        }
    }

    /** Vérifie une condition : $fn renvoie null si conforme, sinon la description de l'écart. */
    private function check(string $step, string $label, string $ref, callable $fn): void
    {
        try {
            $gap = $fn();
            $this->row($step, $label, $gap === null ? 'RÉUSSI' : 'ÉCHEC', $ref, (string) $gap);
        } catch (Throwable $e) {
            $this->row($step, $label, 'ÉCHEC', $ref, $this->msg($e));
        }
    }

    /** Une action qui DOIT être refusée (exception métier) : réussi si refus, échec si elle passe. */
    private function refused(string $step, string $label, string $ref, callable $fn): void
    {
        try {
            $fn();
            $this->row($step, $label, 'ÉCHEC', $ref, 'l\'action a été acceptée alors qu\'elle devait être refusée');
        } catch (Throwable $e) {
            $this->row($step, $label, 'RÉUSSI', $ref, 'refus : '.Str::limit($e->getMessage(), 140));
        }
    }

    private function row(string $step, string $label, string $status, string $ref, string $note): void
    {
        $this->rows[] = [$step, $label, $status, $ref, $note];
        $this->line(sprintf('  %-7s %-8s %s', $step, $status, $label));
    }

    private function journal(\DateTimeInterface $since): void
    {
        $n = DB::table('admin_actions')->where('actor_id', $this->admin->getKey())->where('created_at', '>=', $since)->count();
        $this->check('F9', 'Actions de l\'administrateur présentes au journal d\'audit', '', fn () => $n > 0 ? null : 'aucune ligne');
    }

    private function report(): int
    {
        $this->newLine();
        $this->info('Compte rendu (docs/27 §10) — bac à sable, aucun argent réel');
        $this->table(['Étape', 'Libellé', 'Statut', 'Référence', 'Remarque'], $this->rows);
        $c = array_count_values(array_column($this->rows, 2));
        $this->line(sprintf('RÉUSSI %d · ÉCHEC %d · À VOIR %d · MANUEL %d', $c['RÉUSSI'] ?? 0, $c['ÉCHEC'] ?? 0, $c['À VOIR'] ?? 0, $c['MANUEL'] ?? 0));
        $this->line('Reste à votre charge : écrans (B10, D3/D11, F5–F8), tableau de bord Genius Pay (C7), question §8.4 au support, rapprochement manuel éventuel (B9), signature du §10.');
        $this->line('Rien n\'a été supprimé : les commandes de test restent visibles (marquées test). Aucun mode live n\'a été activé.');

        return ($c['ÉCHEC'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }
}

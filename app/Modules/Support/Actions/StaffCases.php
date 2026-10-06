<?php

namespace App\Modules\Support\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Admin\Actions\AdminAudit;
use App\Modules\Catalog\Exceptions\ModerationDenied;
use App\Modules\Support\Exceptions\SupportConflict;
use App\Modules\Support\Exceptions\SupportForbidden;
use App\Modules\Support\Support\CaseRules;
use App\Shared\CommandReceipts;
use Illuminate\Support\Facades\DB;

/**
 * Traitement par le personnel habilité. Règles transversales :
 * – habilitation en vigueur + adresse vérifiée + double authentification, revérifiées à CHAQUE action ;
 * – aucun traitement d'un dossier dont on est partie prenante (demandeur, autre partie, partie de la commande, auteur du contenu signalé) ;
 * – le contenu privé d'un dossier n'est accessible qu'à la personne AFFECTÉE, après « Ouvrir le dossier » avec motif ; l'accès prend fin avec l'affectation ;
 * – toute action est journalisée (auteur, dossier, motif, résultat), les refus aussi ;
 * – une décision est UNE, motivée ; elle sépare strictement décision, effet sur la commande et suite financière (jamais exécutée ici).
 */
final class StaffCases
{
    public function __construct(private AdminAudit $audit, private CaseStore $store, private DisputeEffects $effects) {}

    public function claim(User $staff, string $reference): void
    {
        $this->run($staff, 'case.claim', $reference, null, function ($c) use ($staff) {
            $this->assertNoConflict($staff, $c);
            if ($c->assignee_id !== null) {
                throw new SupportConflict('Ce dossier est déjà affecté.');
            }
            $this->assertLive($c);
            $this->assign($c, $staff->getKey(), $staff->getKey());
        });
    }

    /** Affecter à un autre membre du personnel : réservé aux administrateurs. */
    public function assignTo(User $admin, string $reference, string $assigneeId): void
    {
        $this->run($admin, 'case.assign', $reference, null, function ($c) use ($admin, $assigneeId) {
            if (! $admin->isAdministrator()) {
                throw new ModerationDenied('Seul un administrateur peut affecter un dossier à quelqu’un d’autre.');
            }
            $to = User::query()->whereKey($assigneeId)->first();
            if ($to === null || ! $to->isStaff() || ! $to->emailVerified() || ! $to->hasTwoFactor() || $to->isSuspended()) {
                throw new SupportConflict('Cette personne n’est pas habilitée à traiter des dossiers (habilitation, adresse vérifiée et double authentification requises).');
            }
            $this->assertNoConflict($to, $c);
            $this->assertLive($c);
            $this->assign($c, $to->getKey(), $admin->getKey());
        });
    }

    /** Fin d'affectation : l'accès au contenu privé prend fin immédiatement. */
    public function release(User $staff, string $reference): void
    {
        $this->run($staff, 'case.release', $reference, null, function ($c) use ($staff) {
            if ($c->assignee_id === null || ($c->assignee_id !== $staff->getKey() && ! $staff->isAdministrator())) {
                throw new SupportConflict('Seule la personne affectée (ou un administrateur) peut libérer ce dossier.');
            }
            $this->update($c, ['assignee_id' => null, 'access_reason' => null, 'access_opened_at' => null, 'status' => in_array($c->status, ['in_review'], true) ? 'open' : $c->status]);
            $this->store->event($c->id, 'released', $staff->getKey(), 'internal', 'Affectation terminée : accès au contenu privé retiré');
        });
    }

    /** « Ouvrir le dossier » : motif obligatoire, journalisé. Donne accès, pour la durée de l'affectation, au contenu nécessaire au traitement. */
    public function openAccess(User $staff, string $reference, string $reason): void
    {
        $reason = trim($reason);
        $this->run($staff, 'case.open', $reference, $reason, function ($c) use ($staff, $reason) {
            $this->assertAssignee($staff, $c);
            $this->assertLive($c);
            if (mb_strlen($reason) < 10 || mb_strlen($reason) > 500) {
                throw new SupportConflict('Indiquez le motif de l’ouverture du dossier (10 à 500 caractères) : il est journalisé.');
            }
            $this->update($c, ['access_reason' => $reason, 'access_opened_at' => now()]);
            $this->store->event($c->id, 'access_opened', $staff->getKey(), 'internal', 'Dossier ouvert pour examen', ['reason' => mb_substr($reason, 0, 200)]);
        });
    }

    public function setStatus(User $staff, string $reference, string $status, int $expectedVersion): void
    {
        $this->run($staff, 'case.status', $reference, $status, function ($c) use ($staff, $status, $expectedVersion) {
            $this->assertAssignee($staff, $c);
            $this->assertLive($c);
            if (! in_array($status, CaseRules::LIVE, true)) {
                throw new SupportConflict('État inconnu.');
            }
            if ($c->row_version !== $expectedVersion) {
                throw new SupportConflict('Ce dossier a changé pendant que vous le consultiez : rechargez la page.');
            }
            if ($status !== $c->status) {
                $this->update($c, ['status' => $status]);
                $this->store->event($c->id, 'status', $staff->getKey(), 'requester', 'État : '.CaseRules::STAFF_STATUS[$status], ['from' => $c->status, 'to' => $status]);
            }
        });
    }

    public function setPriority(User $staff, string $reference, string $priority): void
    {
        $this->run($staff, 'case.priority', $reference, $priority, function ($c) use ($staff, $priority) {
            $this->assertAssignee($staff, $c);
            if (! in_array($priority, ['normal', 'high'], true)) {
                throw new SupportConflict('Priorité inconnue.');
            }
            $this->update($c, ['priority' => $priority]);
            $this->store->event($c->id, 'priority', $staff->getKey(), 'internal', 'Priorité : '.$priority);
        });
    }

    /** Clôture d'un dossier d'assistance, de signalement ou de suivi (un litige / une réclamation se termine par une décision). */
    public function close(User $staff, string $reference, string $note): void
    {
        $note = trim($note);
        $this->run($staff, 'case.close', $reference, $note, function ($c) use ($staff, $note) {
            $this->assertAssignee($staff, $c);
            $this->assertLive($c);
            if (in_array($c->kind, CaseRules::DISPUTE_KINDS, true)) {
                throw new SupportConflict('Un litige, une annulation ou une réclamation se termine par une décision motivée.');
            }
            if (mb_strlen($note) < 10 || mb_strlen($note) > 1000) {
                throw new SupportConflict('Indiquez la conclusion (10 à 1000 caractères).');
            }
            $this->store->message($c->id, $staff->getKey(), $c->requester_id === null ? 'internal' : 'requester', $note);
            $this->update($c, ['status' => 'closed', 'closed_at' => now(), 'assignee_id' => null, 'access_reason' => null, 'access_opened_at' => null]);
            $this->store->event($c->id, 'closed', $staff->getKey(), 'requester', 'Dossier clos');
            if ($c->requester_id !== null) {
                $this->store->notify($c->requester_id, 'support_update', 'case_closed:'.$c->id, 'Votre dossier d’assistance est clos', $c->reference);
            }
        });
    }

    /**
     * Décision sur un litige, une demande d'annulation ou une réclamation. UNE seule décision (index unique + verrou + version attendue + clé d'opération).
     *
     * @param  array{outcome: string, reason: string, financial_need: string, financial_note?: ?string}  $d
     */
    public function decide(User $staff, string $reference, array $d, int $expectedVersion, string $operationKey): void
    {
        $this->run($staff, 'case.decide', $reference, $d['reason'] ?? null, function ($c) use ($staff, $d, $expectedVersion, $operationKey) {
            [$id] = CommandReceipts::once($staff->getKey(), 'support.decide', $operationKey, ['c' => $c->id, 'd' => $d, 'v' => $expectedVersion], function () use ($c, $staff, $d, $expectedVersion) {
                $case = DB::table('support_cases')->where('id', $c->id)->lockForUpdate()->first();
                $this->assertAssignee($staff, $case);
                if (! in_array($case->kind, CaseRules::DISPUTE_KINDS, true)) {
                    throw new SupportConflict('Seuls les litiges, annulations et réclamations donnent lieu à une décision.');
                }
                if (! in_array($case->status, CaseRules::LIVE, true) || DB::table('support_decisions')->where('case_id', $case->id)->exists()) {
                    throw new SupportConflict('Ce dossier a déjà fait l’objet d’une décision.');
                }
                if ($case->row_version !== $expectedVersion) {
                    throw new SupportConflict('Ce dossier a changé pendant que vous le consultiez : rechargez la page.');
                }
                if ($case->access_opened_at === null) {
                    throw new SupportConflict('Ouvrez le dossier (avec motif) avant de décider.');
                }
                $outcome = (string) ($d['outcome'] ?? '');
                $need = (string) ($d['financial_need'] ?? '');
                $reason = trim((string) ($d['reason'] ?? ''));
                $note = trim((string) ($d['financial_note'] ?? ''));
                $this->validateDecision($case, $outcome, $need, $reason, $note);

                // 1) EFFET SUR LA COMMANDE (appliqué dans la même transaction, tracé dans l'historique de la commande)
                [$from, $to] = $case->kind === 'claim' ? [null, null] : $this->effects->apply($case, $outcome, $staff->getKey(), $reason);
                // 2) DÉCISION (trace unique) — 3) SUITE FINANCIÈRE : un besoin, « à traiter », jamais exécuté ici
                DB::table('support_decisions')->insert([
                    'case_id' => $case->id, 'outcome' => $outcome, 'reason' => $reason, 'order_state_from' => $from, 'order_state_to' => $to,
                    'financial_need' => $need, 'financial_status' => $need === 'none' ? 'none' : 'to_process', 'financial_note' => $note === '' ? null : $note, 'decided_by' => $staff->getKey(), 'created_at' => now(),
                ]);
                // Blocage interne : levé seulement si la décision ne laisse aucune suite financière bloquante (aucune ou « reversement à autoriser »).
                if (in_array($need, ['none', 'release'], true)) {
                    DB::table('payout_holds')->where('case_id', $case->id)->whereNull('released_at')->update(['released_at' => now(), 'released_by' => $staff->getKey(), 'release_reason' => 'Décision rendue sans suite financière bloquante']);
                }
                // La décision met fin au traitement : affectation et accès prennent fin.
                $this->update($case, ['status' => 'decided', 'decided_at' => now(), 'assignee_id' => null, 'access_reason' => null, 'access_opened_at' => null]);
                $this->store->message($case->id, $staff->getKey(), $case->kind === 'claim' ? 'requester' : 'parties', 'Décision : '.CaseRules::OUTCOMES[$outcome].".\n".$reason);
                $this->store->event($case->id, 'decided', $staff->getKey(), $case->kind === 'claim' ? 'requester' : 'parties', 'Décision rendue : '.CaseRules::OUTCOMES[$outcome], ['outcome' => $outcome, 'financial' => $need]);
                foreach (array_filter([$case->requester_id, $case->counterparty_id]) as $u) {
                    $this->store->notify($u, 'dispute_update', 'case_decided:'.$case->id.':'.$u, 'Décision rendue sur votre dossier', $case->reference);
                }

                return $case->id;
            });

            return $id;
        });
    }

    /** Ouverture d'un dossier à partir d'un besoin de suivi déjà enregistré : action EXPLICITE, une seule fois ; l'origine est conservée, rien n'est transformé en litige. */
    public function fromFollowUp(User $staff, int $followUpId): string
    {
        $f = DB::table('order_follow_ups')->join('orders', 'orders.id', '=', 'order_follow_ups.order_id')->where('order_follow_ups.id', $followUpId)->first(['order_follow_ups.id', 'order_follow_ups.kind', 'orders.id as order_id', 'orders.reference']);
        $ref = null;
        $this->audit->runStaff($staff, 'case.from_follow_up', 'order', $f?->order_id, $f?->reference, null, function () use ($staff, $f, &$ref) {
            if ($f === null) {
                throw new SupportConflict('Besoin de suivi introuvable.');
            }
            $existing = DB::table('support_cases')->where('origin', 'follow_up')->where('origin_ref', (string) $f->id)->value('reference');
            if ($existing !== null) {
                throw new SupportConflict("Un dossier existe déjà pour ce besoin de suivi : {$existing}.");
            }
            $id = $this->store->create([
                'kind' => 'follow_up', 'order_id' => $f->order_id, 'target_type' => 'order', 'target_id' => $f->order_id, 'target_label' => $f->reference, 'category' => $f->kind,
                'subject' => mb_substr('Besoin de suivi — commande '.$f->reference, 0, 160), 'origin' => 'follow_up', 'origin_ref' => (string) $f->id,
            ]);
            $this->store->event($id, 'opened', $staff->getKey(), 'internal', 'Dossier ouvert depuis un besoin de suivi enregistré', ['follow_up' => $f->id, 'kind' => $f->kind]);
            $ref = DB::table('support_cases')->where('id', $id)->value('reference');
        });

        return (string) $ref;
    }

    // ---------------------------------------------------------------------------------------------

    private function validateDecision(object $case, string $outcome, string $need, string $reason, string $note): void
    {
        $allowedOutcomes = $case->kind === 'claim' ? ['answered'] : ['continue', 'validate_delivery', 'cancel'];
        if (! in_array($outcome, $allowedOutcomes, true)) {
            throw new SupportConflict('Décision non disponible pour ce type de dossier.');
        }
        if (mb_strlen($reason) < 20 || mb_strlen($reason) > 2000) {
            throw new SupportConflict('Le motif de la décision est obligatoire (20 à 2000 caractères) : il est communiqué aux parties.');
        }
        $needs = match ($outcome) {
            'cancel' => ['refund', 'partial', 'none'], 'answered' => ['none', 'refund', 'partial'], default => ['none', 'release'],
        };
        if (! in_array($need, $needs, true)) {
            throw new SupportConflict('Choisissez explicitement la suite financière à traiter pour cette décision.');
        }
        if ($need === 'partial' && mb_strlen($note) < 10) {
            throw new SupportConflict('Précisez la répartition à traiter (10 caractères au moins) : aucune règle de répartition n’est calculée par FreeCI, la décision reste humaine.');
        }
        if (mb_strlen($note) > 500) {
            throw new SupportConflict('La note financière fait au plus 500 caractères.');
        }
    }

    private function run(User $staff, string $action, string $reference, ?string $reason, callable $do): mixed
    {
        $c = DB::table('support_cases')->where('reference', $reference)->first();

        return $this->audit->runStaff($staff, $action, 'case', $reference, $c?->subject, $reason, function () use ($c, $do) {
            if ($c === null) {
                throw new SupportForbidden;
            }

            return DB::transaction(function () use ($c, $do) {
                $locked = DB::table('support_cases')->where('id', $c->id)->lockForUpdate()->first();

                return $do($locked);
            });
        });
    }

    private function assertNoConflict(User $staff, object $case): void
    {
        if (CaseRules::involves($staff->getKey(), $case)) {
            throw new ModerationDenied('Vous êtes partie prenante de ce dossier (demandeur, autre partie, partie de la commande ou auteur du contenu) : vous ne pouvez pas le traiter.');
        }
    }

    private function assertAssignee(User $staff, object $case): void
    {
        $this->assertNoConflict($staff, $case);
        if ($case->assignee_id !== $staff->getKey()) {
            throw new ModerationDenied('Ce dossier ne vous est pas affecté.');
        }
    }

    private function assertLive(object $case): void
    {
        if (! in_array($case->status, CaseRules::LIVE, true)) {
            throw new SupportConflict('Ce dossier n’est plus en cours de traitement.');
        }
    }

    private function assign(object $case, string $to, string $by): void
    {
        $this->update($case, ['assignee_id' => $to, 'access_reason' => null, 'access_opened_at' => null, 'status' => $case->status === 'open' ? 'in_review' : $case->status]);
        $this->store->event($case->id, 'assigned', $by, 'requester', 'Dossier pris en charge', ['assignee' => $to]);
    }

    /** @param array<string, mixed> $set */
    private function update(object $case, array $set): void
    {
        DB::table('support_cases')->where('id', $case->id)->update($set + ['row_version' => $case->row_version + 1, 'updated_at' => now()]);
    }
}

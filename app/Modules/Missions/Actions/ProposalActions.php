<?php

namespace App\Modules\Missions\Actions;

use App\Modules\Accounts\Actions\AccountStanding;
use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Support\PrivateContact;
use App\Modules\Missions\Exceptions\MissionConflict;
use App\Modules\Missions\Exceptions\MissionForbidden;
use App\Modules\Missions\Models\Mission;
use App\Modules\Missions\Models\Proposal;
use App\Modules\Missions\Models\ProposalVersion;
use App\Modules\Missions\Support\MilestoneRules;
use App\Modules\Missions\Support\MissionHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Propositions d'un freelance : soumission, révision (nouvelle version, l'ancienne est conservée), retrait avant sélection.
 * Visible uniquement de son auteur et du client de la mission. Impossible de candidater à sa propre mission (action + déclencheur SQL).
 */
final class ProposalActions
{
    /** @param array<string, mixed> $input */
    public function submit(User $freelancer, string $missionId, array $input, int $expectedNumber): ProposalVersion
    {
        AccountStanding::assertCanStartNew($freelancer);
        $v = $this->normalize($input);
        if (! empty($input['use_milestones']) && $v['milestones'] === []) {
            throw ValidationException::withMessages(['milestones' => 'Renseignez les jalons (2 à '.config('freeci.missions.milestones.count')[1].') ou décochez le paiement par jalons.']);
        }
        if ($v['milestones'] !== []) {          // avec des jalons, le délai total est la somme des délais des jalons (jamais saisi deux fois)
            $v['delivery_days'] = array_sum(array_map(fn ($m) => (int) $m['days'], $v['milestones'])) ?: null;
        }
        $this->validate($v);

        return DB::transaction(function () use ($freelancer, $missionId, $v, $expectedNumber) {
            if (! $freelancer->hasRole('freelance') || $freelancer->freelanceProfile?->published_at === null) {
                throw ValidationException::withMessages(['profile' => 'Activez votre espace freelance et publiez votre profil pour candidater.']);
            }
            $m = Mission::query()->whereKey($missionId)->lockForUpdate()->first();
            $live = $m?->publishedVersion;
            if ($m === null || $live === null) {
                throw new MissionForbidden;
            }
            if ($m->status !== 'open') {
                throw new MissionConflict('Cette mission n’accepte plus de propositions.');
            }
            if ($m->client_id === $freelancer->getKey()) {
                throw new MissionConflict('Vous ne pouvez pas candidater à votre propre mission.');
            }
            if ($live->application_deadline->lte(now())) {
                throw new MissionConflict('La date limite de candidature est dépassée : les propositions sont closes.');
            }

            $p = Proposal::query()->where('mission_id', $m->getKey())->where('freelancer_id', $freelancer->getKey())->orderByDesc('created_at')->lockForUpdate()->first();
            if ($p !== null && $p->state === 'selected') {
                throw new MissionConflict('Votre proposition est retenue : elle ne peut plus être modifiée.');
            }
            $current = $p === null ? 0 : (int) $p->versions()->max('number');
            if ($current !== $expectedNumber) {
                throw new MissionConflict('Votre proposition a changé depuis l’ouverture de ce formulaire (autre onglet ?). Rechargez la page ; rien n’a été écrasé.');
            }
            if ($p === null || $p->state === 'closed') {
                $p = Proposal::create(['mission_id' => $m->getKey(), 'freelancer_id' => $freelancer->getKey(), 'state' => 'active']);
            } elseif ($p->state !== 'active') {
                $p->forceFill(['state' => 'active', 'row_version' => $p->row_version + 1])->save();       // retirée ou libérée : une nouvelle version la réactive
            }
            $pv = ProposalVersion::create([
                'proposal_id' => $p->getKey(), 'number' => $current + 1, 'mission_version_id' => $live->getKey(), 'price_xof' => $v['price_xof'], 'delivery_days' => $v['delivery_days'],
                'revisions_included' => $v['revisions_included'], 'scope' => $v['scope'], 'deliverables' => $v['deliverables'], 'delivery_mode' => $v['delivery_mode'],
                'valid_until' => now()->addDays($v['validity_days']), 'message' => $v['message'], 'milestones' => $v['milestones'] === [] ? null : array_map(fn ($m) => ['title' => $m['title'], 'scope' => $m['scope'], 'price_xof' => $m['price_xof'], 'days' => $m['days']], $v['milestones']),
            ]);
            MissionHistory::log($m->getKey(), $current === 0 ? 'proposal_submitted' : 'proposal_revised', $freelancer, 'freelance', null, ['number' => $pv->number], $live->getKey(), $p->getKey());

            return $pv;
        });
    }

    public function withdraw(User $freelancer, string $proposalId): void
    {
        DB::transaction(function () use ($freelancer, $proposalId) {
            $p = Proposal::query()->whereKey($proposalId)->where('freelancer_id', $freelancer->getKey())->lockForUpdate()->first() ?? throw new MissionForbidden;
            Mission::query()->whereKey($p->mission_id)->lockForUpdate()->first();                      // ordre fixe : mission puis proposition
            $p->refresh();
            if ($p->state !== 'active') {
                throw new MissionConflict($p->state === 'selected' ? 'Votre proposition est retenue : elle ne peut plus être retirée ici.' : 'Cette proposition n’est plus active.');
            }
            $p->forceFill(['state' => 'withdrawn', 'row_version' => $p->row_version + 1])->save();
            MissionHistory::log($p->mission_id, 'proposal_withdrawn', $freelancer, 'freelance', null, null, null, $p->getKey());
        });
    }

    /** @return array<string, mixed> */
    public function normalize(array $in): array
    {
        $lines = fn ($v) => array_values(array_filter(array_map(fn ($l) => trim((string) preg_replace('/\s+/u', ' ', $l)), is_array($v) ? $v : (preg_split('/\R/u', (string) $v) ?: [])), fn ($l) => $l !== ''));
        $int = fn ($v) => trim((string) $v) === '' ? null : (int) preg_replace('/[\s\x{202F}\x{00A0}]/u', '', (string) $v);

        return [
            'price_xof' => $int($in['price_xof'] ?? null), 'delivery_days' => $int($in['delivery_days'] ?? null), 'revisions_included' => $int($in['revisions_included'] ?? null) ?? 0,
            'validity_days' => $int($in['validity_days'] ?? null), 'scope' => trim((string) ($in['scope'] ?? '')), 'deliverables' => $lines($in['deliverables'] ?? ''),
            'delivery_mode' => ($in['delivery_mode'] ?? 'files') === 'message' ? 'message' : 'files', 'message' => trim((string) ($in['message'] ?? '')) ?: null,
            'milestones' => ! empty($in['use_milestones']) ? MilestoneRules::normalize($in['milestones'] ?? []) : [],
        ];
    }

    private function validate(array $v): void
    {
        $c = config('freeci.missions.proposal');
        $e = [];
        [$pMin, $pMax] = $c['price_xof'];
        if ($v['price_xof'] === null || $v['price_xof'] < $pMin || $v['price_xof'] > $pMax) {
            $e['price_xof'] = 'Le prix ferme doit être compris entre '.number_format($pMin, 0, ',', ' ').' et '.number_format($pMax, 0, ',', ' ').' FCFA (valeurs provisoires).';
        }
        [$dMin, $dMax] = $c['delivery_days'];
        if ($v['delivery_days'] === null || $v['delivery_days'] < $dMin || $v['delivery_days'] > $dMax) {
            $e['delivery_days'] = "Le délai doit être compris entre {$dMin} et {$dMax} jours.";
        }
        [$rMin, $rMax] = $c['revisions'];
        if ($v['revisions_included'] < $rMin || $v['revisions_included'] > $rMax) {
            $e['revisions_included'] = "Les corrections incluses vont de {$rMin} à {$rMax}.";
        }
        [$vMin, $vMax] = $c['validity_days'];
        if ($v['validity_days'] === null || $v['validity_days'] < $vMin || $v['validity_days'] > $vMax) {
            $e['validity_days'] = "La durée de validité va de {$vMin} à {$vMax} jours.";
        }
        [$sMin, $sMax] = $c['scope'];
        if (mb_strlen($v['scope']) < $sMin || mb_strlen($v['scope']) > $sMax) {
            $e['scope'] = "Décrivez le périmètre proposé ({$sMin} à {$sMax} caractères).";
        }
        if ($v['deliverables'] === [] || count($v['deliverables']) > $c['deliverables_max'] || collect($v['deliverables'])->contains(fn ($l) => mb_strlen($l) > 200)) {
            $e['deliverables'] = 'Indiquez de 1 à '.$c['deliverables_max'].' livrables (une ligne chacun, 200 caractères au plus).';
        }
        if ($v['message'] !== null && mb_strlen($v['message']) > 1000) {
            $e['message'] = 'Le message fait au plus 1 000 caractères.';
        }
        foreach (['scope' => $v['scope'], 'deliverables' => implode("\n", $v['deliverables']), 'message' => (string) $v['message']] as $f => $text) {
            if (! isset($e[$f]) && PrivateContact::found($text)) {
                $e[$f] = 'Retirez les coordonnées privées (adresse e-mail, numéro de téléphone) : les échanges passent par FreeCI.';
            }
        }
        if ($v['milestones'] !== []) {
            $e += MilestoneRules::errors($v['milestones'], $v['price_xof']);
        }
        if ($e !== []) {
            throw ValidationException::withMessages($e);
        }
    }
}

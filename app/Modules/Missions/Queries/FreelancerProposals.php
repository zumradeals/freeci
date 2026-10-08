<?php

namespace App\Modules\Missions\Queries;

use App\Modules\Accounts\Models\User;
use App\Modules\Missions\Exceptions\MissionForbidden;
use App\Modules\Missions\Models\Proposal;
use App\Modules\Missions\Models\ProposalVersion;
use App\Shared\Dates;
use App\Shared\Money;

/** « Mes propositions » : bornées à leur auteur. Aucune donnée d'un concurrent. */
final class FreelancerProposals
{
    /** @return list<array<string, mixed>> */
    public function list(User $freelancer): array
    {
        return Proposal::query()->where('freelancer_id', $freelancer->getKey())->with('mission.publishedVersion.category')->orderByDesc('updated_at')->get()->map(function (Proposal $p) {
            $v = ProposalVersion::query()->where('proposal_id', $p->getKey())->orderByDesc('number')->first();
            $live = $p->mission->publishedVersion;
            $stale = $p->state === 'active' && $live !== null && $v->mission_version_id !== $live->getKey();
            [$label, $tone] = match (true) {
                $p->state === 'selected' => ['Retenue', 'success'],
                $p->state === 'withdrawn' => ['Retirée', 'neutral'],
                $p->state === 'released' => ['Libérée (commande non payée)', 'warning'],
                $p->state === 'closed' => ['Mission terminée', 'neutral'],
                $stale => ['Besoin modifié : à reconfirmer', 'warning'],
                $v->valid_until->lte(now()) => ['Validité dépassée', 'warning'],
                default => ['En attente du choix du client', 'info'],
            };

            return ['id' => $p->getKey(), 'missionTitle' => $live?->title ?? 'Mission', 'missionSlug' => $p->mission->slug, 'category' => $live?->category?->name, 'state' => $p->state, 'label' => $label, 'tone' => $tone, 'number' => $v->number,
                'price' => Money::xof($v->price_xof), 'days' => $v->delivery_days, 'validUntil' => Dates::format($v->valid_until), 'stale' => $stale,
                'missionOpen' => $p->mission->status === 'open' && $live !== null && $live->application_deadline->gt(now())];
        })->all();
    }

    /** Valeurs de la dernière version de SA proposition, pour préremplir la révision. @return array<string, mixed>|null */
    public function current(User $freelancer, string $missionId): ?array
    {
        $p = Proposal::query()->where('mission_id', $missionId)->where('freelancer_id', $freelancer->getKey())->orderByDesc('created_at')->first();
        if ($p === null) {
            return null;
        }
        $v = ProposalVersion::query()->where('proposal_id', $p->getKey())->orderByDesc('number')->first();

        return ['proposalId' => $p->getKey(), 'state' => $p->state, 'number' => $v->number, 'price_xof' => $v->price_xof, 'delivery_days' => $v->delivery_days, 'revisions_included' => $v->revisions_included,
            'scope' => $v->scope, 'deliverables' => implode("\n", $v->deliverables), 'delivery_mode' => $v->delivery_mode, 'message' => $v->message];
    }

    public function ownedProposal(User $freelancer, string $proposalId): array
    {
        $p = Proposal::query()->whereKey($proposalId)->where('freelancer_id', $freelancer->getKey())->with('mission.publishedVersion')->first() ?? throw new MissionForbidden;

        return ['id' => $p->getKey(), 'state' => $p->state, 'title' => $p->mission->publishedVersion?->title ?? 'Mission'];
    }
}

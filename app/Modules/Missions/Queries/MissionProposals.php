<?php

namespace App\Modules\Missions\Queries;

use App\Modules\Accounts\Models\User;
use App\Modules\Missions\Actions\MissionAuthoring;
use App\Modules\Missions\Actions\MissionLifecycle;
use App\Modules\Missions\Exceptions\MissionForbidden;
use App\Modules\Missions\Models\Mission;
use App\Modules\Missions\Models\MissionVersion;
use App\Modules\Missions\Models\Proposal;
use App\Modules\Missions\Models\ProposalVersion;
use App\Shared\Dates;
use App\Shared\Money;

/**
 * Propositions d'une mission, vues PAR SON CLIENT uniquement : version courante de chaque proposition active ou retenue, avec ce qui bloque
 * sa sélection (besoin modifié, validité dépassée…). Un freelance ne voit jamais les propositions de ses concurrents (voir FreelancerProposals).
 */
final class MissionProposals
{
    public function __construct(private MissionAuthoring $authoring, private MissionLifecycle $lifecycle) {}

    /** @return array<string, mixed> */
    public function __invoke(User $client, string $missionId, array $compareIds = []): array
    {
        $this->lifecycle->expireOverdue($client->getKey());
        $m = $this->authoring->owned($client, $missionId);
        $live = MissionVersion::query()->where('mission_id', $m->getKey())->where('state', 'published')->first();
        $selectionOpen = $m->status === 'open' && $live !== null && MissionLifecycle::selectionEnd($live)->gt(now());

        $items = Proposal::query()->where('mission_id', $m->getKey())->whereIn('state', ['active', 'selected'])->with('freelancer.freelanceProfile')->get()->map(function (Proposal $p) use ($live, $selectionOpen) {
            $v = ProposalVersion::query()->where('proposal_id', $p->getKey())->orderByDesc('number')->first();
            $stale = $live !== null && $v->mission_version_id !== $live->getKey();
            $expired = $v->valid_until->lte(now());
            $block = match (true) {
                $p->state === 'selected' => 'Proposition retenue',
                ! $selectionOpen => 'La sélection n’est pas possible dans l’état actuel de la mission.',
                $stale => 'Besoin modifié depuis cette proposition : son auteur doit la reconfirmer.',
                $expired => 'Validité dépassée.',
                default => null,
            };

            return [
                'proposalId' => $p->getKey(), 'versionId' => $v->getKey(), 'number' => $v->number, 'author' => $p->freelancer->freelanceProfile?->display_name ?? $p->freelancer->name,
                'headline' => $p->freelancer->freelanceProfile?->headline, 'profileSlug' => $p->freelancer->freelanceProfile?->published_at ? $p->freelancer->freelanceProfile->slug : null,
                'price' => Money::xof($v->price_xof), 'priceXof' => $v->price_xof, 'days' => $v->delivery_days, 'revisions' => $v->revisions_included, 'deliverables' => $v->deliverables,
                'scope' => $v->scope, 'mode' => $v->delivery_mode === 'files' ? 'Au moins un fichier contrôlé' : 'Par message seul', 'message' => $v->message,
                'validUntil' => Dates::format($v->valid_until), 'submittedAt' => Dates::format($v->submitted_at), 'selected' => $p->state === 'selected',
                'selectable' => $block === null, 'block' => $block, 'stale' => $stale, 'expired' => $expired,
                'history' => $p->versions()->get()->map(fn ($x) => ['number' => $x->number, 'price' => Money::xof($x->price_xof), 'days' => $x->delivery_days, 'when' => Dates::format($x->submitted_at)])->all(),
            ];
        })->sortBy('priceXof')->values()->all();

        $compare = array_values(array_filter($items, fn ($i) => in_array($i['proposalId'], $compareIds, true)));

        return ['mission' => $m, 'live' => $live, 'items' => $items, 'compare' => array_slice($compare, 0, 3), 'selectionOpen' => $selectionOpen, 'selectionEnd' => $live ? Dates::format(MissionLifecycle::selectionEnd($live)) : null];
    }

    /** Formulaire de sélection : une version précise, ses conditions, les éléments de brief à renseigner. @return array<string, mixed> */
    public function forSelection(User $client, string $missionId, string $versionId): array
    {
        $data = ($this)($client, $missionId);
        $item = collect($data['items'])->firstWhere('versionId', $versionId) ?? throw new MissionForbidden;

        return $data + ['item' => $item];
    }
}

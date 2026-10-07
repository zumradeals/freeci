<?php

namespace App\Modules\Missions\Queries;

use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Missions\Actions\MissionAuthoring;
use App\Modules\Missions\Actions\MissionLifecycle;
use App\Modules\Missions\Exceptions\MissionForbidden;
use App\Modules\Missions\Models\Mission;
use App\Modules\Missions\Models\MissionEvent;
use App\Modules\Missions\Models\MissionVersion;
use App\Modules\Missions\Models\Proposal;
use App\Modules\Missions\Support\MissionRules;
use App\Shared\Dates;
use App\Shared\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** « Mes missions » et détail : toujours bornés au client propriétaire (mission d'autrui = MissionForbidden → 404). */
final class ClientMissions
{
    public function __construct(private MissionAuthoring $authoring, private MissionLifecycle $lifecycle) {}

    /** @return list<array<string, mixed>> */
    public function list(User $client): array
    {
        $this->lifecycle->expireOverdue($client->getKey());
        $missions = Mission::query()->where('client_id', $client->getKey())->with(['publishedVersion'])->orderByDesc('updated_at')->get();
        $versions = MissionVersion::query()->whereIn('mission_id', $missions->pluck('id'))->whereIn('state', MissionVersion::OPEN)->get()->keyBy('mission_id');
        $counts = Proposal::query()->whereIn('mission_id', $missions->pluck('id'))->where('state', 'active')->selectRaw('mission_id, count(*) as n')->groupBy('mission_id')->pluck('n', 'mission_id');

        return $missions->map(function (Mission $m) use ($versions, $counts) {
            $w = $versions->get($m->getKey());
            $live = $m->publishedVersion;
            $shown = $w ?? $live;
            [$label, $tone, $icon, $note] = MissionPresenter::status($m, $w, $live);

            return [
                'id' => $m->getKey(), 'title' => $shown?->title ?: 'Sans titre', 'status' => $label, 'tone' => $tone, 'icon' => $icon, 'note' => $note,
                'budget' => $shown?->budget_xof ? Money::xof($shown->budget_xof) : null, 'deadline' => $live?->application_deadline ? Dates::format($live->application_deadline) : null,
                'proposals' => (int) ($counts[$m->getKey()] ?? 0), 'needsAction' => $m->status === 'selection_ended' || $w?->state === 'changes_requested',
            ];
        })->all();
    }

    /** @return array<string, mixed> */
    public function hub(User $client, string $missionId): array
    {
        $this->lifecycle->expireOverdue($client->getKey());
        $m = $this->authoring->owned($client, $missionId);
        $w = $this->authoring->working($m);
        $live = MissionVersion::query()->where('mission_id', $m->getKey())->where('state', 'published')->first();
        [$label, $tone, $icon, $note] = MissionPresenter::status($m, $w, $live);
        $active = Proposal::query()->where('mission_id', $m->getKey())->whereIn('state', ['active', 'selected'])->count();
        $order = $m->status === 'reserved' || $m->status === 'awarded' ? DB::table('orders')->where('mission_id', $m->getKey())->whereNotIn('state', ['cancelled', 'expired'])->value('reference') : null;

        return [
            'mission' => $m, 'working' => $w, 'live' => $live, 'status' => $label, 'tone' => $tone, 'icon' => $icon, 'note' => $note,
            'categories' => Category::query()->where(fn ($q) => $q->active()->orWhere('id', $w?->category_id))->orderBy('position')->get(['id', 'name']), 'proposals' => $active, 'orderReference' => $order,
            'problems' => $w?->isEditable() ? $this->problems($w) : [],
            'selectionEnd' => $live ? Dates::format(MissionLifecycle::selectionEnd($live)) : null,
            'canEdit' => $w?->isEditable() === true, 'canSubmit' => $w?->isEditable() === true, 'canUnsubmit' => $w?->state === 'in_review', 'canPreview' => $w !== null,
            'canRevise' => $w === null && in_array($m->status, ['open', 'selection_ended'], true), 'canClose' => in_array($m->status, ['open', 'selection_ended'], true),
            'canCancel' => in_array($m->status, ['draft', 'in_review'], true), 'canReopen' => $m->status === 'selection_ended',
            'history' => MissionEvent::query()->where('mission_id', $m->getKey())->whereNull('proposal_id')->orderByDesc('id')->limit(20)->get()->map(fn ($e) => ['type' => $e->type, 'when' => Dates::format($e->occurred_at), 'by' => $e->actor_label, 'note' => $e->note, 'meta' => $e->meta])->all(),
        ];
    }

    /** Ce qui empêche la soumission, champ par champ. @return array<string, string> */
    private function problems(MissionVersion $v): array
    {
        try {
            MissionRules::validate(collect(MissionRules::FIELDS)->mapWithKeys(fn ($f) => [$f => $v->{$f}])->all(), strict: true);
        } catch (ValidationException $e) {
            return array_map(fn ($m) => $m[0], $e->errors());
        }

        return [];
    }

    /** Aperçu public de la version de travail (rien n'est publié). @return array<string, mixed> */
    public function preview(User $client, string $missionId): array
    {
        $m = $this->authoring->owned($client, $missionId);
        $v = $this->authoring->working($m) ?? throw new MissionForbidden;
        $cat = Category::query()->find($v->category_id);

        return [
            'slug' => $m->slug, 'title' => $v->title ?: 'Titre à renseigner', 'description' => $v->description, 'category' => $cat->name, 'budget' => Money::xof((int) $v->budget_xof),
            'deadline' => $v->application_deadline ? Dates::format($v->application_deadline) : 'à renseigner', 'inputs' => $v->client_inputs, 'briefFiles' => $v->brief_requires_files, 'isDemo' => $m->is_demo,
            'accepting' => false, 'status' => 'preview', 'own' => true, 'mine' => null, 'missionId' => $m->getKey(), 'version' => $v->number,
        ];
    }
}

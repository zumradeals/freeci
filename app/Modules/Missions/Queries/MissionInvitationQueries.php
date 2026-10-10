<?php

namespace App\Modules\Missions\Queries;

use App\Modules\Accounts\Models\User;
use App\Modules\Missions\Actions\MissionInvitations;
use App\Shared\Dates;
use App\Shared\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Lectures des invitations (F-12) : côté freelance (titre, budget, date limite, message ; jamais l'identité du client) et côté client. */
final class MissionInvitationQueries
{
    /**
     * État affiché : « proposition reçue » et « expirée » se déduisent de la proposition et de la mission, ils ne sont pas stockés.
     *
     * @return array{0: string, 1: string, 2: string} [clé, libellé, ton]
     */
    private function display(object $r): array
    {
        if ($r->state === 'declined') {
            return ['declined', 'Déclinée', ''];
        }
        if ($r->state === 'withdrawn') {
            return ['withdrawn', 'Retirée', ''];
        }
        if ($r->proposal_state !== null) {
            return ['proposed', 'Proposition envoyée', 'success'];
        }
        $open = $r->mission_status === 'open' && $r->deadline !== null && $r->deadline > now()->toDateTimeString();

        return $open ? ['pending', 'À traiter', 'warning'] : ['expired', 'Expirée', ''];
    }

    private function base()
    {
        return DB::table('mission_invitations as i')->join('missions as m', 'm.id', '=', 'i.mission_id')->join('mission_versions as v', 'v.id', '=', 'm.published_version_id')
            ->selectRaw('i.id, i.state, i.message, i.decline_reason, i.created_at, i.freelancer_id, i.mission_id, m.slug, m.status as mission_status, v.title, v.budget_xof, v.application_deadline as deadline,
                (SELECT pr.state FROM proposals pr WHERE pr.mission_id = i.mission_id AND pr.freelancer_id = i.freelancer_id AND pr.state IN (\'active\',\'selected\') LIMIT 1) as proposal_state');
    }

    /** @return list<array<string, mixed>> */
    public function forFreelancer(User $u): array
    {
        return $this->base()->where('i.freelancer_id', $u->getKey())->orderByDesc('i.created_at')->orderByDesc('i.id')->limit(100)->get()->map(function ($r) {
            [$key, $label, $tone] = $this->display($r);

            return ['id' => (string) $r->id, 'key' => $key, 'label' => $label, 'tone' => $tone, 'title' => $r->title, 'slug' => $r->slug, 'budget' => Money::xof((int) $r->budget_xof)->formatted().' FCFA',
                'deadline' => Dates::format(Carbon::parse($r->deadline)), 'received' => Dates::format(Carbon::parse($r->created_at)), 'message' => $r->message,
                'reason' => $r->decline_reason ? MissionInvitations::REASONS[$r->decline_reason] ?? null : null];
        })->all();
    }

    public function pendingCount(User $u): int
    {
        return count(array_filter($this->forFreelancer($u), fn ($i) => $i['key'] === 'pending'));
    }

    /** @return list<array<string, mixed>> */
    public function forMission(User $client, string $missionId): array
    {
        return $this->base()->addSelect('p.display_name', 'p.slug as profile_slug')->join('freelance_profiles as p', 'p.user_id', '=', 'i.freelancer_id')
            ->where('i.mission_id', $missionId)->where('i.client_id', $client->getKey())->orderBy('i.created_at')->orderBy('i.id')->get()->map(function ($r) {
                [$key, $label, $tone] = $this->display($r);

                return ['id' => (string) $r->id, 'key' => $key, 'label' => $label, 'tone' => $tone, 'name' => $r->display_name, 'profile' => $r->profile_slug, 'sent' => Dates::format(Carbon::parse($r->created_at)),
                    'reason' => $r->decline_reason ? MissionInvitations::REASONS[$r->decline_reason] ?? null : null];
            })->all();
    }

    /** Missions du client que ce freelance peut encore recevoir en invitation (ouvertes, date limite non passée, pas déjà invité). @return list<array<string, mixed>> */
    public function inviteChoices(User $client, string $freelancerUserId): array
    {
        return DB::table('missions as m')->join('mission_versions as v', 'v.id', '=', 'm.published_version_id')->where('m.client_id', $client->getKey())->where('m.status', 'open')->where('v.application_deadline', '>', now())
            ->orderBy('v.application_deadline')->orderBy('m.id')->get(['m.id', 'v.title', 'v.budget_xof', 'v.application_deadline'])->map(fn ($r) => [
                'id' => (string) $r->id, 'title' => $r->title, 'budget' => Money::xof((int) $r->budget_xof)->formatted().' FCFA', 'deadline' => Dates::format(Carbon::parse($r->application_deadline)),
                'invited' => DB::table('mission_invitations')->where('mission_id', $r->id)->where('freelancer_id', $freelancerUserId)->exists(),
            ])->all();
    }

    /** Titre de la mission du client (publiée), ou null si elle n'est pas la sienne. */
    public function missionTitle(User $client, string $missionId): ?string
    {
        return DB::table('missions as m')->join('mission_versions as v', 'v.id', '=', 'm.published_version_id')->where('m.id', $missionId)->where('m.client_id', $client->getKey())->value('v.title');
    }
}

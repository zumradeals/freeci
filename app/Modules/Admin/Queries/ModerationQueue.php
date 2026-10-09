<?php

namespace App\Modules\Admin\Queries;

use App\Shared\Dates;
use App\Shared\Money;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Files de modération et fiches de contrôle (version soumise comparée à la version publique). Lecture seule : les décisions passent par
 * `ModerateContent`. Ne montre que du contenu DESTINÉ à être public (services, missions) : jamais de conversation, de brief ni de fichier.
 */
final class ModerationQueue
{
    public function counts(): array
    {
        return [
            'services' => DB::table('service_versions')->where('state', 'in_review')->count(),
            'missions' => DB::table('mission_versions')->where('state', 'in_review')->count(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function pendingServices(string $viewerId): array
    {
        return DB::table('service_versions as v')->join('services as s', 's.id', '=', 'v.service_id')->join('freelance_profiles as p', 'p.id', '=', 's.freelance_profile_id')->join('users as u', 'u.id', '=', 'p.user_id')
            ->where('v.state', 'in_review')->orderBy('v.submitted_at')
            ->get(['v.id', 'v.title', 'v.number', 'v.submitted_at', 's.status', 'u.name as owner', 'u.id as owner_id'])
            ->map(fn ($r) => ['id' => $r->id, 'title' => $r->title, 'owner' => $r->owner, 'since' => Dates::format(Carbon::parse($r->submitted_at)), 'ago' => Carbon::parse($r->submitted_at)->locale('fr')->diffForHumans(null, true), 'kind' => $r->status === 'published' || $r->status === 'suspended' ? 'Modification (v'.$r->number.')' : 'Première soumission', 'own' => $r->owner_id === $viewerId])->all();
    }

    /** @return list<array<string, mixed>> */
    public function pendingMissions(string $viewerId): array
    {
        return DB::table('mission_versions as v')->join('missions as m', 'm.id', '=', 'v.mission_id')->join('users as u', 'u.id', '=', 'm.client_id')
            ->where('v.state', 'in_review')->orderBy('v.submitted_at')
            ->get(['v.id', 'v.title', 'v.number', 'v.submitted_at', 'm.status', 'u.name as owner', 'u.id as owner_id'])
            ->map(fn ($r) => ['id' => $r->id, 'title' => $r->title, 'owner' => $r->owner, 'since' => Dates::format(Carbon::parse($r->submitted_at)), 'ago' => Carbon::parse($r->submitted_at)->locale('fr')->diffForHumans(null, true), 'kind' => in_array($r->status, ['open', 'suspended', 'selection_ended', 'reserved', 'awarded'], true) ? 'Modification (v'.$r->number.')' : 'Première soumission', 'own' => $r->owner_id === $viewerId])->all();
    }

    /** Contenus en ligne ou suspendus, pour suspendre ou remettre en ligne. */
    public function live(string $kind, string $status, ?string $q, string $viewerId, int $perPage): LengthAwarePaginator
    {
        $like = $q === null || trim($q) === '' ? null : '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_substr(trim($q), 0, 80)).'%';
        if ($kind === 'service') {
            $query = DB::table('services as s')->join('freelance_profiles as p', 'p.id', '=', 's.freelance_profile_id')->join('users as u', 'u.id', '=', 'p.user_id')
                ->where('s.status', $status === 'suspended' ? 'suspended' : 'published')
                ->when($like, fn ($w) => $w->where(fn ($x) => $x->where('s.title', 'ilike', $like)->orWhere('u.name', 'ilike', $like)))
                ->orderBy('s.title')->select('s.id', 's.title', 'u.name as owner', 'u.id as owner_id', 's.status');
        } else {
            $query = DB::table('missions as m')->join('mission_versions as v', 'v.id', '=', 'm.published_version_id')->join('users as u', 'u.id', '=', 'm.client_id')
                ->where('m.status', $status === 'suspended' ? 'suspended' : 'open')
                ->when($like, fn ($w) => $w->where(fn ($x) => $x->where('v.title', 'ilike', $like)->orWhere('u.name', 'ilike', $like)))
                ->orderBy('v.title')->select('m.id', 'v.title', 'u.name as owner', 'u.id as owner_id', 'm.status');
        }

        return $query->paginate($perPage)->withQueryString()->through(fn ($r) => ['id' => $r->id, 'title' => $r->title, 'owner' => $r->owner, 'own' => $r->owner_id === $viewerId, 'suspended' => $r->status === 'suspended']);
    }

    /** @return array<string, mixed>|null */
    public function service(string $versionId, string $viewerId): ?array
    {
        $v = DB::table('service_versions')->where('id', $versionId)->first();
        if ($v === null) {
            return null;
        }
        $s = DB::table('services as s')->join('freelance_profiles as p', 'p.id', '=', 's.freelance_profile_id')->join('users as u', 'u.id', '=', 'p.user_id')
            ->where('s.id', $v->service_id)->first(['s.id', 's.status', 's.slug', 'u.name as owner', 'u.id as owner_id', 'p.published_at as profile_published']);
        $live = DB::table('service_versions')->where('service_id', $v->service_id)->where('state', 'published')->where('id', '<>', $v->id)->first();
        $cat = fn ($row) => $row === null ? null : DB::table('categories')->where('id', $row->category_id)->value('name');
        $map = fn ($row) => $row === null ? null : [
            'Titre' => $row->title, 'Catégorie' => $cat($row), 'Résumé' => $row->summary, 'Périmètre' => $row->scope, 'Prix' => Money::xof((int) $row->price_xof)->formatted().' FCFA',
            'Délai' => $row->delivery_days.' jour(s)', 'Corrections incluses' => (string) $row->revisions_included,
            'Formules' => $this->tiers($row->tiers ?? null), 'Options payantes' => $this->options($row->options ?? null),
            'Livrables' => $this->lines($row->deliverables), 'Exclusions' => $this->lines($row->exclusions), 'À fournir par le client' => $this->lines($row->client_inputs),
            'Fichiers au brief' => $row->brief_requires_files ? 'Exigés' : 'Non exigés', 'Livraison' => $row->delivery_requires_files ? 'Avec fichiers' : 'Par message',
            'Images' => count(json_decode((string) $row->images, true) ?: []).' image(s)',
        ];
        $events = DB::table('service_events')->where('service_id', $v->service_id)->orderByDesc('id')->limit(12)->get(['type', 'actor_label', 'note', 'occurred_at']);

        return [
            'versionId' => $v->id, 'serviceId' => $s->id, 'state' => $v->state, 'number' => $v->number, 'title' => $v->title, 'owner' => $s->owner, 'own' => $s->owner_id === $viewerId,
            'inReview' => $v->state === 'in_review', 'status' => $s->status, 'profilePublished' => $s->profile_published !== null, 'liveNumber' => $live?->number,
            'rows' => $this->diff($map($v), $map($live)), 'history' => $this->history($events), 'decisionNote' => $v->decision_note,
        ];
    }

    /** @return array<string, mixed>|null */
    public function mission(string $versionId, string $viewerId): ?array
    {
        $v = DB::table('mission_versions')->where('id', $versionId)->first();
        if ($v === null) {
            return null;
        }
        $m = DB::table('missions as m')->join('users as u', 'u.id', '=', 'm.client_id')->where('m.id', $v->mission_id)->first(['m.id', 'm.status', 'u.name as owner', 'u.id as owner_id']);
        $live = DB::table('mission_versions')->where('mission_id', $v->mission_id)->where('state', 'published')->where('id', '<>', $v->id)->first();
        $map = fn ($row) => $row === null ? null : [
            'Titre' => $row->title, 'Catégorie' => DB::table('categories')->where('id', $row->category_id)->value('name'), 'Description' => $row->description, 'Budget' => Money::xof((int) $row->budget_xof)->formatted().' FCFA',
            'Date limite de candidature' => $row->application_deadline === null ? '—' : Dates::format(Carbon::parse($row->application_deadline)),
            'À fournir par le client' => $this->lines($row->client_inputs), 'Fichiers au brief' => $row->brief_requires_files ? 'Exigés' : 'Non exigés',
        ];
        $events = DB::table('mission_events')->where('mission_id', $v->mission_id)->whereNotIn('type', ['proposal_submitted', 'proposal_revised', 'proposal_withdrawn'])->orderByDesc('id')->limit(12)->get(['type', 'actor_label', 'note', 'occurred_at']);

        return [
            'versionId' => $v->id, 'missionId' => $m->id, 'state' => $v->state, 'number' => $v->number, 'title' => $v->title, 'owner' => $m->owner, 'own' => $m->owner_id === $viewerId,
            'inReview' => $v->state === 'in_review', 'status' => $m->status, 'liveNumber' => $live?->number,
            'rows' => $this->diff($map($v), $map($live)), 'history' => $this->history($events), 'decisionNote' => $v->decision_note,
        ];
    }

    /** Fiche d'un contenu EN LIGNE ou suspendu (sans version en contrôle) : pour suspendre ou remettre en ligne. */
    public function liveService(string $serviceId, string $viewerId): ?array
    {
        $s = DB::table('services as s')->join('freelance_profiles as p', 'p.id', '=', 's.freelance_profile_id')->join('users as u', 'u.id', '=', 'p.user_id')->where('s.id', $serviceId)->first(['s.id', 's.title', 's.status', 'u.name as owner', 'u.id as owner_id']);

        return $s === null ? null : ['id' => $s->id, 'title' => $s->title, 'status' => $s->status, 'owner' => $s->owner, 'own' => $s->owner_id === $viewerId,
            'history' => $this->history(DB::table('service_events')->where('service_id', $serviceId)->orderByDesc('id')->limit(12)->get(['type', 'actor_label', 'note', 'occurred_at']))];
    }

    public function liveMission(string $missionId, string $viewerId): ?array
    {
        $m = DB::table('missions as m')->join('users as u', 'u.id', '=', 'm.client_id')->where('m.id', $missionId)->first(['m.id', 'm.status', 'u.name as owner', 'u.id as owner_id']);
        if ($m === null) {
            return null;
        }
        $title = DB::table('mission_versions')->where('mission_id', $missionId)->orderByDesc('number')->value('title');

        return ['id' => $m->id, 'title' => $title, 'status' => $m->status, 'owner' => $m->owner, 'own' => $m->owner_id === $viewerId,
            'history' => $this->history(DB::table('mission_events')->where('mission_id', $missionId)->whereNotIn('type', ['proposal_submitted', 'proposal_revised', 'proposal_withdrawn'])->orderByDesc('id')->limit(12)->get(['type', 'actor_label', 'note', 'occurred_at']))];
    }

    private function lines(?string $json): string
    {
        $a = json_decode((string) $json, true);

        return is_array($a) && $a !== [] ? implode("\n", array_map(fn ($x) => '• '.(is_array($x) ? json_encode($x, JSON_UNESCAPED_UNICODE) : $x), $a)) : '—';
    }

    /** @return list<array{label: string, new: string, old: ?string, changed: bool}> */
    private function diff(array $new, ?array $old): array
    {
        $rows = [];
        foreach ($new as $label => $value) {
            $o = $old[$label] ?? null;
            $rows[] = ['label' => $label, 'new' => (string) $value, 'old' => $o === null ? null : (string) $o, 'changed' => $old !== null && (string) $o !== (string) $value];
        }

        return $rows;
    }

    private const EVENTS = [
        'created' => 'Créé', 'submitted' => 'Soumis au contrôle', 'approved' => 'Approuvé', 'changes_requested' => 'Correction demandée', 'suspended' => 'Suspendu', 'reinstated' => 'Remis en ligne',
        'withdrawn_submission' => 'Soumission retirée', 'reopened' => 'Rouvert', 'closed' => 'Fermé', 'cancelled' => 'Annulé', 'expired' => 'Expiré',
    ];

    private function history($events): array
    {
        return collect($events)->map(fn ($e) => ['what' => self::EVENTS[$e->type] ?? $e->type, 'by' => $e->actor_label, 'note' => $e->note, 'when' => Dates::format(Carbon::parse($e->occurred_at))])->all();
    }

    /** Formules d'une version, une ligne par formule (relecture par la modération). */
    private function tiers(?string $json): string
    {
        $t = json_decode((string) $json, true) ?: [];

        return $t === [] ? 'Offre unique' : implode("\n", array_map(fn ($x) => $x['name'].' : '.Money::xof((int) $x['price_xof'])->formatted().' FCFA, '.$x['delivery_days'].' jour(s), '.$x['revisions_included'].' correction(s) — '.implode(' ; ', $x['includes'] ?? []), $t));
    }

    /** Options payantes d'une version, une ligne par option. */
    private function options(?string $json): string
    {
        $o = json_decode((string) $json, true) ?: [];

        return $o === [] ? 'Aucune' : implode("\n", array_map(fn ($x) => $x['label'].' : + '.Money::xof((int) $x['price_xof'])->formatted().' FCFA, '.($x['delivery_days'] >= 0 ? '+' : '−').abs((int) $x['delivery_days']).' jour(s)', $o));
    }
}

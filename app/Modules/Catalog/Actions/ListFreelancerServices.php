<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Enums\ServiceStatus;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\ServiceVersion;
use App\Modules\Catalog\Support\ImageUrls;
use App\Shared\Money;

/** « Mes services » : uniquement les services du propriétaire, avec états lisibles et actions réellement disponibles (revérifiées côté serveur). */
final class ListFreelancerServices
{
    public function __construct(private ServiceAuthoring $authoring, private ServiceEditorData $editor) {}

    /** @return list<array<string, mixed>> */
    public function __invoke(User $freelancer): array
    {
        $services = Service::query()->whereHas('freelanceProfile', fn ($q) => $q->where('user_id', $freelancer->getKey()))->with('category')->orderByDesc('updated_at')->get();
        foreach ($services as $s) {
            $this->authoring->ensureVersions($s);
        }
        $versions = ServiceVersion::query()->whereIn('service_id', $services->pluck('id'))->whereIn('state', [...ServiceVersion::OPEN, 'published'])->get()->groupBy('service_id');

        return $services->map(function (Service $s) use ($versions, $freelancer) {
            $all = $versions->get($s->getKey(), collect());
            $working = $all->first(fn ($v) => in_array($v->state, ServiceVersion::OPEN, true));
            $live = $all->firstWhere('state', 'published');
            $shown = $working ?? $live;
            [$label, $tone, $icon, $note] = $this->describe($s, $working, $live);

            return [
                'id' => $s->getKey(), 'title' => $shown?->title ?: $s->title, 'slug' => $s->slug, 'category' => $s->category->name,
                'price' => $shown?->price_xof ? Money::xof($shown->price_xof) : null,
                'thumb' => ImageUrls::present($shown?->images ?? [])[0]['card'] ?? null, 'deliveryDays' => $shown?->delivery_days ?: null,
                'toComplete' => $working?->state === 'draft' ? count($this->editor->submissionProblems($freelancer, $s->getKey())) : 0,
                'status' => $label, 'tone' => $tone, 'icon' => $icon, 'note' => $note,
                'published' => $s->status === ServiceStatus::Published, 'working' => $working?->state, 'workingNumber' => $working?->number, 'liveNumber' => $live?->number,
                'canEdit' => $working !== null && $working->isEditable(), 'canPreview' => $working !== null,
                'canSubmit' => $working !== null && $working->isEditable(), 'canUnsubmit' => $working?->state === 'in_review',
                'canRevise' => $working === null && $live !== null, 'canWithdraw' => $s->status === ServiceStatus::Published,
                'canRestore' => $s->status === ServiceStatus::Archived, 'suspended' => $s->status === ServiceStatus::Suspended,
            ];
        })->all();
    }

    /** @return array{0: string, 1: string, 2: string, 3: ?string} libellé, ton, icône, note (motif…) */
    private function describe(Service $s, ?ServiceVersion $working, ?ServiceVersion $live): array
    {
        return match (true) {
            $working?->state === 'changes_requested' => ['À corriger', 'warning', 'warn', $working->decision_note],
            $working?->state === 'in_review' => [$live ? 'Modification en contrôle' : 'En contrôle', 'info', 'clock', $live ? 'La version publiée (v'.$live->number.') reste en ligne pendant le contrôle.' : null],
            $s->status === ServiceStatus::Suspended => ['Suspendu par la modération', 'error', 'error', 'Le service n’est plus proposé. Les commandes en cours et leurs accords ne changent pas.'],
            $s->status === ServiceStatus::Archived => ['Retiré du catalogue', 'neutral', 'minus-circle', 'Le service n’est plus proposé. Les commandes en cours et leurs accords ne changent pas.'],
            $working?->state === 'draft' && $live !== null => ['Publié · modification en brouillon', 'success', 'check-circle', 'Votre brouillon n’est pas visible : la version publiée (v'.$live->number.') reste en ligne.'],
            $s->status === ServiceStatus::Published => ['Publié', 'success', 'check-circle', null],
            default => ['Brouillon', 'neutral', 'pencil', null],
        };
    }
}

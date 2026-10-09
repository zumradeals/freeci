<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Accounts\Actions\PublishFreelanceProfile;
use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Data\ServiceDetail;
use App\Modules\Catalog\Exceptions\ServiceForbidden;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\ServiceEvent;
use App\Modules\Catalog\Models\ServiceVersion;
use App\Modules\Catalog\Support\ImageProcessor;
use App\Modules\Catalog\Support\ImageUrls;
use App\Modules\Catalog\Support\ServiceRules;
use App\Shared\Dates;
use App\Shared\Money;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** Lecture pour l'éditeur et l'aperçu : toujours bornée au propriétaire (service d'autrui = ServiceForbidden). */
final class ServiceEditorData
{
    public function __construct(private ServiceAuthoring $authoring) {}

    /** @return Collection<int, Category> */
    public function categoryChoices(): Collection
    {
        return Category::query()->active()->orderBy('position')->get(['id', 'name']);
    }

    /** @return array<string, mixed> */
    public function editor(User $owner, string $serviceId): array
    {
        $service = $this->authoring->owned($owner, $serviceId);
        $this->authoring->ensureVersions($service);
        $v = $this->authoring->working($service);
        $live = ServiceVersion::query()->where('service_id', $service->getKey())->where('state', 'published')->first();
        $profile = $service->freelanceProfile;

        return [
            'service' => $service, 'version' => $v, 'live' => $live,
            'categories' => Category::query()->where(fn ($q) => $q->active()->orWhere('id', $v?->category_id))->orderBy('position')->get(['id', 'name']),
            'images' => $v ? ImageUrls::present($v->images) : [], 'imageIds' => $v ? array_map(fn ($i) => $i['id'] ?? null, $v->images) : [],
            'imagesEnabled' => ImageProcessor::available(), 'limits' => config('freeci.catalog'),
            'profilePublished' => $profile->published_at !== null, 'profileMissing' => PublishFreelanceProfile::missing($profile),
            'history' => ServiceEvent::query()->where('service_id', $service->getKey())->orderByDesc('id')->limit(15)->get()->map(fn (ServiceEvent $e) => [
                'type' => $e->type, 'when' => Dates::format($e->occurred_at), 'by' => $e->actor_label, 'note' => $e->note,
            ])->all(),
        ];
    }

    /** Problèmes bloquant la soumission, champ par champ (liste vide = soumissible). @return array<string, string> */
    public function submissionProblems(User $owner, string $serviceId): array
    {
        $service = $this->authoring->owned($owner, $serviceId);
        $v = $this->authoring->working($service);
        if ($v === null) {
            return [];
        }
        $problems = [];
        try {
            ServiceRules::validate(collect(ServiceRules::FIELDS)->mapWithKeys(fn ($f) => [$f => $v->{$f}])->all(), strict: true);
        } catch (ValidationException $e) {
            $problems = array_map(fn ($m) => $m[0], $e->errors());
        }
        if ($service->freelanceProfile->published_at === null) {
            $problems['profile'] = 'Votre profil n’est pas publié : publiez-le d’abord (il s’affiche avec vos services).';
        }
        if (count($v->images) < 1 && ImageProcessor::available()) {
            $problems['images'] = 'Ajoutez une image de couverture : elle s’affiche sur la carte de votre service dans le catalogue.';
        }
        foreach ($v->images as $img) {
            if (isset($img['id']) && mb_strlen(trim((string) ($img['alt'] ?? ''))) < 3) {
                $problems['images'] = 'Décrivez chaque image (texte alternatif).';
            }
        }

        return $problems;
    }

    /** Aperçu exact tel qu'il serait publié (version de travail), sans rien publier. */
    public function preview(User $owner, string $serviceId): ServiceDetail
    {
        $service = $this->authoring->owned($owner, $serviceId);
        $this->authoring->ensureVersions($service);
        $v = $this->authoring->working($service) ?? throw new ServiceForbidden;
        $p = $service->freelanceProfile;
        $cat = Category::query()->find($v->category_id);

        return new ServiceDetail(
            slug: $service->slug, title: $v->title ?: 'Titre à renseigner', summary: $v->summary, scope: $v->scope, categorySlug: $cat->slug, categoryName: $cat->name,
            sellerName: $p->display_name, sellerInitials: ServiceProjection::initials($p->display_name), sellerHeadline: $p->headline, sellerCity: $p->city,
            price: Money::xof((int) $v->price_xof), deliveryDays: (int) $v->delivery_days, revisionsIncluded: $v->revisions_included, deliverables: $v->deliverables, exclusions: $v->exclusions,
            clientInputs: $v->client_inputs, images: ImageUrls::present($v->images), isDemo: $p->is_demo, version: 0, acceptsRequests: false, sellerUserId: $p->user_id, sellerSlug: null,
            tiers: $v->tiers ?? [], options: $v->options ?? [],
        );
    }
}

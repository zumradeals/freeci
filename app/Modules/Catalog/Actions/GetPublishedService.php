<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Data\ServiceDetail;
use App\Modules\Catalog\Exceptions\ServiceNotAvailable;
use App\Modules\Catalog\Exceptions\ServiceNotFound;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Support\ImageUrls;
use App\Shared\Money;

/** Fiche publique d'un service (projection à liste de champs explicite). */
final class GetPublishedService
{
    /**
     * @throws ServiceNotFound si le service n'existe pas ou n'est pas encore public (brouillon, en contrôle)
     * @throws ServiceNotAvailable si le service a été retiré (suspendu, archivé) : message dédié, sans donnée privée
     */
    public function __invoke(string $slug): ServiceDetail
    {
        $service = Service::query()->with(['category', 'freelanceProfile'])->where('slug', $slug)->first();

        if ($service === null) {
            throw new ServiceNotFound;
        }
        if ($service->status->isWithdrawn()) {
            throw new ServiceNotAvailable;
        }
        if (! Service::query()->published()->whereKey($service->getKey())->exists()) {
            throw new ServiceNotFound;
        }

        $p = $service->freelanceProfile;

        return new ServiceDetail(
            slug: $service->slug,
            title: $service->title,
            summary: $service->summary,
            scope: $service->scope,
            categorySlug: $service->category->slug,
            categoryName: $service->category->name,
            sellerName: $p->display_name,
            sellerInitials: ServiceProjection::initials($p->display_name),
            sellerHeadline: $p->headline,
            sellerCity: $p->city,
            price: Money::xof($service->price_xof),
            deliveryDays: $service->delivery_days,
            revisionsIncluded: $service->revisions_included,
            deliverables: $service->deliverables,
            exclusions: $service->exclusions,
            clientInputs: $service->client_inputs,
            images: ImageUrls::present($service->images),
            isDemo: $service->is_demo,
            version: $service->row_version,
            acceptsRequests: $service->accepts_requests,
            sellerUserId: $p->user_id,
            sellerSlug: $p->published_at !== null ? $p->slug : null,
        );
    }
}

<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Data\ServiceCard;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Support\ImageUrls;
use App\Shared\Money;
use Illuminate\Support\Str;

/** Seul endroit où un modèle Service devient une carte publique. */
final class ServiceProjection
{
    public static function card(Service $s): ServiceCard
    {
        $profile = $s->freelanceProfile;
        $image = ImageUrls::present($s->images)[0] ?? null;

        return new ServiceCard(
            slug: $s->slug,
            title: $s->title,
            categoryName: $s->category->name,
            sellerName: $profile->display_name,
            sellerInitials: self::initials($profile->display_name),
            sellerHeadline: $profile->headline,
            deliveryDays: $s->delivery_days,
            price: Money::xof($s->price_xof),
            imageSrc: $image['card'] ?? $image['src'] ?? null,
            imageAlt: $image['alt'] ?? null,
            isDemo: $s->is_demo,
            id: (string) $s->getKey(),
            sellerUserId: (string) $profile->user_id,
        );
    }

    public static function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return implode('', array_map(fn ($p) => mb_strtoupper(Str::substr($p, 0, 1)), array_slice($parts, 0, 2))) ?: '?';
    }
}

<?php

namespace App\Modules\Catalog\Support;

/** Résout les images d'un service en adresses utilisables : médias téléversés → route contrôlée ; images d'exemple existantes → inchangées. */
final class ImageUrls
{
    /**
     * @param  list<array<string, mixed>>  $images
     * @return list<array{src:string,card:string,alt:string,caption:string}>
     */
    public static function present(array $images): array
    {
        return array_values(array_map(fn ($i) => isset($i['id'])
            ? ['src' => route('media.show', [$i['id'], 'large'], false), 'card' => route('media.show', [$i['id'], 'card'], false), 'alt' => (string) ($i['alt'] ?? ''), 'caption' => (string) ($i['caption'] ?? '')]
            : ['src' => (string) ($i['src'] ?? ''), 'card' => (string) ($i['card'] ?? $i['src'] ?? ''), 'alt' => (string) ($i['alt'] ?? ''), 'caption' => (string) ($i['caption'] ?? '')], $images));
    }
}

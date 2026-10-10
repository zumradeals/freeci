<?php

namespace App\Shared;

use Illuminate\Http\Request;

/**
 * Référencement (F-16) : adresse publique, adresses canoniques, balises de partage et données structurées. Rien de privé n'y passe : seules des données déjà publiques sur la page.
 * Le partage (aperçus) fonctionne même quand le site est masqué aux moteurs ; seul l'indexation (robots, plan du site) suit le réglage « masquer ».
 */
final class Seo
{
    /** Paramètres qui ne changent pas la nature d'une liste : tous les autres (recherche, tri, filtres) rendent la page non indexable. */
    private const KEPT = ['categorie', 'page'];

    private const LISTS = ['services.index', 'freelances.index', 'missions.index'];

    public static function base(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    /** Adresse publique absolue d'une route nommée : toujours sur le domaine configuré (APP_URL), jamais sur l'hôte de la requête. @param  array<string, mixed>|string  $params */
    public static function url(string $route, array|string $params = []): string
    {
        return self::base().route($route, $params, false);
    }

    /** Image d'aperçu : raster uniquement (les réseaux sociaux n'affichent pas le SVG) ; sinon null, donc l'image FreeCI par défaut. */
    public static function raster(?string $src): ?string
    {
        return $src === null || $src === '' || preg_match('/\.svg(\?|$)/i', $src) ? null : $src;
    }

    public static function absolute(?string $pathOrUrl): string
    {
        if ($pathOrUrl === null || $pathOrUrl === '') {
            return self::base().'/images/og-default.png';
        }

        return preg_match('#^https?://#i', $pathOrUrl) ? $pathOrUrl : self::base().'/'.ltrim($pathOrUrl, '/');
    }

    public static function indexingBlocked(): bool
    {
        return (bool) config('freeci.noindex');
    }

    /** Une liste filtrée (recherche, tri, prix, délai, budget…) n'est pas indexable. */
    public static function isFiltered(Request $r): bool
    {
        if (! in_array((string) $r->route()?->getName(), self::LISTS, true)) {
            return false;
        }
        foreach ($r->query() as $k => $v) {
            if (! in_array($k, self::KEPT, true) && ! self::isTracking((string) $k) && $v !== '' && $v !== null && $v !== []) {
                return true;
            }
        }

        return false;
    }

    /** Paramètres de suivi publicitaire : sans effet sur le contenu, jamais repris dans l'adresse canonique. */
    private static function isTracking(string $key): bool
    {
        return str_starts_with($key, 'utm_') || in_array($key, ['fbclid', 'gclid', 'ref', 'mc_cid', 'mc_eid'], true);
    }

    /** Adresse canonique : le chemin, la catégorie et la page (si > 1) ; jamais de paramètre de suivi, de recherche ni de filtre. */
    public static function canonical(Request $r): string
    {
        $q = [];
        $isList = in_array((string) $r->route()?->getName(), self::LISTS, true);
        if ($isList && is_string($r->query('categorie')) && preg_match('/^[a-z0-9-]{1,80}$/', $r->query('categorie'))) {
            $q['categorie'] = $r->query('categorie');
        }
        if ($isList && (int) $r->query('page') > 1) {
            $q['page'] = (int) $r->query('page');
        }

        return self::base().'/'.ltrim($r->path() === '/' ? '' : $r->path(), '/').($q === [] ? '' : '?'.http_build_query($q));
    }

    /** Texte court pour une balise : espaces normalisés, coupé à une limite de mot, avec « … ». */
    public static function trim(?string $text, int $max): string
    {
        $t = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $text)));
        if (mb_strlen($t) <= $max) {
            return $t;
        }
        $cut = mb_substr($t, 0, $max - 1);
        $sp = mb_strrpos($cut, ' ');

        return rtrim($sp !== false && $sp > $max * 0.6 ? mb_substr($cut, 0, $sp) : $cut, ' ,;:.-–—').'…';
    }

    /** JSON-LD sûr dans une balise script (jamais de « </script> » injectable). @param  array<string, mixed>  $data */
    public static function json(array $data): string
    {
        return (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }

    /** @param  list<array{name: string, url: string}>  $trail */
    public static function breadcrumbs(array $trail): array
    {
        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => array_map(fn ($c, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $c['name'], 'item' => $c['url']], $trail, array_keys($trail))];
    }

    /** @return array<string, mixed> */
    public static function organization(): array
    {
        return ['@context' => 'https://schema.org', '@graph' => [
            ['@type' => 'Organization', '@id' => self::base().'/#organisation', 'name' => 'FreeCI', 'url' => self::base().'/', 'logo' => self::base().'/favicon.svg', 'areaServed' => 'CI'],
            ['@type' => 'WebSite', '@id' => self::base().'/#site', 'url' => self::base().'/', 'name' => 'FreeCI', 'inLanguage' => 'fr', 'publisher' => ['@id' => self::base().'/#organisation'],
                'potentialAction' => ['@type' => 'SearchAction', 'target' => ['@type' => 'EntryPoint', 'urlTemplate' => self::base().'/services?q={terme}'], 'query-input' => 'required name=terme']],
        ]];
    }

    /** @param  array{title: string, summary: string, url: string, price: int, seller: string, image: ?string, rating: ?array{avg: string, count: int}, city: ?string, category: string}  $s */
    public static function service(array $s): array
    {
        $d = ['@context' => 'https://schema.org', '@type' => 'Service', 'name' => $s['title'], 'description' => self::trim($s['summary'], 300), 'url' => $s['url'], 'serviceType' => $s['category'], 'areaServed' => 'CI',
            'provider' => array_filter(['@type' => 'Person', 'name' => $s['seller'], 'address' => $s['city'] ? ['@type' => 'PostalAddress', 'addressLocality' => $s['city'], 'addressCountry' => 'CI'] : null]),
            'offers' => ['@type' => 'Offer', 'price' => (string) $s['price'], 'priceCurrency' => 'XOF', 'url' => $s['url']]];
        if ($s['image']) {
            $d['image'] = $s['image'];
        }
        if ($s['rating'] !== null && $s['rating']['count'] > 0) {
            $d['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => str_replace(',', '.', $s['rating']['avg']), 'reviewCount' => $s['rating']['count'], 'bestRating' => '5', 'worstRating' => '1'];
        }

        return $d;
    }

    /** @param  array{name: string, headline: string, url: string, city: ?string, image: ?string, rating: ?array{avg: string, count: int}}  $p */
    public static function person(array $p): array
    {
        $d = ['@context' => 'https://schema.org', '@type' => 'Person', 'name' => $p['name'], 'jobTitle' => $p['headline'], 'url' => $p['url']];
        if ($p['city']) {
            $d['address'] = ['@type' => 'PostalAddress', 'addressLocality' => $p['city'], 'addressCountry' => 'CI'];
        }
        if ($p['image']) {
            $d['image'] = $p['image'];
        }
        if ($p['rating'] !== null && $p['rating']['count'] > 0) {
            $d['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => str_replace(',', '.', $p['rating']['avg']), 'reviewCount' => $p['rating']['count'], 'bestRating' => '5', 'worstRating' => '1'];
        }

        return $d;
    }
}

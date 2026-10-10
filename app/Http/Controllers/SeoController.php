<?php

namespace App\Http\Controllers;

use App\Shared\Seo;
use App\Shared\SitemapQueries;
use Illuminate\Http\Response;

/** robots.txt et plan du site (F-16). Le réglage « masquer le site aux moteurs » commande les deux : jamais de plan du site publié tant que l'indexation est fermée. */
class SeoController extends Controller
{
    public const PRIVATE_PATHS = ['/admin', '/espace', '/freelance', '/commandes', '/connexion', '/inscription', '/mot-de-passe-oublie', '/reinitialisation', '/livewire', '/notifications', '/avis',
        '/services/*/demande', '/services/*/contacter', '/missions/*/proposition', '/freelances/*/inviter'];

    public function robots(): Response
    {
        if (Seo::indexingBlocked()) {
            $body = "User-agent: *\nDisallow: /\n";
        } else {
            $body = "User-agent: *\n".implode("\n", array_map(fn ($p) => 'Disallow: '.$p, self::PRIVATE_PATHS))."\n\nSitemap: ".Seo::base()."/sitemap.xml\n";
        }

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    public function index(): Response
    {
        abort_if(Seo::indexingBlocked(), 404);
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach (SitemapQueries::KINDS as $k) {
            $xml .= '  <sitemap><loc>'.e(Seo::base().'/sitemap-'.$k.'.xml').'</loc></sitemap>'."\n";
        }

        return $this->xml($xml.'</sitemapindex>'."\n");
    }

    public function show(string $kind, SitemapQueries $q): Response
    {
        abort_if(Seo::indexingBlocked() || ! in_array($kind, SitemapQueries::KINDS, true), 404);
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($q->urls($kind) as $u) {
            $xml .= '  <url><loc>'.e($u['loc']).'</loc>'.($u['lastmod'] ? '<lastmod>'.$u['lastmod'].'</lastmod>' : '').'</url>'."\n";
        }

        return $this->xml($xml.'</urlset>'."\n");
    }

    private function xml(string $body): Response
    {
        return response($body, 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }
}

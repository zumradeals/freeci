<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
@php
  $fullTitle = isset($title) ? $title.' — FreeCI' : 'FreeCI — des compétences en Côte d’Ivoire';
  $desc = \App\Shared\Seo::trim($description ?? 'FreeCI : trouvez une prestation à prix et délai annoncés, suivez votre commande de la demande à la validation.', 160);
  $robotsValue = config('freeci.noindex') ? 'noindex, nofollow' : ($robots ?? (\App\Shared\Seo::isFiltered(request()) ? 'noindex, follow' : null));
  // Balises de partage, adresse canonique et données structurées : pages publiques seulement (jamais les pages privées, formulaires, erreurs ni brouillons).
  $share = request()->isMethod('GET') && ! str_contains((string) ($robots ?? ''), 'noindex') && ! request()->routeIs('admin.*', 'account.*', 'freelance.*', 'orders.*', 'login*', 'register', 'password.*');
  $seo = $seo ?? [];
@endphp
<title>{{ $fullTitle }}</title>
<meta name="description" content="{{ $desc }}">
@if($robotsValue)<meta name="robots" content="{{ $robotsValue }}">@endif
@if($share)
<link rel="canonical" href="{{ \App\Shared\Seo::canonical(request()) }}">
<meta property="og:site_name" content="FreeCI">
<meta property="og:locale" content="fr_FR">
<meta property="og:type" content="{{ $seo['type'] ?? 'website' }}">
<meta property="og:title" content="{{ \App\Shared\Seo::trim($fullTitle, 70) }}">
<meta property="og:description" content="{{ $desc }}">
<meta property="og:url" content="{{ \App\Shared\Seo::canonical(request()) }}">
<meta property="og:image" content="{{ \App\Shared\Seo::absolute(\App\Shared\Seo::raster($seo['image'] ?? null)) }}">
<meta property="og:image:alt" content="{{ \App\Shared\Seo::trim($seo['imageAlt'] ?? $fullTitle, 120) }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ \App\Shared\Seo::trim($fullTitle, 70) }}">
<meta name="twitter:description" content="{{ $desc }}">
<meta name="twitter:image" content="{{ \App\Shared\Seo::absolute(\App\Shared\Seo::raster($seo['image'] ?? null)) }}">
@foreach($seo['jsonld'] ?? [] as $ld)<script type="application/ld+json">{!! \App\Shared\Seo::json($ld) !!}</script>
@endforeach
@endif
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
@vite(['resources/css/app.css', 'resources/js/app.js'])
@livewireStyles

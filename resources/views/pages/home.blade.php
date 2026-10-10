<x-layouts.public :title="'Trouver une prestation en Côte d’Ivoire'" :seo="['jsonld' => [\App\Shared\Seo::organization()]]" main-class="home-modern">
@php
  $hm = fn (string $k) => config("freeci.home.$k") ?: \App\Modules\Admin\Settings\AppSettings::default("home.$k");
  $announce = trim((string) config('freeci.home.announce_text'));
  $announceUrl = \App\Modules\Admin\Navigation\Destinations::url((string) config('freeci.home.announce_link'));
  $missionUrl = \App\Modules\Admin\Navigation\Destinations::url($hm('mission_btn_dest')) ?? route('client.missions.new');
  $freelanceUrl = \App\Modules\Admin\Navigation\Destinations::url($hm('freelance_btn_dest')) ?? route('freelance.activate');
  $featured = collect($categories)->first(fn ($c) => $c->featured && $c->services > 0);
  $catUrl = fn ($c) => route('services.index', ['categorie' => $c->slug]);
@endphp
@if(config('freeci.home.announce_enabled') && $announce !== '')
<div class="announce" role="region" aria-label="Annonce"><div class="container"><p>{{ $announce }}</p>@if($announceUrl)<a class="btn btn-secondary" href="{{ $announceUrl }}">{{ config('freeci.home.announce_link_label') ?: 'En savoir plus' }}</a>@endif</div></div>
@endif
<section class="home-hero on-dark" aria-labelledby="h-hero">
  <div class="container home-hero-grid">
    <div class="home-hero-copy">
      <p class="eyebrow">{{ $hm('eyebrow') }}</p>
      <h1 id="h-hero">{{ $hm('title') }}</h1>
      <p class="home-lede">{{ $hm('lede') }}</p>
      <form class="home-search" role="search" method="get" action="{{ route('services.index') }}">
        <label class="sr-only" for="q">Que recherchez-vous ?</label>
        <div class="home-search-box"><div class="search-field"><x-fc.icon name="search" :size="22" /><input class="input" id="q" name="q" type="search" placeholder="{{ $hm('search_hint') }}" maxlength="100"></div><button class="btn btn-accent" type="submit">Rechercher</button></div>
      </form>
      <div class="home-chips" role="group" aria-label="Recherches fréquentes">
        @foreach(array_slice(array_values(array_filter(array_map('trim', explode(',', (string) $hm('chips'))))), 0, 6) as $term)
        <a class="chip" href="{{ route('services.index', ['q' => $term]) }}">{{ $term }}</a>
        @endforeach
      </div>
      <div class="home-mission-link"><span>{{ $hm('mission_prompt') }}</span><a href="{{ $missionUrl }}">{{ $hm('mission_btn_label') }} <x-fc.icon name="arrow-right" :size="18" /></a></div>
    </div>
    <div class="home-hero-visual">
      <img class="home-work-image" src="{{ asset('images/home/creative-work-1200.webp') }}" srcset="{{ asset('images/home/creative-work-640.webp') }} 640w, {{ asset('images/home/creative-work-1200.webp') }} 1200w" sizes="(min-width: 1024px) 48vw, (min-width: 640px) 70vw, 92vw" width="1200" height="800" alt="" fetchpriority="high">
      <aside class="home-tracker" aria-labelledby="h-tracker"><h2 id="h-tracker">Votre projet, étape par étape</h2>
        <ol>@foreach(['clipboard' => 'Accord', 'card' => 'Paiement', 'package' => 'Livraison', 'check-circle' => 'Validation'] as $icon => $label)<li @if($icon === 'package') class="is-now" aria-current="step" @endif><x-fc.icon :name="$icon" :size="26" /><span>{{ $label }}</span></li>@endforeach</ol>
        <p class="home-tracker-example">Exemple : plans en DWG, 35 000 FCFA. Le travail démarre une fois le paiement confirmé ; rien n’est validé à votre place.</p>
      </aside>
    </div>
  </div>
</section>
<section class="home-trust" aria-label="Vos repères sur FreeCI"><div class="container"><ul>
  <li><x-fc.icon name="clipboard" :size="28" /><div><h2>Un accord clair</h2><p>Prix, délai et périmètre figés dès l’accord : ils ne changent plus.</p></div></li>
  <li><x-fc.icon name="message" :size="28" /><div><h2>Un suivi au même endroit</h2><p>Vos échanges et livraisons dans un seul dossier.</p></div></li>
  <li><x-fc.icon name="check-circle" :size="28" /><div><h2>Vous validez la livraison</h2><p>Vous examinez le travail avant de décider.</p></div></li>
</ul></div></section>

@if($featured)
<section class="home-section home-featured" aria-labelledby="h-feat"><div class="container"><div class="featured-cat"><span class="ico"><x-fc.icon :name="$featured->icon" :size="26" /></span><div><p class="eyebrow">À la une</p><h2 class="t-h2" id="h-feat">{{ $featured->name }}</h2><p class="muted">{{ $featured->services }} {{ $featured->services > 1 ? 'services publiés' : 'service publié' }}</p></div><a class="btn btn-primary" href="{{ $catUrl($featured) }}">Voir cette catégorie</a></div></div></section>
@endif

<section class="home-section" aria-labelledby="h-cats"><div class="container">
  <div class="section-head"><h2 class="t-h2" id="h-cats">Que souhaitez-vous réaliser ?</h2><a class="btn btn-link" href="{{ route('services.index') }}">Tous les services <x-fc.icon name="arrow-right" :size="18" /></a></div>
  <ul class="home-categories">@foreach($categories as $c)<li><a href="{{ $catUrl($c) }}"><x-fc.icon :name="$c->icon" :size="30" /><span>{{ $c->name }}</span>@if($c->services > 0)<small>{{ $c->services }} {{ $c->services > 1 ? 'services' : 'service' }}</small>@endif</a></li>@endforeach</ul>
</div></section>

<section class="home-section home-services" id="prestations" aria-labelledby="h-svc"><div class="container">
  <div class="section-head"><div><h2 class="t-h2" id="h-svc">Des services pour passer à l’action</h2><p class="muted small">Les dernières prestations publiées sur FreeCI.</p></div><a class="btn btn-link" href="{{ route('services.index') }}">Voir tout le catalogue <x-fc.icon name="arrow-right" :size="18" /></a></div>
  @if(count($services))<div class="svc-grid">@foreach($services as $service)<x-fc.service-card :service="$service" />@endforeach</div>
  @else<div class="card empty"><span class="ico-lg"><x-fc.icon name="inbox" :size="26" /></span><p><strong>Les premiers services seront publiés ici.</strong></p><a class="btn btn-secondary" href="{{ $missionUrl }}">{{ $hm('mission_btn_label') }}</a></div>@endif
</div></section>

<section class="home-section" aria-labelledby="h-two"><div class="container">
  <div class="section-head"><h2 class="t-h2" id="h-two">Deux façons de commencer</h2></div>
  <div class="home-ways">
    <article class="home-way"><x-fc.icon name="grid" :size="34" /><div><h3>Je choisis un service</h3><p>Comparez les offres, puis envoyez votre demande.</p><a class="btn btn-accent" href="{{ route('services.index') }}">Explorer les services <x-fc.icon name="arrow-right" :size="18" /></a></div></article>
    <article class="home-way home-way-dark on-dark"><x-fc.icon name="file" :size="34" /><div><h3>Je publie une mission</h3><p>Décrivez votre besoin et recevez des propositions.</p><a class="btn btn-secondary btn-lg" href="{{ $missionUrl }}">{{ $hm('mission_btn_label') }} <x-fc.icon name="arrow-right" :size="18" /></a></div></article>
  </div>
</div></section>
<section class="home-section home-process" id="comment" aria-labelledby="h-ways"><div class="container">
  <div class="section-head"><h2 class="t-h2" id="h-ways">Un parcours simple, du besoin au résultat</h2><a class="btn btn-link" href="{{ route('info', 'fonctionnement') }}">Comment ça marche <x-fc.icon name="arrow-right" :size="18" /></a></div>
  <ol class="home-steps">
    <li><span class="home-step-number" aria-hidden="true">1</span><div><h3>Choisir</h3><p>Trouvez un service ou publiez votre besoin.</p></div></li>
    <li><span class="home-step-number" aria-hidden="true">2</span><div><h3>Convenir</h3><p>Accordez-vous sur le prix, le délai et le travail attendu.</p></div></li>
    <li><span class="home-step-number" aria-hidden="true">3</span><div><h3>Suivre</h3><p>Après paiement confirmé et brief complet, le travail commence.</p></div></li>
    <li><span class="home-step-number" aria-hidden="true">4</span><div><h3>Valider</h3><p>Examinez le travail et validez la livraison.</p></div></li>
  </ol>
</div></section>
<section class="home-freelance on-dark" aria-labelledby="h-fl"><div class="container">
  <div><h2 class="t-h2" id="h-fl">{{ $hm('freelance_title') }}</h2><p>{{ $hm('freelance_text') }}</p></div><a class="btn btn-accent btn-lg" href="{{ $freelanceUrl }}">{{ $hm('freelance_btn_label') }} <x-fc.icon name="arrow-right" :size="18" /></a>
</div></section>
</x-layouts.public>

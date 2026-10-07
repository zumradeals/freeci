<x-layouts.public :title="'Trouver une prestation en Côte d’Ivoire'">
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
<section class="hero hv-hero on-dark" aria-labelledby="h-hero">
  <div class="hero-grid-bg" aria-hidden="true"></div>
  <div class="container">
    <div>
      <p class="eyebrow">{{ $hm('eyebrow') }}</p>
      <h1 class="t-display" id="h-hero">{{ $hm('title') }}<span class="dot" aria-hidden="true">.</span></h1>
      <p class="lede">{{ $hm('lede') }}</p>
      <form class="search" role="search" method="get" action="{{ route('services.index') }}">
        <label for="q">Que recherchez-vous ?</label>
        <div class="search-box">
          <div class="search-field"><x-fc.icon name="search" :size="22" /><input class="input" id="q" name="q" type="search" placeholder="{{ $hm('search_hint') }}" autocomplete="off" maxlength="100"></div>
          <button class="btn btn-primary btn-lg" type="submit">Rechercher</button>
        </div>
      </form>
      <div class="chips" role="group" aria-label="Recherches fréquentes"><span class="lbl sr-only-m">Recherches fréquentes :</span>
        @foreach(array_slice(array_values(array_filter(array_map('trim', explode(',', (string) $hm('chips'))))), 0, 6) as $term)
        <a class="chip" href="{{ route('services.index', ['q' => $term]) }}">{{ $term }}</a>
        @endforeach</div>
      <div class="hero-alt"><span>{{ $hm('mission_prompt') }}</span><a class="btn btn-secondary btn-lg" href="{{ $missionUrl }}">{{ $hm('mission_btn_label') }}</a></div>
    </div>
    <aside class="hv-tracker" aria-labelledby="h-card">
      <header><div><p class="ref">Exemple · commande FC-2610-00123</p><h2 id="h-card">Plans en DWG</h2></div><span class="hv-pill"><x-fc.icon name="clock" :size="16" />Livraison à examiner</span></header>
      <ol class="hv-steps" aria-label="Étapes de la commande">
        <li class="done"><span class="dot" aria-hidden="true"><x-fc.icon name="check" :size="16" /></span><div><b>Accord</b><span>Prix, délai et corrections figés.</span></div></li>
        <li class="done"><span class="dot" aria-hidden="true"><x-fc.icon name="check" :size="16" /></span><div><b>Paiement et brief</b><span>Paiement confirmé, brief complet.</span></div></li>
        <li class="now" aria-current="step"><span class="dot" aria-hidden="true">3</span><div><b>Livraison</b><span>Vous examinez, puis validez ou demandez une correction.</span></div></li>
        <li><span class="dot" aria-hidden="true">4</span><div><b>Validation et avis</b><span>Rien n’est validé à votre place.</span></div></li>
      </ol>
      <footer><span>Montant convenu</span><strong>35 000 <small>FCFA</small></strong></footer>
    </aside>
  </div>
</section>

<section class="hv-trust" aria-label="Nos garanties"><div class="container">
  <ul>
    <li><span class="hv-ico"><x-fc.icon name="shield" :size="22" /></span><div><b>Prix et délai figés</b><span>Ce qui est convenu à l’accord ne change plus.</span></div></li>
    <li><span class="hv-ico"><x-fc.icon name="card" :size="22" /></span><div><b>Le travail démarre une fois le paiement confirmé</b><span>Le freelance commence quand le paiement est confirmé et le brief complet.</span></div></li>
    <li><span class="hv-ico"><x-fc.icon name="check-circle" :size="22" /></span><div><b>Rien n’est validé à votre place</b><span>Vous examinez chaque livraison, puis vous validez.</span></div></li>
  </ul>
</div></section>

@if($featured)
<section class="section section-featured" aria-labelledby="h-feat"><div class="container"><div class="featured-cat"><span class="ico"><x-fc.icon :name="$featured->icon" :size="26" /></span><div><p class="eyebrow">À la une</p><h2 class="t-h2" id="h-feat">{{ $featured->name }}</h2><p class="muted">{{ $featured->services }} {{ $featured->services > 1 ? 'services publiés' : 'service publié' }}</p></div><a class="btn btn-primary" href="{{ $catUrl($featured) }}">Voir cette catégorie</a></div></div></section>
@endif

<section class="section" aria-labelledby="h-cats"><div class="container">
  <div class="section-head"><h2 class="t-h2" id="h-cats">Parcourir par besoin</h2><a class="btn btn-link" href="{{ route('services.index') }}">Tous les services <x-fc.icon name="arrow-right" :size="18" /></a></div>
  <ul class="cats">
    @foreach($categories as $c)
    <li><a class="cat" href="{{ $catUrl($c) }}"><span class="ico"><x-fc.icon :name="$c->icon" :size="22" /></span><span class="t">{{ $c->name }}@if($c->services > 0)<small class="cat-n">{{ $c->services }} {{ $c->services > 1 ? 'services' : 'service' }}</small>@endif</span></a></li>
    @endforeach
  </ul>
</div></section>

<section class="section section-alt" id="prestations" aria-labelledby="h-svc"><div class="container">
  <div class="section-head"><div><h2 class="t-h2" id="h-svc">Services publiés récemment</h2></div>
    <a class="btn btn-link" href="{{ route('services.index') }}">Voir tout le catalogue <x-fc.icon name="arrow-right" :size="18" /></a></div>
  @if(count($services))
  <div class="svc-grid">
    @foreach($services as $service)<x-fc.service-card :service="$service" />@endforeach
  </div>
  @else
  <div class="card empty"><span class="ico-lg"><x-fc.icon name="inbox" :size="26" /></span><p style="font-weight:600">Les premiers services seront publiés ici.</p></div>
  @endif
</div></section>

<section class="section" aria-labelledby="h-two"><div class="container">
  <div class="section-head"><div><h2 class="t-h2" id="h-two">Deux façons de commencer</h2><p>Le même suivi de commande dans les deux cas : accord, paiement, livraison, validation.</p></div></div>
  <div class="hv-ways">
    <div class="hv-way"><h3>Je choisis un service</h3><p>Prix et délai sont annoncés. Vous comparez, vous envoyez votre demande, le freelance accepte.</p>
      <ul><li>Prix et corrections figés à l’accord</li><li>Le freelance répond dans le délai prévu</li></ul>
      <a class="btn btn-primary" href="{{ route('services.index') }}">Voir les services</a></div>
    <div class="hv-way hv-way-dark"><h3>Je décris mon besoin</h3><p>Publiez une mission, recevez des propositions à prix ferme, comparez et retenez la meilleure.</p>
      <ul><li>Seul le client voit les propositions</li><li>Aucune coordonnée privée n’est publiée</li></ul>
      <a class="btn btn-accent" href="{{ $missionUrl }}">{{ $hm('mission_btn_label') }}</a></div>
  </div>
</div></section>

<section class="section section-alt" id="comment" aria-labelledby="h-ways"><div class="container">
  <div class="section-head"><div><h2 class="t-h2" id="h-ways">Comment ça marche</h2></div>
    <a class="btn btn-link" href="{{ route('info', 'fonctionnement') }}">Le détail <x-fc.icon name="arrow-right" :size="18" /></a></div>
  <ol class="steps-row">
    <li><b>Choisir</b><span>Un service à prix et délai annoncés, ou une mission décrite par vos soins.</span></li>
    <li><b>Convenir</b><span>Le freelance accepte : l’accord est figé (prix, délai, corrections).</span></li>
    <li><b>Suivre</b><span>Paiement, brief, livraison et corrections dans un seul dossier.</span></li>
    <li><b>Valider</b><span>Vous validez la livraison, puis laissez un avis si la commande est réelle.</span></li>
  </ol>
</div></section>

<section class="freelance-band on-dark" aria-labelledby="h-fl"><div class="container">
  <div><h2 class="t-h2" id="h-fl">{{ $hm('freelance_title') }}</h2><p style="margin-top:6px">{{ $hm('freelance_text') }}</p></div>
  <a class="btn btn-primary btn-lg" href="{{ $freelanceUrl }}">{{ $hm('freelance_btn_label') }}</a>
</div></section>

</x-layouts.public>

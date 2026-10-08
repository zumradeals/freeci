@php
  $svcs = collect($p['services']);
  $cheapest = $svcs->sortBy(fn ($s) => $s->price->xof)->first();
  $fastest = $svcs->min('deliveryDays');
  $count = $svcs->count();
  $countLabel = $count.' '.($count > 1 ? 'services publiés' : 'service publié');
  $missionUrl = \App\Modules\Admin\Navigation\Destinations::url((string) config('freeci.home.mission_btn_dest')) ?? route('client.missions.new');
@endphp
<x-layouts.public :title="$p['name']" :description="$p['headline']" main-class="svc-page">
<section class="pf-band" aria-labelledby="pf-name">
  <div class="container">
    <nav class="sd-crumbs" aria-label="Fil d’Ariane"><a href="{{ url('/') }}">Accueil</a><span aria-hidden="true">/</span><a href="{{ route('freelances.index') }}">Freelances</a><span aria-hidden="true">/</span><span aria-current="page">{{ $p['name'] }}</span></nav>
    <div class="pf-id">
      <x-fc.avatar :name="$p['name']" :user="$p['userId']" size="xl" class="pf-avatar" :alt="'Photo de '.$p['name']" />
      <div class="pf-id-text"><p class="pf-kicker">Profil freelance</p><h1 id="pf-name">{{ $p['name'] }}</h1><p class="pf-headline">{{ $p['headline'] }}</p>
        <p class="pf-meta">@if($p['city'])<span><x-fc.icon name="pin" :size="16" /> {{ $p['city'] }}</span>@endif @if($p['rating'])<span><x-fc.rating :avg="$p['rating']['avg']" :count="$p['rating']['count']" /></span>@endif <span>{{ $countLabel }}</span></p></div>
      <div class="pf-actions">@if($count)<a class="btn btn-accent btn-lg" href="#h-svcs">Voir les services</a>@endif<span class="pf-fav"><x-fc.fav-button-inline kind="freelance" :slug="$slug" :on="$p['favorited']" /></span></div>
    </div>
  </div>
</section>
<div class="container sp-wrap profile-page">
  <div class="sp-grid">
  <div class="sp-main">
    @if($p['bio'] || count($p['skills']))
    <section class="sp-sec profile-about" aria-labelledby="h-about" style="margin-top:0"><h2 id="h-about">Présentation</h2>
      @if($p['bio'])<div class="profile-bio">{!! nl2br(e($p['bio'])) !!}</div>@endif
      @if(count($p['skills']))<h3 class="pf-h3">Compétences</h3><ul class="talent-skills">@foreach($p['skills'] as $sk)<li><span class="badge tone-neutral">{{ $sk }}</span></li>@endforeach</ul>@endif
    </section>
    @endif
    <section class="sp-sec profile-services" aria-labelledby="h-svcs"><div class="section-head"><h2 id="h-svcs">Services publiés</h2>@if($count)<span class="muted small">{{ $count }} {{ $count > 1 ? 'services' : 'service' }}</span>@endif</div>
      @if($count)<div class="svc-grid service-grid pf-sg">@foreach($p['services'] as $service)<x-fc.service-card :service="$service" :level="3" />@endforeach</div>
      @else<p class="muted" style="margin-top:8px">Aucun service publié pour l’instant.</p>@endif</section>
    <section class="sp-sec profile-reviews" aria-labelledby="h-rev" id="avis"><h2 id="h-rev">Avis des clients</h2>
      @if($p['rating'])
        <div class="pf-sum card">
          <div class="pf-big"><b>{{ $p['rating']['avg'] }}</b><span>sur 5</span><p class="muted small">{{ $p['rating']['count'] }} avis publié{{ $p['rating']['count'] > 1 ? 's' : '' }}</p></div>
          <ul class="pf-split">
            <li><span>Issus d’un service</span><b>@if($p['rating']['service']['avg']){{ $p['rating']['service']['avg'] }} / 5 @else — @endif</b><small class="muted">{{ $p['rating']['service']['count'] }} avis</small></li>
            <li><span>Issus d’une mission</span><b>@if($p['rating']['mission']['avg']){{ $p['rating']['mission']['avg'] }} / 5 @else — @endif</b><small class="muted">{{ $p['rating']['mission']['count'] }} avis</small></li>
          </ul>
        </div>
        <x-fc.reviews :reviews="$reviews" />
      @else
        <p class="muted">Aucun avis pour le moment.</p>
      @endif</section>
    @auth<p class="small muted"><a href="{{ route('support.report.form', ['profile', $slug]) }}">Signaler ce profil</a></p>@endauth
  </div>
  <aside class="sp-aside" aria-label="Le profil en bref">
    <div class="sp-offer card"><p class="muted small">En bref</p>
      <ul class="sp-facts">
        <li><x-fc.icon name="package" :size="22" /><span><b>{{ $countLabel }}</b><small>Prestations à prix annoncé</small></span></li>
        @if($cheapest)<li><x-fc.icon name="card" :size="22" /><span><b>Dès <x-fc.money :amount="$cheapest->price" /></b><small>Prix du service le plus accessible</small></span></li>@endif
        @if($fastest)<li><x-fc.icon name="clock" :size="22" /><span><b>Dès {{ $fastest }} {{ $fastest > 1 ? 'jours' : 'jour' }}</b><small>Délai le plus court annoncé</small></span></li>@endif
      </ul>
      @if($count)<a class="btn btn-primary btn-lg btn-block" href="#h-svcs">Voir les services</a>@endif
      <ul class="sp-assure">
        <li><x-fc.icon name="check-circle" :size="18" />Prix et délai annoncés sur chaque service</li>
        <li><x-fc.icon name="check-circle" :size="18" />Avis publiés uniquement après une commande validée</li>
        <li><x-fc.icon name="check-circle" :size="18" />Échanges via FreeCI, sans coordonnées publiées</li>
      </ul>
    </div>
  </aside>
  </div>
  <section class="sd-cta pf-cta" aria-label="Publier une mission"><div class="container">
    <span class="sd-cta-ico"><x-fc.icon name="briefcase" :size="30" /></span>
    <div><h2>Vous avez un besoin précis ?</h2><p>Publiez une mission : les freelances intéressés vous envoient leurs propositions.</p></div>
    <a class="btn btn-primary" href="{{ $missionUrl }}">Publier une mission</a>
  </div></section>
</div>
@if($count)
<div class="sticky-buy" role="region" aria-label="Voir les services"><div class="sum"><b>{{ $p['name'] }}</b><small>@if($cheapest)Dès <x-fc.money :amount="$cheapest->price" /> · @endif{{ $countLabel }}</small></div><a class="btn btn-primary btn-lg" href="#h-svcs">Voir</a></div>
@endif
</x-layouts.public>

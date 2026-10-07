<x-layouts.public :title="$p['name']" :description="$p['headline']" main-class="svc-page">
<div class="container profile-page">
  <nav class="crumbs" aria-label="Fil d’Ariane"><a class="back-m" href="{{ route('freelances.index') }}"><x-fc.icon name="arrow-right" :size="16" class="flip" />Retour aux freelances</a><a class="hide-m" href="{{ route('freelances.index') }}">Freelances</a><span class="sep hide-m" aria-hidden="true">›</span><span class="hide-m" aria-current="page">{{ $p['name'] }}</span></nav>
  <header class="card profile-header">
    <div class="profile-identity"><span class="avatar avatar-xl" aria-hidden="true">{{ $p['initials'] }}</span><div><p class="eyebrow">Profil freelance</p><h1 class="t-h1">{{ $p['name'] }}</h1><p class="profile-headline">{{ $p['headline'] }}</p>@if($p['city'])<p class="muted">{{ $p['city'] }}</p>@endif
    @if($p['rating'])<p><x-fc.rating :avg="$p['rating']['avg']" :count="$p['rating']['count']" /></p>@endif</div></div>
    <div class="profile-actions">@if(count($p['services']))<a class="btn btn-primary" href="#h-svcs">Voir les services</a>@endif<x-fc.fav-button-inline kind="freelance" :slug="$slug" :on="$p['favorited']" /></div>
  </header>
  @if($p['bio'] || count($p['skills']))
  <section class="card profile-about" aria-labelledby="h-about"><h2 class="t-h2" id="h-about">Présentation</h2>
    @if($p['bio'])<div class="profile-bio">{!! nl2br(e($p['bio'])) !!}</div>@endif
    @if(count($p['skills']))<div><h3 class="t-h3">Compétences</h3><ul class="talent-skills">@foreach($p['skills'] as $sk)<li><span class="badge tone-neutral">{{ $sk }}</span></li>@endforeach</ul></div>@endif
  </section>
  @endif
  <section class="profile-services" aria-labelledby="h-svcs"><h2 class="t-h2" id="h-svcs">Services publiés</h2>
    @if(count($p['services']))<div class="svc-grid">@foreach($p['services'] as $service)<x-fc.service-card :service="$service" :level="3" />@endforeach</div>
    @else<p class="muted" style="margin-top:8px">Aucun service publié pour l’instant.</p>@endif</section>
  <section class="card profile-reviews" aria-labelledby="h-rev" id="avis"><h2 class="t-h2" id="h-rev">Avis</h2>
    @if($p['rating'])
      <p class="muted"><x-fc.rating :avg="$p['rating']['avg']" :count="$p['rating']['count']" /> — moyenne de tous les avis publiés.
        Dont {{ $p['rating']['service']['count'] }} issu{{ $p['rating']['service']['count'] > 1 ? 's' : '' }} d’un service @if($p['rating']['service']['avg']) (moyenne {{ $p['rating']['service']['avg'] }}/5)@endif
        et {{ $p['rating']['mission']['count'] }} issu{{ $p['rating']['mission']['count'] > 1 ? 's' : '' }} d’une mission @if($p['rating']['mission']['avg']) (moyenne {{ $p['rating']['mission']['avg'] }}/5)@endif.</p>
      <x-fc.reviews :reviews="$reviews" />
    @else
      <p class="muted">Aucun avis pour le moment.</p>
    @endif</section>

  @auth<p class="small muted"><a href="{{ route('support.report.form', ['profile', $slug]) }}">Signaler ce profil</a></p>@endauth
</div>
</x-layouts.public>

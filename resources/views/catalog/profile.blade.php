<x-layouts.public :title="$p['name']" :description="$p['headline']" main-class="svc-page">
<div class="container">
  <nav class="crumbs" aria-label="Fil d’Ariane"><a class="back-m" href="{{ route('services.index') }}"><x-fc.icon name="arrow-right" :size="16" class="flip" />Retour aux services</a><a class="hide-m" href="{{ route('services.index') }}">Services</a><span class="sep hide-m" aria-hidden="true">›</span><span class="hide-m" aria-current="page">{{ $p['name'] }}</span></nav>
  <section class="card vendor" aria-labelledby="h-name" style="max-width:760px"><div class="top"><span class="avatar avatar-lg" aria-hidden="true">{{ $p['initials'] }}</span>
    <div><h1 class="t-h1" id="h-name">{{ $p['name'] }}</h1>@if($p['rating'])<p class="rate-l"><x-fc.rating :avg="$p['rating']['avg']" :count="$p['rating']['count']" /></p>@endif<p class="muted">{{ $p['headline'] }}@if($p['city']) · {{ $p['city'] }}@endif</p>@if($p['isDemo'])<p><span class="tag-demo">Profil fictif de démonstration</span></p>@endif</div></div>
    @if($p['bio'])<p style="margin-top:12px">{!! nl2br(e($p['bio'])) !!}</p>@endif
    @if(count($p['skills']))<ul class="chips-s" aria-label="Compétences" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;list-style:none;padding:0">@foreach($p['skills'] as $sk)<li><span class="badge tone-neutral">{{ $sk }}</span></li>@endforeach</ul>@endif
  </section>
  <section aria-labelledby="h-svcs" style="margin-top:24px"><h2 class="t-h2" id="h-svcs">Services publiés</h2>
    @if(count($p['services']))<div class="svc-grid" style="margin-top:12px">@foreach($p['services'] as $service)<x-fc.service-card :service="$service" :level="3" />@endforeach</div>
    @else<p class="muted" style="margin-top:8px">Aucun service publié pour l’instant.</p>@endif</section>
  <section aria-labelledby="h-rev" id="avis" style="margin-top:24px"><h2 class="t-h2" id="h-rev">Avis</h2>
    @if($p['rating'])
      <p class="muted"><x-fc.rating :avg="$p['rating']['avg']" :count="$p['rating']['count']" /> — moyenne de tous les avis publiés.
        Dont {{ $p['rating']['service']['count'] }} issu{{ $p['rating']['service']['count'] > 1 ? 's' : '' }} d’un service @if($p['rating']['service']['avg']) (moyenne {{ $p['rating']['service']['avg'] }}/5)@endif
        et {{ $p['rating']['mission']['count'] }} issu{{ $p['rating']['mission']['count'] > 1 ? 's' : '' }} d’une mission @if($p['rating']['mission']['avg']) (moyenne {{ $p['rating']['mission']['avg'] }}/5)@endif.</p>
      <x-fc.reviews :reviews="$reviews" />
    @else
      <p class="muted">Aucun avis pour l’instant. Les avis ne sont publiés qu’après une commande réelle validée et clôturée. Aucun badge de vérification n’existe sur FreeCI.</p>
    @endif</section>
  <p style="margin-top:12px"><x-fc.fav-button-inline kind="freelance" :slug="$slug" :on="$p['favorited']" /></p>
  @auth<p class="small muted" style="margin-top:12px"><a href="{{ route('support.report.form', ['profile', $slug]) }}">Signaler ce profil</a></p>@endauth
</div>
</x-layouts.public>

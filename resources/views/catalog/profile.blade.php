<x-layouts.public :title="$p['name']" :description="$p['headline']" main-class="svc-page">
<div class="container">
  <nav class="crumbs" aria-label="Fil d’Ariane"><a class="back-m" href="{{ route('services.index') }}"><x-fc.icon name="arrow-right" :size="16" class="flip" />Retour aux services</a><a class="hide-m" href="{{ route('services.index') }}">Services</a><span class="sep hide-m" aria-hidden="true">›</span><span class="hide-m" aria-current="page">{{ $p['name'] }}</span></nav>
  <section class="card vendor" aria-labelledby="h-name" style="max-width:760px"><div class="top"><span class="avatar avatar-lg" aria-hidden="true">{{ $p['initials'] }}</span>
    <div><h1 class="t-h1" id="h-name">{{ $p['name'] }}</h1><p class="muted">{{ $p['headline'] }}@if($p['city']) · {{ $p['city'] }}@endif</p>@if($p['isDemo'])<p><span class="tag-demo">Profil fictif de démonstration</span></p>@endif</div></div>
    @if($p['bio'])<p style="margin-top:12px">{!! nl2br(e($p['bio'])) !!}</p>@endif
    @if(count($p['skills']))<ul class="chips-s" aria-label="Compétences" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;list-style:none;padding:0">@foreach($p['skills'] as $sk)<li><span class="badge tone-neutral">{{ $sk }}</span></li>@endforeach</ul>@endif
  </section>
  <section aria-labelledby="h-svcs" style="margin-top:24px"><h2 class="t-h2" id="h-svcs">Services publiés</h2>
    @if(count($p['services']))<div class="svc-grid" style="margin-top:12px">@foreach($p['services'] as $service)<x-fc.service-card :service="$service" :level="3" />@endforeach</div>
    @else<p class="muted" style="margin-top:8px">Aucun service publié pour l’instant.</p>@endif</section>
  <p class="muted small" style="margin-top:24px">Aucun avis ni badge de vérification : ils n’existent pas encore sur FreeCI.</p>
</div>
</x-layouts.public>

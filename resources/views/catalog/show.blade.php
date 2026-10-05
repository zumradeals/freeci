<x-layouts.public :title="$service->title" :description="$service->summary" main-class="svc-page">
@php
  $days = $service->deliveryDays.' '.($service->deliveryDays > 1 ? 'jours' : 'jour');
  $rev = $service->revisionsIncluded === 0 ? 'Aucune' : $service->revisionsIncluded;
  $revLabel = $service->revisionsIncluded > 1 ? 'Corrections incluses' : 'Correction incluse';
@endphp
<div class="container">
  @isset($preview)<div class="notice tone-warning" role="note" style="margin-top:16px"><x-fc.icon name="flag" /><p><strong>Aperçu — non publié.</strong> Voici le rendu public de votre version de travail ; personne d’autre ne le voit. <a href="{{ $preview }}">Revenir à l’édition</a></p></div>@endisset
  <nav class="crumbs" aria-label="Fil d’Ariane"><a class="back-m" href="{{ route('services.index') }}"><x-fc.icon name="arrow-right" :size="16" class="flip" />Retour aux services</a><a class="hide-m" href="{{ route('services.index') }}">Services</a><span class="sep hide-m" aria-hidden="true">›</span><a class="hide-m" href="{{ route('services.index', ['categorie' => $service->categorySlug]) }}">{{ $service->categoryName }}</a></nav>
  <div class="svc-layout">
    <div>
      <p class="eyebrow cat-eyebrow">{{ $service->categoryName }}@if($service->isDemo) · <span class="tag-demo">Exemple fictif</span>@endif</p>
      <h1 class="t-h1">{{ $service->title }}</h1>
      <p class="seller-line"><span class="avatar" aria-hidden="true">{{ $service->sellerInitials }}</span><span><b>{{ $service->sellerName }}</b><small class="muted">{{ $service->sellerHeadline }}@if($service->sellerCity) · {{ $service->sellerCity }}@endif</small></span></p>

      <section class="buy-summary card" aria-label="Offre">
        <p class="muted small">Prix fixe pour le périmètre décrit</p><x-fc.money :amount="$service->price" size="lg" />
        <ul class="facts-row">
          <li><x-fc.icon name="clock" /><span><b>{{ $days }}</b><small>Délai</small></span></li>
          <li><x-fc.icon name="pencil" /><span><b>{{ $rev }}</b><small>{{ $revLabel }}</small></span></li>
          <li><x-fc.icon name="package" /><span><b>{{ count($service->deliverables) }} {{ count($service->deliverables) > 1 ? 'éléments' : 'élément' }}</b><small>Livrables</small></span></li>
        </ul>
        @include('catalog._cta', ['service' => $service, 'id' => 'buy-cta'])
      </section>

      <figure class="gallery" @if(count($service->images) > 1) data-gallery @endif>
        @if(count($service->images))
        <div class="stage"><img src="{{ $service->images[0]['src'] }}" alt="{{ $service->images[0]['alt'] }}" width="640" height="427"></div>
        @if(count($service->images) > 1)
        <div class="thumbs" role="group" aria-label="Choisir une image">
          @foreach($service->images as $k => $img)
          <button class="th" type="button" aria-pressed="{{ $k === 0 ? 'true' : 'false' }}" data-src="{{ $img['src'] }}" data-alt="{{ $img['alt'] }}" data-cap="{{ $img['caption'] }}"><img src="{{ $img['src'] }}" alt="Aperçu : {{ $img['alt'] }}"></button>
          @endforeach
        </div>
        @endif
        <figcaption>{{ $service->images[0]['caption'] }}</figcaption>
        @endif
      </figure>

      <div class="prose-sections">
        <section aria-labelledby="s0"><h2 class="t-h2" id="s0">En bref</h2><p>{{ $service->summary }}</p></section>
        <section aria-labelledby="s1"><h2 class="t-h2" id="s1">Ce que vous recevez</h2>
          <ul class="checklist ok">@foreach($service->deliverables as $d)<li><x-fc.icon name="check-circle" /><span>{{ $d }}</span></li>@endforeach</ul></section>
        <section aria-labelledby="s2"><h2 class="t-h2" id="s2">Périmètre inclus</h2><p>{{ $service->scope }}</p></section>
        <section aria-label="Précisions">
          <details class="fold" data-open-desktop><summary><span>Ce qui n’est pas inclus <span class="muted" style="font-weight:500">({{ count($service->exclusions) }})</span></span><x-fc.icon name="chev-down" class="chev" /></summary><div class="fold-body"><ul class="checklist no">@foreach($service->exclusions as $d)<li><x-fc.icon name="minus-circle" /><span>{{ $d }}</span></li>@endforeach</ul></div></details>
          <details class="fold" data-open-desktop><summary><span>Ce que vous devez fournir <span class="muted" style="font-weight:500">({{ count($service->clientInputs) }})</span></span><x-fc.icon name="chev-down" class="chev" /></summary><div class="fold-body"><ul class="checklist need">@foreach($service->clientInputs as $d)<li><x-fc.icon name="clipboard" /><span>{{ $d }}</span></li>@endforeach</ul></div></details>
        </section>
        <section aria-labelledby="s5"><h2 class="t-h2" id="s5">Le vendeur</h2>
          <div class="card vendor"><div class="top"><span class="avatar avatar-lg" aria-hidden="true">{{ $service->sellerInitials }}</span>
            <div><p class="t-h3">{{ $service->sellerName }}</p><p class="muted">{{ $service->sellerHeadline }}@if($service->sellerCity) · {{ $service->sellerCity }}@endif</p></div></div>
            @if($service->sellerSlug)<p style="margin-top:8px"><a href="{{ route('freelances.show', $service->sellerSlug) }}">Voir le profil complet</a></p>@endif
            @if($service->isDemo)<p class="muted small">Profil fictif de démonstration.</p>@endif</div></section>
        <section aria-labelledby="s6"><h2 class="t-h2" id="s6">Avis</h2>
          <p class="muted">Aucun avis pour l’instant. Les avis ne sont publiés qu’après une commande validée.</p></section>
      </div>
    </div>

    <aside class="offer-aside" aria-label="Offre">
      <div class="offer">
        <div><p class="muted small">Prix fixe pour le périmètre décrit</p><x-fc.money :amount="$service->price" size="lg" /></div>
        <dl>
          <div><dt><x-fc.icon name="clock" />Délai</dt><dd>{{ $days }}</dd></div>
          <div><dt><x-fc.icon name="pencil" />{{ $revLabel }}</dt><dd>{{ $rev }}</dd></div>
          <div><dt><x-fc.icon name="package" />Livrables</dt><dd>{{ count($service->deliverables) }}</dd></div>
        </dl>
        @include('catalog._cta', ['service' => $service, 'id' => null])
      </div>
    </aside>
  </div>
</div>
<div class="sticky-buy" role="region" aria-label="Demander cette prestation"><div class="sum"><x-fc.money :amount="$service->price" /><small>{{ $days }}@if($service->revisionsIncluded) · {{ $service->revisionsIncluded }} {{ $service->revisionsIncluded > 1 ? 'corrections' : 'correction' }}@endif</small></div>@if($service->acceptsRequests && (! auth()->check() || auth()->id() !== $service->sellerUserId))<a class="btn btn-primary btn-lg" href="{{ route('services.request', $service->slug) }}">Demander</a>@endif</div>

</x-layouts.public>

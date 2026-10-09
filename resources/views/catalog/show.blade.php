<x-layouts.public :title="$service->title" :description="$service->summary" main-class="svc-page">
@php
  $days = $service->deliveryDays.' '.($service->deliveryDays > 1 ? 'jours' : 'jour');
  $rev = $service->revisionsIncluded === 0 ? 'Aucune' : $service->revisionsIncluded;
  $revLabel = $service->revisionsIncluded > 1 ? 'Corrections incluses' : 'Correction incluse';
@endphp
<div class="container">
  @isset($preview)<div class="notice tone-warning" role="note" style="margin-top:16px"><x-fc.icon name="flag" /><p><strong>Aperçu — non publié.</strong> Voici le rendu public de votre version de travail ; personne d’autre ne le voit. <a href="{{ $preview }}">Revenir à l’édition</a></p></div>@endisset
  <nav class="crumbs sp-crumbs" aria-label="Fil d’Ariane"><a class="back-m" href="{{ route('services.index') }}"><x-fc.icon name="arrow-right" :size="16" class="flip" />Retour aux services</a><a class="hide-m" href="{{ url('/') }}">Accueil</a><span class="sep hide-m" aria-hidden="true">/</span><a class="hide-m" href="{{ route('services.index') }}">Services</a><span class="sep hide-m" aria-hidden="true">/</span><a class="hide-m" href="{{ route('services.index', ['categorie' => $service->categorySlug]) }}">{{ $service->categoryName }}</a><span class="sep hide-m" aria-hidden="true">/</span><span class="hide-m" aria-current="page">{{ $service->title }}</span></nav>
  <div class="svc-layout">
    <div>
      <p class="eyebrow cat-eyebrow sp-cat">{{ $service->categoryName }}@if($service->isDemo) · <span class="tag-demo">Exemple fictif</span>@endif</p>
      <h1 class="t-h1">{{ $service->title }}</h1>
      <div class="sp-meta">
        @if($service->sellerSlug)<a class="sp-seller" href="{{ route('freelances.show', $service->sellerSlug) }}">@else<span class="sp-seller">@endif<x-fc.avatar :name="$service->sellerName" :user="$service->sellerUserId" /><span><b>{{ $service->sellerName }}</b><small>{{ $service->sellerHeadline }}@if($service->sellerCity) · {{ $service->sellerCity }}@endif</small></span>@if($service->sellerSlug)</a>@else</span>@endif
        @if($service->ratingCount > 0)<p class="rate-l"><x-fc.rating :avg="$service->ratingAvg" :count="$service->ratingCount" /> <a class="small" href="#avis">Voir les avis</a></p>@endif
        @if($service->id !== '')<span class="sp-fav"><x-fc.fav-button-inline kind="service" :slug="$service->slug" :on="$service->favorited" /></span>@endif
      </div>

      <section class="buy-summary card" aria-label="Offre">
        <p class="muted small">{{ count($service->tiers) >= 2 ? 'À partir de' : 'Prix fixe pour le périmètre décrit' }}</p><x-fc.money :amount="$service->price" size="lg" />
        <ul class="facts-row">
          <li><x-fc.icon name="clock" /><span><b>{{ $days }}</b><small>Délai</small></span></li>
          <li><x-fc.icon name="pencil" /><span><b>{{ $rev }}</b><small>{{ $revLabel }}</small></span></li>
          <li><x-fc.icon name="package" /><span><b>{{ count($service->deliverables) }} {{ count($service->deliverables) > 1 ? 'éléments' : 'élément' }}</b><small>Livrables</small></span></li>
        </ul>
        @include('catalog._cta', ['service' => $service, 'id' => 'buy-cta'])
      </section>
      @if(count($service->tiers) >= 2 || count($service->options))@include('catalog._tiers', ['service' => $service])@endif

      <figure class="gallery" data-gallery>
        @if(count($service->images))
        <a class="stage" data-gallery-full href="{{ $service->images[0]['src'] }}" target="_blank" rel="noopener" aria-label="Agrandir l’image dans un nouvel onglet"><img src="{{ $service->images[0]['src'] }}" alt="{{ $service->images[0]['alt'] }}" width="640" height="427"><span class="gallery-enlarge">Agrandir l’image ↗</span></a>
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
        <section aria-labelledby="s2"><h2 class="t-h2" id="s2">Périmètre inclus</h2><p class="service-description">{{ $service->scope }}</p></section>
        <div class="sp-two">
          <section class="card sp-box" aria-labelledby="s3"><h2 id="s3"><x-fc.icon name="minus-circle" /> Ce qui n’est pas inclus</h2><ul class="checklist no">@foreach($service->exclusions as $d)<li><x-fc.icon name="minus-circle" /><span>{{ $d }}</span></li>@endforeach</ul></section>
          <section class="card sp-box" aria-labelledby="s4"><h2 id="s4"><x-fc.icon name="clipboard" /> Ce que vous devez fournir</h2><ul class="checklist need">@foreach($service->clientInputs as $d)<li><x-fc.icon name="clipboard" /><span>{{ $d }}</span></li>@endforeach</ul></section>
        </div>
        <section aria-labelledby="s5"><h2 class="t-h2" id="s5">À propos du freelance</h2>
          <div class="card vendor"><div class="top"><x-fc.avatar :name="$service->sellerName" :user="$service->sellerUserId" size="lg" />
            <div><p class="t-h3">{{ $service->sellerName }}</p><p class="muted">{{ $service->sellerHeadline }}@if($service->sellerCity) · {{ $service->sellerCity }}@endif</p></div></div>
            <p style="margin:8px 0 0;display:flex;gap:8px;flex-wrap:wrap"><x-fc.seller-pill :user="$service->sellerUserId" rate /></p>
            @if($service->sellerSlug)<p style="margin-top:8px"><a class="btn btn-secondary" href="{{ route('freelances.show', $service->sellerSlug) }}">Voir le profil complet</a></p>@endif
            </div></section>
        <section aria-labelledby="s6" id="avis"><h2 class="t-h2" id="s6">Avis</h2>
          @if(isset($reviews) && $reviews->total() > 0)
            <p class="muted"><x-fc.rating :avg="$service->ratingAvg" :count="$service->ratingCount" /> — avis de clients ayant validé une commande de CE service (les avis issus d’une mission ne sont pas comptés ici).</p>
            <x-fc.reviews :reviews="$reviews" />
          @else
            <p class="muted">Aucun avis pour l’instant. Les avis ne sont publiés qu’après une commande réelle validée et clôturée.</p>
          @endif</section>
      </div>
    </div>

    <aside class="offer-aside" aria-label="Offre">
      <div class="offer sp-offer">
        <div><p class="muted small">{{ count($service->tiers) >= 2 ? 'À partir de' : 'Prix fixe pour le périmètre décrit' }}</p><x-fc.money :amount="$service->price" size="lg" /></div>
        <ul class="sp-facts">
          <li><x-fc.icon name="clock" :size="22" /><span><b>{{ $days }}</b><small>Délai</small></span></li>
          <li><x-fc.icon name="pencil" :size="22" /><span><b>{{ $rev }}</b><small>{{ $revLabel }}</small></span></li>
          <li><x-fc.icon name="package" :size="22" /><span><b>{{ count($service->deliverables) }} {{ count($service->deliverables) > 1 ? 'éléments' : 'élément' }}</b><small>Livrables</small></span></li>
        </ul>
        @include('catalog._cta', ['service' => $service, 'id' => null, 'aside' => true])
      </div>
    </aside>
  </div>
  @if(! empty($similar))
  <section class="sp-similar" aria-labelledby="sim"><div class="section-head"><h2 class="t-h2" id="sim">Autres services : {{ $service->categoryName }}</h2><a class="btn btn-link" href="{{ route('services.index', ['categorie' => $service->categorySlug]) }}">Tous les services <x-fc.icon name="arrow-right" :size="18" /></a></div>
    <div class="svc-grid service-grid">@foreach($similar as $other)<x-fc.service-card :service="$other" :level="2" />@endforeach</div></section>
  @endif
</div>
<div class="sticky-buy" role="region" aria-label="Demander cette prestation"><div class="sum"><x-fc.money :amount="$service->price" /><small>{{ $days }}@if($service->revisionsIncluded) · {{ $service->revisionsIncluded }} {{ $service->revisionsIncluded > 1 ? 'corrections' : 'correction' }}@endif</small></div>@if($service->acceptsRequests && (! auth()->check() || auth()->id() !== $service->sellerUserId))@if(app(\App\Modules\Catalog\Actions\SellerSignals::class)->for($service->sellerUserId)['available'])<a class="btn btn-primary btn-lg" href="{{ count($service->tiers) >= 2 ? '#formules' : route('services.request', $service->slug) }}">{{ count($service->tiers) >= 2 ? 'Choisir' : 'Demander' }}</a>@else<span class="btn btn-lg is-off" aria-disabled="true">Indisponible</span>@endif @endif</div>

@auth<div class="container" style="margin:8px auto 96px"><p class="small muted"><a href="{{ route('support.report.form', ['service', $service->slug]) }}">Signaler ce service</a>@if($service->sellerSlug) · <a href="{{ route('support.report.form', ['profile', $service->sellerSlug]) }}">Signaler le profil du vendeur</a>@endif</p></div>@endauth
</x-layouts.public>

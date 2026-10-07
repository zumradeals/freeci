@php($hm = fn (string $k) => config("freeci.home.$k") ?: \App\Modules\Admin\Settings\AppSettings::default("home.$k"))
<x-layouts.public :title="'Trouver une prestation en Côte d’Ivoire'">
<section class="hero on-dark" aria-labelledby="h-hero">
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
      <div class="hero-alt"><span>{{ $hm('mission_prompt') }}</span><a class="btn btn-secondary btn-lg" href="{{ route('client.missions.new') }}">Publier une mission</a></div>
    </div>
    <aside class="hero-card" aria-labelledby="h-card">
      <p class="eyebrow muted">Le suivi d’une commande</p>
      <h2 class="t-h3" id="h-card" style="margin-top:8px">Chaque étape, au même endroit</h2>
      <ol class="empty-steps" style="margin-top:12px">
        <li><div><b>Accord</b><span>Le freelance accepte : prix, délai et corrections sont figés.</span></div></li>
        <li><div><b>Paiement et brief</b><span>Le travail démarre une fois le paiement confirmé et le brief complet.</span></div></li>
        <li><div><b>Livraison</b><span>Vous examinez, puis demandez une correction ou validez.</span></div></li>
        <li><div><b>Validation et avis</b><span>Rien n’est validé à votre place.</span></div></li>
      </ol>
    </aside>
  </div>
</section>

<section class="section" id="prestations" aria-labelledby="h-svc"><div class="container">
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

<section class="section section-alt" aria-labelledby="h-cats"><div class="container">
  <div class="section-head"><h2 class="t-h2" id="h-cats">Parcourir par besoin</h2><a class="btn btn-link" href="{{ route('services.index') }}">Tous les services <x-fc.icon name="arrow-right" :size="18" /></a></div>
  <ul class="cats">
    @foreach($categories as $c)
    <li><a class="cat" href="{{ route('services.index', ['categorie' => $c->slug]) }}"><span class="ico"><x-fc.icon :name="$c->icon" :size="22" /></span><span class="t">{{ $c->name }}@if($c->services > 0)<small class="cat-n">{{ $c->services }} {{ $c->services > 1 ? 'services' : 'service' }}</small>@endif</span></a></li>
    @endforeach
  </ul>
</div></section>

<section class="section section-compact" id="comment" aria-labelledby="h-ways"><div class="container">
  <div class="section-head"><div><h2 class="t-h2" id="h-ways">Comment ça marche</h2><p style="margin-top:6px">Le travail démarre après paiement confirmé et brief complet ; vous examinez chaque livraison puis validez : rien n’est validé à votre place.</p></div>
    <a class="btn btn-link" href="{{ route('info', 'fonctionnement') }}">Le détail <x-fc.icon name="arrow-right" :size="18" /></a></div>
  {{-- Sur grand écran, les étapes sont déjà dans l'encadré de l'en-tête : elles ne sont répétées que sur téléphone et tablette. --}}
  <ol class="steps-row only-narrow">
    <li><b>Choisir</b><span>Un service à prix et délai annoncés, ou une mission décrite par vos soins.</span></li>
    <li><b>Convenir</b><span>Le freelance accepte : l’accord est figé (prix, délai, corrections).</span></li>
    <li><b>Suivre</b><span>Paiement, brief, livraison et corrections dans un seul dossier.</span></li>
    <li><b>Valider</b><span>Vous validez la livraison, puis laissez un avis si la commande est réelle.</span></li>
  </ol>
</div></section>

<section class="freelance-band on-dark" aria-labelledby="h-fl"><div class="container">
  <div><h2 class="t-h2" id="h-fl">{{ $hm('freelance_title') }}</h2><p style="margin-top:6px">{{ $hm('freelance_text') }}</p></div>
  <a class="btn btn-primary btn-lg" href="{{ route('freelance.activate') }}">Créer mon profil</a>
</div></section>

</x-layouts.public>

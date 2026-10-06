<x-layouts.public :title="'Trouver une prestation en Côte d’Ivoire'">
<section class="hero on-dark" aria-labelledby="h-hero">
  <div class="hero-grid-bg" aria-hidden="true"></div>
  <div class="container">
    <div>
      <p class="eyebrow">Des compétences en Côte d’Ivoire</p>
      <h1 class="t-display" id="h-hero">Un freelance pour votre prochain projet<span class="dot" aria-hidden="true">.</span></h1>
      <p class="lede">Comparez des prestations à prix et délai annoncés, ou décrivez votre besoin et recevez des propositions.</p>
      <form class="search" role="search" method="get" action="{{ route('services.index') }}">
        <label for="q">Que recherchez-vous ?</label>
        <div class="search-box">
          <div class="search-field"><x-fc.icon name="search" :size="22" /><input class="input" id="q" name="q" type="search" placeholder="Un plan, un logo, un site web" autocomplete="off" maxlength="100"></div>
          <button class="btn btn-primary btn-lg" type="submit">Rechercher</button>
        </div>
      </form>
      <div class="chips" role="group" aria-label="Recherches fréquentes"><span class="lbl sr-only-m">Recherches fréquentes :</span>
        @foreach(['Plan AutoCAD', 'Logo', 'Site web', 'Traduction'] as $term)
        <a class="chip" href="{{ route('services.index', ['q' => $term]) }}">{{ $term }}</a>
        @endforeach</div>
      <a class="hero-link" href="{{ route('client.missions.new') }}">Un besoin précis ? Publier une mission <x-fc.icon name="arrow-right" :size="18" /></a>
    </div>
    <aside class="hero-card" aria-labelledby="h-card">
      <p class="eyebrow muted">Suivi d’une commande · exemple</p>
      <h2 class="t-h3" id="h-card" style="margin-top:8px">Chaque étape, au même endroit</h2>
      <p class="muted small" style="margin-top:6px">Accord, paiement, brief, livraison, corrections, validation : vous savez où en est votre commande et ce qui est attendu de vous.</p>
      <div class="mini-order">
  <div class="row" style="justify-content:space-between;align-items:flex-start;gap:8px 12px">
    <div style="min-width:0"><p style="font-weight:650;line-height:1.35">Convertir vos plans PDF en fichiers AutoCAD (DWG)</p><p class="muted small">DEMO-26018 · Kader Soro</p></div>
    <span class="badge tone-info"><x-fc.icon name="info" :size="16" />Livrée</span>
  </div>
  <div class="mini-steps" aria-hidden="true"><i class="done"></i><i class="done"></i><i class="done"></i><i class="done"></i><i class="cur"></i><i></i><i></i></div>
  <p class="small muted">Étape 5 sur 7 · Livraison</p>
  <div class="row" style="justify-content:space-between"><span class="action-tag"><x-fc.icon name="arrow-right" :size="16" />Examiner la livraison</span><span class="price price-md">35 000<small> FCFA</small></span></div>
  <p class="small muted">À décider avant le <strong style="color:var(--ink-900)">15 oct. 2026, 11:15</strong></p>
</div>
    </aside>
  </div>
</section>

<section class="section" id="prestations" aria-labelledby="h-svc"><div class="container">
  <div class="section-head"><div><h2 class="t-h2" id="h-svc">Services publiés récemment</h2>
    @if(collect($services)->contains(fn ($s) => $s->isDemo))<p style="margin-top:6px"><span class="tag-demo">Exemples de démonstration</span> <span class="muted">Certains services de cette liste sont des données d’exemple.</span></p>@endif</div>
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
    <li><a class="cat" href="{{ route('services.index', ['categorie' => $c->slug]) }}"><span class="ico"><x-fc.icon :name="$c->icon" :size="22" /></span><span class="t">{{ $c->name }}</span></a></li>
    @endforeach
  </ul>
</div></section>

<section class="section" id="comment" aria-labelledby="h-ways"><div class="container">
  <div class="section-head"><div><h2 class="t-h2" id="h-ways">Comment ça marche</h2><p style="margin-top:6px">Le travail démarre après paiement confirmé et brief complet ; vous examinez chaque livraison puis validez : rien n’est validé à votre place.</p></div>
    <a class="btn btn-link" href="{{ route('info', 'fonctionnement') }}">Le détail <x-fc.icon name="arrow-right" :size="18" /></a></div>
  <ol class="steps-row">
    <li><b>Choisir</b><span>Un service à prix et délai annoncés, ou une mission décrite par vos soins.</span></li>
    <li><b>Convenir</b><span>Le freelance accepte : l’accord est figé (prix, délai, corrections).</span></li>
    <li><b>Suivre</b><span>Paiement, brief, livraison et corrections dans un seul dossier.</span></li>
    <li><b>Valider</b><span>Vous validez la livraison, puis laissez un avis si la commande est réelle.</span></li>
  </ol>
  <div class="row" style="gap:12px;margin-top:20px"><a class="btn btn-primary btn-lg" href="{{ route('services.index') }}">Voir les services</a><a class="btn btn-secondary btn-lg" href="{{ route('client.missions.new') }}">Publier une mission</a></div>
</div></section>

<section class="freelance-band on-dark" aria-labelledby="h-fl"><div class="container">
  <div><h2 class="t-h2" id="h-fl">Vous êtes freelance ?</h2><p style="margin-top:6px">Présentez vos compétences et publiez vos prestations.</p></div>
  <a class="btn btn-primary btn-lg" href="{{ route('freelance.activate') }}">Créer mon profil</a>
</div></section>

</x-layouts.public>

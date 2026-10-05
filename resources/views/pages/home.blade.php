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
      <a class="hero-link" href="{{ route('coming-soon', 'publier-une-mission') }}">Un besoin précis ? Publier une mission (bientôt) <x-fc.icon name="arrow-right" :size="18" /></a>
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

<section class="section" aria-labelledby="h-cats"><div class="container">
  <div class="section-head"><h2 class="t-h2" id="h-cats">Quel est votre besoin ?</h2><a class="btn btn-link" href="{{ route('services.index') }}">Tous les services <x-fc.icon name="arrow-right" :size="18" /></a></div>
  <ul class="cats">
    @foreach($categories as $c)
    <li><a class="cat" href="{{ route('services.index', ['categorie' => $c->slug]) }}"><span class="ico"><x-fc.icon :name="$c->icon" :size="22" /></span><span class="t">{{ $c->name }}</span></a></li>
    @endforeach
  </ul>
</div></section>
<section class="section section-alt" id="prestations" aria-labelledby="h-svc"><div class="container">
  <div class="section-head"><div><h2 class="t-h2" id="h-svc">Services publiés récemment</h2>
    <p style="margin-top:6px"><span class="tag-demo">Exemples fictifs</span> <span class="muted">Prix et vendeurs inventés ; aucun avis ni note.</span></p></div>
    <a class="btn btn-link" href="{{ route('services.index') }}">Voir tout le catalogue <x-fc.icon name="arrow-right" :size="18" /></a></div>
  @if(count($services))
  <div class="svc-grid">
    @foreach($services as $service)<x-fc.service-card :service="$service" />@endforeach
  </div>
  @else
  <div class="card empty"><span class="ico-lg"><x-fc.icon name="inbox" :size="26" /></span><p style="font-weight:600">Les premiers services seront publiés ici.</p></div>
  @endif
</div></section>
<section class="section" id="comment" aria-labelledby="h-ways"><div class="container">
  <div class="section-head"><div><h2 class="t-h2" id="h-ways">Deux façons de démarrer</h2><p style="margin-top:6px">Dans les deux cas, vous retrouvez le même dossier de commande.</p></div></div>
  <div class="two-ways">
    <article class="card way"><h3 class="t-h3">Je choisis un service</h3><p class="muted">Vous savez ce que vous voulez : comparez prix, délai et corrections incluses.</p>
      <details class="fold way-fold" data-open-desktop><summary><span>Voir les 4 étapes </span><svg class="chev" width="20" height="20" aria-hidden="true" focusable="false"><use href="#i-chev-down"/></svg></summary><div class="fold-body"><ol><li><div><b>Je compare</b><span>Prix, livrables et corrections sont annoncés avant toute demande.</span></div></li><li><div><b>Je décris mon besoin</b><span>Le freelance accepte ou refuse.</span></div></li><li><div><b>Je paie</b><span>Après l’acceptation.</span></div></li><li><div><b>Je suis la livraison</b><span>Corrections prévues, puis validation.</span></div></li></ol></div></details>
      <a class="btn btn-primary" href="{{ route('services.index') }}">Voir les services</a></article>
    <article class="card way"><h3 class="t-h3">Je publie une mission</h3><p class="muted">Votre besoin demande une proposition sur mesure.</p>
      <details class="fold way-fold" data-open-desktop><summary><span>Voir les 4 étapes </span><svg class="chev" width="20" height="20" aria-hidden="true" focusable="false"><use href="#i-chev-down"/></svg></summary><div class="fold-body"><ol><li><div><b>Je décris mon besoin</b><span>Budget, échéances ; pièces privées.</span></div></li><li><div><b>Je compare les propositions</b><span>Prix ferme, délai, livrables.</span></div></li><li><div><b>Je choisis et je paie</b><span>Une seule proposition retenue.</span></div></li><li><div><b>Je suis la livraison</b><span>Même suivi que pour un service.</span></div></li></ol></div></details>
      <a class="btn btn-secondary" href="{{ route('coming-soon', 'publier-une-mission') }}">Publier une mission <x-fc.soon /></a></article>
  </div>
</div></section>

<section class="section section-alt" aria-labelledby="h-track"><div class="container"><div class="trackshow">
  <div class="stack"><h2 class="t-h2" id="h-track">Un suivi clair, de la demande à la validation</h2>
    <p class="muted" style="font-size:1.0625rem">Le travail démarre après paiement confirmé et brief complet. Vous examinez chaque livraison puis validez : rien n’est validé à votre place.</p>
    <div class="hero-card-m card"><p class="eyebrow muted">Suivi d’une commande · exemple illustratif</p><div class="mini-order">
  <div class="row" style="justify-content:space-between;align-items:flex-start;gap:8px 12px">
    <div style="min-width:0"><p style="font-weight:650;line-height:1.35">Convertir vos plans PDF en fichiers AutoCAD (DWG)</p><p class="muted small">DEMO-26018 · Kader Soro</p></div>
    <span class="badge tone-info"><x-fc.icon name="info" :size="16" />Livrée</span>
  </div>
  <div class="mini-steps" aria-hidden="true"><i class="done"></i><i class="done"></i><i class="done"></i><i class="done"></i><i class="cur"></i><i></i><i></i></div>
  <p class="small muted">Étape 5 sur 7 · Livraison</p>
  <div class="row" style="justify-content:space-between"><span class="action-tag"><x-fc.icon name="arrow-right" :size="16" />Examiner la livraison</span><span class="price price-md">35 000<small> FCFA</small></span></div>
  <p class="small muted">À décider avant le <strong style="color:var(--ink-900)">15 oct. 2026, 11:15</strong></p>
</div></div></div>
  <details class="fold track-fold" data-open-desktop><summary><span>Ce que vous retrouvez dans chaque commande </span><svg class="chev" width="20" height="20" aria-hidden="true" focusable="false"><use href="#i-chev-down"/></svg></summary><div class="fold-body"><ul class="checklist ok"><li><x-fc.icon name="check-circle" :size="20" /><span><b>Un accord figé :</b> prix, périmètre, délai et corrections.</span></li><li><x-fc.icon name="check-circle" :size="20" /><span><b>Des échéances visibles :</b> ce qui est attendu de vous.</span></li><li><x-fc.icon name="check-circle" :size="20" /><span><b>Un historique daté</b> de chaque étape.</span></li></ul></div></details>
</div></div></section>

<section class="freelance-band on-dark" aria-labelledby="h-fl"><div class="container">
  <div><h2 class="t-h2" id="h-fl">Vous êtes freelance ?</h2><p style="margin-top:6px">Présentez vos compétences et publiez vos prestations.</p></div>
  <a class="btn btn-primary btn-lg" href="{{ route('coming-soon', 'creer-un-profil') }}">Créer mon profil <x-fc.soon /></a>
</div></section>

</x-layouts.public>

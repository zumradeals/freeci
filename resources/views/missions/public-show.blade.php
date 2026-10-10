@php
  $msUrl = \App\Shared\Seo::url('missions.show', ['slug' => $m['slug']]);
  $msSeo = ['jsonld' => [\App\Shared\Seo::breadcrumbs([['name' => 'Accueil', 'url' => \App\Shared\Seo::base().'/'], ['name' => 'Missions', 'url' => \App\Shared\Seo::url('missions.index')], ['name' => $m['title'], 'url' => $msUrl]])]];
  $msDesc = 'Mission à '.str_replace(["\u{202F}", "\u{00A0}"], ' ', $m['budget']->formatted()).' FCFA, candidatures jusqu’au '.$m['deadline'].'. '.\App\Shared\Seo::trim($m['description'], 100);
@endphp
<x-layouts.public :title="$m['title']" :description="$msDesc" :seo="$msSeo" main-class="svc-page">
@php
  $left = $m['daysLeft'] ?? null;
  $leftLabel = $left === null ? null : ($left === 0 ? 'Dernier jour' : 'J-'.$left);
@endphp
<div class="container mission-detail sp-wrap">
  @isset($preview)<div class="notice tone-warning" role="note"><x-fc.icon name="flag" /><p><strong>Aperçu — non publié.</strong> Rendu public de votre version de travail ; personne d’autre ne le voit. <a href="{{ $preview }}">Revenir à l’édition</a></p></div>@endisset
  <nav class="crumbs sp-crumbs" aria-label="Fil d’Ariane"><a class="back-m" href="{{ route('missions.index') }}"><x-fc.icon name="arrow-right" :size="16" class="flip" />Missions</a><a class="hide-m" href="{{ url('/') }}">Accueil</a><span class="sep hide-m" aria-hidden="true">/</span><a class="hide-m" href="{{ route('missions.index') }}">Missions</a><span class="sep hide-m" aria-hidden="true">/</span>@if(! empty($m['categorySlug']))<a class="hide-m" href="{{ route('missions.index', ['categorie' => $m['categorySlug']]) }}">{{ $m['category'] }}</a>@else<span class="hide-m">{{ $m['category'] }}</span>@endif<span class="sep hide-m" aria-hidden="true">/</span><span class="hide-m" aria-current="page">{{ $m['title'] }}</span></nav>
  <div class="sp-grid mission-detail-grid">
  <div class="sp-main mission-detail-content">
    <p class="eyebrow sp-cat">{{ $m['category'] }}@if($m['isDemo'] ?? false) · <span class="tag-demo">Exemple fictif</span>@endif</p>
    <h1 class="t-h1">{{ $m['title'] }}</h1>
    <div class="sp-meta">@if($leftLabel)<span class="mc-left">{{ $leftLabel }}</span>@endif<p class="muted">Candidatures @if($m['status'] === 'preview' || $m['accepting'])ouvertes jusqu’au @else closes depuis le @endif<b class="sp-strong">{{ $m['deadline'] }}</b></p></div>

    <section class="sp-sec mission-detail-section" aria-labelledby="h-d"><h2 id="h-d">Le besoin</h2><div class="mission-description">{!! nl2br(e($m['description'])) !!}</div></section>
    @if(count($m['inputs']))<section class="card sp-box ms-inputs" aria-labelledby="h-i"><h2 id="h-i"><x-fc.icon name="clipboard" /> Ce que le client fournira</h2><ul class="checklist need">@foreach($m['inputs'] as $i)<li><x-fc.icon name="clipboard" /><span>{{ $i }}</span></li>@endforeach</ul>
      <p class="muted small">Seuls les intitulés sont publics ; les réponses ne sont communiquées qu’au freelance retenu.</p></section>@endif
    <section class="sp-sec" aria-labelledby="h-s"><h2 id="h-s">Comment ça se passe</h2>
      <ol class="ms-steps">
        <li><span class="ms-ico"><x-fc.icon name="clipboard" :size="24" /></span><div><b>Vous envoyez une proposition</b><p>À prix ferme, avec votre délai. Seul le client la voit.</p></div></li>
        <li><span class="ms-ico"><x-fc.icon name="check-circle" :size="24" /></span><div><b>Le client choisit</b><p>Il compare les propositions et retient la vôtre.</p></div></li>
        <li><span class="ms-ico"><x-fc.icon name="card" :size="24" /></span><div><b>Accord, paiement, livraison</b><p>Prix, délai et périmètre sont figés dès l’accord.</p></div></li>
        <li><span class="ms-ico"><x-fc.icon name="package" :size="24" /></span><div><b>Le client valide</b><p>Il examine le travail livré avant de décider.</p></div></li>
      </ol></section>
    <p class="note-line"><x-fc.icon name="shield" :size="16" /><span>Aucune pièce jointe ni coordonnée du client n’est publiée. Les échanges passent par FreeCI.</span></p>
  </div>
  <aside class="sp-aside mission-detail-aside" aria-label="Résumé et candidature">
  <section class="card sp-offer mission-overview"><p class="muted small">Budget du client</p><x-fc.money :amount="$m['budget']" size="lg" />
    <ul class="sp-facts">
      <li><x-fc.icon name="calendar" :size="22" /><span><b>{{ $m['deadlineDate'] ?? $m['deadline'] }}</b><small>Candidatures jusqu’au @if($leftLabel)({{ $leftLabel }})@endif</small></span></li>
      <li><x-fc.icon name="briefcase" :size="22" /><span><b>{{ $m['category'] }}</b><small>Catégorie</small></span></li>
    </ul>
    @if($m['status'] !== 'preview' && ! $m['accepting'])<p class="note-line"><x-fc.icon name="lock" :size="16" /><span><strong>Cette mission n’accepte plus de propositions.</strong></span></p>@endif
  @unless(isset($preview))
    <div class="mission-proposal-box" aria-labelledby="h-p"><h2 class="sr-only" id="h-p">Votre proposition</h2>
    @if($m['own'])<p>C’est votre mission. <a href="{{ route('client.missions') }}">Gérer mes missions</a></p>
    @elseif($m['mine'])
      <p><span class="badge tone-{{ $m['mine']['state'] === 'selected' ? 'success' : ($m['mine']['stale'] || $m['mine']['expired'] ? 'warning' : 'info') }}">{{ ['active' => 'Proposition envoyée', 'selected' => 'Retenue', 'withdrawn' => 'Retirée', 'released' => 'Libérée', 'closed' => 'Mission terminée'][$m['mine']['state']] }}</span> version {{ $m['mine']['number'] }} · <x-fc.money :amount="$m['mine']['price']" /> · valable jusqu’au {{ $m['mine']['validUntil'] }}</p>
      @if($m['mine']['stale'] && $m['mine']['state'] === 'active')<p class="note-line"><x-fc.icon name="warn" :size="16" /><span><strong>Besoin modifié depuis votre proposition.</strong> Reconfirmez-la pour qu’elle puisse être retenue.</span></p>@endif
      @if($m['accepting'] && $m['mine']['state'] !== 'selected')<div class="row"><a class="btn btn-primary btn-block" href="{{ route('missions.proposal', $m['slug']) }}">{{ $m['mine']['stale'] ? 'Reconfirmer ma proposition' : ($m['mine']['state'] === 'active' ? 'Réviser ma proposition' : 'Proposer à nouveau') }}</a>@if($m['mine']['state'] === 'active')<a class="btn btn-link" href="{{ route('freelance.proposals.withdraw', $m['mine']['id']) }}">Retirer ma proposition</a>@endif</div>@endif
    @elseif($m['accepting'])
      <p class="small">Vous avez les compétences pour ce besoin ? Envoyez une proposition à prix ferme ; seul le client la verra.</p>
      @guest<a class="btn btn-primary btn-lg btn-block" href="{{ route('login') }}">Se connecter pour proposer</a>
      @else<a class="btn btn-primary btn-lg btn-block" href="{{ route('missions.proposal', $m['slug']) }}">Faire une proposition</a>@endguest
    @else<p class="muted">Cette mission n’accepte plus de propositions.</p>@endif
    </div>
    <ul class="sp-assure">
      <li><x-fc.icon name="check-circle" :size="18" />Seul le client voit votre proposition</li>
      <li><x-fc.icon name="check-circle" :size="18" />Prix, délai et périmètre figés dès l’accord</li>
      <li><x-fc.icon name="check-circle" :size="18" />Échanges via FreeCI, sans coordonnées publiées</li>
      <li><x-fc.icon name="check-circle" :size="18" />Le client valide la livraison</li>
    </ul>
  @endunless
  </section>
  </aside>
  </div>
  @if(! empty($similar))
  <section class="sp-similar" aria-labelledby="sim"><div class="section-head"><h2 class="t-h2" id="sim">Autres missions : {{ $m['category'] }}</h2><a class="btn btn-link" href="{{ route('missions.index', ['categorie' => $m['categorySlug']]) }}">Toutes les missions <x-fc.icon name="arrow-right" :size="18" /></a></div>
    <div class="mission-grid mg-new">@foreach($similar as $o)<x-fc.mission-card :m="$o" />@endforeach</div></section>
  @endif
  @unless(isset($preview))
    @auth<p class="small muted" style="margin-top:24px"><a href="{{ route('support.report.form', ['mission', $m['slug']]) }}">Signaler cette mission</a></p>@endauth
  @endunless
</div>
@if(! isset($preview) && $m['accepting'] && ! $m['own'])
<div class="sticky-buy" role="region" aria-label="Proposer vos services"><div class="sum"><x-fc.money :amount="$m['budget']" /><small>{{ $leftLabel }} · jusqu’au {{ $m['deadlineDate'] ?? $m['deadline'] }}</small></div><a class="btn btn-primary btn-lg" href="{{ $m['mine'] ? route('missions.proposal', $m['slug']) : (auth()->check() ? route('missions.proposal', $m['slug']) : route('login')) }}">{{ $m['mine'] ? 'Ma proposition' : 'Proposer' }}</a></div>
@endif
</x-layouts.public>

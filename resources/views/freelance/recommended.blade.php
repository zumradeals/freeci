<x-layouts.account title="Missions pour vous" space="freelancer">
  <div class="page-body">
    <nav class="sd-crumbs" aria-label="Fil d’Ariane"><a href="{{ route('freelance.dashboard') }}">Espace freelance</a> › <span aria-current="page">Missions pour vous</span></nav>
    <div class="sx-head"><div><p class="sx-kicker">Espace freelance</p><h1>Missions pour vous</h1><p class="muted">Missions ouvertes qui correspondent à vos alertes (catégorie et budget).</p></div><div class="sx-acts"><a class="btn btn-secondary" href="{{ route('freelance.alerts') }}">Gérer mes alertes</a></div></div>
    <div class="ac-grid"><div class="ac-main">
      @if($r['items'])
        <div class="mr-list">@foreach($r['items'] as $m)
          <article class="mr-card"><header><div><b style="font-size:1.0625rem"><a href="{{ route('missions.show', $m['slug']) }}">{{ $m['title'] }}</a></b><div class="muted small">{{ $m['category'] }}</div></div>@if($m['why'])<span class="mr-why">{{ $m['why'] }}</span>@endif</header>
            <div class="mr-meta"><span><x-fc.icon name="card" :size="18" /> {{ $m['budget'] }}</span><span><x-fc.icon name="clock" :size="18" /> Candidature avant le {{ $m['deadline'] }}</span></div>
            <div class="row" style="gap:10px"><a class="btn btn-secondary" href="{{ route('missions.show', $m['slug']) }}">Voir la mission<span class="sr-only"> : {{ $m['title'] }}</span></a><a class="btn btn-primary" href="{{ route('missions.proposal', $m['slug']) }}">Proposer<span class="sr-only"> pour : {{ $m['title'] }}</span></a></div></article>
        @endforeach</div>
        <p class="muted small">Les missions où vous avez déjà une proposition, celles dont la date limite est passée et vos propres missions n’apparaissent pas ici.</p>
      @else
        <div class="card empty"><span class="ico-lg"><x-fc.icon name="briefcase" :size="26" /></span>
          <p style="font-weight:600">{{ match($r['mode']) { 'paused' => 'Toutes vos alertes sont en pause.', 'none' => 'Aucune alerte et aucun service publié.', default => 'Aucune mission ouverte ne correspond pour le moment.' } }}</p>
          <p class="muted" style="max-width:36em">{{ $r['mode'] === 'none' ? 'Créez une alerte (catégorie et budget minimum) pour voir ici les missions qui vous correspondent.' : ($r['mode'] === 'paused' ? 'Réactivez une alerte pour voir les missions correspondantes.' : 'Vous serez prévenu dès qu’une mission correspondante est publiée.') }}</p></div>
      @endif
    </div>
    <aside class="ac-side"><section class="ed-ck"><h3>Sans alerte ?</h3><p class="muted small" style="margin:0 0 8px">Tant que vous n’avez créé aucune alerte, la liste utilise les <b>catégories de vos services publiés</b>, sans filtre de budget. Créez une alerte pour affiner.</p><a class="btn btn-secondary" href="{{ route('freelance.alerts') }}">Créer une alerte</a></section></aside></div>
  </div>
</x-layouts.account>

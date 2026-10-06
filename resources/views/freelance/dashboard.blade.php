<x-layouts.account title="Espace freelance" space="freelancer">
  @php($isNew = ($o['counts']['Commandes au total'] ?? 0) === 0 && count($o['tasks']) === 0 && count($services) === 0)
  <header class="page-head">
    <div class="row-top"><div><p class="eyebrow">Espace freelance</p><h1 class="t-h1">{{ $profile['display_name'] ?? $user->name }}</h1><p class="lead">{{ $isNew ? 'Votre espace pour publier vos services et traiter les demandes.' : 'Les demandes à traiter et les commandes en cours, puis vos services.' }}</p></div>
</div>
  </header>
  @if($isNew)
    <section aria-labelledby="h-start"><div class="sect-head"><h2 class="t-h2" id="h-start">Pour recevoir vos premières demandes</h2></div>
      <div class="start-grid">
        <a class="start-card primary" href="{{ route('freelance.services.new') }}"><span class="ico"><x-fc.icon name="briefcase" :size="22" /></span><b>Créer un service</b><span>Un brouillon reste invisible jusqu’à son approbation.</span></a>
        <a class="start-card" href="{{ route('freelance.profile') }}"><span class="ico"><x-fc.icon name="user" :size="22" /></span><b>Compléter mon profil</b><span>Présentation, compétences, ville : ce que voient les clients.</span></a>
        <a class="start-card" href="{{ route('missions.index') }}"><span class="ico"><x-fc.icon name="search" :size="22" /></span><b>Voir les missions ouvertes</b><span>Répondez par une proposition chiffrée.</span></a>
      </div>
    </section>
  @else
  <div class="page-body">
    <section class="card panel" aria-labelledby="h-stats"><div class="card-head"><h2 class="t-h2" id="h-stats">En un coup d’œil</h2><span class="meta-r">{{ $o['counts']['Commandes au total'] }} commande{{ $o['counts']['Commandes au total'] > 1 ? 's' : '' }} au total</span></div>
      <div class="metrics">@foreach(array_slice($o['counts'], 0, 4, true) as $label => $n)<a class="metric {{ $label === 'Demandes à accepter' && $n > 0 ? 'metric-featured' : '' }}" href="{{ route('freelance.orders') }}"><span class="metric-label">{{ $label }}</span><span class="metric-value">{{ $n }}</span></a>@endforeach</div></section>
    <div class="split">
      <div class="stack-col">
        <section class="card panel" aria-labelledby="h-todo"><div class="card-head"><h2 class="t-h2" id="h-todo">À faire maintenant</h2>@if(count($o['tasks']) > 1)<span class="meta-r">Échéances d’abord</span>@endif</div>
          @if(count($o['tasks']))
            <div class="tasks">@foreach($o['tasks'] as $t)<x-orders.task :t="$t" />@endforeach</div>
          @else
            <p class="muted empty-note"><x-fc.icon name="check-circle" :size="20" /> Aucune demande à traiter. Les demandes reçues sur vos services apparaîtront ici, avec le délai pour répondre.</p>
          @endif
        </section>
        @if(count($o['waiting']))
        <section class="card panel" aria-labelledby="h-wait"><div class="card-head"><h2 class="t-h2" id="h-wait">En attente du client</h2><a class="btn btn-link" href="{{ route('freelance.orders') }}">Toutes les commandes <x-fc.icon name="arrow-right" :size="18" /></a></div>
          <div class="order-list">@foreach($o['waiting'] as $c)<x-orders.card :c="$c" />@endforeach</div>
        </section>
        @endif
      </div>
      <aside class="card panel" aria-labelledby="h-svc"><div class="card-head"><h2 class="t-h2" id="h-svc">Vos services</h2><a class="btn btn-link" href="{{ route('freelance.services') }}">Gérer</a></div>
        @if(count($services))
          <ul class="stack-sm">@foreach($services as $s)<li class="row" style="justify-content:space-between;gap:8px 16px"><span style="min-width:0">@if($s['published'])<a href="{{ route('services.show', $s['slug']) }}">{{ $s['title'] }}</a>@else{{ $s['title'] ?: 'Sans titre' }}@endif<br><small class="muted">{{ $s['status'] }}</small></span>@if($s['price'])<x-fc.money :amount="$s['price']" />@endif</li>@endforeach</ul>
        @else
          <p class="muted">Aucun service à votre nom.</p><a class="btn btn-secondary" href="{{ route('freelance.services.new') }}">Créer un service</a>
        @endif
        <ul class="link-list"><li><a href="{{ route('freelance.earnings') }}"><x-fc.icon name="card" :size="20" />Mes revenus</a></li><li><a href="{{ route('missions.index') }}"><x-fc.icon name="search" :size="20" />Missions ouvertes</a></li><li><a href="{{ route('freelance.profile') }}"><x-fc.icon name="user" :size="20" />Mon profil</a></li></ul></aside>
    </div>
  </div>
  @endif
</x-layouts.account>

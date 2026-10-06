<x-layouts.account title="Espace freelance" space="freelancer">
  @php($isNew = ($o['counts']['Commandes au total'] ?? 0) === 0 && count($o['tasks']) === 0 && count($services) === 0)
  <header class="page-head">
    <div class="row-top"><div><p class="eyebrow">Espace freelance</p><h1 class="t-h1">{{ $profile['display_name'] ?? $user->name }}</h1></div>
      @unless($isNew)<a class="btn btn-primary btn-lg" href="{{ route('freelance.services.new') }}">Créer un service</a>@endunless</div>
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
  <div class="cols">
    <div class="stack-lg">
      <section aria-labelledby="h-todo"><div class="sect-head"><h2 class="t-h2" id="h-todo">À faire maintenant</h2>@if(count($o['tasks']) > 1)<span class="small muted">Échéances d’abord</span>@endif</div>
        @if(count($o['tasks']))
          <div class="tasks">@foreach($o['tasks'] as $t)<x-orders.task :t="$t" />@endforeach</div>
        @else
          <div class="card"><p><x-fc.icon name="check-circle" :size="20" /> <strong>Aucune demande à traiter.</strong> <span class="muted">Les demandes reçues sur vos services apparaîtront ici, avec le délai pour répondre.</span></p></div>
        @endif
      </section>
      @if(count($o['waiting']))
      <section aria-labelledby="h-wait"><div class="sect-head"><h2 class="t-h2" id="h-wait">En attente du client</h2><a class="btn btn-link" href="{{ route('freelance.orders') }}">Toutes les commandes <x-fc.icon name="arrow-right" :size="18" /></a></div>
        <div class="order-list">@foreach($o['waiting'] as $c)<x-orders.card :c="$c" />@endforeach</div>
      </section>
      @endif
    </div>
    <div class="stack-lg">
      <section aria-labelledby="h-stats"><div class="sect-head"><h2 class="t-h2" id="h-stats">En un coup d’œil</h2></div>
        <div class="stats-row">@foreach(array_slice($o['counts'], 0, 4, true) as $label => $n)<a href="{{ route('freelance.orders') }}"><b>{{ $n }}</b><span>{{ $label }}</span></a>@endforeach</div></section>
      <section aria-labelledby="h-svc"><div class="sect-head"><h2 class="t-h2" id="h-svc">Vos services</h2><a class="btn btn-link" href="{{ route('freelance.services') }}">Gérer</a></div>
        @if(count($services))
          <div class="card"><ul class="stack-sm">@foreach($services as $s)<li class="row" style="justify-content:space-between;gap:8px 16px"><span style="min-width:0">@if($s['published'])<a href="{{ route('services.show', $s['slug']) }}">{{ $s['title'] }}</a>@else{{ $s['title'] ?: 'Sans titre' }}@endif<br><small class="muted">{{ $s['status'] }}</small></span>@if($s['price'])<x-fc.money :amount="$s['price']" />@endif</li>@endforeach</ul></div>
        @else
          <div class="card"><p class="muted">Aucun service à votre nom.</p><a class="btn btn-secondary" href="{{ route('freelance.services.new') }}">Créer un service</a></div>
        @endif
      </section>
    </div>
  </div>
  @endif
</x-layouts.account>

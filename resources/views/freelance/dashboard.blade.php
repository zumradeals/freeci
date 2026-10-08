<x-layouts.account title="Espace freelance" space="freelancer">
  @php($isNew = ($o['counts']['Commandes au total'] ?? 0) === 0 && count($o['tasks']) === 0 && count($services) === 0)
  <header class="sx-head">
    <div><p class="sx-kicker">Espace freelance</p><h1>{{ $profile['display_name'] ?? $user->name }}</h1><p class="muted">{{ $isNew ? 'Votre espace pour publier vos services et traiter les demandes.' : 'Les demandes à traiter et les commandes en cours, puis vos services.' }}</p></div>
    <div class="sx-acts"><a class="btn btn-primary" href="{{ route('freelance.services.new') }}"><x-fc.icon name="pencil" /> Nouveau service</a><a class="btn btn-secondary" href="{{ route('missions.index') }}"><x-fc.icon name="search" /> Missions ouvertes</a></div>
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
    @php($c = $o['counts'])
    <section class="sx-metrics" aria-label="En un coup d’œil">
      <x-fc.metric-card label="Demandes à accepter" :n="$c['Demandes à accepter']" ico="inbox" :hot="$c['Demandes à accepter'] > 0" :href="route('freelance.orders')" />
      <x-fc.metric-card label="En cours" :n="$c['En cours']" ico="clock" :href="route('freelance.orders')" />
      <x-fc.metric-card label="Livrées" :n="$c['Livrées']" ico="package" :href="route('freelance.orders')" />
      <x-fc.metric-card label="Services publiés" :n="collect($services)->where('published', true)->count()" ico="briefcase" :href="route('freelance.services')" />
    </section>
    <div class="sx-split">
      <div class="sx-col">
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
      <aside class="sx-col">
        <section class="card panel" aria-labelledby="h-svc"><div class="card-head"><h2 class="t-h2" id="h-svc">Vos services</h2><a class="btn btn-link" href="{{ route('freelance.services') }}">Gérer</a></div>
          @if(count($services))
            <ul class="stack-sm">@foreach($services as $s)<li class="row" style="justify-content:space-between;gap:8px 16px"><span style="min-width:0">@if($s['published'])<a href="{{ route('services.show', $s['slug']) }}">{{ $s['title'] }}</a>@else{{ $s['title'] ?: 'Sans titre' }}@endif<br><small class="muted">{{ $s['status'] }}</small></span>@if($s['price'])<x-fc.money :amount="$s['price']" />@endif</li>@endforeach</ul>
          @else
            <p class="muted">Aucun service à votre nom.</p><a class="btn btn-secondary" href="{{ route('freelance.services.new') }}">Créer un service</a>
          @endif
        </section>
        <section class="card panel" aria-labelledby="h-rev"><h2 class="t-h2" id="h-rev">Revenus</h2>
          @php($m = fn (int $n) => \App\Shared\Money::xof($n)->formatted().' FCFA')
          <ul class="sx-rev"><li><span>À venir</span><b>{{ $m($revenue['upcoming']) }}</b></li><li><span>Disponible</span><b>{{ $m($revenue['available']) }}</b></li><li><span>Versé</span><b>{{ $m($revenue['paid']) }}</b></li></ul>
          <a class="btn btn-secondary" href="{{ route('freelance.earnings') }}">Voir mes revenus</a></section>
        <section class="card panel" aria-labelledby="h-prof"><h2 class="t-h2" id="h-prof">Votre profil public</h2>
          <p><span class="badge tone-{{ ($profile['published'] ?? false) ? 'success' : 'warning' }}">{{ ($profile['published'] ?? false) ? 'Publié' : 'Non publié' }}</span></p>
          <p class="muted">Les clients voient votre présentation, vos compétences et vos services publiés.</p>
          <div class="sx-acts">@if(! empty($profile['slug']) && ($profile['published'] ?? false))<a class="btn btn-secondary" href="{{ route('freelances.show', $profile['slug']) }}">Voir mon profil</a>@endif<a class="btn btn-link" href="{{ route('freelance.profile') }}">Modifier</a></div>
        </section>
      </aside>
    </div>
  </div>
  @endif
</x-layouts.account>

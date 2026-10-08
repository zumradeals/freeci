<x-layouts.account title="Espace client" space="client">
  @php($isNew = ($o['counts']['Commandes au total'] ?? 0) === 0 && count($o['tasks']) === 0)
  <header class="sx-head">
    <div><p class="sx-kicker">Espace client</p><h1>{{ $isNew ? 'Bienvenue' : 'Bonjour' }}, {{ $user->firstName() }}</h1><p class="muted">{{ $isNew ? 'Votre espace pour choisir un service, suivre vos commandes et vos paiements.' : 'Ce qui demande votre attention, puis vos commandes récentes.' }}</p></div>
    <div class="sx-acts"><a class="btn btn-primary" href="{{ route('services.index') }}"><x-fc.icon name="search" /> Choisir un service</a><a class="btn btn-secondary" href="{{ route('client.missions.new') }}"><x-fc.icon name="pencil" /> Publier une mission</a></div>
  </header>
  @if($isNew)
    <section aria-labelledby="h-start"><div class="sect-head"><h2 class="t-h2" id="h-start">Par où commencer ?</h2></div>
      <div class="start-grid">
        <a class="start-card primary" href="{{ route('services.index') }}"><span class="ico"><x-fc.icon name="search" :size="22" /></span><b>Choisir un service</b><span>Prix, délai et corrections annoncés : demandez ce qui vous convient.</span></a>
        <a class="start-card" href="{{ route('client.missions.new') }}"><span class="ico"><x-fc.icon name="pencil" :size="22" /></span><b>Publier une mission</b><span>Décrivez votre besoin et comparez les propositions reçues.</span></a>
        @if($user->hasRole('freelance'))
          <a class="start-card" href="{{ route('freelance.dashboard') }}"><span class="ico"><x-fc.icon name="briefcase" :size="22" /></span><b>Aller à l’espace freelance</b><span>Gérez vos services, demandes et revenus.</span></a>
        @else
          <a class="start-card" href="{{ route('freelance.activate') }}"><span class="ico"><x-fc.icon name="briefcase" :size="22" /></span><b>Proposer vos services</b><span>Activez l’espace freelance pour publier vos prestations.</span></a>
        @endif
      </div>
      <p class="muted" style="margin-top:14px">Vos commandes, échéances et actions attendues apparaîtront ici dès la première demande. <a href="{{ route('info', 'fonctionnement') }}">Comment ça marche</a></p>
    </section>
  @else
  <div class="page-body">
    @php($c = $o['counts'])
    <section class="sx-metrics" aria-label="En un coup d’œil">
      <x-fc.metric-card label="À examiner" :n="$c['À examiner']" ico="package" :hot="$c['À examiner'] > 0" :href="route('orders.index')" />
      <x-fc.metric-card label="En cours" :n="$c['En cours']" ico="clock" :href="route('orders.index')" />
      <x-fc.metric-card label="En attente de paiement" :n="$c['En attente de paiement']" ico="card" :href="route('orders.index')" />
      <x-fc.metric-card label="Commandes au total" :n="$c['Commandes au total']" ico="clipboard" :href="route('orders.index')" />
    </section>
    <div class="sx-split">
      <div class="sx-col">
        <section class="card panel" aria-labelledby="h-todo"><div class="card-head"><h2 class="t-h2" id="h-todo">À faire maintenant</h2>@if(count($o['tasks']) > 1)<span class="meta-r">Échéances d’abord</span>@endif</div>
          @if(count($o['tasks']))
            <div class="tasks">@foreach($o['tasks'] as $t)<x-orders.task :t="$t" />@endforeach</div>
          @else
            <p class="muted empty-note"><x-fc.icon name="check-circle" :size="20" /> Rien à faire pour l’instant. Une action attendue de votre part apparaîtra ici avec son échéance.</p>
          @endif
        </section>
        <section class="card panel" aria-labelledby="h-orders"><div class="card-head"><h2 class="t-h2" id="h-orders">Commandes récentes</h2><a class="btn btn-link" href="{{ route('orders.index') }}">Toutes les commandes <x-fc.icon name="arrow-right" :size="18" /></a></div>
          <div class="order-list">@foreach(array_slice($o['orders'], 0, 6) as $c)<x-orders.card :c="$c" />@endforeach</div>
        </section>
      </div>
      <aside class="sx-col"><section class="card panel" aria-labelledby="h-short"><h2 class="t-h2" id="h-short">Raccourcis</h2>
        <ul class="link-list">
          <li><a href="{{ route('services.index') }}"><x-fc.icon name="search" :size="20" />Parcourir les services</a></li>
          <li><a href="{{ route('client.missions.new') }}"><x-fc.icon name="pencil" :size="20" />Publier une mission</a></li>
          <li><a href="{{ route('client.missions') }}"><x-fc.icon name="briefcase" :size="20" />Mes missions</a></li>
          <li><a href="{{ route('favorites.index') }}"><x-fc.icon name="heart" :size="20" />Mes favoris</a></li>
          <li><a href="{{ route('client.finances') }}"><x-fc.icon name="card" :size="20" />Mes paiements</a></li></ul></section>
        <section class="card panel sx-help" aria-labelledby="h-help"><span class="sx-mi"><x-fc.icon name="message" :size="22" /></span><h2 class="t-h2" id="h-help">Besoin d’aide ?</h2><p class="muted">Une question sur une commande ? L’assistance suit votre demande avec une référence.</p><a class="btn btn-secondary" href="{{ route('support.index') }}">Ouvrir l’assistance</a></section></aside>
    </div>
  </div>
  @endif
</x-layouts.account>

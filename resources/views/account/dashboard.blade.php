<x-layouts.account title="Espace client" space="client">
  @php($isNew = ($o['counts']['Commandes au total'] ?? 0) === 0 && count($o['tasks']) === 0)
  <header class="page-head">
    <div class="row-top"><div><p class="eyebrow">Espace client</p><h1 class="t-h1">{{ $isNew ? 'Bienvenue' : 'Bonjour' }}, {{ $user->firstName() }}</h1>
      @if($user->is_demo)<p class="demo-time"><x-fc.icon name="flag" :size="18" />Compte de démonstration</p>@endif</div>
      @unless($isNew)<a class="btn btn-primary btn-lg" href="{{ route('services.index') }}">Parcourir les services</a>@endunless</div>
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
  <div class="cols">
    <div class="stack-lg">
      <section aria-labelledby="h-todo"><div class="sect-head"><h2 class="t-h2" id="h-todo">À faire maintenant</h2>@if(count($o['tasks']) > 1)<span class="small muted">Échéances d’abord</span>@endif</div>
        @if(count($o['tasks']))
          <div class="tasks">@foreach($o['tasks'] as $t)<x-orders.task :t="$t" />@endforeach</div>
        @else
          <div class="card"><p><x-fc.icon name="check-circle" :size="20" /> <strong>Rien à faire pour l’instant.</strong> <span class="muted">Une action attendue de votre part apparaîtra ici avec son échéance.</span></p></div>
        @endif
      </section>
      <section aria-labelledby="h-orders"><div class="sect-head"><h2 class="t-h2" id="h-orders">Commandes récentes</h2><a class="btn btn-link" href="{{ route('orders.index') }}">Toutes les commandes <x-fc.icon name="arrow-right" :size="18" /></a></div>
        <div class="order-list">@foreach(array_slice($o['orders'], 0, 6) as $c)<x-orders.card :c="$c" />@endforeach</div>
      </section>
    </div>
    <div class="stack-lg">
      <section aria-labelledby="h-stats"><div class="sect-head"><h2 class="t-h2" id="h-stats">En un coup d’œil</h2></div>
        <div class="stats-row">@foreach(array_slice($o['counts'], 0, 4, true) as $label => $n)<a href="{{ route('orders.index') }}"><b>{{ $n }}</b><span>{{ $label }}</span></a>@endforeach</div></section>
      <section aria-labelledby="h-short"><div class="sect-head"><h2 class="t-h2" id="h-short">Raccourcis</h2></div>
        <div class="card"><ul class="link-list">
          <li><a href="{{ route('client.missions.new') }}"><x-fc.icon name="pencil" :size="20" />Publier une mission</a></li>
          <li><a href="{{ route('client.missions') }}"><x-fc.icon name="briefcase" :size="20" />Mes missions</a></li>
          <li><a href="{{ route('favorites.index') }}"><x-fc.icon name="heart" :size="20" />Mes favoris</a></li>
          <li><a href="{{ route('client.finances') }}"><x-fc.icon name="card" :size="20" />Mes paiements</a></li></ul></div></section>
    </div>
  </div>
  @endif
</x-layouts.account>

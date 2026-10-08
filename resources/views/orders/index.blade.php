<x-layouts.account title="Commandes" :space="$space">
  <div class="page-body">
    <header class="page-head"><div class="row-top"><div><p class="eyebrow">{{ $space === 'freelancer' ? 'Espace freelance' : 'Espace client' }}</p><h1 class="t-h1">{{ $space === 'freelancer' ? 'Demandes et commandes' : 'Commandes' }}</h1><p class="lead">{{ $space === 'freelancer' ? 'Les demandes reçues et vos commandes, avec ce qui est attendu de vous.' : 'Vos demandes et commandes, de la demande à la validation.' }}</p></div></div></header>
    @php
      $all = collect($orders);
      $tabs = ['toutes' => ['Toutes', $all->count()], 'a-traiter' => ['À traiter', $all->where('group', 'todo')->count()], 'en-cours' => ['En cours', $all->where('group', 'active')->count()], 'terminees' => ['Terminées', $all->where('group', 'done')->count()]];
      $cur = array_key_exists(request('statut'), $tabs) ? request('statut') : 'toutes';
      $shown = match ($cur) { 'a-traiter' => $all->where('group', 'todo'), 'en-cours' => $all->where('group', 'active'), 'terminees' => $all->where('group', 'done'), default => $all };
      $route = $space === 'freelancer' ? 'freelance.orders' : 'orders.index';
    @endphp
    @if(count($orders))
      <nav class="od-tabs" aria-label="Filtrer les commandes">@foreach($tabs as $k => [$label, $n])<a class="od-tab {{ $cur === $k ? 'on' : '' }}" href="{{ route($route, $k === 'toutes' ? [] : ['statut' => $k]) }}" @if($cur === $k) aria-current="page" @endif>{{ $label }} <span class="n">{{ $n }}</span></a>@endforeach</nav>
      <section class="card panel" aria-labelledby="h-list"><h2 class="sr-only" id="h-list">{{ count($shown) }} {{ $space === 'freelancer' ? ($shown->count() > 1 ? 'demandes et commandes' : 'demande ou commande') : ($shown->count() > 1 ? 'commandes' : 'commande') }}</h2>
        @if($shown->count())
        <div class="od-table"><div class="od-row head" aria-hidden="true"><span>Commande</span><span>{{ $space === 'freelancer' ? 'Client' : 'Freelance' }}</span><span>Statut</span><span>Montant</span><span>Échéance</span><span></span></div>@foreach($shown as $c)<x-orders.row :c="$c" />@endforeach</div>
        @else<p class="muted empty-note">Aucune commande dans cette catégorie.</p>@endif
      </section>
    @else
      <div class="card empty"><span class="ico-lg"><x-fc.icon name="inbox" :size="26" /></span><p style="font-weight:600">{{ $space === 'freelancer' ? 'Aucune demande reçue pour l’instant.' : 'Aucune commande pour l’instant.' }}</p>
        <p class="muted" style="max-width:36em">{{ $space === 'freelancer' ? 'Les demandes des clients sur vos services apparaîtront ici.' : 'Choisissez un service et envoyez votre demande.' }}</p>
        @if($space !== 'freelancer')<a class="btn btn-primary" href="{{ route('services.index') }}">Parcourir les services</a>@endif</div>
    @endif
  </div>
</x-layouts.account>

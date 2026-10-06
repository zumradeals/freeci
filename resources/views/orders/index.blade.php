<x-layouts.account title="Commandes" :space="$space">
  <header class="page-head"><div class="row-top"><div><p class="eyebrow">{{ $space === 'freelancer' ? 'Espace freelance' : 'Espace client' }}</p><h1 class="t-h1">{{ $space === 'freelancer' ? 'Demandes et commandes' : 'Commandes' }}</h1><p class="lead">{{ $space === 'freelancer' ? 'Les demandes reçues et vos commandes, avec ce qui est attendu de vous.' : 'Vos demandes et commandes, de la demande à la validation.' }}</p></div></div></header>
  @if(count($orders))
    <section class="card panel" aria-labelledby="h-list"><div class="card-head"><h2 class="t-h2" id="h-list">{{ count($orders) }} {{ $space === 'freelancer' ? (count($orders) > 1 ? 'demandes et commandes' : 'demande ou commande') : (count($orders) > 1 ? 'commandes' : 'commande') }}</h2></div><div class="order-list">@foreach($orders as $c)<x-orders.card :c="$c" />@endforeach</div></section>
  @else
    <div class="card empty"><span class="ico-lg"><x-fc.icon name="inbox" :size="26" /></span><p style="font-weight:600">{{ $space === 'freelancer' ? 'Aucune demande reçue pour l’instant.' : 'Aucune commande pour l’instant.' }}</p>
      <p class="muted" style="max-width:36em">{{ $space === 'freelancer' ? 'Les demandes des clients sur vos services apparaîtront ici.' : 'Choisissez un service et envoyez votre demande.' }}</p>
      @if($space !== 'freelancer')<a class="btn btn-primary" href="{{ route('services.index') }}">Parcourir les services</a>@endif</div>
  @endif
</x-layouts.account>

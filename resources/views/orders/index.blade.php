<x-layouts.account title="Commandes" :space="$space">
  <header class="page-head"><div class="row-top"><div><p class="eyebrow">{{ $space === 'freelancer' ? 'Espace freelance' : 'Espace client' }}</p><h1 class="t-h1">{{ $space === 'freelancer' ? 'Demandes et commandes' : 'Commandes' }}</h1></div></div></header>
  @if(count($orders))
    <div class="order-list">@foreach($orders as $c)<x-orders.card :c="$c" />@endforeach</div>
  @else
    <div class="card empty"><span class="ico-lg"><x-fc.icon name="inbox" :size="26" /></span><p style="font-weight:600">{{ $space === 'freelancer' ? 'Aucune demande reçue pour l’instant.' : 'Aucune commande pour l’instant.' }}</p>
      <p class="muted" style="max-width:36em">{{ $space === 'freelancer' ? 'Les demandes des clients sur vos services apparaîtront ici.' : 'Choisissez un service et envoyez votre demande.' }}</p>
      @if($space !== 'freelancer')<a class="btn btn-primary" href="{{ route('services.index') }}">Parcourir les services</a>@endif</div>
  @endif
</x-layouts.account>

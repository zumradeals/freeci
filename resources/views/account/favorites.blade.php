<x-layouts.account title="Mes favoris" :space="$space">
  <header class="page-head"><div class="row-top"><div><p class="eyebrow">Espace {{ $space === 'freelancer' ? 'freelance' : 'client' }}</p><h1 class="t-h1">Mes favoris</h1></div></div></header>
  <div class="notice tone-info"><x-fc.icon name="lock" /><p><strong>Vos favoris sont privés</strong> : personne d’autre ne les voit et ils n’influencent aucun classement.</p></div>
  @forelse($items as $i)
    <section class="card" style="margin-top:12px">
      @if($i['available'] && $i['kind'] === 'service')
        <p class="muted small">Service</p><h2 class="t-h3"><a href="{{ route('services.show', $i['card']->slug) }}">{{ $i['card']->title }}</a></h2>
        <p class="muted">{{ $i['card']->sellerName }} · <x-fc.money :amount="$i['card']->price" /> · {{ $i['card']->deliveryDays }} {{ $i['card']->deliveryDays > 1 ? 'jours' : 'jour' }}</p>
      @elseif($i['available'])
        <p class="muted small">Freelance</p><h2 class="t-h3"><a href="{{ route('freelances.show', $i['profile']['slug']) }}">{{ $i['profile']['name'] }}</a></h2><p class="muted">{{ $i['profile']['headline'] }}@if($i['profile']['city']) · {{ $i['profile']['city'] }}@endif</p>
      @else
        <p class="muted small">{{ $i['kind'] === 'service' ? 'Service' : 'Freelance' }}</p><h2 class="t-h3">Contenu indisponible</h2><p class="muted">Ce contenu n’est plus publié ou a été retiré : il n’est pas accessible depuis vos favoris.</p>
      @endif
      <form method="post" action="{{ route('favorites.remove', $i['id']) }}" style="margin-top:8px">@csrf<button class="btn btn-secondary" type="submit">Retirer des favoris</button></form>
    </section>
  @empty
    <div class="card empty" style="margin-top:16px"><span class="ico-lg"><x-fc.icon name="heart" :size="26" /></span><p style="font-weight:600">Aucun favori pour l’instant.</p><p class="muted">Utilisez le cœur sur un service ou un profil pour le retrouver ici.</p><a class="btn btn-primary" href="{{ route('services.index') }}">Parcourir les services</a></div>
  @endforelse
</x-layouts.account>

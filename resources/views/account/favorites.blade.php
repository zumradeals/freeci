<x-layouts.account title="Mes favoris" :space="$space">
  <div class="page-body">
    <div class="sx-head"><div><p class="sx-kicker">Espace {{ $space === 'freelancer' ? 'freelance' : 'client' }}</p><h1>Mes favoris</h1><p class="muted">Les services et freelances que vous avez enregistrés, visibles de vous seul.</p></div></div>
    <div class="sv-info"><x-fc.icon name="lock" :size="18" /><span><b>Vos favoris sont privés</b> : personne d’autre ne les voit et ils n’influencent aucun classement.</span></div>
    @php($tabs = ['tous' => 'Tous', 'service' => 'Services', 'freelance' => 'Freelances'])
    <nav class="sv-tabs" aria-label="Filtrer les favoris" style="margin-top:16px">@foreach($tabs as $k => $label)<a class="sv-tab {{ $kind === $k ? 'on' : '' }}" href="{{ route('favorites.index', array_filter(['type' => $k === 'tous' ? null : $k, 'espace' => $space === 'freelancer' ? 'freelance' : null])) }}" @if($kind === $k) aria-current="page" @endif>{{ $label }} <span class="n">{{ $counts[$k] }}</span></a>@endforeach</nav>
    @forelse($items as $i)
      @if($loop->first)<div class="ac-fav">@endif
      <article class="ac-fc {{ $i['available'] ? '' : 'off' }}">
        <div class="ac-th">
          @if($i['available'] && $i['kind'] === 'service' && $i['card']->imageSrc)<img src="{{ $i['card']->imageSrc }}" alt="{{ $i['card']->imageAlt }}" width="640" height="480" loading="lazy">
          @elseif($i['available'] && $i['kind'] === 'freelance')<x-fc.avatar :name="$i['profile']['name']" :user="$i['profile']['userId']" size="xl" />
          @else<x-fc.icon :name="$i['available'] ? 'package' : 'lock'" :size="32" />@endif
        </div>
        <div class="ac-fb">
          <span class="muted small">{{ $i['kind'] === 'service' ? 'Service' : 'Freelance' }}</span>
          @if($i['available'] && $i['kind'] === 'service')
            <h2><a href="{{ route('services.show', $i['card']->slug) }}">{{ $i['card']->title }}</a></h2>
            <p class="muted small">{{ $i['card']->sellerName }} · {{ $i['card']->deliveryDays }} {{ $i['card']->deliveryDays > 1 ? 'jours' : 'jour' }}</p><p class="p"><x-fc.money :amount="$i['card']->price" /></p>
          @elseif($i['available'])
            <h2><a href="{{ route('freelances.show', $i['profile']['slug']) }}">{{ $i['profile']['name'] }}</a></h2><p class="muted small">{{ $i['profile']['headline'] }}@if($i['profile']['city']) · {{ $i['profile']['city'] }}@endif</p>
          @else
            <h2>Contenu indisponible</h2><p class="muted small">Ce contenu n’est plus publié ou a été retiré : il n’est pas accessible depuis vos favoris.</p>
          @endif
        </div>
        <div class="ac-ff">
          @if($i['available'])<a class="btn btn-link" href="{{ $i['kind'] === 'service' ? route('services.show', $i['card']->slug) : route('freelances.show', $i['profile']['slug']) }}">Voir</a>@else<span></span>@endif
          <form method="post" action="{{ route('favorites.remove', $i['id']) }}">@csrf<button class="btn btn-link" type="submit">Retirer des favoris</button></form>
        </div>
      </article>
      @if($loop->last)</div>@endif
    @empty
      <div class="ed-card empty" style="margin-top:16px;justify-items:center;text-align:center"><span class="ico-lg"><x-fc.icon name="heart" :size="26" /></span><p style="font-weight:600">{{ $counts['tous'] > 0 ? 'Aucun favori dans cette catégorie.' : 'Aucun favori pour l’instant.' }}</p><p class="muted">Utilisez le cœur sur un service ou un profil pour le retrouver ici.</p><a class="btn btn-primary" href="{{ route('services.index') }}">Parcourir les services</a></div>
    @endforelse
  </div>
</x-layouts.account>

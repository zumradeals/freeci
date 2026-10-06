{{-- Bouton favori en ligne (fiches) : texte explicite, même formulaire que la carte. --}}
@props(['kind', 'slug', 'on' => false])
@auth
<form method="post" action="{{ route('favorites.toggle', [$kind, $slug]) }}" style="display:inline">@csrf<input type="hidden" name="intent" value="{{ $on ? 'remove' : 'add' }}">
  <button class="btn btn-secondary" type="submit" aria-pressed="{{ $on ? 'true' : 'false' }}"><x-fc.icon name="heart" :size="18" />{{ $on ? 'Retirer des favoris' : 'Ajouter aux favoris' }}</button></form>
@else
<a class="btn btn-secondary" href="{{ route('login') }}"><x-fc.icon name="heart" :size="18" />Se connecter pour ajouter aux favoris</a>
@endauth

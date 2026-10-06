{{-- Favori PRIVÉ : formulaire POST (intention explicite add/remove). Visiteur non connecté : lien vers la connexion. --}}
@props(['kind', 'slug', 'on' => false])
@auth
<form method="post" action="{{ route('favorites.toggle', [$kind, $slug]) }}" class="fav">@csrf<input type="hidden" name="intent" value="{{ $on ? 'remove' : 'add' }}">
  <button class="fav-btn" type="submit" aria-pressed="{{ $on ? 'true' : 'false' }}" aria-label="{{ $on ? 'Retirer des favoris' : 'Ajouter aux favoris' }}"><x-fc.icon name="heart" :size="20" /></button></form>
@else
<span class="fav"><a class="fav-btn" href="{{ route('login') }}" aria-label="Se connecter pour ajouter aux favoris"><x-fc.icon name="heart" :size="20" /></a></span>
@endauth

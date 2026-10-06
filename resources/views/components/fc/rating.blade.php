{{-- Note réelle : moyenne et nombre d'avis PUBLIÉS. Aucun avis = aucune note affichée (rien d'inventé). --}}
@props(['avg' => null, 'count' => 0])
@if($count > 0 && $avg)<span class="rating" aria-label="Note moyenne {{ $avg }} sur 5, {{ $count }} avis publié{{ $count > 1 ? 's' : '' }}"><span aria-hidden="true">★</span> <strong>{{ $avg }}</strong><span class="muted"> · {{ $count }} avis</span></span>@endif

<x-layouts.info :title="$title" :slug="$slug" :approved="$approved">
  <div class="prose">{!! $html !!}</div>
  @if($slug === 'contact')
    <div class="card" style="max-width:46rem">@auth<p>Depuis votre espace connecté, ouvrez une demande : elle est suivie avec une référence.</p><a class="btn btn-primary" href="{{ route('support.index') }}">Ouvrir l’assistance</a>@else<p>Connectez-vous pour ouvrir une demande d’assistance suivie.</p><a class="btn btn-primary" href="{{ route('login') }}">Se connecter</a>@endauth</div>
  @endif
</x-layouts.info>

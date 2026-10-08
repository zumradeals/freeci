<x-layouts.public title="Trop de tentatives" robots="noindex">
  <x-fc.error-page code="429" icon="clock" tone="w" title="Trop de tentatives">
    Vous avez effectué trop d’actions en peu de temps. Patientez une minute puis réessayez.
    <x-slot:actions><a class="btn btn-primary btn-lg" href="{{ url()->previous() }}">Réessayer</a><a class="btn btn-secondary btn-lg" href="{{ route('home') }}">Retour à l’accueil</a></x-slot:actions>
  </x-fc.error-page>
</x-layouts.public>

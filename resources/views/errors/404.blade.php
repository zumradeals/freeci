<x-layouts.public title="Page introuvable" robots="noindex">
  <x-fc.error-page code="404" icon="minus-circle" title="Page introuvable">
    Cette page est introuvable, ou vous n’y avez pas accès.
    <x-slot:actions><a class="btn btn-primary btn-lg" href="{{ route('home') }}">Retour à l’accueil</a><a class="btn btn-secondary btn-lg" href="{{ route('services.index') }}">Parcourir les services</a></x-slot:actions>
    <x-slot:extra><div class="er-links"><a href="{{ route('services.index') }}"><x-fc.icon name="search" :size="18" />Services</a><a href="{{ route('missions.index') }}"><x-fc.icon name="briefcase" :size="18" />Missions</a><a href="{{ route('freelances.index') }}"><x-fc.icon name="user" :size="18" />Freelances</a></div></x-slot:extra>
  </x-fc.error-page>
</x-layouts.public>

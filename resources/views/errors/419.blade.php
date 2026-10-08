<x-layouts.public title="Page expirée" robots="noindex">
  <x-fc.error-page code="419" icon="clock" tone="w" title="Votre page a expiré">
    Pour votre sécurité, le formulaire n’est plus valide (page restée ouverte trop longtemps). Rien n’a été enregistré : rechargez la page puis recommencez.
    <x-slot:actions><a class="btn btn-primary btn-lg" href="{{ url()->previous() }}">Recharger la page</a><a class="btn btn-secondary btn-lg" href="{{ route('home') }}">Retour à l’accueil</a></x-slot:actions>
  </x-fc.error-page>
</x-layouts.public>

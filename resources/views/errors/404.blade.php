<x-layouts.public title="Page introuvable" robots="noindex">
<div class="container narrow-page">
  <div class="card empty"><span class="ico-lg"><x-fc.icon name="minus-circle" :size="26" /></span>
    <h1 class="t-h2">Page introuvable</h1>
    <p class="muted" style="max-width:36em">Cette page est introuvable, ou vous n’y avez pas accès.</p>
    <a class="btn btn-primary" href="{{ route('home') }}">Retour à l’accueil</a></div>
</div>
</x-layouts.public>

<x-layouts.public title="Service indisponible" robots="noindex">
<div class="container narrow-page">
  <div class="card empty"><span class="ico-lg"><x-fc.icon name="minus-circle" :size="26" /></span>
    <h1 class="t-h2">Ce service n’est plus disponible</h1>
    <p class="muted" style="max-width:36em">Il a été retiré par son auteur ou par FreeCI. Vous pouvez parcourir les autres services.</p>
    <a class="btn btn-primary" href="{{ route('services.index') }}">Voir les services</a></div>
</div>
</x-layouts.public>

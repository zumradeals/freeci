<x-layouts.public :title="$title.' — bientôt'" robots="noindex">
<div class="container narrow-page">
  <div class="card empty"><span class="ico-lg"><x-fc.icon name="clock" :size="26" /></span>
    <p class="eyebrow">Bientôt disponible</p>
    <h1 class="t-h2">{{ $title }}</h1>
    <p class="muted" style="max-width:36em">{{ $text }} Cette fonction n’existe pas encore dans cette version de démonstration : rien ne peut y être envoyé, payé ou enregistré.</p>
    <a class="btn btn-primary" href="{{ route('services.index') }}">Parcourir les services</a></div>
</div>
</x-layouts.public>

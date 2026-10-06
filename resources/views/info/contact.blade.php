<x-layouts.public :title="$title" :robots="$approved ? null : 'noindex, nofollow'">
<div class="container" style="padding-block:32px;max-width:760px">
  <h1 class="t-h1">{{ $title }}</h1>
  <x-fc.draft-banner :approved="$approved" />
  <div class="stack-sm" style="margin-top:16px">
    @auth<div class="card"><h2 class="t-h2">Contacter l’assistance</h2><p>Depuis votre espace connecté, ouvrez une demande : elle est suivie avec une référence.</p><a class="btn btn-primary" href="{{ route('support.index') }}">Ouvrir l’assistance</a></div>
    @else<div class="card"><h2 class="t-h2">Contacter l’assistance</h2><p>L’assistance se fait depuis votre espace connecté, avec suivi de dossier.</p><a class="btn btn-primary" href="{{ route('login') }}">Se connecter</a></div>@endauth
    <div class="card"><h2 class="t-h2">Autres moyens de contact</h2>
      <p>Adresse de contact : @if($legal['contact_email']){{ $legal['contact_email'] }}@else<em>à renseigner</em>@endif</p>
      <p class="muted small">Aucun numéro de téléphone ni adresse postale n’est publié tant que l’exploitant ne les a pas fournis.</p></div>
  </div>
</div></x-layouts.public>

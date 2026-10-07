<x-layouts.admin title="Codes de récupération">
  <div class="page-body">
    <header class="page-head"><div class="row-top"><div><p class="eyebrow">Administration</p><h1 class="t-h1">{{ $first ? 'Double authentification activée' : 'Nouveaux codes de récupération' }}</h1></div></div></header>
    <div class="card stack">
      <div class="notice tone-warning"><x-fc.icon name="warn" /><p><strong>Conservez ces {{ count($codes) }} codes maintenant : ils ne seront plus jamais affichés.</strong> Chaque code ne sert qu’une fois, à la place du code de l’application, si vous perdez votre téléphone. @unless($first)Les anciens codes ne fonctionnent plus.@endunless</p></div>
      <ul class="codes" aria-label="Codes de récupération">@foreach($codes as $c)<li>{{ $c }}</li>@endforeach</ul>
      <p class="muted small">Notez-les dans un endroit sûr, hors de cet ordinateur (coffre de mots de passe, papier rangé). Ne les envoyez ni par courriel ni par messagerie. Si vous perdez à la fois l’application et les codes, la récupération se fait depuis le serveur (procédure console journalisée).</p>
      <a class="btn btn-primary" href="{{ route('admin.home') }}">J’ai conservé mes codes — continuer</a>
    </div>
  </div>
</x-layouts.admin>

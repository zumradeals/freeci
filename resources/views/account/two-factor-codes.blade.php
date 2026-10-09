<x-layouts.account :title="$first ? 'Double authentification activée' : 'Nouveaux codes de secours'" :space="$space">
  <div class="page-body">
    <div class="sx-head"><div><p class="sx-kicker">Mon compte</p><h1>{{ $first ? 'Double authentification activée' : 'Nouveaux codes de secours' }}</h1><p class="muted">{{ $first ? 'Dernière étape : conservez vos codes de secours.' : 'Les anciens codes ne fonctionnent plus.' }}</p></div></div>
    <div class="ac-grid">
      <section class="ed-card">
        <div class="notice tone-warning" role="alert"><x-fc.icon name="warn" /><p><strong>Ces codes ne seront plus jamais affichés.</strong> Chacun ne sert qu’une fois. Si vous perdez votre téléphone et ces codes, vous ne pourrez plus vous connecter sans l’aide de l’équipe.</p></div>
        <ul class="tf-codes" aria-label="Codes de secours">@foreach($codes as $c)<li>{{ $c }}</li>@endforeach</ul>
        <div class="tf-acts">
          <button class="btn btn-secondary" type="button" onclick="navigator.clipboard && navigator.clipboard.writeText(this.dataset.t)" data-t="{{ implode("\n", $codes) }}">Copier les codes</button>
          <button class="btn btn-secondary" type="button" onclick="window.print()">Imprimer</button>
          <a class="btn btn-secondary" download="codes-de-secours-freeci.txt" href="data:text/plain;charset=utf-8,{{ rawurlencode("Codes de secours FreeCI (un seul usage chacun)\n".implode("\n", $codes)."\n") }}">Télécharger (.txt)</a></div>
        <form method="get" action="{{ route('account.settings') }}" class="stack-sm">
          <label><input type="checkbox" required> J’ai conservé ces codes en lieu sûr (hors de ce téléphone).</label>
          <div><button class="btn btn-primary" type="submit">Terminer</button></div></form>
      </section>
      <aside class="ac-side"><section class="ed-ck"><h3>Où les garder ?</h3><ul class="tf-lv">
        <li class="ok"><x-fc.icon name="check" :size="18" /><span>Dans un gestionnaire de mots de passe</span></li>
        <li class="ok"><x-fc.icon name="check" :size="18" /><span>Sur papier, dans un endroit sûr</span></li>
        <li class="no"><x-fc.icon name="close" :size="18" /><span>Pas dans une capture d’écran sur le même téléphone</span></li></ul></section></aside>
    </div>
  </div>
</x-layouts.account>

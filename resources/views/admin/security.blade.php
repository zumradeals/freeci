<x-layouts.admin title="Ma sécurité">
  <div class="page-body">
    <header class="page-head"><div class="row-top"><div><p class="eyebrow">Administration</p><h1 class="t-h1">Ma sécurité</h1><p class="lead">Votre double authentification, vos codes de récupération et votre confirmation d’identité.</p></div></div></header>
    <div class="stack-lg" style="max-width:44em">
      <section class="card stack"><h2 class="t-h3">Accès administration</h2>
        <p>Adresse vérifiée : <strong>{{ $user->emailVerified() ? 'oui' : 'non' }}</strong> · Double authentification : <strong>{{ $user->hasTwoFactor() ? 'activée' : 'inactive' }}</strong> · Codes de récupération restants : <strong>{{ $remaining }}</strong></p>
      </section>
      <section class="card stack"><h2 class="t-h3">Régénérer les codes de récupération</h2>
        <p>Les anciens codes cessent de fonctionner. Votre mot de passe vous sera redemandé s’il y a plus de {{ config('freeci.admin.reauth_minutes') }} minutes.</p>
        <form method="post" action="{{ route('admin.security.codes') }}">@csrf<button class="btn btn-secondary" type="submit" data-once>Générer de nouveaux codes</button></form>
      </section>
      <section class="card stack"><h2 class="t-h3">Perte de l’application d’authentification</h2>
        <p class="muted">Utilisez un code de récupération. Sans code, la réinitialisation se fait depuis le serveur : <code>php artisan freeci:admin:mfa-reset &lt;courriel&gt;</code> (journalisée, ferme toutes les sessions du compte).</p>
      </section>
    </div>
  </div>
</x-layouts.admin>

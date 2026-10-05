<x-layouts.public title="Créer un compte" robots="noindex">
<div class="container auth-wrap">
  <div class="card auth-card">
    <h1 class="t-h2">Créer un compte</h1>
    <p class="muted">Gratuit. Vous accédez à votre espace client ; les commandes et les paiements arrivent dans un prochain lot.</p>
    @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>Vérifiez les champs signalés ci-dessous.</p></div>@endif
    <form method="post" action="{{ route('register') }}" class="stack">
      @csrf
      <x-fc.field name="name" label="Nom complet" autocomplete="name" />
      <x-fc.field name="email" label="Adresse e-mail" type="email" autocomplete="email" />
      <x-fc.field name="password" label="Mot de passe" type="password" autocomplete="new-password" hint="Au moins 10 caractères, avec des lettres et des chiffres." />
      <x-fc.field name="password_confirmation" label="Confirmer le mot de passe" type="password" autocomplete="new-password" />
      <button class="btn btn-primary btn-lg btn-block" type="submit">Créer mon compte</button>
    </form>
    <p class="small muted">Déjà un compte ? <a href="{{ route('login') }}">Se connecter</a></p>
  </div>
</div>
</x-layouts.public>

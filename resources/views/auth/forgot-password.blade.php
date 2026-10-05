<x-layouts.public title="Mot de passe oublié" robots="noindex">
<div class="container auth-wrap">
  <div class="card auth-card">
    <h1 class="t-h2">Mot de passe oublié</h1>
    <p class="muted">Indiquez l’adresse de votre compte. Si elle existe, nous vous envoyons un lien valable 60 minutes.</p>
    @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>Vérifiez le champ signalé ci-dessous.</p></div>@endif
    <form method="post" action="{{ route('password.email') }}" class="stack">
      @csrf
      <x-fc.field name="email" label="Adresse e-mail" type="email" autocomplete="email" />
      <button class="btn btn-primary btn-lg btn-block" type="submit">Envoyer le lien</button>
    </form>
    <p class="small"><a href="{{ route('login') }}">Retour à la connexion</a></p>
  </div>
</div>
</x-layouts.public>

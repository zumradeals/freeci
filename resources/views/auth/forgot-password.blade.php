<x-layouts.auth title="Mot de passe oublié" heading="Pas de panique." lead="Nous vous envoyons un lien pour choisir un nouveau mot de passe.">
    <h1>Mot de passe oublié</h1>
    <p class="au-sub">Indiquez l’adresse de votre compte. Si elle existe, nous vous envoyons un lien valable 60 minutes.</p>
    @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>Vérifiez le champ signalé ci-dessous.</p></div>@endif
    <form method="post" action="{{ route('password.email') }}" class="stack">
      @csrf
      <x-fc.field name="email" label="Adresse e-mail" type="email" autocomplete="email" />
      <button class="btn btn-primary btn-lg btn-block" type="submit">Envoyer le lien</button>
    </form>
    <p class="au-foot"><a href="{{ route('login') }}">Retour à la connexion</a></p>
</x-layouts.auth>

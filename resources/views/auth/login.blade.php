<x-layouts.auth title="Connexion" heading="Content de vous revoir." lead="Retrouvez vos commandes, vos missions et vos messages.">
    <h1>Se connecter</h1>
    @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>Vérifiez les champs signalés ci-dessous.</p></div>@endif
    <form method="post" action="{{ route('login') }}" class="stack">
      @csrf
      <x-fc.field name="email" label="Adresse e-mail" type="email" autocomplete="email" />
      <x-fc.field name="password" label="Mot de passe" type="password" autocomplete="current-password" reveal />
      <div class="au-row"><label class="check"><input type="checkbox" name="remember" value="1"> Rester connecté sur cet appareil</label><a href="{{ route('password.request') }}">Mot de passe oublié ?</a></div>
      <button class="btn btn-primary btn-lg btn-block" type="submit">Se connecter</button>
    </form>
    <p class="au-foot">Pas encore de compte ? <a href="{{ route('register') }}">Créer un compte</a></p>
</x-layouts.auth>

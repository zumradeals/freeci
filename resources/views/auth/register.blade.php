<x-layouts.auth title="Créer un compte" heading="Les bons talents. Pour vos projets." lead="Créez votre compte gratuit pour choisir un service, publier une mission et suivre vos commandes.">
    <h1>Créer un compte</h1>
    <p class="au-sub">Gratuit. Vous accédez à votre espace client ; vous pourrez activer l’espace freelance ensuite.</p>
    @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>Vérifiez les champs signalés ci-dessous.</p></div>@endif
    <form method="post" action="{{ route('register') }}" class="stack">
      @csrf
      <x-fc.field name="name" label="Nom complet" autocomplete="name" />
      <x-fc.field name="email" label="Adresse e-mail" type="email" autocomplete="email" />
      <x-fc.field name="password" label="Mot de passe" type="password" autocomplete="new-password" hint="Au moins 10 caractères, avec des lettres et des chiffres." reveal strength />
      <x-fc.field name="password_confirmation" label="Confirmer le mot de passe" type="password" autocomplete="new-password" reveal />
      <p class="au-note"><x-fc.icon name="lock" :size="16" /><span>En créant un compte, vous prenez connaissance des <a href="{{ route('info', 'conditions') }}">conditions d’utilisation</a> et de la <a href="{{ route('info', 'confidentialite') }}">politique de confidentialité</a>.</span></p>
      <button class="btn btn-primary btn-lg btn-block" type="submit">Créer mon compte</button>
    </form>
    <p class="au-foot">Déjà un compte ? <a href="{{ route('login') }}">Se connecter</a></p>
</x-layouts.auth>

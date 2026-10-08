<x-layouts.auth title="Nouveau mot de passe" heading="Un nouveau départ." lead="Choisissez un mot de passe solide pour protéger votre compte.">
    <h1>Nouveau mot de passe</h1>
    @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>Vérifiez les champs signalés ci-dessous.</p></div>@endif
    <form method="post" action="{{ route('password.update') }}" class="stack">
      @csrf
      <input type="hidden" name="token" value="{{ $token }}">
      <x-fc.field name="email" label="Adresse e-mail" type="email" :value="$email" autocomplete="email" />
      <x-fc.field name="password" label="Nouveau mot de passe" type="password" autocomplete="new-password" hint="Au moins 10 caractères, avec des lettres et des chiffres." reveal strength />
      <x-fc.field name="password_confirmation" label="Confirmer le mot de passe" type="password" autocomplete="new-password" reveal />
      <button class="btn btn-primary btn-lg btn-block" type="submit">Enregistrer</button>
    </form>
</x-layouts.auth>

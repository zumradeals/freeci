<x-layouts.public title="Nouveau mot de passe" robots="noindex">
<div class="container auth-wrap">
  <div class="card auth-card">
    <h1 class="t-h2">Nouveau mot de passe</h1>
    @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>Vérifiez les champs signalés ci-dessous.</p></div>@endif
    <form method="post" action="{{ route('password.update') }}" class="stack">
      @csrf
      <input type="hidden" name="token" value="{{ $token }}">
      <x-fc.field name="email" label="Adresse e-mail" type="email" :value="$email" autocomplete="email" />
      <x-fc.field name="password" label="Nouveau mot de passe" type="password" autocomplete="new-password" hint="Au moins 10 caractères, avec des lettres et des chiffres." />
      <x-fc.field name="password_confirmation" label="Confirmer le mot de passe" type="password" autocomplete="new-password" />
      <button class="btn btn-primary btn-lg btn-block" type="submit">Enregistrer</button>
    </form>
  </div>
</div>
</x-layouts.public>

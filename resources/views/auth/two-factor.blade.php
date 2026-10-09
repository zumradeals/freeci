<x-layouts.auth title="Vérification en deux étapes" heading="Une vérification de plus, pour vous protéger." lead="Votre mot de passe est correct. Il reste à confirmer que c’est bien vous.">
    <h1>Vérification en deux étapes</h1>
    <p class="muted">Saisissez le code à 6 chiffres affiché dans votre application d’authentification.</p>
    @if(session('error'))<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>{{ session('error') }}</p></div>@endif
    <form method="post" action="{{ route('login.2fa.verify') }}" class="stack">
      @csrf
      <div class="field"><label for="f-code">Code à 6 chiffres</label>
        <input class="input tf-code" id="f-code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="20" required autofocus @error('code') aria-invalid="true" aria-describedby="e-code" @enderror>
        @error('code')<p class="field-error" id="e-code"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
      <button class="btn btn-primary btn-lg btn-block" type="submit" data-once>Valider</button>
    </form>
    <details class="tf-lost"><summary>Je n’ai plus mon téléphone</summary>
      <div class="stack-sm">
        <p class="muted small">Saisissez dans le champ ci-dessus l’un de vos <strong>codes de secours</strong> (forme <code>abcde-fghij</code>) : il ne sert qu’une fois.</p>
        <p class="muted small">Plus de codes non plus ? Écrivez à l’équipe{{ $contact ? ' : '.$contact : '' }}. Pour votre sécurité, elle vérifiera votre identité avant toute réinitialisation ; rien n’est réinitialisé automatiquement.</p>
      </div>
    </details>
    <form method="post" action="{{ route('login.2fa.cancel') }}" class="au-foot">@csrf<span class="muted small">Après plusieurs codes refusés, la vérification est bloquée quelques minutes.</span> <button class="btn btn-link" type="submit">Annuler la connexion</button></form>
</x-layouts.auth>

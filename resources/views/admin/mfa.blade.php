<x-layouts.admin title="Vérification en deux étapes">
  <header class="page-head"><div class="row-top"><div><p class="eyebrow">Administration</p><h1 class="t-h1">Vérification en deux étapes</h1></div></div></header>
  <div class="card stack" style="max-width:34em">
    <p>Saisissez le code à 6 chiffres de votre application d’authentification, ou l’un de vos codes de récupération (à usage unique, forme <code>abcde-fghij</code>).</p>
    <form method="post" action="{{ route('admin.mfa.verify') }}" class="stack">@csrf
      <div class="field"><label for="f-code">Code</label><input class="input" id="f-code" name="code" autocomplete="one-time-code" maxlength="20" required autofocus @error('code') aria-invalid="true" aria-describedby="e-code" @enderror>@error('code')<p class="field-error" id="e-code"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
      <button class="btn btn-primary btn-lg" type="submit" data-once>Valider</button>
    </form>
    <p class="muted small">Après plusieurs codes refusés, la vérification est bloquée quelques minutes. Application et codes perdus : procédure de récupération depuis le serveur (voir la documentation).</p>
  </div>
</x-layouts.admin>

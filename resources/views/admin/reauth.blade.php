<x-layouts.admin title="Confirmer votre identité">
  <div class="page-body">
    <header class="page-head"><div class="row-top"><div><p class="eyebrow">Administration</p><h1 class="t-h1">Confirmer votre identité</h1></div></div></header>
    <div class="card stack" style="max-width:34em">
      <p>Cette opération est sensible. Saisissez votre mot de passe ; la confirmation reste valable {{ config('freeci.admin.reauth_minutes') }} minutes.</p>
      <form method="post" action="{{ route('admin.reauth.confirm') }}" class="stack">@csrf
        <div class="field"><label for="f-password">Mot de passe</label><input class="input" id="f-password" type="password" name="password" autocomplete="current-password" required @error('password') aria-invalid="true" aria-describedby="e-password" @enderror>@error('password')<p class="field-error" id="e-password"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
        <button class="btn btn-primary btn-lg" type="submit" data-once>Confirmer</button>
      </form>
    </div>
  </div>
</x-layouts.admin>

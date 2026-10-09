<x-layouts.account title="Activer la double authentification" :space="$space">
  <div class="page-body">
    <nav class="sd-crumbs" aria-label="Fil d’Ariane"><a href="{{ route('account.settings') }}">Mon compte</a> › <span aria-current="page">Double authentification</span></nav>
    <div class="sx-head"><div><p class="sx-kicker">Mon compte</p><h1>Activer la double authentification</h1><p class="muted">Trois étapes, environ deux minutes.</p></div></div>
    @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>{{ $errors->first() }}</p></div>@endif
    <div class="ac-grid">
      <section class="ed-card"><ol class="tf-st">
        <li><div><h2>Installez une application d’authentification</h2><p class="muted" style="margin:0">Sur votre téléphone : Google Authenticator, Microsoft Authenticator, Aegis ou 2FAS (gratuites).</p></div></li>
        <li><div><h2>Ajoutez FreeCI dans l’application</h2>
          <div class="tf-qr"><div class="tf-qrbox" role="img" aria-label="Code QR à scanner avec l’application d’authentification">{!! $qr !!}</div>
            <div style="display:grid;gap:8px"><p style="margin:0">Scannez ce code avec l’application, ou saisissez cette clé (type « basé sur l’heure ») :</p><span class="tf-key">{{ $key }}</span><p class="muted small" style="margin:0">Cette clé est secrète : ne la partagez jamais.</p></div></div></div></li>
        <li><div><h2>Saisissez le code affiché</h2>
          <form method="post" action="{{ route('account.2fa.enable') }}" class="stack-sm" style="max-width:24em">@csrf
            <div class="field"><label for="tf-code">Code à 6 chiffres</label><input class="input" id="tf-code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="20" required @error('code') aria-invalid="true" @enderror></div>
            <div class="field"><label for="tf-pw">Votre mot de passe actuel</label><input class="input" id="tf-pw" name="current_password" type="password" required autocomplete="current-password" @error('current_password') aria-invalid="true" @enderror></div>
            <div class="tf-acts"><button class="btn btn-primary" type="submit" data-once>Vérifier et activer</button><a class="btn btn-link" href="{{ route('account.settings') }}">Annuler</a></div></form></div></li>
      </ol></section>
      <aside class="ac-side"><section class="ed-ck"><h3>Bon à savoir</h3><ul class="tf-lv">
        <li class="ok"><x-fc.icon name="lock" :size="18" /><span>La double authentification n’est active qu’après la saisie d’un code valide.</span></li>
        <li class="ok"><x-fc.icon name="check" :size="18" /><span>À l’étape suivante, vous recevrez 8 codes de secours, affichés une seule fois.</span></li>
        <li class="ok"><x-fc.icon name="check" :size="18" /><span>Vos autres sessions ouvertes seront fermées à l’activation.</span></li></ul></section></aside>
    </div>
  </div>
</x-layouts.account>

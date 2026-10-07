<x-layouts.admin title="Activation de l’administration">
  <div class="page-body">
    <header class="page-head"><div class="row-top"><div><p class="eyebrow">Administration</p><h1 class="t-h1">Activer l’administration</h1></div></div></header>
    <div class="notice tone-warning"><x-fc.icon name="shield" /><p>Votre habilitation d’administrateur est en vigueur, mais <strong>les fonctions d’administration restent fermées</strong> tant que votre adresse n’est pas vérifiée et que la double authentification n’est pas activée.</p></div>
    <ol class="steps" style="margin-top:24px;padding:0">
      <li class="card"><h2 class="t-h3">1. Vérifier votre adresse e-mail @if($emailOk)<span class="badge tone-success"><x-fc.icon name="check-circle" />Vérifiée</span>@else<span class="badge tone-warning">À faire</span>@endif</h2>
        @if($emailOk)
          <p class="muted">L’adresse {{ $user->email }} est vérifiée.</p>
        @else
          <p>Nous envoyons un lien à <strong>{{ $user->email }}</strong>. Ouvrez-le dans ce navigateur, connecté à votre compte.</p>
          @if($mailConfigured)
            <form method="post" action="{{ route('admin.activation.email') }}">@csrf<button class="btn btn-primary" type="submit" data-once>Envoyer le lien de vérification</button></form>
          @else
            <div class="notice tone-error"><x-fc.icon name="error" /><p><strong>Le courrier n’est pas configuré</strong> sur cette installation : aucun courriel ne peut être envoyé, et un message écrit dans les journaux ne vaut pas vérification. Configurez le SMTP (<code>MAIL_*</code>, voir <code>docs/15-lot-8-administration.md</code>), ou, depuis le serveur, utilisez l’attestation console journalisée : <code>php artisan freeci:admin:verify-email {{ $user->email }}</code>.</p></div>
          @endif
        @endif
      </li>
      <li class="card"><h2 class="t-h3">2. Activer la double authentification <span class="badge tone-warning">{{ $emailOk ? 'À faire' : 'Après l’étape 1' }}</span></h2>
        @if(! $emailOk)
          <p class="muted">Cette étape s’ouvre dès que l’adresse est vérifiée.</p>
        @elseif($pending === null)
          <p>Vous aurez besoin d’une application d’authentification (Google Authenticator, Aegis, FreeOTP, Microsoft Authenticator…).</p>
          <form method="post" action="{{ route('admin.activation.begin') }}">@csrf<button class="btn btn-primary" type="submit" data-once>Commencer</button></form>
        @else
          <p>Dans votre application d’authentification, ajoutez un compte <strong>par saisie manuelle</strong> (type « basé sur l’heure ») avec cette clé :</p>
          <p class="secret">{{ implode(' ', str_split($pending['secret'], 4)) }}</p>
          <p class="muted small">Cette clé est un secret : ne la partagez pas. Elle n’est affichée que pendant l’activation.</p>
          <form method="post" action="{{ route('admin.activation.enable') }}" class="stack" style="max-width:24em">@csrf
            <div class="field"><label for="f-code">Code à 6 chiffres affiché par l’application</label><input class="input" id="f-code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="12" required @error('code') aria-invalid="true" aria-describedby="e-code" @enderror>@error('code')<p class="field-error" id="e-code"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
            <button class="btn btn-primary" type="submit" data-once>Confirmer et activer</button>
          </form>
        @endif
      </li>
    </ol>
  </div>
</x-layouts.admin>

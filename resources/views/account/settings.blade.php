<x-layouts.account title="Mon compte" :space="$space">
  <header class="page-head"><div class="row-top"><div><p class="eyebrow">Espace {{ $space === 'freelancer' ? 'freelance' : 'client' }}</p><h1 class="t-h1">Mon compte</h1></div></div></header>
  @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>{{ $errors->first() }}</p></div>@endif

  <section class="card" style="max-width:640px;margin-top:12px" aria-labelledby="h-info"><h2 class="t-h2" id="h-info">Informations personnelles</h2>
    <form method="post" action="{{ route('account.name') }}" class="stack-sm">@csrf
      <div class="field"><label for="name">Nom</label><input class="input" id="name" name="name" value="{{ old('name', auth()->user()->name) }}" minlength="2" maxlength="100" required autocomplete="name"></div>
      <p class="muted small">Adresse e-mail actuelle : <strong>{{ auth()->user()->email }}</strong> {{ auth()->user()->emailVerified() ? '(vérifiée)' : '(non vérifiée)' }}. Le nom affiché publiquement d’un freelance se règle dans son profil.</p>
      <button class="btn btn-primary" type="submit">Enregistrer</button></form></section>

  <section class="card" style="max-width:640px;margin-top:12px" aria-labelledby="h-mail"><h2 class="t-h2" id="h-mail">Changer d’adresse e-mail</h2>
    @if($pendingEmail)<div class="notice tone-info"><x-fc.icon name="info" /><p>Vérification en attente pour <strong>{{ $pendingEmail->new_email }}</strong>. Votre adresse actuelle reste active jusqu’à confirmation.</p></div>@endif
    @unless($mailReady)<div class="notice tone-warning"><x-fc.icon name="warn" /><p>Le courrier n’est pas configuré sur ce serveur : le changement d’adresse est indisponible (la nouvelle adresse ne peut pas être vérifiée).</p></div>@endunless
    <p class="muted small">La nouvelle adresse reçoit un lien de vérification ; l’adresse actuelle est prévenue. Rien n’est remplacé avant la vérification.</p>
    <form method="post" action="{{ route('account.email.request') }}" class="stack-sm">@csrf
      <div class="field"><label for="ne">Nouvelle adresse</label><input class="input" id="ne" name="email" type="email" maxlength="254" required autocomplete="email"></div>
      <div class="field"><label for="ne-p">Mot de passe actuel</label><input class="input" id="ne-p" name="current_password" type="password" required autocomplete="current-password"></div>
      <button class="btn btn-secondary" type="submit" @disabled(! $mailReady)>Envoyer le lien de vérification</button></form></section>

  <section class="card" style="max-width:640px;margin-top:12px" aria-labelledby="h-pw"><h2 class="t-h2" id="h-pw">Mot de passe</h2>
    <form method="post" action="{{ route('account.password') }}" class="stack-sm">@csrf
      <div class="field"><label for="cp">Mot de passe actuel</label><input class="input" id="cp" name="current_password" type="password" required autocomplete="current-password"></div>
      <div class="field"><label for="np">Nouveau mot de passe (10 caractères minimum, lettres et chiffres)</label><input class="input" id="np" name="password" type="password" minlength="10" required autocomplete="new-password"></div>
      <div class="field"><label for="npc">Confirmer le nouveau mot de passe</label><input class="input" id="npc" name="password_confirmation" type="password" required autocomplete="new-password"></div>
      <p class="muted small">Vos autres sessions seront fermées.</p>
      <button class="btn btn-primary" type="submit">Changer le mot de passe</button></form></section>

  <section class="card" style="max-width:640px;margin-top:12px" aria-labelledby="h-ses"><h2 class="t-h2" id="h-ses">Sessions ouvertes</h2>
    <ul class="stack-sm" style="list-style:none;padding:0">@foreach($sessions as $s)
      <li><strong>{{ $s['device'] }}</strong>{{ $s['current'] ? ' — cette session' : '' }}<br><span class="muted small">{{ $s['ip'] }} · dernière activité {{ $s['last']->timezone(config('app.timezone'))->translatedFormat('j F Y') }}</span>
        @unless($s['current'])<form method="post" action="{{ route('account.sessions.revoke', $s['id']) }}" style="display:inline">@csrf<button class="btn btn-link" type="submit">Fermer</button></form>@endunless</li>@endforeach</ul>
    @if(count($sessions) > 1)<form method="post" action="{{ route('account.sessions.revoke-others') }}">@csrf<button class="btn btn-secondary" type="submit">Fermer toutes les autres sessions</button></form>@endif</section>

  <section class="card" style="max-width:640px;margin-top:12px" aria-labelledby="h-exp"><h2 class="t-h2" id="h-exp">Exporter mes données</h2>
    <p class="muted small">Un fichier JSON de vos propres données (compte, profil, commandes, messages que vous avez envoyés, avis, favoris, notifications, sécurité). Il ne contient pas les données privées des autres personnes ni vos secrets. Il est généré à la demande, téléchargé directement et non conservé sur le serveur. Limite : {{ config('freeci.account.export_per_day') }} par jour.</p>
    <form method="post" action="{{ route('account.export') }}" class="stack-sm">@csrf
      <div class="field"><label for="ep">Mot de passe actuel</label><input class="input" id="ep" name="current_password" type="password" required autocomplete="current-password"></div>
      <button class="btn btn-secondary" type="submit">Télécharger mes données</button></form></section>

  <section class="card" style="max-width:640px;margin-top:12px" aria-labelledby="h-close"><h2 class="t-h2" id="h-close">Fermer mon compte</h2>
    @if($closure)
      <div class="notice tone-warning" role="status"><x-fc.icon name="warn" /><p><strong>Fermeture demandée.</strong> Elle pourra être exécutée à partir du {{ \Illuminate\Support\Carbon::parse($closure->due_at)->translatedFormat('j F Y') }}, si aucune obligation n’est en cours. Aucune nouvelle activité ne peut être démarrée d’ici là.</p></div>
      @if($blockers)<p><strong>Obstacles actuels :</strong></p><ul>@foreach($blockers as $b)<li>{{ $b }}</li>@endforeach</ul><p class="muted small">Tant que ces points subsistent, le compte n’est pas fermé.</p>@else<p class="muted small">Aucun obstacle à ce jour.</p>@endif
      <form method="post" action="{{ route('account.closure.cancel') }}">@csrf<button class="btn btn-secondary" type="submit">Annuler la demande</button></form>
    @elseif($isStaff)
      <p class="muted">Votre compte porte une habilitation du personnel : elle doit d’abord être révoquée avant toute fermeture.</p>
    @else
      <p><strong>Ce qui se passe :</strong></p>
      <ul class="small">
        <li>Un délai de réflexion de {{ $graceDays }} jours s’applique (valeur provisoire) ; vous pouvez annuler pendant ce délai.</li>
        <li>La fermeture n’est exécutée que si <strong>aucune obligation</strong> n’est en cours : commande active, litige, remboursement ou reversement à finaliser, mission ou proposition ouverte. Ces éléments ne sont jamais supprimés par la fermeture.</li>
        <li>Une fois exécutée : votre nom, votre adresse e-mail, votre profil, vos favoris, vos notifications et vos sessions sont effacés ou anonymisés ; vos services sont archivés ; la connexion devient impossible. <strong>Ce n’est pas réversible.</strong></li>
        <li>Les commandes, messages échangés, écritures financières et dossiers d’assistance <strong>sont conservés</strong> (obligations envers les autres parties) et rattachés à un « Compte fermé ». Leur durée de conservation n’est pas encore arrêtée.</li>
        <li>Vous pouvez d’abord <a href="#h-exp">exporter vos données</a>.</li></ul>
      <form method="post" action="{{ route('account.closure.request') }}" class="stack-sm" data-once>@csrf
        <div class="field"><label for="cl-p">Mot de passe actuel</label><input class="input" id="cl-p" name="current_password" type="password" required autocomplete="current-password"></div>
        <label><input type="checkbox" name="confirm" value="1" required> J’ai lu ces conséquences et je demande la fermeture de mon compte.</label>
        <button class="btn btn-primary" type="submit">Demander la fermeture</button></form>
    @endif</section>
</x-layouts.account>

<x-layouts.account title="Mon compte" :space="$space">
  <div class="page-body">
    <div class="sx-head"><div><p class="sx-kicker">Espace {{ $space === 'freelancer' ? 'freelance' : 'client' }}</p><h1>Mon compte</h1><p class="muted">Vos informations, votre sécurité, vos données et la fermeture du compte.</p></div></div>
    @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>{{ $errors->first() }}</p></div>@endif

    <div class="ac-grid"><div class="ac-main">
    <section class="ed-card" aria-labelledby="h-info"><h2 id="h-info">Informations personnelles</h2>
      @include('account._photo')
      <p class="muted small">Facultative. Votre photo n’est visible que des personnes avec qui vous avez une commande et de l’équipe ; elle devient publique si vous publiez un profil freelance.</p>
      <div class="ac-id"><div><b>{{ auth()->user()->name }}</b><p class="muted small">{{ auth()->user()->email }} <span class="badge tone-{{ auth()->user()->emailVerified() ? 'success' : 'warning' }}">{{ auth()->user()->emailVerified() ? 'Vérifiée' : 'Non vérifiée' }}</span></p></div></div>
      <form method="post" action="{{ route('account.name') }}" class="stack-sm">@csrf
        <div class="field"><label for="name">Nom</label><input class="input" id="name" name="name" value="{{ old('name', auth()->user()->name) }}" minlength="2" maxlength="100" required autocomplete="name"></div>
        <p class="muted small">Le nom affiché publiquement d’un freelance se règle dans son profil.</p>
        <button class="btn btn-primary" type="submit">Enregistrer</button></form></section>

    <section class="ed-card" aria-labelledby="h-mail"><h2 id="h-mail">Changer d’adresse e-mail</h2>
      @if($pendingEmail)<div class="notice tone-info"><x-fc.icon name="info" /><p>Vérification en attente pour <strong>{{ $pendingEmail->new_email }}</strong>. Votre adresse actuelle reste active jusqu’à confirmation.</p></div>@endif
      @unless($mailReady)<div class="notice tone-warning"><x-fc.icon name="warn" /><p>Le courrier n’est pas configuré sur ce serveur : le changement d’adresse est indisponible (la nouvelle adresse ne peut pas être vérifiée).</p></div>@endunless
      <p class="muted small">La nouvelle adresse reçoit un lien de vérification ; l’adresse actuelle est prévenue. Rien n’est remplacé avant la vérification.</p>
      <form method="post" action="{{ route('account.email.request') }}" class="stack-sm">@csrf
        <div class="ac-r2"><div class="field"><label for="ne">Nouvelle adresse</label><input class="input" id="ne" name="email" type="email" maxlength="254" required autocomplete="email"></div>
        <div class="field"><label for="ne-p">Mot de passe actuel</label><input class="input" id="ne-p" name="current_password" type="password" required autocomplete="current-password"></div></div>
@if($twoFactor)<div class="field"><label for="ne-c">Code de votre application d’authentification</label><input class="input" id="ne-c" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="20" required><p class="muted small" style="margin:0">La double authentification est active : un code s’ajoute au mot de passe pour cet acte.</p></div>@endif
        <button class="btn btn-secondary" type="submit" @disabled(! $mailReady)>Envoyer le lien de vérification</button></form></section>

    <section class="ed-card" aria-labelledby="h-pw"><h2 id="h-pw">Mot de passe</h2>
      <form method="post" action="{{ route('account.password') }}" class="stack-sm">@csrf
        <div class="field"><label for="cp">Mot de passe actuel</label><input class="input" id="cp" name="current_password" type="password" required autocomplete="current-password"></div>
        <div class="ac-r2"><div class="field"><label for="np">Nouveau mot de passe (10 caractères minimum, lettres et chiffres)</label><input class="input" id="np" name="password" type="password" minlength="10" required autocomplete="new-password"></div>
        <div class="field"><label for="npc">Confirmer le nouveau mot de passe</label><input class="input" id="npc" name="password_confirmation" type="password" required autocomplete="new-password"></div></div>
        @if($twoFactor)<div class="field"><label for="np-c">Code de votre application d’authentification</label><input class="input" id="np-c" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="20" required><p class="muted small" style="margin:0">La double authentification est active : un code s’ajoute au mot de passe pour cet acte.</p></div>@endif
        <p class="muted small">Vos autres sessions seront fermées.</p>
        <button class="btn btn-primary" type="submit">Changer le mot de passe</button></form></section>

    <section class="ed-card" id="h-2fa" aria-labelledby="h-2fa-t">
      <div class="tf-state"><h2 id="h-2fa-t">Double authentification</h2>@if($twoFactor)<span class="badge tone-success">Activée</span>@else<span class="badge tone-warning">Non activée</span>@endif</div>
      @if($twoFactor)
        <p>Un code de votre application d’authentification est demandé à chaque nouvelle connexion. Activée le {{ $twoFactor['since']->timezone(config('app.timezone'))->translatedFormat('j F Y') }}.@if($isStaff) Elle est <strong>obligatoire pour l’équipe</strong> et ne peut pas être désactivée.@endif</p>
        <div class="tf-mini">
          <div><b>Codes de secours : {{ $twoFactor['remaining'] }} sur {{ \App\Modules\Accounts\Security\TwoFactor::CODES }} restants</b><p class="muted small" style="margin:2px 0 8px">Chaque code ne sert qu’une fois. Générer de nouveaux codes annule les anciens.</p>
            <details class="tf-act"><summary class="btn btn-secondary">Générer de nouveaux codes</summary>
              <form method="post" action="{{ route('account.2fa.codes') }}" class="stack-sm">@csrf
                <div class="ac-r2"><div class="field"><label for="tc-p">Mot de passe actuel</label><input class="input" id="tc-p" name="current_password" type="password" required autocomplete="current-password"></div>
                <div class="field"><label for="tc-c">Code de l’application</label><input class="input" id="tc-c" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="20" required></div></div>
                <button class="btn btn-primary" type="submit" data-once>Générer</button></form></details></div>
          @unless($isStaff)
          <div><b>Désactiver</b><p class="muted small" style="margin:2px 0 8px">Votre compte repasse à la protection par mot de passe seul. Vos autres sessions sont fermées.</p>
            <details class="tf-act"><summary class="btn btn-link">Désactiver la double authentification</summary>
              <form method="post" action="{{ route('account.2fa.disable') }}" class="stack-sm" onsubmit="return confirm('Désactiver la double authentification ?')">@csrf
                <div class="ac-r2"><div class="field"><label for="td-p">Mot de passe actuel</label><input class="input" id="td-p" name="current_password" type="password" required autocomplete="current-password"></div>
                <div class="field"><label for="td-c">Code de l’application</label><input class="input" id="td-c" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="20" required></div></div>
                <button class="btn btn-primary" type="submit" data-once>Désactiver</button></form></details></div>
          @endunless
        </div>
      @else
        <p>Ajoutez une deuxième étape à la connexion : un code à 6 chiffres généré par une application sur votre téléphone. Même si votre mot de passe est découvert, personne ne peut entrer dans votre compte sans ce code.</p>
        <ul class="tf-lv"><li class="ok"><x-fc.icon name="check" :size="18" /><span>Facultative : vous pouvez l’activer et la désactiver quand vous voulez.</span></li><li class="ok"><x-fc.icon name="check" :size="18" /><span>Aucun SMS, aucun frais : une application d’authentification suffit (Google Authenticator, Microsoft Authenticator, Aegis, 2FAS…).</span></li><li class="ok"><x-fc.icon name="check" :size="18" /><span>8 codes de secours vous sont remis pour le cas où vous perdez votre téléphone.</span></li></ul>
        <div class="tf-acts"><a class="btn btn-primary" href="{{ route('account.2fa') }}"><x-fc.icon name="lock" :size="18" /> Activer la double authentification</a></div>
      @endif</section>

    <section class="ed-card" aria-labelledby="h-exp"><h2 id="h-exp">Exporter mes données</h2>
      <p class="muted small">Un fichier JSON de vos propres données (compte, profil, commandes, messages que vous avez envoyés, avis, favoris, notifications, sécurité). Il ne contient pas les données privées des autres personnes ni vos secrets. Il est généré à la demande, téléchargé directement et non conservé sur le serveur. Limite : {{ config('freeci.account.export_per_day') }} par jour.</p>
      <form method="post" action="{{ route('account.export') }}" class="stack-sm">@csrf
        <div class="field"><label for="ep">Mot de passe actuel</label><input class="input" id="ep" name="current_password" type="password" required autocomplete="current-password"></div>
        <button class="btn btn-secondary" type="submit">Télécharger mes données</button></form></section>

    <section class="ed-card ac-danger" aria-labelledby="h-close"><h2 id="h-close">Fermer mon compte</h2>
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
    </div><aside class="ac-side">
    <section class="ed-ck" aria-labelledby="h-sec"><h3 id="h-sec">Niveau de sécurité</h3>
      <ul class="tf-lv"><li class="ok"><x-fc.icon name="check" :size="18" /><span>Mot de passe défini</span></li><li class="{{ auth()->user()->emailVerified() ? 'ok' : 'no' }}"><x-fc.icon :name="auth()->user()->emailVerified() ? 'check' : 'warn'" :size="18" /><span>Adresse e-mail {{ auth()->user()->emailVerified() ? 'vérifiée' : 'non vérifiée' }}</span></li><li class="{{ $twoFactor ? 'ok' : 'no' }}"><x-fc.icon :name="$twoFactor ? 'check' : 'warn'" :size="18" /><span>Double authentification {!! $twoFactor ? 'activée' : '<b>non activée</b>' !!}</span></li></ul>
      @unless($twoFactor)<p class="muted small" style="margin:12px 0 0">Conseil : activez-la avant de recevoir vos premiers paiements.</p>@endunless</section>
    <section class="ed-ck" aria-labelledby="h-ses"><h3 id="h-ses">Sessions ouvertes</h3>
      <ul class="ac-ses">@foreach($sessions as $s)
        <li><div><b>{{ $s['device'] }}</b><small>{{ $s['ip'] }} · dernière activité {{ $s['last']->timezone(config('app.timezone'))->translatedFormat('j F Y') }}</small></div>@if($s['current'])<span class="badge tone-success">Cette session</span>@else<form method="post" action="{{ route('account.sessions.revoke', $s['id']) }}" style="display:inline">@csrf<button class="btn btn-link" type="submit">Fermer</button></form>@endif</li>@endforeach</ul>
      @if(count($sessions) > 1)<form method="post" action="{{ route('account.sessions.revoke-others') }}">@csrf<button class="btn btn-secondary" type="submit">Fermer toutes les autres sessions</button></form>@endif</section>

      <section class="ed-ck"><h3>Raccourcis</h3><ul class="ac-short"><li><a href="{{ route('notifications.preferences') }}"><x-fc.icon name="inbox" /><span>Préférences de notification</span><x-fc.icon name="arrow-right" :size="16" /></a></li><li><a href="{{ route('favorites.index') }}"><x-fc.icon name="heart" /><span>Mes favoris</span><x-fc.icon name="arrow-right" :size="16" /></a></li></ul></section></aside>
    </div>
  </div>
</x-layouts.account>

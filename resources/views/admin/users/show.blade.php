@php($ini = collect(preg_split('/\s+/', trim($u['name'])) ?: [])->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') ?: '?')
<x-layouts.admin title="Compte utilisateur">
  <div class="page-body">
    <nav class="crumbs" aria-label="Fil d’Ariane"><a class="back-m" href="{{ route('admin.users') }}"><x-fc.icon name="arrow-right" :size="16" class="flip" />Utilisateurs</a><a class="hide-m" href="{{ route('admin.users') }}">Utilisateurs</a><span class="sep hide-m" aria-hidden="true">›</span><span class="hide-m" aria-current="page">{{ $u['name'] }}</span></nav>
    <div class="sx-head"><div class="us-id"><x-fc.avatar :name="$u['name']" :photo="$u['photo']['id'] ?? null" size="xl" /><div><p class="sx-kicker">Compte utilisateur</p><h1>{{ $u['name'] }}</h1><p class="muted">{{ $u['email'] }}@if($u['demo']) · compte de démonstration @endif</p></div></div>
      @if($u['suspended'])<span class="badge tone-error">Suspendu depuis le {{ $u['suspendedAt'] }}</span>@else<span class="badge tone-success">Actif</span>@endif</div>
    <div class="ac-grid"><div class="ac-main">
      <section class="ed-card" aria-labelledby="h-photo"><h2 id="h-photo">Photo de profil</h2>
        @if($u['photo'])
          <div class="ph-adm"><x-fc.avatar :name="$u['name']" :photo="$u['photo']['id']" size="ph" :alt="'Photo de '.$u['name']" /><div style="display:grid;gap:8px"><p style="margin:0"><b>En ligne</b> · ajoutée le {{ $u['photo']['since'] }}</p><div class="rq-info"><x-fc.icon name="info" :size="18" /><span>La photo est publique dès son dépôt pour un profil freelance publié ; sinon elle n’est visible que des personnes en commande avec le compte. Un signalement arrive par l’assistance (motif « Photo de profil inappropriée »).</span></div></div></div>
        @else
          <p class="muted" style="margin:0">Aucune photo en ligne : les initiales s’affichent.</p>
        @endif
        @if(count($u['photoHistory']))<ol class="ed-tl">@foreach($u['photoHistory'] as $h)<li><span class="d"><x-fc.icon name="check" :size="14" /></span><p><b>{{ $h['what'] }}</b><br><span class="muted small">{{ $h['when'] }}@if($h['by']) · par {{ $h['by'] }}@endif</span>@if($h['reason'])<br><span class="muted small">Motif : {{ $h['reason'] }}</span>@endif</p></li>@endforeach</ol>@endif
      </section>
      @if(count($u['portfolio']) || count($u['portfolioRemoved']))
      <section class="ed-card" aria-labelledby="h-poa"><h2 id="h-poa">Réalisations</h2>
        <p class="muted" style="margin:0">{{ count($u['portfolio']) }} réalisation(s) en ligne. Retirer une réalisation exige un motif, est inscrit au journal d’audit et notifie la personne ; le fichier est conservé {{ config('freeci.account.removed_photo_days') }} jours (ou le temps d’un dossier ouvert), puis effacé.</p>
        <div style="display:grid;gap:10px">@foreach($u['portfolio'] as $it)
          <div class="po-adm"><img src="{{ route('portfolio.show', [$it['id'], 'card']) }}" alt="{{ $it['title'] }}" width="96" height="64" loading="lazy"><div><b>{{ $it['title'] }}</b><small>{{ $it['description'] }}</small></div>
            @if(auth()->id() !== $u['id'])<details><summary class="btn btn-secondary">Retirer<span class="sr-only"> {{ $it['title'] }}</span></summary>
              <form method="post" action="{{ route('admin.users.portfolio.remove', [$u['id'], $it['id']]) }}" style="display:grid;gap:8px;margin-top:8px" onsubmit="return confirm('Retirer cette réalisation ?')">@csrf
                <div class="field"><label for="pr-{{ $it['id'] }}">Motif (10 à 1000 caractères)</label><textarea class="textarea" id="pr-{{ $it['id'] }}" name="reason" rows="3" required minlength="10" maxlength="1000"></textarea></div>
                <button class="btn btn-danger" type="submit" data-once>Retirer cette réalisation</button></form></details>@endif</div>
        @endforeach</div>
        @if(count($u['portfolioRemoved']))<ol class="ed-tl">@foreach($u['portfolioRemoved'] as $h)<li><span class="d"><x-fc.icon name="check" :size="14" /></span><p><b>Réalisation retirée : {{ $h['title'] }}</b><br><span class="muted small">{{ $h['when'] }}@if($h['by']) · par {{ $h['by'] }}@endif @if($h['reason'])<br>Motif : {{ $h['reason'] }}@endif</span></p></li>@endforeach</ol>@endif
      </section>
      @endif
      <section class="ed-card" aria-labelledby="h-act"><h2 id="h-act">Activité</h2>
        <div class="us-stats">
          <div class="us-s"><small>Commandes client</small><b>{{ $u['ordersAsClient'][0] }}</b><span>dont {{ $u['ordersAsClient'][1] }} en cours</span></div>
          <div class="us-s"><small>Commandes freelance</small><b>{{ $u['ordersAsFreelancer'][0] }}</b><span>dont {{ $u['ordersAsFreelancer'][1] }} en cours</span></div>
          <div class="us-s"><small>Services · missions</small><b>{{ $u['services'] }} · {{ $u['missions'] }}</b><span>{{ $u['services'] }} service(s) · {{ $u['missions'] }} mission(s)</span></div>
        </div>
        <p class="muted small" style="margin:0">Ces informations servent à la gestion du compte. Les conversations, briefs et fichiers privés ne sont pas accessibles depuis cet écran.</p></section>
      <section class="ed-card" aria-labelledby="h-info"><h2 id="h-info">Compte</h2>
        <dl class="us-dl">
          <div><dt>État</dt><dd>@if($u['suspended'])Suspendu depuis le {{ $u['suspendedAt'] }}@else Actif @endif</dd></div>
          <div><dt>Adresse e-mail</dt><dd>{{ $u['verifiedAt'] ? 'Vérifiée le '.$u['verifiedAt'] : 'Non vérifiée' }}</dd></div>
          <div><dt>Inscrit le</dt><dd>{{ $u['since'] }}</dd></div>
          <div><dt>Rôles</dt><dd>{{ count($u['roles']) ? implode(', ', $u['roles']) : 'aucun' }}@if($u['admin']) · <strong>administrateur</strong> (double authentification : {{ $u['mfa'] ? 'activée' : 'inactive' }})@endif@unless($u['admin']) · double authentification : {{ $u['mfa'] ? 'activée' : 'non activée' }}@endunless</dd></div>
        </dl></section>
      <section class="ed-card" aria-labelledby="h-his"><h2 id="h-his">Historique des suspensions</h2>
        @if(count($u['history']))<ol class="ed-tl">@foreach($u['history'] as $h)<li><span class="d"><x-fc.icon name="check" :size="14" /></span><p><b>{{ $h['action'] }}</b> · {{ $h['when'] }} · par {{ $h['actor'] }}<br><span class="muted small">{{ $h['reason'] }}</span></p></li>@endforeach</ol>@else<p class="muted" style="margin:0">Aucune suspension enregistrée.</p>@endif
      </section></div>
      <aside class="ac-side">
        @if($u['mfa'] && ! $u['staff'] && auth()->id() !== $u['id'])
          <section class="ed-ck ph-rm" aria-labelledby="h-mfa"><h3 id="h-mfa" style="color:#a02a1f">Réinitialiser la double authentification</h3>
            <div class="rq-info"><x-fc.icon name="info" :size="18" /><span>À n’utiliser que si la personne a perdu son téléphone <strong>et</strong> ses codes de secours, après avoir vérifié son identité. Toutes ses sessions sont fermées ; elle reçoit le motif et peut réactiver la double authentification. Acte journalisé.</span></div>
            <form method="post" action="{{ route('admin.users.mfa.reset', $u['id']) }}" style="display:grid;gap:12px" onsubmit="return confirm('Réinitialiser la double authentification de ce compte ?')">@csrf
              <div class="field"><label for="f-mfa-reason">Motif et vérification d’identité effectuée (10 à 1000 caractères)</label><textarea class="textarea" id="f-mfa-reason" name="reason" rows="4" required minlength="10" maxlength="1000"></textarea></div>
              <button class="btn btn-danger" type="submit" data-once>Réinitialiser</button></form></section>
        @endif
        @if($u['photo'] && auth()->id() !== $u['id'])
          <section class="ed-ck ph-rm" aria-labelledby="h-rmp"><h3 id="h-rmp" style="color:#a02a1f">Retirer la photo</h3>
            <div class="rq-info"><x-fc.icon name="info" :size="18" /><span>La photo cesse d’être affichée partout. La personne reçoit le motif et peut déposer une autre photo. Le fichier est conservé {{ config('freeci.account.removed_photo_days') }} jours (ou le temps d’un dossier d’assistance ouvert), puis effacé.</span></div>
            <form method="post" action="{{ route('admin.users.photo.remove', $u['id']) }}" style="display:grid;gap:12px" onsubmit="return confirm('Retirer cette photo de profil ?')">@csrf
              <div class="field"><label for="f-photo-reason">Motif (10 à 1000 caractères)</label><textarea class="textarea" id="f-photo-reason" name="reason" rows="4" required minlength="10" maxlength="1000"></textarea></div>
              <button class="btn btn-danger" type="submit" data-once>Retirer la photo</button></form>
            <p class="muted small" style="margin:0">Votre mot de passe sera redemandé s’il y a plus de {{ config('freeci.admin.reauth_minutes') }} minutes. Action inscrite au journal d’audit.</p></section>
        @endif
        @if($u['suspended'])
          <section class="ed-ck md-dec" aria-labelledby="h-rea"><h3 id="h-rea">Réactiver le compte</h3>
            <form method="post" action="{{ route('admin.users.change', [$u['id'], 'reactiver']) }}" style="display:grid;gap:12px" onsubmit="return confirm('Réactiver ce compte ?')">@csrf
              <div class="field"><label for="f-reason">Motif (10 à 1000 caractères)</label><textarea class="textarea" id="f-reason" name="reason" rows="5" required minlength="10" maxlength="1000">{{ old('reason') }}</textarea>@error('reason')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
              <button class="btn btn-primary" type="submit" data-once>Réactiver</button></form></section>
        @elseif($u['admin'])
          <section class="ed-ck"><h3>Administrateur</h3><div class="rq-info"><x-fc.icon name="info" :size="18" /><span>Ce compte porte une habilitation d’administrateur : elle se retire par la console avant toute suspension.</span></div></section>
        @else
          <section class="ed-ck md-dec" style="border-color:#e7b9b3" aria-labelledby="h-sus"><h3 id="h-sus" style="color:#a02a1f">Suspendre le compte</h3>
            <div class="rq-info"><x-fc.icon name="info" :size="18" /><span><strong>Effets :</strong> plus de nouvelle demande, mission, proposition, soumission au contrôle ni nouvelle conversation ; les services et missions en ligne sont masqués. <strong>Aucune commande n’est supprimée ni interrompue</strong> : paiement, livraison, corrections, validation et messages d’une commande active se poursuivent. Le motif est communiqué à l’utilisateur.</span></div>
            <form method="post" action="{{ route('admin.users.change', [$u['id'], 'suspendre']) }}" style="display:grid;gap:12px" onsubmit="return confirm('Suspendre ce compte ?')">@csrf
              <div class="field"><label for="f-reason">Motif (10 à 1000 caractères)</label><textarea class="textarea" id="f-reason" name="reason" rows="5" required minlength="10" maxlength="1000">{{ old('reason') }}</textarea>@error('reason')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
              <button class="btn btn-danger" type="submit" data-once>Suspendre</button></form>
            <p class="muted small" style="margin:0">Votre mot de passe sera redemandé s’il y a plus de {{ config('freeci.admin.reauth_minutes') }} minutes.</p></section>
        @endif
      </aside></div>
  </div>
</x-layouts.admin>

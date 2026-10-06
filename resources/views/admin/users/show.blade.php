<x-layouts.admin title="Compte utilisateur">
  <header class="page-head"><div class="row-top"><div><p class="eyebrow"><a href="{{ route('admin.users') }}">← Utilisateurs</a></p><h1 class="t-h1">{{ $u['name'] }}</h1>
    <p class="muted">{{ $u['email'] }}@if($u['demo']) · compte de démonstration @endif</p></div></div></header>
  <div class="cols">
    <div class="stack-lg">
      <section class="card stack" aria-labelledby="h-info"><h2 class="t-h3" id="h-info">Compte</h2>
        <dl class="stack-sm">
          <div><dt class="muted small">État</dt><dd>@if($u['suspended'])<span class="badge tone-error">Suspendu depuis le {{ $u['suspendedAt'] }}</span>@else<span class="badge tone-success">Actif</span>@endif</dd></div>
          <div><dt class="muted small">Adresse e-mail</dt><dd>{{ $u['verifiedAt'] ? 'Vérifiée le '.$u['verifiedAt'] : 'Non vérifiée' }}</dd></div>
          <div><dt class="muted small">Inscrit le</dt><dd>{{ $u['since'] }}</dd></div>
          <div><dt class="muted small">Rôles</dt><dd>{{ count($u['roles']) ? implode(', ', $u['roles']) : 'aucun' }}@if($u['admin']) · <strong>administrateur</strong> (double authentification : {{ $u['mfa'] ? 'activée' : 'inactive' }})@endif</dd></div>
          <div><dt class="muted small">Commandes en tant que client</dt><dd>{{ $u['ordersAsClient'][0] }} au total, dont {{ $u['ordersAsClient'][1] }} en cours</dd></div>
          <div><dt class="muted small">Commandes en tant que freelance</dt><dd>{{ $u['ordersAsFreelancer'][0] }} au total, dont {{ $u['ordersAsFreelancer'][1] }} en cours</dd></div>
          <div><dt class="muted small">Services · missions</dt><dd>{{ $u['services'] }} service(s) · {{ $u['missions'] }} mission(s)</dd></div>
        </dl>
        <p class="muted small">Ces informations servent à la gestion du compte. Les conversations, briefs et fichiers privés ne sont pas accessibles depuis cet écran.</p>
      </section>
      <section aria-labelledby="h-his"><h2 class="t-h3" id="h-his">Historique des suspensions</h2>
        @if(count($u['history']))<ul class="hist">@foreach($u['history'] as $h)<li><strong>{{ $h['action'] }}</strong> · {{ $h['when'] }} · par {{ $h['actor'] }}<br><span class="muted">{{ $h['reason'] }}</span></li>@endforeach</ul>@else<p class="muted">Aucune suspension enregistrée.</p>@endif
      </section>
    </div>
    <div class="stack">
      @if($u['suspended'])
        <section class="card stack"><h2 class="t-h3">Réactiver le compte</h2>
          <form method="post" action="{{ route('admin.users.change', [$u['id'], 'reactiver']) }}" class="stack" onsubmit="return confirm('Réactiver ce compte ?')">@csrf
            <div class="field"><label for="f-reason">Motif (10 à 1000 caractères)</label><textarea class="textarea" id="f-reason" name="reason" required minlength="10" maxlength="1000">{{ old('reason') }}</textarea>@error('reason')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
            <button class="btn btn-primary" type="submit" data-once>Réactiver</button></form></section>
      @elseif($u['admin'])
        <div class="notice tone-info"><x-fc.icon name="info" /><p>Ce compte porte une habilitation d’administrateur : elle se retire par la console avant toute suspension.</p></div>
      @else
        <section class="card stack"><h2 class="t-h3">Suspendre le compte</h2>
          <div class="notice tone-info"><x-fc.icon name="info" /><p><strong>Effets :</strong> plus de nouvelle demande, mission, proposition, soumission au contrôle ni nouvelle conversation ; les services et missions en ligne sont masqués. <strong>Aucune commande n’est supprimée ni interrompue</strong> : paiement, livraison, corrections, validation et messages d’une commande active se poursuivent. Le motif est communiqué à l’utilisateur.</p></div>
          <form method="post" action="{{ route('admin.users.change', [$u['id'], 'suspendre']) }}" class="stack" onsubmit="return confirm('Suspendre ce compte ?')">@csrf
            <div class="field"><label for="f-reason">Motif (10 à 1000 caractères)</label><textarea class="textarea" id="f-reason" name="reason" required minlength="10" maxlength="1000">{{ old('reason') }}</textarea>@error('reason')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
            <button class="btn btn-danger" type="submit" data-once>Suspendre</button></form>
          <p class="muted small">Votre mot de passe sera redemandé s’il y a plus de {{ config('freeci.admin.reauth_minutes') }} minutes.</p></section>
      @endif
    </div>
  </div>
</x-layouts.admin>

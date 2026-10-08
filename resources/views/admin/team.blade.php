@php($ini = fn (string $n) => collect(preg_split('/\s+/', trim($n)) ?: [])->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') ?: '?')
<x-layouts.admin title="Équipe et habilitations">
  <div class="page-body">
    <div class="sx-head"><div><p class="sx-kicker">Administration</p><h1>Équipe et habilitations</h1><p class="muted">Qui peut administrer FreeCI et qui traite les dossiers d’assistance.</p></div></div>
    <div class="ac-grid"><div class="ac-main">
    <section class="ed-card" aria-labelledby="h-adm"><div class="pl-sech"><h2 id="h-adm">Administrateurs</h2><span>{{ count($admins) }} personne{{ count($admins) > 1 ? 's' : '' }}</span></div>
      <div class="rq-info" role="note"><x-fc.icon name="warn" :size="18" /><span><strong>Un administrateur peut tout faire :</strong> opérations financières, paramètres, modération, comptes, journal d’audit. N’accordez ce pouvoir qu’à une personne de confiance. La personne doit avoir un compte à adresse vérifiée ; son accès s’ouvre après activation de la double authentification. Vous ne pouvez pas retirer votre propre habilitation, ni la dernière.</span></div>
      @foreach($admins as $m)
        <div class="tm-m"><span class="us-av" aria-hidden="true">{{ $ini($m['name']) }}</span><div><b>{{ $m['name'] }}@if($m['userId'] === $me) <span class="muted small">(vous)</span>@endif</b><small>{{ $m['email'] }} · depuis le {{ $m['since'] }}@if($m['until']) · jusqu’au {{ $m['until'] }}@endif · accordé par {{ $m['by'] }}</small></div>
          <span class="badge {{ $m['ready'] ? 'tone-success' : 'tone-warning' }}">{{ $m['ready'] ? 'Accès prêt' : 'Activation en attente' }}</span></div>
        @if($m['userId'] !== $me)
          <details class="rv-act"><summary><span>Retirer l’habilitation d’administrateur de {{ $m['name'] }}</span><x-fc.icon name="chev-down" :size="16" /></summary>
            <form method="post" action="{{ route('admin.team.admin.revoke', $m['userId']) }}" class="in" data-once>@csrf
              <div class="field"><label for="ra-{{ $m['userId'] }}">Motif (10 caractères minimum)</label><textarea class="textarea" id="ra-{{ $m['userId'] }}" name="reason" rows="2" minlength="10" maxlength="1000" required></textarea></div>
              <label class="check"><input type="checkbox" name="confirm" value="1" required> <span>Je confirme le retrait de ce pouvoir.</span></label>
              <div><button class="btn btn-secondary" type="submit">Retirer</button></div></form></details>
        @endif
      @endforeach
      <details class="rv-act"><summary><span>Accorder l’habilitation d’administrateur</span><x-fc.icon name="chev-down" :size="16" /></summary>
        <form method="post" action="{{ route('admin.team.admin.grant') }}" class="in" data-once>@csrf
          <div class="ac-r2"><div class="field"><label for="ga-email">Adresse e-mail du compte</label><input class="input" id="ga-email" name="email" type="email" value="{{ old('email') }}" maxlength="254" required autocomplete="off"></div>
          <div class="field"><label for="ga-until">Jusqu’au (facultatif)</label><input class="input" id="ga-until" name="until" type="date" value="{{ old('until') }}"></div></div>
          <div class="field"><label for="ga-reason">Motif (10 caractères minimum)</label><textarea class="textarea" id="ga-reason" name="reason" rows="2" minlength="10" maxlength="1000" required>{{ old('reason') }}</textarea></div>
          <div class="field"><label for="ga-phrase">Pour confirmer, saisissez exactement : <strong>{{ $phrase }}</strong></label><input class="input" id="ga-phrase" name="phrase" autocomplete="off" required></div>
          <label class="check"><input type="checkbox" name="confirm" value="1" required> <span>Je comprends que cette personne aura tous les pouvoirs d’administration.</span></label>
          <div><button class="btn btn-primary" type="submit">Accorder le pouvoir d’administrateur</button></div>
        </form></details>
    </section>

    <section class="ed-card" aria-labelledby="h-team"><div class="pl-sech"><h2 id="h-team">Équipe d’assistance</h2><span>{{ count($members) }} personne{{ count($members) > 1 ? 's' : '' }}</span></div>
      @forelse($members as $m)
        <div class="tm-m"><span class="us-av" aria-hidden="true">{{ $ini($m['name']) }}</span><div><b>{{ $m['name'] }}</b><small>{{ $m['email'] }} · depuis le {{ $m['since'] }}@if($m['until']) · jusqu’au {{ $m['until'] }}@endif</small><small>Motif : {{ $m['reason'] }}</small></div>
          <span class="badge {{ $m['ready'] ? 'tone-success' : 'tone-warning' }}">{{ $m['ready'] ? 'Accès prêt' : 'Activation en attente' }}</span></div>
        <details class="rv-act"><summary><span>Retirer l’habilitation de {{ $m['name'] }}</span><x-fc.icon name="chev-down" :size="16" /></summary>
          <form method="post" action="{{ route('admin.team.revoke', $m['userId']) }}" class="in" data-once>@csrf
            <div class="field"><label for="r-{{ $m['userId'] }}">Motif (10 caractères minimum)</label><textarea class="textarea" id="r-{{ $m['userId'] }}" name="reason" rows="2" minlength="10" maxlength="1000" required></textarea></div>
            <div><button class="btn btn-secondary" type="submit">Retirer</button></div></form></details>
      @empty
        <div class="empty" style="display:grid;justify-items:center;text-align:center;gap:8px;padding:12px"><span class="ico-lg"><x-fc.icon name="user" :size="26" /></span><p style="font-weight:600">Aucune personne dans l’équipe d’assistance.</p><p class="muted">Seul l’administrateur traite les dossiers pour l’instant.</p></div>
      @endforelse
      <details class="rv-act" @if($errors->any() || old('email')) open @endif><summary><span>Assistance : accorder l’habilitation</span><x-fc.icon name="chev-down" :size="16" /></summary>
        <form method="post" action="{{ route('admin.team.grant') }}" class="in" data-once>@csrf
          <div class="ac-r2"><div class="field"><label for="g-email">Adresse e-mail du compte</label><input class="input" id="g-email" name="email" type="email" value="{{ old('email') }}" maxlength="254" required autocomplete="off"></div>
          <div class="field"><label for="g-until">Jusqu’au (facultatif)</label><input class="input" id="g-until" name="until" type="date" value="{{ old('until') }}"></div></div>
          <div class="field"><label for="g-reason">Motif (10 caractères minimum)</label><textarea class="textarea" id="g-reason" name="reason" rows="2" minlength="10" maxlength="1000" required>{{ old('reason') }}</textarea></div>
          <div><button class="btn btn-primary" type="submit">Accorder</button></div>
        </form></details>
    </section></div>
    <aside class="ac-side"><section class="ed-ck tm-cap" aria-labelledby="h-cap"><h3 id="h-cap">Ce que permet chaque habilitation</h3>
      <p style="margin:0"><b>Administrateur</b></p><ul><li class="y"><x-fc.icon name="check" :size="16" />Opérations financières et paramètres</li><li class="y"><x-fc.icon name="check" :size="16" />Modération et gestion des comptes</li><li class="y"><x-fc.icon name="check" :size="16" />Journal d’audit et assistance</li></ul>
      <p style="margin:6px 0 0"><b>Support</b></p><ul><li class="y"><x-fc.icon name="check" :size="16" />Traitement des dossiers d’assistance</li><li class="n"><x-fc.icon name="close" :size="16" /><span>Ni modération, ni gestion des comptes, ni journal d’audit, ni opération financière</span></li></ul></section>
      <section class="ed-ck"><h3>Avant d’accorder</h3><ul class="ac-ess"><li><x-fc.icon name="check" :size="18" /><div>Un compte à adresse vérifiée</div></li><li><x-fc.icon name="shield" :size="18" /><div>Double authentification activée : l’accès ne s’ouvre qu’ensuite</div></li></ul></section></aside></div>
  </div>
</x-layouts.admin>

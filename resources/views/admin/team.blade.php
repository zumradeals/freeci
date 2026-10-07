<x-layouts.admin title="Équipe et habilitations">
  <header class="page-head"><div class="row-top"><div><p class="eyebrow">Administration</p><h1 class="t-h1">Équipe et habilitations</h1><p class="lead">Qui peut administrer FreeCI et qui traite les dossiers d’assistance.</p></div></div></header>
  <div class="page-body">
    <section class="card panel" aria-labelledby="h-adm"><div class="card-head"><h2 class="t-h2" id="h-adm">Administrateurs</h2><span class="muted small">{{ count($admins) }} personne{{ count($admins) > 1 ? 's' : '' }}</span></div>
      <div class="notice tone-warning" role="note"><x-fc.icon name="warn" /><p><strong>Un administrateur peut tout faire :</strong> opérations financières, paramètres, modération, comptes, journal d’audit. N’accordez ce pouvoir qu’à une personne de confiance. La personne doit avoir un compte à adresse vérifiée ; son accès s’ouvre après activation de la double authentification. Vous ne pouvez pas retirer votre propre habilitation, ni la dernière.</p></div>
      @foreach($admins as $m)
        <article class="record"><div class="record-head"><div><strong>{{ $m['name'] }}@if($m['userId'] === $me) <span class="muted small">(vous)</span>@endif</strong><p class="muted small">{{ $m['email'] }} · depuis le {{ $m['since'] }}@if($m['until']) · jusqu’au {{ $m['until'] }}@endif · accordé par {{ $m['by'] }}</p></div>
          <span class="badge {{ $m['ready'] ? 'tone-success' : 'tone-warning' }}">{{ $m['ready'] ? 'Accès prêt' : 'Activation en attente' }}</span></div>
          @if($m['userId'] !== $me)
          <details><summary>Retirer l’habilitation d’administrateur</summary>
            <form method="post" action="{{ route('admin.team.admin.revoke', $m['userId']) }}" class="stack-sm mt-8" data-once>@csrf
              <div class="field"><label for="ra-{{ $m['userId'] }}">Motif (10 caractères minimum)</label><textarea class="textarea" id="ra-{{ $m['userId'] }}" name="reason" rows="2" minlength="10" maxlength="1000" required></textarea></div>
              <label class="check"><input type="checkbox" name="confirm" value="1" required> <span>Je confirme le retrait de ce pouvoir.</span></label>
              <button class="btn btn-secondary" type="submit">Retirer</button></form></details>
          @endif</article>
      @endforeach
      <details><summary>Accorder l’habilitation d’administrateur</summary>
        <form method="post" action="{{ route('admin.team.admin.grant') }}" class="form-grid mt-8" data-once>@csrf
          <div class="field"><label for="ga-email">Adresse e-mail du compte</label><input class="input" id="ga-email" name="email" type="email" value="{{ old('email') }}" maxlength="254" required autocomplete="off"></div>
          <div class="field"><label for="ga-until">Jusqu’au (facultatif)</label><input class="input" id="ga-until" name="until" type="date" value="{{ old('until') }}"></div>
          <div class="field" style="grid-column:1/-1"><label for="ga-reason">Motif (10 caractères minimum)</label><textarea class="textarea" id="ga-reason" name="reason" rows="2" minlength="10" maxlength="1000" required>{{ old('reason') }}</textarea></div>
          <div class="field" style="grid-column:1/-1"><label for="ga-phrase">Pour confirmer, saisissez exactement : <strong>{{ $phrase }}</strong></label><input class="input" id="ga-phrase" name="phrase" autocomplete="off" required></div>
          <label class="check" style="grid-column:1/-1"><input type="checkbox" name="confirm" value="1" required> <span>Je comprends que cette personne aura tous les pouvoirs d’administration.</span></label>
          <div><button class="btn btn-primary" type="submit">Accorder le pouvoir d’administrateur</button></div>
        </form></details>
    </section>

    <div class="notice tone-info"><x-fc.icon name="info" /><p>L’habilitation « support » ne donne <strong>ni modération, ni gestion des comptes, ni journal d’audit, ni opération financière</strong>. La personne doit avoir un compte, une adresse vérifiée et la double authentification activée. </p></div>

    <section class="card panel" aria-labelledby="h-add"><div class="card-head"><h2 class="t-h2" id="h-add">Assistance : accorder l’habilitation</h2></div>
      <form method="post" action="{{ route('admin.team.grant') }}" class="form-grid" data-once>@csrf
        <div class="field"><label for="g-email">Adresse e-mail du compte</label><input class="input" id="g-email" name="email" type="email" value="{{ old('email') }}" maxlength="254" required autocomplete="off"></div>
        <div class="field"><label for="g-until">Jusqu’au (facultatif)</label><input class="input" id="g-until" name="until" type="date" value="{{ old('until') }}"></div>
        <div class="field" style="grid-column:1/-1"><label for="g-reason">Motif (10 caractères minimum)</label><textarea class="textarea" id="g-reason" name="reason" rows="2" minlength="10" maxlength="1000" required>{{ old('reason') }}</textarea></div>
        <div><button class="btn btn-primary" type="submit">Accorder</button></div>
      </form>
    </section>

    <section class="card panel" aria-labelledby="h-team"><div class="card-head"><h2 class="t-h2" id="h-team">Équipe d’assistance actuelle</h2><span class="muted small">{{ count($members) }} personne{{ count($members) > 1 ? 's' : '' }}</span></div>
      @forelse($members as $m)
        <article class="record"><div class="record-head"><div><strong>{{ $m['name'] }}</strong><p class="muted small">{{ $m['email'] }} · depuis le {{ $m['since'] }}@if($m['until']) · jusqu’au {{ $m['until'] }}@endif</p><p class="muted small">Motif : {{ $m['reason'] }}</p></div>
          <span class="badge {{ $m['ready'] ? 'tone-success' : 'tone-warning' }}">{{ $m['ready'] ? 'Accès prêt' : 'Activation en attente' }}</span></div>
          <details><summary>Retirer l’habilitation</summary>
            <form method="post" action="{{ route('admin.team.revoke', $m['userId']) }}" class="stack-sm mt-8" data-once>@csrf
              <div class="field"><label for="r-{{ $m['userId'] }}">Motif (10 caractères minimum)</label><textarea class="textarea" id="r-{{ $m['userId'] }}" name="reason" rows="2" minlength="10" maxlength="1000" required></textarea></div>
              <button class="btn btn-secondary" type="submit">Retirer</button></form></details></article>
      @empty
        <div class="card empty"><span class="ico-lg"><x-fc.icon name="user" :size="26" /></span><p style="font-weight:600">Aucune personne dans l’équipe d’assistance.</p><p class="muted">Seul l’administrateur traite les dossiers pour l’instant.</p></div>
      @endforelse
    </section>
  </div>
</x-layouts.admin>

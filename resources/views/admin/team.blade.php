<x-layouts.admin title="Équipe d’assistance">
  <header class="page-head"><div class="row-top"><div><p class="eyebrow">Administration</p><h1 class="t-h1">Équipe d’assistance</h1><p class="lead">Les personnes qui traitent les dossiers d’assistance qui leur sont affectés.</p></div></div></header>
  <div class="page-body">
    <div class="notice tone-info"><x-fc.icon name="info" /><p>L’habilitation « support » ne donne <strong>ni modération, ni gestion des comptes, ni journal d’audit, ni opération financière</strong>. La personne doit avoir un compte, une adresse vérifiée et la double authentification activée. L’habilitation d’administrateur ne s’accorde pas ici : elle reste réservée à la console du serveur.</p></div>

    <section class="card panel" aria-labelledby="h-add"><div class="card-head"><h2 class="t-h2" id="h-add">Accorder l’habilitation</h2></div>
      <form method="post" action="{{ route('admin.team.grant') }}" class="form-grid" data-once>@csrf
        <div class="field"><label for="g-email">Adresse e-mail du compte</label><input class="input" id="g-email" name="email" type="email" value="{{ old('email') }}" maxlength="254" required autocomplete="off"></div>
        <div class="field"><label for="g-until">Jusqu’au (facultatif)</label><input class="input" id="g-until" name="until" type="date" value="{{ old('until') }}"></div>
        <div class="field" style="grid-column:1/-1"><label for="g-reason">Motif (10 caractères minimum)</label><textarea class="textarea" id="g-reason" name="reason" rows="2" minlength="10" maxlength="1000" required>{{ old('reason') }}</textarea></div>
        <div><button class="btn btn-primary" type="submit">Accorder</button></div>
      </form>
    </section>

    <section class="card panel" aria-labelledby="h-team"><div class="card-head"><h2 class="t-h2" id="h-team">Équipe actuelle</h2><span class="muted small">{{ count($members) }} personne{{ count($members) > 1 ? 's' : '' }}</span></div>
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

@php
  $dl = $d->delivery;
  $titles = ['submit' => 'Soumettre la livraison v'.(($dl['latestVersion'] ?? 0) + 1), 'correction' => 'Demander une correction', 'validate' => 'Valider la livraison v'.($dl['latestVersion'] ?? ''),
    'disagreement' => 'Signaler un désaccord', 'extension' => 'Proposer un report d’échéance', 'extension-accept' => 'Accepter le report d’échéance', 'extension-decline' => 'Refuser le report d’échéance'];
  $title = $titles[$kind];
  $c = $dl['corrections'];
  $ext = $dl['extension'] ?? null;
  $maxDays = (int) config('freeci.orders.extension_max_days');
@endphp
<x-layouts.account :title="$title" :space="$space">
  <nav class="crumbs" aria-label="Fil d’Ariane"><a class="back-m" href="{{ route('orders.show', $d->reference) }}"><x-fc.icon name="arrow-right" :size="16" class="flip" />Retour à la commande</a><a class="hide-m" href="{{ route('orders.show', $d->reference) }}">Commande {{ $d->reference }}</a><span class="sep hide-m" aria-hidden="true">›</span><span class="hide-m" aria-current="page">{{ $title }}</span></nav>
  <div class="sx-head"><div><p class="sx-kicker">Commande {{ $d->reference }}</p><h1 id="c-title">{{ $title }}</h1><p class="muted">{{ $d->title }} · {{ $d->otherPartyLabel }} : {{ $d->otherPartyName }}</p></div></div>
  @if($errors->any())<div class="rq-info" role="alert"><x-fc.icon name="error" :size="18" /><span>{{ $errors->first() }}</span></div>@endif
  <div class="ac-grid" style="margin-top:16px">
    @if($kind === 'submit')
      @php($files = collect($draft['files'])->where('tone', 'success'))
      <form method="post" action="{{ route('orders.delivery.submit', $d->reference) }}" data-once>@csrf
        <input type="hidden" name="delivery_id" value="{{ $draft['id'] }}"><input type="hidden" name="expected_version" value="{{ $d->version }}"><input type="hidden" name="operation_key" value="{{ $operationKey }}">
        <section class="ed-card" aria-labelledby="h-act"><h2 id="h-act">Votre livraison</h2><p class="muted">Livraison v{{ ($dl['latestVersion'] ?? 0) + 1 }} · {{ $files->count() }} fichier{{ $files->count() > 1 ? 's' : '' }}</p>
          <div class="ac-acts"><button class="btn btn-primary btn-lg" type="submit" data-once-label="Envoi…">Soumettre la livraison</button><a class="btn btn-link" href="{{ route('orders.delivery', $d->reference) }}">Revenir au brouillon</a></div></section></form>
      <aside class="ac-side"><section class="ed-ck" aria-labelledby="h-wh"><h3 id="h-wh">Ce qui va se passer</h3><ul class="ac-ess">
        <li><x-fc.icon name="check" :size="18" /><div>La livraison <strong>v{{ ($dl['latestVersion'] ?? 0) + 1 }}</strong> ({{ $files->count() }} fichier{{ $files->count() > 1 ? 's' : '' }}) est <strong>conservée telle quelle</strong> : vous ne pourrez plus la modifier.</div></li>
        <li><x-fc.icon name="check" :size="18" /><div>{{ $d->otherPartyName }} peut l’examiner pendant {{ config('freeci.orders.review_days') }} jours, demander une correction ou la valider.</div></li>
        <li><x-fc.icon name="check" :size="18" /><div>Une nouvelle version <strong>ne remplace pas</strong> la précédente : toutes restent consultables.</div></li>
        @if($dl['extension'])<li><x-fc.icon name="info" :size="18" /><div>Votre proposition de report en attente sera retirée.</div></li>@endif</ul></section></aside>

    @elseif($kind === 'correction')
      @if($c['remaining'] === 0)
        <section class="ed-card"><h2>Corrections épuisées</h2><div class="rq-info"><x-fc.icon name="warn" :size="18" /><span>Les corrections incluses dans l’accord sont toutes utilisées ({{ $c['used'] }} sur {{ $c['included'] }}). Vous pouvez valider la livraison, ou la laisser non validée : rien ne vous y oblige. Tout changement de périmètre passe par une nouvelle commande.</span></div>
          <div><a class="btn btn-secondary" href="{{ route('orders.show', $d->reference) }}">Revenir à la commande</a></div></section>
      @else
      <form method="post" action="{{ route('orders.correction.store', $d->reference) }}" data-once novalidate>@csrf
        <input type="hidden" name="delivery_id" value="{{ $dl['latestId'] }}"><input type="hidden" name="expected_version" value="{{ $d->version }}"><input type="hidden" name="operation_key" value="{{ $operationKey }}">
        <section class="ed-card" aria-labelledby="h-act"><h2 id="h-act">Points à corriger</h2><p class="muted" style="margin-top:-8px">Correction n° {{ $c['used'] + 1 }} sur {{ $c['included'] }} · liée à la livraison v{{ $dl['latestVersion'] }}</p>
          <div class="field"><label for="reason">Points à corriger <span class="req">(obligatoire, 15 caractères minimum)</span></label>
            <textarea class="textarea" id="reason" name="reason" rows="5" maxlength="2000" required @error('reason') aria-invalid="true" @enderror>{{ old('reason') }}</textarea>
            <p class="hint">Restez dans le périmètre convenu : un changement de périmètre ou de prix passe par une nouvelle commande.</p>
            @error('reason')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
          <div class="rq-info"><x-fc.icon name="warn" :size="18" /><span>@if($c['remaining'] === 1)Après cette demande, <strong>vous n’aurez plus de correction incluse</strong>.@else Il vous restera <strong>{{ $c['remaining'] - 1 }} correction{{ $c['remaining'] - 1 > 1 ? 's' : '' }}</strong> sur {{ $c['included'] }}.@endif {{ $d->otherPartyName }} répond par une nouvelle version.</span></div>
          <div class="ac-acts"><button class="btn btn-primary btn-lg" type="submit" data-once-label="Envoi…">Envoyer la demande de correction</button><a class="btn btn-link" href="{{ route('orders.show', $d->reference) }}">Revenir à la commande</a></div></section></form>
      @endif
      <aside class="ac-side"><section class="ed-ck"><h3>Corrections incluses</h3><div class="dl-counter"><span class="dl-dots" aria-hidden="true">@for($i = 0; $i < $c['included']; $i++)<i class="{{ $i < $c['used'] ? 'u' : '' }}"></i>@endfor</span><b>{{ $c['used'] }} utilisée{{ $c['used'] > 1 ? 's' : '' }} sur {{ $c['included'] }}</b></div></section></aside>

    @elseif($kind === 'disagreement')
      <form method="post" action="{{ route('orders.disagreement.store', $d->reference) }}" data-once novalidate>@csrf
        <input type="hidden" name="delivery_id" value="{{ $dl['latestId'] }}"><input type="hidden" name="expected_version" value="{{ $d->version }}"><input type="hidden" name="operation_key" value="{{ $operationKey }}">
        <section class="ed-card" aria-labelledby="h-act"><h2 id="h-act">Votre désaccord</h2>
          <div class="field"><label for="note">Votre désaccord <span class="req">(obligatoire, 15 caractères minimum)</span></label>
            <textarea class="textarea" id="note" name="note" rows="5" maxlength="2000" required @error('note') aria-invalid="true" @enderror>{{ old('note') }}</textarea>
            @error('note')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
          <div class="ac-acts"><button class="btn btn-primary btn-lg" type="submit" data-once-label="Envoi…">Enregistrer mon désaccord</button><a class="btn btn-link" href="{{ route('orders.show', $d->reference) }}">Revenir à la commande</a></div></section></form>
      <aside class="ac-side"><section class="ed-ck" aria-labelledby="h-wh"><h3 id="h-wh">Vous n’êtes pas obligé de valider</h3><ul class="ac-ess">
        <li><x-fc.icon name="check" :size="18" /><div>Vos corrections incluses sont utilisées ({{ $c['used'] }} sur {{ $c['included'] }}). Vous pouvez laisser la livraison <strong>non validée</strong> : la commande reste ouverte, rien n’est validé ni clôturé à votre place.</div></li>
        <li><x-fc.icon name="check" :size="18" /><div>Signaler un désaccord <strong>enregistre un besoin de suivi</strong> avec votre message, visible des deux parties dans l’historique.</div></li>
        <li><x-fc.icon name="info" :size="18" /><div><strong>Aucun support n’est contacté automatiquement</strong> et ce n’est pas un litige : ce dispositif n’existe pas encore dans cette version.</div></li></ul></section></aside>

    @elseif($kind === 'validate')
      @php($latest = collect($dl['deliveries'])->firstWhere('isLatest', true))
      <form method="post" action="{{ route('orders.validation.store', $d->reference) }}" data-once novalidate>@csrf
        <input type="hidden" name="delivery_id" value="{{ $dl['latestId'] }}"><input type="hidden" name="expected_version" value="{{ $d->version }}"><input type="hidden" name="operation_key" value="{{ $operationKey }}">
        <section class="ed-card" aria-labelledby="h-act"><h2 id="h-act">Votre validation</h2><p class="muted">Livraison v{{ $dl['latestVersion'] }} · {{ count($latest['files'] ?? []) }} fichier{{ count($latest['files'] ?? []) > 1 ? 's' : '' }} · déposée le {{ $latest['when'] ?? '' }}</p>
          <div class="field"><label class="check" for="confirm"><input type="checkbox" id="confirm" name="confirm" value="1" required @error('confirm') aria-invalid="true" @enderror> <span>J’ai examiné la livraison v{{ $dl['latestVersion'] }} et je la valide.</span></label>
            @error('confirm')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
          <div class="ac-acts"><button class="btn btn-primary btn-lg" type="submit" data-once-label="Validation…">Valider la livraison</button><a class="btn btn-link" href="{{ route('orders.show', $d->reference) }}">Revenir à la commande</a></div></section></form>
      <aside class="ac-side"><section class="ed-ck" aria-labelledby="h-wh"><h3 id="h-wh">Ce qui va se passer</h3><ul class="ac-ess">
        <li><x-fc.icon name="check" :size="18" /><div>La commande est <strong>clôturée</strong>.</div></li>
        <li><x-fc.icon name="check" :size="18" /><div>Vous ne pouvez <strong>plus demander de correction</strong>.</div></li>
        <li><x-fc.icon name="info" :size="18" /><div>Cette validation <strong>ne confirme ni ne déclenche aucun reversement</strong> : le suivi financier reste séparé et n’existe pas encore dans cette version.</div></li></ul></section></aside>

    @elseif($kind === 'extension')
      @php($due = $d->dueAt)
      <form method="post" action="{{ route('orders.extension.store', $d->reference) }}" data-once novalidate>@csrf
        <input type="hidden" name="expected_version" value="{{ $d->version }}"><input type="hidden" name="operation_key" value="{{ $operationKey }}">
        <section class="ed-card" aria-labelledby="h-act"><h2 id="h-act">Votre proposition</h2><p class="muted" style="margin-top:-8px">Échéance actuelle : <strong>{{ \App\Shared\Dates::format($due) }}</strong>. Elle ne change que si {{ $d->otherPartyName }} accepte.</p>
          <div class="field"><label for="proposed_date">Nouvelle date d’échéance <span class="req">(obligatoire)</span></label>
            <input class="input" type="date" id="proposed_date" name="proposed_date" required value="{{ old('proposed_date') }}" min="{{ $due->copy()->addDay()->format('Y-m-d') }}" max="{{ $due->copy()->addDays($maxDays)->format('Y-m-d') }}" aria-describedby="pd-h">
            <p class="hint" id="pd-h">Au plus {{ $maxDays }} jours après l’échéance actuelle ; l’heure reste {{ $due->copy()->timezone('Africa/Abidjan')->format('H:i') }}.</p>
            @error('proposed_date')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
          <div class="field"><label for="reason">Motif <span class="req">(obligatoire, visible du client)</span></label>
            <textarea class="textarea" id="reason" name="reason" rows="4" maxlength="1000" required @error('reason') aria-invalid="true" @enderror>{{ old('reason') }}</textarea>
            @error('reason')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
          <div class="ac-acts"><button class="btn btn-primary btn-lg" type="submit" data-once-label="Envoi…">Envoyer la proposition</button><a class="btn btn-link" href="{{ route('orders.show', $d->reference) }}">Revenir à la commande</a></div></section></form>

    @else
      <form method="post" action="{{ route('orders.extension.answer.store', [$d->reference, $kind === 'extension-accept' ? 'accepter' : 'refuser']) }}" data-once novalidate>@csrf
        <input type="hidden" name="extension_id" value="{{ $ext['id'] }}"><input type="hidden" name="expected_version" value="{{ $d->version }}"><input type="hidden" name="operation_key" value="{{ $operationKey }}">
        <section class="ed-card" aria-labelledby="h-act"><h2 id="h-act">Votre réponse</h2>
          <div class="field"><label for="note">Message au freelance <span class="req">(facultatif)</span></label><textarea class="textarea" id="note" name="note" rows="3" maxlength="1000">{{ old('note') }}</textarea></div>
          <div class="ac-acts"><button class="btn {{ $kind === 'extension-accept' ? 'btn-primary' : 'btn-secondary' }} btn-lg" type="submit" data-once-label="Enregistrement…">{{ $kind === 'extension-accept' ? 'Accepter le report' : 'Refuser le report' }}</button><a class="btn btn-link" href="{{ route('orders.show', $d->reference) }}">Revenir</a></div></section></form>
      <aside class="ac-side"><section class="ed-ck" aria-labelledby="h-wh"><h3 id="h-wh">Proposition de {{ $d->freelancerName }}</h3>
        <dl class="as-sum"><div><dt>Échéance actuelle</dt><dd>{{ $ext['previous'] }}</dd></div><div><dt>Échéance proposée</dt><dd>{{ $ext['proposed'] }}</dd></div><div><dt>Motif</dt><dd>« {{ $ext['reason'] }} »</dd></div></dl>
        <p class="muted small">{{ $kind === 'extension-accept' ? 'Si vous acceptez, la nouvelle échéance remplace l’actuelle ; l’ancienne est conservée dans l’historique.' : 'Si vous refusez, l’échéance reste inchangée.' }}</p></section></aside>
    @endif
  </div>
</x-layouts.account>

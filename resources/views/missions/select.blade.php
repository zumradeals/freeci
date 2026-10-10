<x-layouts.account title="Retenir une proposition" space="client">
  <div class="page-body">
    <nav class="ed-crumbs" aria-label="Fil d’Ariane"><a href="{{ route('client.missions') }}">Mes missions</a><span aria-hidden="true">/</span><a href="{{ route('client.missions.proposals', $mission->getKey()) }}">Propositions</a><span aria-hidden="true">/</span><span aria-current="page">Retenir une proposition</span></nav>
    <header class="sx-head"><div><p class="sx-kicker">Dernière étape avant la commande</p><h1 id="c-title">Retenir la proposition de {{ $item['author'] }}</h1><p class="muted">{{ $live->title }} · version {{ $item['number'] }} de la proposition</p></div></header>
    <div class="ed-grid">
      <div class="ed-main">
        <section class="ed-card" aria-labelledby="c-title"><h2>Conditions que vous acceptez</h2>
      <dl class="defs"><div><dt>Prix ferme</dt><dd><x-fc.money :amount="$item['price']" /></dd></div><div><dt>Délai</dt><dd>{{ $item['days'] }} jours à partir du départ du travail</dd></div><div><dt>Corrections incluses</dt><dd>{{ $item['revisions'] }}</dd></div>
        <div><dt>Périmètre</dt><dd>{{ $item['scope'] }}</dd></div><div><dt>Livrables</dt><dd>@foreach($item['deliverables'] as $d){{ $d }}@if(! $loop->last)<br>@endif @endforeach</dd></div><div><dt>Mode de livraison</dt><dd>{{ $item['mode'] }}</dd></div><div><dt>Valable jusqu’au</dt><dd>{{ $item['validUntil'] }}</dd></div></dl>
        </section>
        @if(count($item['milestones']))<section class="ed-card" aria-labelledby="h-plan"><h2 id="h-plan">Plan de paiement par jalons (figé à la sélection)</h2>
          <ol class="jl-tl">@foreach($item['milestones'] as $x)<li class="{{ $loop->first ? 'cur' : '' }}"><span class="dot">{{ $x['rank'] }}</span><div><b>{{ $x['title'] }}</b><small>{{ $x['days'] }} jours · {{ $loop->first ? 'commande créée maintenant' : 's’ouvre après validation du jalon '.($x['rank'] - 1) }}</small></div><b>{{ $x['price']->formatted() }} FCFA</b></li>@endforeach</ol>
          <div class="jl-sum"><div><span>Total des {{ count($item['milestones']) }} jalons</span><span>{{ $item['price']->formatted() }} FCFA</span></div><div class="tot"><span>À payer maintenant (jalon 1)</span><span>{{ $item['milestones'][0]['price']->formatted() }} FCFA</span></div></div>
          <p class="muted small" style="margin:0">Le brief saisi à cette étape sert aux {{ count($item['milestones']) }} jalons. Vous pourrez arrêter le plan après un jalon validé : les jalons non ouverts ne sont jamais dus.</p></section>@endif
        <section class="ed-card">
      @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>Vérifiez les champs signalés ci-dessous. Vos saisies sont conservées.</p></div>@endif
      <form method="post" action="{{ route('client.missions.select.store', [$mission->getKey(), $item['versionId']]) }}" data-once style="display:grid;gap:16px;margin-top:16px" novalidate>@csrf
        <input type="hidden" name="expected_version" value="{{ $mission->row_version }}"><input type="hidden" name="operation_key" value="{{ $operationKey }}">
        @if(count($live->client_inputs))<h2 class="t-h2 card-title">Votre brief (privé)</h2><p class="muted small">Vos réponses ne sont communiquées qu’au freelance retenu, dans la commande.</p>
          @foreach($live->client_inputs as $k => $label)@php($e = $errors->first('answers.'.$k))<div class="field"><label for="a-{{ $k }}">{{ $label }}</label><textarea class="textarea" id="a-{{ $k }}" name="answers[{{ $k }}]" rows="2" maxlength="1000" required @if($e) aria-invalid="true" aria-describedby="ae-{{ $k }}" @endif>{{ old('answers.'.$k) }}</textarea>@if($e)<p class="field-error" id="ae-{{ $k }}"><x-fc.icon name="error" :size="16" />{{ $e }}</p>@endif</div>@endforeach @endif
        <div class="field"><label for="notes">Précisions (facultatif)</label><textarea class="textarea" id="notes" name="notes" rows="3" maxlength="3000">{{ old('notes') }}</textarea></div>
        <div class="field"><label class="check" for="conditions"><input type="checkbox" id="conditions" name="conditions" value="1" required @checked(old('conditions'))> <span>J’ai lu les conditions de cette proposition (prix, délai, corrections, périmètre, livrables) et je les accepte.</span></label>@error('conditions')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
        <div class="row"><button class="btn btn-primary btn-lg" type="submit" data-once-label="Enregistrement…">Retenir cette proposition</button><a class="btn btn-link" href="{{ route('client.missions.proposals', $mission->getKey()) }}">Revenir aux propositions</a></div>
      </form>
        </section>
      </div>
      <aside class="ed-side" aria-label="Ce qui va se passer">
        <div class="ed-ck"><h3>Ce qui va se passer</h3><ol class="ed-tl">
          <li><span class="d">1</span><p>La mission est <strong>réservée</strong> et une <strong>commande</strong> est créée avec ces conditions <strong>figées</strong> : elles ne seront jamais recalculées.</p></li>
          <li><span class="d">2</span><p>La commande attend le <strong>paiement</strong>. La mission n’est <strong>attribuée</strong> qu’une fois le paiement confirmé côté serveur ; <strong>aucun travail ne commence</strong> sans paiement confirmé et brief complet.</p></li>
          <li><span class="d">3</span><p>Si vous annulez la commande ou si elle expire avant paiement, la proposition est libérée et la mission passe en « sélection terminée » : <strong>rien n’est rouvert automatiquement</strong>, vous choisissez de la rouvrir ou de la fermer.</p></li></ol></div>
        <p class="rq-info"><x-fc.icon name="info" :size="18" /><span>Vous ne pouvez retenir qu’<strong>une seule</strong> proposition à la fois.</span></p>
      </aside>
    </div>
  </div>
</x-layouts.account>

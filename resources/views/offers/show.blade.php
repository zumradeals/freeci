<x-layouts.account :title="'Offre : '.$o['title']" :space="$space">
  <div class="page-body">
    <nav class="sd-crumbs" aria-label="Fil d’Ariane"><a href="{{ route('messages.show', array_filter(['conversation' => $o['conversation'], 'espace' => $o['mine'] ? 'freelance' : null])) }}">Messages</a> › <span aria-current="page">Offre</span></nav>
    <div class="sx-head"><div><p class="sx-kicker">Messages</p><h1>{{ $o['mine'] ? 'Votre offre personnalisée' : 'Offre personnalisée de '.$o['freelancerName'] }}</h1><p class="muted">{{ $o['mine'] ? 'Pour '.$o['withName'].'.' : 'Lisez les conditions, puis acceptez ou refusez.' }}</p></div><span class="of-st {{ $o['tone'] }}">{{ $o['stateLabel'] }}</span></div>
    @if(session('status'))<div class="notice tone-success" role="status"><x-fc.icon name="check-circle" /><p>{{ session('status') }}</p></div>@endif
    @if(session('error'))<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>{{ session('error') }}</p></div>@endif
    @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>Vérifiez les champs signalés ci-dessous. Votre saisie est conservée.</p></div>@endif
    <div class="ac-grid"><div class="ac-main">
      <section class="ed-card"><h2>{{ $o['title'] }}</h2>
        <div class="of-kv"><div><small>Prix</small><b><x-fc.money :amount="\App\Shared\Money::xof($o['price'])" /></b></div><div><small>Délai</small><b>{{ $o['days'] }} {{ $o['days'] > 1 ? 'jours' : 'jour' }}</b></div><div><small>Corrections</small><b>{{ $o['revisions'] }} {{ $o['revisions'] > 1 ? 'incluses' : 'incluse' }}</b></div></div>
        <div><b>Ce qui est inclus</b><p style="margin:4px 0 0">{!! nl2br(e($o['scope'])) !!}</p></div>
        <div><b>Livrables</b><ul class="of-list">@foreach($o['deliverables'] as $x)<li>{{ $x }}</li>@endforeach</ul></div>
        <p class="muted small" style="margin:0">{{ $o['deliveryMode'] === 'files' ? 'Chaque livraison comporte au moins un fichier contrôlé.' : 'Livraison par message : aucun fichier exigé.' }}</p>
        @if($o['iAmClient'] && count($o['clientInputs']) && $o['actionable'])<div class="of-sum"><b>Ce que vous devrez fournir</b><ul class="of-list">@foreach($o['clientInputs'] as $x)<li>{{ $x }}</li>@endforeach</ul><p class="muted small" style="margin:0">Vous le renseignez ci-dessous, au moment d’accepter : le travail démarre quand le paiement est confirmé et le brief complet.</p></div>
        @elseif(count($o['clientInputs']))<div class="of-sum"><b>Éléments à fournir par le client</b><ul class="of-list">@foreach($o['clientInputs'] as $x)<li>{{ $x }}</li>@endforeach</ul></div>@endif
      </section>
      @if($o['actionable'] && $o['iAmClient'])
        <section class="ed-card" aria-labelledby="h-dec"><h2 id="h-dec">Votre décision</h2>
          <form method="post" action="{{ route('offers.accept', $o['id']) }}" class="stack-sm" data-once>@csrf
            <input type="hidden" name="operation_key" value="{{ $operationKey }}">
            @foreach($o['clientInputs'] as $i => $label)
              <div class="field"><label for="of-a{{ $i }}">{{ $label }}</label><textarea class="textarea" id="of-a{{ $i }}" name="answers[{{ $i }}]" rows="2" maxlength="1000" required @error('answers.'.$i) aria-invalid="true" @enderror>{{ old('answers.'.$i) }}</textarea>@error('answers.'.$i)<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
            @endforeach
            <div class="field"><label for="of-notes">Précisions pour le freelance (facultatif)</label><textarea class="textarea" id="of-notes" name="notes" rows="3" maxlength="3000">{{ old('notes') }}</textarea></div>
            <label><input type="checkbox" name="conditions" value="1" @checked(old('conditions')) @error('conditions') aria-invalid="true" @enderror> J’ai lu l’offre et j’accepte les conditions de la demande (version en vigueur).</label>@error('conditions')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror
            <div class="of-comp" style="margin:0;border:0;padding:0"><button class="btn btn-primary" type="submit" data-once>Accepter l’offre</button></div>
            <p class="muted small" style="margin:0">Accepter crée la commande : vous avez ensuite {{ config('freeci.orders.payment_hours') }} h pour payer. <b>Aucun paiement à cette étape.</b> Prix, délai et périmètre sont alors figés.</p>
          </form>
          <form method="post" action="{{ route('offers.decline', $o['id']) }}" class="stack-sm" data-once style="border-top:1px solid var(--line);padding-top:12px">@csrf
            <div class="field"><label for="of-note">Refuser l’offre — motif (facultatif, 500 caractères au plus)</label><input class="input" id="of-note" name="note" maxlength="500"></div>
            <div><button class="btn btn-secondary" type="submit" data-once>Refuser l’offre</button></div></form></section>
      @elseif($o['actionable'] && $o['mine'])
        <section class="ed-card"><h2>Votre offre est en attente</h2><p class="muted" style="margin:0">Elle n’est pas modifiable : retirez-la pour en envoyer une autre.</p>
          <form method="post" action="{{ route('offers.withdraw', $o['id']) }}" data-once>@csrf<button class="btn btn-secondary" type="submit" data-once>Retirer l’offre</button></form></section>
      @elseif($o['state'] === 'accepted' && $o['orderReference'])
        <section class="ed-card"><h2>Offre acceptée</h2><p style="margin:0">La commande {{ $o['orderReference'] }} a été créée le {{ $o['respondedAt'] }}.</p><div><a class="btn btn-primary" href="{{ route('orders.show', $o['orderReference']) }}">Ouvrir la commande</a></div></section>
      @elseif($o['state'] === 'declined')
        <section class="ed-card"><h2>Offre refusée</h2><p style="margin:0">Refusée le {{ $o['respondedAt'] }}.@if($o['declineNote']) Motif indiqué : « {{ $o['declineNote'] }} ».@endif</p></section>
      @else
        <section class="ed-card"><h2>Offre {{ mb_strtolower($o['stateLabel']) }}</h2><p class="muted" style="margin:0">Cette offre n’est plus disponible. Vous pouvez en recevoir ou en envoyer une nouvelle depuis la conversation.</p></section>
      @endif
    </div>
    <aside class="ac-side"><section class="ed-ck"><h3>Valable jusqu’au {{ $o['validUntilShort'] }}</h3><p class="muted small" style="margin:0">Passé ce délai, l’offre expire d’elle-même, sans aucune conséquence.</p></section>
      <section class="ed-ck"><h3>{{ $o['mine'] ? 'Conversation' : 'Une question ?' }}</h3><p class="muted small" style="margin:0">{{ $o['mine'] ? 'Les échanges se poursuivent dans la conversation.' : 'Écrivez au freelance dans la conversation : il peut retirer cette offre et vous en envoyer une nouvelle.' }}</p>
        <p style="margin:8px 0 0"><a class="btn btn-link" href="{{ route('messages.show', array_filter(['conversation' => $o['conversation'], 'espace' => $o['mine'] ? 'freelance' : null])) }}">Retour à la conversation</a></p></section></aside></div>
  </div>
</x-layouts.account>

<x-layouts.account title="Dossier d’assistance">
  <header class="page-head"><div class="row-top"><div><p class="eyebrow"><a href="{{ route('support.index') }}">← Assistance</a></p><h1 class="t-h1">{{ $c['subject'] }}</h1>
    <p class="muted">{{ $c['reference'] }} · {{ $c['kindLabel'] }} · ouvert le {{ $c['opened'] }}@if($c['orderReference']) · commande <a href="{{ route('orders.show', $c['orderReference']) }}">{{ $c['orderReference'] }}</a>@endif</p></div>
    <span class="badge {{ $c['live'] ? 'tone-info' : 'tone-neutral' }}">{{ $c['status'] }}</span></div></header>

  @if($c['role'] === 'counterparty')<div class="notice tone-warning"><x-fc.icon name="warn" /><p>Vous êtes l’<strong>autre partie</strong> de ce dossier. Vous voyez le motif, les échanges partagés et la décision ; vous pouvez répondre et joindre vos preuves.</p></div>@endif
  @if($c['kind'] === 'dispute' || $c['kind'] === 'cancellation')
    <div class="notice tone-info"><x-fc.icon name="info" /><p><strong>Pendant l’examen :</strong> la livraison, la demande de correction, la validation et les reports de la commande sont <strong>suspendus</strong> ; aucune validation n’a lieu par silence. Les messages de la commande restent possibles. Le reversement non exécuté est bloqué <em>en interne</em> chez FreeCI.</p></div>
  @elseif($c['kind'] === 'claim')
    <div class="notice tone-warning"><x-fc.icon name="warn" /><p>Le reversement a <strong>déjà été envoyé</strong> : FreeCI ne peut ni le bloquer ni le récupérer. Le traitement financier d’une réclamation dépend du prestataire de paiement et <strong>n’est pas garanti</strong>.</p></div>
  @endif

  @if($c['decision'])
    <section class="card stack" aria-labelledby="h-dec" style="margin-top:16px"><h2 class="t-h2" id="h-dec">Décision rendue</h2>
      <p><strong>{{ $c['decision']['outcome'] }}</strong> · {{ $c['decision']['when'] }}</p>
      <p style="white-space:pre-line">{{ $c['decision']['reason'] }}</p>
      @if($c['decision']['orderEffect'])<p><strong>Effet sur la commande :</strong> {{ $c['decision']['orderEffect'] }}</p>@endif
      @if($c['decision']['financial'])<div class="notice tone-warning"><x-fc.icon name="clock" /><p><strong>À traiter financièrement : {{ $c['decision']['financial'] }}.</strong> Cette décision ne vaut pas encore remboursement ni versement : aucune opération financière n’a été exécutée à ce stade.</p></div>@endif
    </section>
  @endif

  <section aria-labelledby="h-th" style="margin-top:24px"><h2 class="t-h2" id="h-th">Échanges</h2>
    <div class="stack" style="margin-top:12px">@foreach($c['thread'] as $m)
      <article class="msg {{ $m['mine'] ? 'msg-mine' : '' }}"><p class="small"><strong>{{ $m['who'] }}</strong>@if($m['staff']) <span class="badge tone-info">Équipe d’assistance</span>@endif · {{ $m['when'] }}@if($m['shared'] && $c['disputeLike']) · <span class="muted">vu par les deux parties</span>@endif</p>
        <p style="white-space:pre-line;overflow-wrap:anywhere">{{ $m['body'] }}</p>
        @foreach($m['files'] as $f)<p class="small">📎 @if($f['url'])<a href="{{ $f['url'] }}">{{ $f['name'] }}</a>@else{{ $f['name'] }} — en cours de contrôle de sécurité, non téléchargeable @endif</p>@endforeach</article>
    @endforeach</div></section>

  @if($c['live'])
  <form method="post" action="{{ route('support.reply', $c['reference']) }}" enctype="multipart/form-data" class="card stack" style="margin-top:16px;max-width:46em">@csrf
    <input type="hidden" name="client_key" value="{{ $key }}">
    <div class="field"><label for="f-body">Votre réponse @if($c['disputeLike']) <span class="muted small">(visible des deux parties et de l’équipe)</span>@endif</label><textarea class="textarea" id="f-body" name="body" required maxlength="4000">{{ old('body') }}</textarea>@error('body')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
    <div class="field"><label for="f-file">Pièce jointe (facultatif)</label><input class="input" id="f-file" type="file" name="file"><p class="hint">PDF, images ou plan DWG. Contrôlée avant d’être téléchargeable. Une pièce n’a aucun effet sur la commande.</p></div>
    <button class="btn btn-primary" type="submit" data-once>Envoyer</button>
  </form>
  @endif

  @if(count($c['events']))<section style="margin-top:24px"><h2 class="t-h3">Suivi</h2><ul class="hist">@foreach(array_reverse($c['events']) as $e)<li>{{ $e['what'] }} · <span class="muted">{{ $e['when'] }}</span></li>@endforeach</ul></section>@endif
</x-layouts.account>

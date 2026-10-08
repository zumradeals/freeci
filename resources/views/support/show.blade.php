<x-layouts.account title="Dossier d’assistance">
  <div class="page-body">
    <nav class="crumbs" aria-label="Fil d’Ariane"><a class="back-m" href="{{ route('support.index') }}"><x-fc.icon name="arrow-right" :size="16" class="flip" />Assistance</a><a class="hide-m" href="{{ route('support.index') }}">Assistance</a><span class="sep hide-m" aria-hidden="true">›</span><span class="hide-m" aria-current="page">{{ $c['reference'] }}</span></nav>
    <div class="sx-head"><div><p class="sx-kicker">Dossier d’assistance</p><h1>{{ $c['subject'] }}</h1>
      <p class="muted">{{ $c['reference'] }} · {{ $c['kindLabel'] }} · ouvert le {{ $c['opened'] }}@if($c['orderReference']) · commande <a href="{{ route('orders.show', $c['orderReference']) }}">{{ $c['orderReference'] }}</a>@endif</p></div>
      <span class="badge {{ $c['live'] ? 'tone-info' : 'tone-neutral' }}">{{ $c['status'] }}</span></div>

    <div class="ac-grid"><div class="ac-main">
    @if($c['role'] === 'counterparty')<div class="rq-info"><x-fc.icon name="warn" :size="18" /><span>Vous êtes l’<b>autre partie</b> de ce dossier. Vous voyez le motif, les échanges partagés et la décision ; vous pouvez répondre et joindre vos preuves.</span></div>@endif
    @if($c['kind'] === 'dispute' || $c['kind'] === 'cancellation')
      <div class="rq-info"><x-fc.icon name="info" :size="18" /><span><b>Pendant l’examen :</b> la livraison, la demande de correction, la validation et les reports de la commande sont <b>suspendus</b> ; aucune validation n’a lieu par silence. Les messages de la commande restent possibles. Le reversement non exécuté est bloqué <em>en interne</em> chez FreeCI.</span></div>
    @elseif($c['kind'] === 'claim')
      <div class="rq-info"><x-fc.icon name="warn" :size="18" /><span>Le reversement a <b>déjà été envoyé</b> : FreeCI ne peut ni le bloquer ni le récupérer. Le traitement financier d’une réclamation dépend du prestataire de paiement et <b>n’est pas garanti</b>.</span></div>
    @endif

    @if($c['decision'])
      <section class="ed-card as-dec" aria-labelledby="h-dec"><h2 id="h-dec">Décision rendue</h2>
        <p><b>{{ $c['decision']['outcome'] }}</b> · {{ $c['decision']['when'] }}</p>
        <p style="white-space:pre-line">{{ $c['decision']['reason'] }}</p>
        @if($c['decision']['orderEffect'])<p><b>Effet sur la commande :</b> {{ $c['decision']['orderEffect'] }}</p>@endif
        @if($c['decision']['financial'])<div class="rq-info"><x-fc.icon name="clock" :size="18" /><span><b>À traiter financièrement : {{ $c['decision']['financial'] }}.</b> Cette décision ne vaut pas encore remboursement ni versement : aucune opération financière n’a été exécutée à ce stade.</span></div>@endif
      </section>
    @endif

    <section class="ed-card" aria-labelledby="h-th"><h2 id="h-th">Échanges</h2>
      <div class="as-th">@foreach($c['thread'] as $m)
        <article class="as-m {{ $m['mine'] ? 'me' : '' }}"><div class="w"><b>{{ $m['who'] }}</b>@if($m['staff'])<span class="badge tone-info">Équipe d’assistance</span>@endif<span>· {{ $m['when'] }}</span>@if($m['shared'] && $c['disputeLike'])<span>· vu par les deux parties</span>@endif</div>
          <p style="white-space:pre-line;overflow-wrap:anywhere">{{ $m['body'] }}</p>
          @foreach($m['files'] as $f)<span class="as-file"><x-fc.icon name="file" :size="16" /> @if($f['url'])<a href="{{ $f['url'] }}">{{ $f['name'] }}</a>@else{{ $f['name'] }} — en cours de contrôle de sécurité, non téléchargeable @endif</span>@endforeach</article>
      @endforeach</div></section>

    @if($c['live'])
    <form method="post" action="{{ route('support.reply', $c['reference']) }}" enctype="multipart/form-data">@csrf
      <input type="hidden" name="client_key" value="{{ $key }}">
      <section class="ed-card" aria-labelledby="h-rep"><h2 id="h-rep">Votre réponse</h2>@if($c['disputeLike'])<p class="muted small" style="margin-top:-8px">Visible des deux parties et de l’équipe.</p>@endif
        <div class="field"><label for="f-body">Message</label><textarea class="textarea" id="f-body" name="body" required maxlength="4000">{{ old('body') }}</textarea>@error('body')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
        <div class="field"><label for="f-file">Pièce jointe (facultatif)</label><input class="input" id="f-file" type="file" name="file"><p class="hint">PDF, images ou plan DWG. Contrôlée avant d’être téléchargeable. Une pièce n’a aucun effet sur la commande.</p></div>
        <div><button class="btn btn-primary" type="submit" data-once>Envoyer</button></div></section>
    </form>
    @endif
    </div>
    <aside class="ac-side"><section class="ed-ck" aria-labelledby="h-sum"><h3 id="h-sum">Résumé</h3><dl class="as-sum">
      <div><dt>Référence</dt><dd>{{ $c['reference'] }}</dd></div><div><dt>Type</dt><dd>{{ $c['kindLabel'] }}</dd></div>
      @if($c['orderReference'])<div><dt>Commande</dt><dd><a href="{{ route('orders.show', $c['orderReference']) }}">{{ $c['orderReference'] }}</a></dd></div>@endif
      <div><dt>Ouvert le</dt><dd>{{ $c['opened'] }}</dd></div><div><dt>Votre rôle</dt><dd>{{ $c['role'] === 'counterparty' ? 'Autre partie' : 'Demandeur' }}</dd></div></dl></section>
      @if(count($c['events']))<section class="ed-ck" aria-labelledby="h-ev"><h3 id="h-ev">Suivi</h3><ol class="ed-tl">@foreach(array_reverse($c['events']) as $e)<li><span class="d"><x-fc.icon name="check" :size="14" /></span><p><b>{{ $e['what'] }}</b><br><span class="muted small">{{ $e['when'] }}</span></p></li>@endforeach</ol></section>@endif</aside>
    </div>
  </div>
</x-layouts.account>

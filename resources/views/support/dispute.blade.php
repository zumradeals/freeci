<x-layouts.account title="Litige ou annulation">
  <div class="page-body">
    <nav class="crumbs" aria-label="Fil d’Ariane"><a class="back-m" href="{{ route('orders.show', $reference) }}"><x-fc.icon name="arrow-right" :size="16" class="flip" />Commande {{ $reference }}</a><a class="hide-m" href="{{ route('orders.show', $reference) }}">Commande {{ $reference }}</a><span class="sep hide-m" aria-hidden="true">›</span><span class="hide-m" aria-current="page">Litige</span></nav>
    <div class="sx-head"><div><p class="sx-kicker">Commande {{ $reference }}</p><h1>Litige, annulation ou réclamation</h1><p class="muted">Un examen contradictoire : l’équipe décide, pas un calcul automatique.</p></div></div>
    @if($opt['live'])
      <div class="rq-info"><x-fc.icon name="info" :size="18" /><span>Un dossier est déjà ouvert pour cette commande : <a href="{{ route('support.show', $opt['live']) }}">{{ $opt['live'] }}</a>.</span></div>
    @elseif(! count($opt['kinds']))
      <div class="ed-card empty" style="justify-items:center;text-align:center"><span class="ico-lg"><x-fc.icon name="info" :size="26" /></span><p style="font-weight:600">Aucun litige ni aucune annulation n’est possible dans l’état actuel de cette commande.</p>
        <p class="muted" style="max-width:40em">Un litige ne s’ouvre que sur une commande payée dont le reversement n’est pas exécuté. Pour toute autre question, contactez le support.</p><a class="btn btn-secondary" href="{{ route('support.new', ['commande' => $reference]) }}">Contacter le support</a></div>
    @else
    <div class="ac-grid"><form method="post" action="{{ route('orders.dispute.store', $reference) }}">@csrf
      <input type="hidden" name="operation_key" value="{{ $key }}">
      <section class="ed-card" aria-labelledby="h-kind"><h2 id="h-kind">Que demandez-vous ?</h2>
        <div class="as-opts">@foreach($opt['kinds'] as $k)<label class="as-opt"><input type="radio" name="kind" value="{{ $k }}" required @checked(old('kind', $opt['kinds'][0]) === $k)><span><b>{{ $kinds[$k] }}</b><small>{{ match($k) {
          'dispute' => 'Désaccord sur la prestation ou la livraison. L’équipe examine les deux versions et décide.',
          'cancellation' => 'Vous souhaitez annuler une commande déjà payée. Un examen contradictoire s’ouvre ; l’équipe décide du travail retenu et d’une éventuelle suite financière.',
          default => 'Le reversement a déjà été envoyé : dépôt d’une réclamation auprès du support.' } }}</small></span></label>@endforeach</div>
        @if(! $opt['executed'])
          <div class="rq-info"><x-fc.icon name="warn" :size="18" /><span><b>Conséquences :</b> la commande passe « en litige ». La livraison, les corrections, la validation et les reports sont <b>suspendus</b> jusqu’à la décision ; rien n’est validé automatiquement. Le reversement non exécuté est bloqué en interne chez FreeCI. <b>Les deux parties voient le motif, les échanges et la décision.</b> La décision est prise par l’équipe, pas par un calcul automatique.</span></div>
        @else
          <div class="rq-info"><x-fc.icon name="warn" :size="18" /><span>Le reversement a <b>déjà été envoyé</b> : FreeCI ne peut ni le bloquer ni le récupérer. Le support examine votre réclamation ; le traitement financier dépend du prestataire de paiement et n’est pas garanti.</span></div>
        @endif
        <div class="field"><label for="f-reason">Motif (20 caractères au moins)</label><textarea class="textarea" id="f-reason" name="reason" required minlength="20" maxlength="4000">{{ old('reason') }}</textarea>@error('reason')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror<p class="hint">Exposez les faits. Vous pourrez ajouter des preuves (pièces contrôlées) dans le dossier.</p></div>
        <label class="check"><input type="checkbox" name="confirm" value="1" required> Je comprends ces conséquences.</label>@error('confirm')<p class="field-error">Confirmez avoir compris les conséquences.</p>@enderror
        <div class="ac-acts"><button class="btn btn-danger btn-lg" type="submit" data-once>Ouvrir le dossier</button><a class="btn btn-link" href="{{ route('orders.show', $reference) }}">Revenir</a></div></section>
    </form>
    <aside class="ac-side"><section class="ed-ck" aria-labelledby="h-before"><h3 id="h-before">Avant d’ouvrir</h3><ol class="ed-tl">
      <li><span class="d">1</span><p><b>Vous exposez les faits.</b><br><span class="muted small">Le motif est vu des deux parties.</span></p></li>
      <li><span class="d">2</span><p><b>L’autre partie répond.</b><br><span class="muted small">Chacun peut joindre des preuves.</span></p></li>
      <li><span class="d">3</span><p><b>L’équipe décide.</b><br><span class="muted small">La décision n’est pas encore un remboursement : le traitement financier suit.</span></p></li></ol></section></aside></div>
    @endif
  </div>
</x-layouts.account>

<x-layouts.account title="Litige ou annulation">
  <div class="page-body">
    <header class="page-head"><div class="row-top"><div><p class="eyebrow"><a href="{{ route('orders.show', $reference) }}">← Commande {{ $reference }}</a></p><h1 class="t-h1">Litige, annulation ou réclamation</h1></div></div></header>
    @if($opt['live'])
      <div class="notice tone-info"><x-fc.icon name="info" /><p>Un dossier est déjà ouvert pour cette commande : <a href="{{ route('support.show', $opt['live']) }}">{{ $opt['live'] }}</a>.</p></div>
    @elseif(! count($opt['kinds']))
      <div class="card empty"><span class="ico-lg"><x-fc.icon name="info" :size="26" /></span><p style="font-weight:600">Aucun litige ni aucune annulation n’est possible dans l’état actuel de cette commande.</p>
        <p class="muted" style="max-width:40em">Un litige ne s’ouvre que sur une commande payée dont le reversement n’est pas exécuté. Pour toute autre question, contactez le support.</p><a class="btn btn-secondary" href="{{ route('support.new', ['commande' => $reference]) }}">Contacter le support</a></div>
    @else
    <form method="post" action="{{ route('orders.dispute.store', $reference) }}" class="card stack" style="max-width:46em">@csrf
      <input type="hidden" name="operation_key" value="{{ $key }}">
      <div class="field"><span class="lbl">Que demandez-vous ?</span>
        @foreach($opt['kinds'] as $k)<label class="check"><input type="radio" name="kind" value="{{ $k }}" required @checked(old('kind', $opt['kinds'][0]) === $k)> <span><strong>{{ $kinds[$k] }}</strong><br><span class="muted small">{{ match($k) {
          'dispute' => 'Désaccord sur la prestation ou la livraison. L’équipe examine les deux versions et décide.',
          'cancellation' => 'Vous souhaitez annuler une commande déjà payée. Un examen contradictoire s’ouvre ; l’équipe décide du travail retenu et d’une éventuelle suite financière.',
          default => 'Le reversement a déjà été envoyé : dépôt d’une réclamation auprès du support.' } }}</span></span></label>@endforeach
      </div>
      @if(! $opt['executed'])
        <div class="notice tone-warning"><x-fc.icon name="warn" /><p><strong>Conséquences :</strong> la commande passe « en litige ». La livraison, les corrections, la validation et les reports sont <strong>suspendus</strong> jusqu’à la décision ; rien n’est validé automatiquement. Le reversement non exécuté est bloqué en interne chez FreeCI. <strong>Les deux parties voient le motif, les échanges et la décision.</strong> La décision est prise par l’équipe, pas par un calcul automatique.</p></div>
      @else
        <div class="notice tone-warning"><x-fc.icon name="warn" /><p>Le reversement a <strong>déjà été envoyé</strong> : FreeCI ne peut ni le bloquer ni le récupérer. Le support examine votre réclamation ; le traitement financier dépend du prestataire de paiement et n’est pas garanti.</p></div>
      @endif
      <div class="field"><label for="f-reason">Motif (20 caractères au moins)</label><textarea class="textarea" id="f-reason" name="reason" required minlength="20" maxlength="4000">{{ old('reason') }}</textarea>@error('reason')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror<p class="hint">Exposez les faits. Vous pourrez ajouter des preuves (pièces contrôlées) dans le dossier.</p></div>
      <label class="check"><input type="checkbox" name="confirm" value="1" required> Je comprends ces conséquences.</label>@error('confirm')<p class="field-error">Confirmez avoir compris les conséquences.</p>@enderror
      <div class="row"><button class="btn btn-danger btn-lg" type="submit" data-once>Ouvrir le dossier</button><a class="btn btn-link" href="{{ route('orders.show', $reference) }}">Revenir</a></div>
    </form>
    @endif
  </div>
</x-layouts.account>

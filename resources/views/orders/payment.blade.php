<x-layouts.account title="Paiement simulé" space="client">
  <nav class="crumbs" aria-label="Fil d’Ariane"><a class="back-m" href="{{ route('orders.show', $p->reference) }}"><x-fc.icon name="arrow-right" :size="16" class="flip" />Retour à la commande</a><a class="hide-m" href="{{ route('orders.show', $p->reference) }}">Commande {{ $p->reference }}</a><span class="sep hide-m" aria-hidden="true">›</span><span class="hide-m" aria-current="page">Paiement</span></nav>
  <h1 class="t-h1">Payer votre commande</h1>

  @if(! $p->sandboxAllowed && $p->paymentState === null)
    <section class="card empty" style="max-width:640px"><span class="ico-lg"><x-fc.icon name="lock" :size="26" /></span>
      <h2 class="t-h2">Le paiement n’est pas ouvert pour cette commande</h2>
      <p class="muted" style="max-width:36em">Aucun paiement ne peut être effectué ici : le paiement réel n’existe pas encore et le paiement simulé est réservé aux commandes de démonstration autorisées. Rien n’est débité.</p>
      <a class="btn btn-primary" href="{{ route('orders.show', $p->reference) }}">Voir la commande</a></section>
  @else
  <div class="notice tone-warning" role="note"><x-fc.icon name="flag" /><p><strong>Paiement simulé — aucun argent n’est débité.</strong> Cette commande est une démonstration ; aucun prestataire de paiement réel n’est utilisé.</p></div>

  <div class="cols">
    <div class="stack-lg">
      {{-- État de la tentative : toujours lu en base, jamais déduit de l'URL --}}
      @if($p->paymentState === 'confirmed')
        <section class="card" aria-labelledby="h-res" style="border-left:4px solid var(--success-700)"><h2 class="t-h2" id="h-res"><span class="badge tone-success"><x-fc.icon name="check-circle" :size="16" />Paiement confirmé</span></h2>
          <dl class="defs"><div><dt>Montant</dt><dd><x-fc.money :amount="$p->amount" /><small>Simulé</small></dd></div><div><dt>Confirmé le</dt><dd>{{ \App\Shared\Dates::format($p->paymentChangedAt) }}</dd></div><div><dt>Référence</dt><dd class="num">{{ $p->providerReference }}</dd></div></dl>
          @if($p->startedAt)<p style="margin-top:12px"><strong>Le travail commence.</strong> Livraison prévue le {{ \App\Shared\Dates::format($p->dueAt) }}.</p>
          @elseif($p->orderState === 'awaiting_brief')<p style="margin-top:12px"><strong>Complétez le brief</strong> pour lancer le travail ({{ $p->briefMissing }} {{ $p->briefMissing > 1 ? 'éléments manquants' : 'élément manquant' }}).</p>
          @else<p style="margin-top:12px">Votre commande est à jour.</p>@endif
          <div style="margin-top:16px"><a class="btn btn-primary" href="{{ route('orders.show', $p->reference) }}#brief">{{ $p->startedAt ? 'Voir ma commande' : 'Compléter le brief' }}</a></div></section>
      @elseif(in_array($p->paymentState, ['created', 'pending', 'unknown'], true))
        <section class="card" aria-labelledby="h-res" style="border-left:4px solid var(--warning-700)"><h2 class="t-h2" id="h-res"><span class="badge tone-warning"><x-fc.icon name="clock" :size="16" />Vérification du paiement en cours</span></h2>
          <p style="margin-top:8px">Nous vérifions votre paiement. <strong>Ne payez pas une seconde fois.</strong> Votre commande reste réservée tant qu’une vérification est en cours.</p>
          <p class="muted small" style="margin-top:6px">Référence {{ $p->providerReference }} · Dernier contrôle : {{ $p->lastCheckedAt ? \App\Shared\Dates::format($p->lastCheckedAt) : 'aucun' }}</p>
          <div class="row" style="margin-top:16px">
            @if($p->canRefresh)<form method="post" action="{{ route('orders.payment.refresh', $p->reference) }}">@csrf<button class="btn btn-primary" type="submit">Actualiser le statut</button></form>@endif
            <a class="btn btn-secondary" href="{{ route('orders.show', $p->reference) }}">Voir la commande</a></div></section>
      @elseif($p->paymentState === 'failed')
        <section class="card" aria-labelledby="h-res" style="border-left:4px solid var(--error-700)"><h2 class="t-h2" id="h-res"><span class="badge tone-error"><x-fc.icon name="error" :size="16" />Paiement non abouti</span></h2>
          <p style="margin-top:8px">Aucun montant n’a été confirmé. Référence {{ $p->providerReference }}.</p></section>
      @elseif($p->paymentState === 'expired' || $p->orderState === 'expired')
        <section class="card"><h2 class="t-h2"><span class="badge tone-neutral"><x-fc.icon name="minus-circle" :size="16" />Délai de paiement dépassé</span></h2><p style="margin-top:8px">La commande a expiré.</p></section>
      @endif

      @if($p->canPay)
        <section class="card" aria-labelledby="h-pay"><h2 class="t-h2 card-title" id="h-pay">{{ $p->paymentState === 'failed' ? 'Réessayer le paiement' : 'Moyen de paiement' }}</h2>
          <div class="choice" style="margin-bottom:12px"><h3 class="t-h3">Simulation</h3><p class="muted small">Le seul moyen disponible : un prestataire simulé. Le résultat est décidé côté serveur ; le retour de votre navigateur ne confirme rien.</p></div>
          @if($p->deadline)<p class="due due-block"><x-fc.icon name="clock" :size="20" /><span>Payer avant le <strong>{{ \App\Shared\Dates::format($p->deadline) }}</strong> <span class="rel">({{ \App\Shared\Dates::until($p->deadline) }})</span></span></p>@endif
          @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>{{ $errors->first() }}</p></div>@endif
          <form method="post" action="{{ route('orders.payment.start', $p->reference) }}" data-once style="display:grid;gap:16px;margin-top:12px" novalidate>
            @csrf
            <input type="hidden" name="operation_key" value="{{ $operationKey }}">
            <label class="check" for="sim"><input type="checkbox" id="sim" name="conditions" value="1" required> <span>Je comprends qu’il s’agit d’un <strong>paiement simulé</strong> : aucun argent n’est débité.</span></label>
            <div><button class="btn btn-primary btn-lg" type="submit" data-once-label="Démarrage…">Payer {{ $p->amount->formatted() }} FCFA (simulation)</button></div>
          </form>
        </section>
      @endif
    </div>

    <div class="stack-lg">
      <section class="card" aria-labelledby="h-sum"><h2 class="t-h2 card-title" id="h-sum">Récapitulatif</h2>
        <p style="font-weight:650">{{ $p->title }}</p><p class="muted small">Freelance : {{ $p->sellerName }}</p>
        <dl class="defs" style="margin-top:8px"><div><dt>Délai</dt><dd>{{ $p->deliveryDays }} {{ $p->deliveryDays > 1 ? 'jours' : 'jour' }} à partir du départ<small>Le départ est enregistré après paiement confirmé et brief complet.</small></dd></div>
          <div><dt>Corrections</dt><dd>{{ $p->revisionsIncluded }}</dd></div>
          <div><dt>Total à payer</dt><dd><x-fc.money :amount="$p->amount" size="lg" /><small>Montant de l’accord figé</small></dd></div></dl></section>
    </div>
  </div>
  @endif
</x-layouts.account>

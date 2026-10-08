@php($sbx = $p->environment === 'sandbox')
<x-layouts.account :title="$sbx ? 'Paiement (mode test)' : 'Paiement'" space="client">
  <nav class="crumbs" aria-label="Fil d’Ariane"><a class="back-m" href="{{ route('orders.show', $p->reference) }}"><x-fc.icon name="arrow-right" :size="16" class="flip" />Retour à la commande</a><a class="hide-m" href="{{ route('orders.show', $p->reference) }}">Commande {{ $p->reference }}</a><span class="sep hide-m" aria-hidden="true">›</span><span class="hide-m" aria-current="page">Paiement</span></nav>
  <div class="rq-wrap pay-page">
  <h1>Payer votre commande</h1>

  @if(! $p->paymentsOpen && $p->paymentState === null)
    <section class="card empty form-card"><span class="ico-lg"><x-fc.icon name="lock" :size="26" /></span>
      <h2 class="t-h2">Le paiement n’est pas ouvert pour cette commande</h2>
      <p class="muted" style="max-width:36em">{{ $p->unavailableMessage }}</p>
      @if($p->orderEnvironment === 'test')<p class="muted small" style="max-width:36em">Cette commande est une commande de <strong>test</strong> : elle ne peut jamais être payée en argent réel.</p>@elseif($p->orderEnvironment === 'legacy')<p class="muted small" style="max-width:36em">Cette commande est antérieure à l’ouverture des paiements : elle n’est pas payable.</p>@endif
      <a class="btn btn-primary" href="{{ route('orders.show', $p->reference) }}">Voir la commande</a></section>
  @else
  @if($sbx)
  <div class="notice tone-warning" role="note"><x-fc.icon name="flag" /><p><strong>Mode test — aucun argent réel n’est débité.</strong> Ce paiement est un <em>test</em> chez Genius Pay (sandbox) : il ne représente aucun montant réellement encaissé, aucun revenu, aucun reversement.</p></div>
  @endif

  @php($payStep = $p->startedAt ? 3 : ($p->paymentState === 'confirmed' ? 2 : 1))
  <ol class="rq-track" aria-label="Étapes de la commande">@foreach(['Accord', 'Paiement', 'Brief', 'Réalisation'] as $i => $label)<li class="{{ $i < $payStep ? 'done' : ($i === $payStep ? 'cur' : '') }}" @if($i === $payStep) aria-current="step" @endif><span class="mk" aria-hidden="true">{{ $i < $payStep ? '✓' : $i + 1 }}</span><span class="lb">{{ $label }}</span></li>@endforeach</ol>

  <div class="rq-grid">
    <div class="rq-main">
      {{-- État de la tentative : toujours lu en base, jamais déduit de l'URL --}}
      @if($p->paymentState === 'confirmed')
        <section class="card" aria-labelledby="h-res" style="border-left:4px solid var(--success-700)"><h2 class="t-h2" id="h-res"><span class="badge tone-success"><x-fc.icon name="check-circle" :size="16" />Paiement confirmé</span></h2>
          <dl class="defs"><div><dt>Montant</dt><dd><x-fc.money :amount="$p->amount" />@if($sbx)<small>Mode test : aucun argent réel</small>@endif</dd></div><div><dt>Confirmé le</dt><dd>{{ \App\Shared\Dates::format($p->paymentChangedAt) }}</dd></div><div><dt>Référence</dt><dd class="num">{{ $p->providerReference }}</dd></div></dl>
          @if($p->startedAt)<p style="margin-top:12px"><strong>Le travail commence.</strong> Livraison prévue le {{ \App\Shared\Dates::format($p->dueAt) }}.</p>
          @elseif($p->orderState === 'awaiting_brief')<p style="margin-top:12px"><strong>Complétez le brief</strong> pour lancer le travail ({{ $p->briefMissing }} {{ $p->briefMissing > 1 ? 'éléments manquants' : 'élément manquant' }}).</p>
          @else<p style="margin-top:12px">Votre commande est à jour.</p>@endif
          <div style="margin-top:16px"><a class="btn btn-primary" href="{{ route('orders.show', $p->reference) }}#brief">{{ $p->startedAt ? 'Voir ma commande' : 'Compléter le brief' }}</a></div></section>
      @elseif(in_array($p->paymentState, ['created', 'pending', 'unknown'], true))
        <section class="card" aria-labelledby="h-res" style="border-left:4px solid var(--warning-700)"><h2 class="t-h2" id="h-res"><span class="badge tone-warning"><x-fc.icon name="clock" :size="16" />Vérification du paiement en cours</span></h2>
          <p style="margin-top:8px">Nous vérifions votre paiement. <strong>Ne payez pas une seconde fois.</strong> Votre commande reste réservée tant qu’une vérification est en cours.</p>
          <p class="muted small" style="margin-top:6px">Référence {{ $p->providerReference }} · Dernier contrôle : {{ $p->lastCheckedAt ? \App\Shared\Dates::format($p->lastCheckedAt) : 'aucun' }}</p>
          @if($p->checkoutUrl)<p style="margin-top:8px">Vous n’avez pas terminé ? <a href="{{ $p->checkoutUrl }}" rel="noopener noreferrer">Reprendre le paiement sur le checkout Genius Pay</a> — c’est la même tentative.</p>@endif
          @if($sbx)<p class="muted small" style="margin-top:6px">Revenir du checkout ne confirme rien : la confirmation vient d’une notification signée, revérifiée par nos serveurs. Elle peut prendre quelques instants.</p>@endif
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
        <section class="card rq-card" aria-labelledby="h-pay"><h2 class="t-h2 card-title" id="h-pay">{{ $p->paymentState === 'failed' ? 'Réessayer le paiement' : 'Moyen de paiement' }}</h2>
          <div class="pm-choice"><span class="lg" aria-hidden="true">GP</span><div><h3 class="t-h3">Genius Pay{{ $sbx ? ' — mode test' : '' }}</h3><p class="muted small">Vous serez redirigé vers le checkout hébergé de Genius Pay{{ $sbx ? ' (environnement de test) : aucun argent réel n’est débité' : '' }}. Choisissez-y le moyen de paiement. Le retour de votre navigateur ne confirme rien : la confirmation est vérifiée côté serveur.</p></div></div>
          @if($p->deadline)<p class="due due-block pm-due"><x-fc.icon name="clock" :size="20" /><span>Payer avant le <strong>{{ \App\Shared\Dates::format($p->deadline) }}</strong> <span class="rel">({{ \App\Shared\Dates::until($p->deadline) }})</span></span></p>@endif
          @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>{{ $errors->first() }}</p></div>@endif
          <form method="post" action="{{ route('orders.payment.start', $p->reference) }}" data-once style="display:grid;gap:16px;margin-top:12px" novalidate>
            @csrf
            <input type="hidden" name="operation_key" value="{{ $operationKey }}">
            <label class="check" for="sim"><input type="checkbox" id="sim" name="conditions" value="1" required> <span>@if($sbx)Je comprends qu’il s’agit d’un <strong>paiement de test</strong> : aucun argent réel n’est débité.@else Je confirme le paiement de <strong>{{ $p->amount->formatted() }} FCFA</strong> pour cette commande.@endif</span></label>
            <div><button class="btn btn-primary btn-lg" type="submit" data-once-label="Démarrage…">{{ $sbx ? 'Continuer vers Genius Pay (test) — ' : 'Payer ' }}{{ $p->amount->formatted() }} FCFA</button></div>
          </form>
        </section>
      @endif
    </div>

    <aside class="rq-side" aria-label="Récapitulatif">
      <section class="rq-sum" aria-labelledby="h-sum"><div class="b">
        <div><p class="muted small" id="h-sum">Récapitulatif</p><h2>{{ $p->title }}</h2><p class="muted small">Freelance : {{ $p->sellerName }}</p></div>
        <ul class="rq-facts"><li><x-fc.icon name="clock" :size="20" /><span><b>{{ $p->deliveryDays }} {{ $p->deliveryDays > 1 ? 'jours' : 'jour' }}</b> à partir du départ</span></li><li><x-fc.icon name="pencil" :size="20" /><span><b>{{ $p->revisionsIncluded }} {{ $p->revisionsIncluded > 1 ? 'corrections' : 'correction' }}</b> {{ $p->revisionsIncluded > 1 ? 'incluses' : 'incluse' }}</span></li></ul>
        <div><p class="muted small">Total à payer</p><p class="rq-price"><x-fc.money :amount="$p->amount" size="lg" /></p><p class="muted small">Montant de l’accord figé</p></div>
        <p class="rq-lock"><x-fc.icon name="lock" :size="18" /><span>Le départ est enregistré après paiement confirmé et brief complet.</span></p>
      </div></section>
    </aside>
  </div>
  @endif
  </div>
</x-layouts.account>

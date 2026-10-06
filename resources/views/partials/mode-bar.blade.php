@unless(\App\Integrations\Payments\PaymentMode::isLive())
<div class="mode-bar" role="region" aria-label="Mode test">
  <div class="container">
    <p class="txt"><strong>Mode test — aucun argent réel</strong> · <span class="short">paiements fictifs</span><span class="long">les commandes créées maintenant sont des commandes de test : aucun paiement réel, aucun revenu ni reversement.</span></p>
  </div>
</div>
@endunless

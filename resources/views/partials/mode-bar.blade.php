@unless(\App\Integrations\Payments\PaymentMode::isLive())
<div class="mode-bar{{ ($wide ?? false) ? ' wide' : '' }}" role="region" aria-label="Mode test">
  <div class="container">
    <p class="txt"><strong>Mode test — aucun argent réel</strong><span class="long"> · les commandes créées sont des commandes de test.</span></p>
  </div>
</div>
@endunless

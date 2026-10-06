@php
  $m = fn (int $n) => \App\Shared\Money::xof($n)->formatted().' FCFA';
  $cats = ['upcoming' => 'À venir', 'blocked' => 'Bloqué', 'available' => 'Disponible', 'processing' => 'En cours de versement', 'paid' => 'Versé'];
  $hints = ['upcoming' => 'Prestation à valider', 'blocked' => 'Une vérification est nécessaire', 'available' => 'Pas encore versé', 'processing' => 'Reversement en traitement', 'paid' => 'Reversement confirmé'];
  $beneficiary = $d['beneficiary'];
  $verified = $beneficiary && $beneficiary['status'] === 'verified';
  $hasTest = array_sum($d['totals']['test']) > 0;
@endphp
<x-layouts.account title="Revenus" space="freelancer">
  <div class="earnings-page">
    <header class="page-head"><p class="eyebrow">Espace freelance</p><h1 class="t-h1">Mes revenus</h1><p class="muted">Suivez vos gains et préparez vos versements.</p></header>

    <section class="card earnings-summary" aria-labelledby="real-earnings-title">
      <div class="earnings-heading"><h2 class="t-h2" id="real-earnings-title">Revenus réels</h2><span class="muted small">Après commission</span></div>
      <dl class="earnings-metrics">
        @foreach($cats as $k => $label)
          <div class="earnings-metric {{ $k === 'available' ? 'earnings-metric-featured' : '' }}"><dt>{{ $label }}</dt><dd>{{ $m($d['totals']['real'][$k]) }}</dd><dd class="earnings-metric-hint">{{ $hints[$k] }}</dd></div>
        @endforeach
      </dl>
      @if(array_sum($d['totals']['real']) === 0)<p class="muted small">Vous n’avez pas encore de revenus réels.@if($hasTest) Vos commandes de test sont présentées séparément ci-dessous.@endif</p>@endif
    </section>

    @if($hasTest)
      <section class="card earnings-summary earnings-test" aria-labelledby="test-earnings-title">
        <div class="earnings-heading"><h2 class="t-h2" id="test-earnings-title">Montants de test</h2><span class="tag-demo">Aucun argent réel</span></div>
        <dl class="earnings-metrics">
          @foreach($cats as $k => $label)<div class="earnings-metric"><dt>{{ $label }}</dt><dd>{{ $m($d['totals']['test'][$k]) }}</dd></div>@endforeach
        </dl>
        <p class="muted small">Ces montants servent à tester le parcours. Ils ne peuvent pas être versés en argent réel.</p>
      </section>
    @endif

    <div class="earnings-details">
      <section class="card earnings-destination stack" aria-labelledby="beneficiary-title">
        <div><h2 class="t-h2" id="beneficiary-title">Où recevoir mes versements ?</h2><p class="muted">Indiquez votre compte Mobile Money ou vos coordonnées bancaires.</p></div>
        @if($beneficiary)
          <div class="notice tone-{{ $verified ? 'success' : 'info' }}"><x-fc.icon name="info" /><div><strong>{{ $verified ? 'Coordonnées vérifiées' : 'Coordonnées en attente de vérification' }}</strong><p>{{ ['mobile_money' => 'Mobile Money', 'bank_transfer' => 'Virement bancaire', 'other' => 'Autre moyen'][$beneficiary['method']] }} · {{ $beneficiary['holder'] }}</p><p class="small">{{ $verified ? 'L’administrateur peut préparer vos versements éligibles.' : 'L’administrateur doit les vérifier avant tout versement.' }}</p></div></div>
        @else
          <div class="notice tone-info"><x-fc.icon name="info" /><p><strong>Coordonnées à compléter.</strong> Même si un montant est disponible, son versement attend la vérification de votre compte par l’administrateur.</p></div>
        @endif
        @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>{{ $errors->first() }}</p></div>@endif
        <form method="post" action="{{ route('freelance.earnings.beneficiary') }}" class="earnings-form">@csrf
          <div class="field"><label for="payout-method">Moyen de réception</label><select class="select" id="payout-method" name="method">@foreach(['mobile_money' => 'Mobile Money', 'bank_transfer' => 'Virement bancaire', 'other' => 'Autre moyen'] as $value => $label)<option value="{{ $value }}" @selected(old('method', $beneficiary['method'] ?? 'mobile_money') === $value)>{{ $label }}</option>@endforeach</select></div>
          <div class="field"><label for="payout-holder">Nom du titulaire du compte</label><input class="input" id="payout-holder" name="holder" value="{{ old('holder', $beneficiary['holder'] ?? '') }}" minlength="2" maxlength="120" autocomplete="name" aria-describedby="holder-hint" aria-invalid="{{ $errors->has('holder') ? 'true' : 'false' }}" required><p class="hint" id="holder-hint">Le nom enregistré sur votre compte Mobile Money ou bancaire.</p></div>
          <div class="field earnings-field-wide"><label for="payout-destination">Numéro Mobile Money ou coordonnées bancaires</label><input class="input" id="payout-destination" name="destination" minlength="6" maxlength="120" autocomplete="off" aria-describedby="destination-hint" aria-invalid="{{ $errors->has('destination') ? 'true' : 'false' }}" required><p class="hint" id="destination-hint">Mobile Money : précisez l’opérateur et le numéro. Banque : précisez la banque et le numéro de compte. Ces coordonnées ne sont pas réaffichées après l’enregistrement.</p></div>
          <p class="muted small earnings-field-wide">{{ $beneficiary ? 'Enregistrer de nouvelles coordonnées remplace les précédentes et nécessite une nouvelle vérification par l’administrateur.' : 'L’administrateur vérifiera vos coordonnées avant tout versement.' }} Enregistrer ne déclenche aucun transfert d’argent.</p>
          <div class="earnings-field-wide"><button class="btn btn-primary" type="submit">{{ $beneficiary ? 'Remplacer mes coordonnées' : 'Enregistrer mes coordonnées' }}</button></div>
        </form>
      </section>
      <aside class="card earnings-guide stack" aria-labelledby="payout-guide-title">
        <h2 class="t-h2" id="payout-guide-title">Comment recevoir mes gains ?</h2>
        <ol><li><strong>La prestation est validée.</strong><p class="muted small">Le client valide votre livraison. Son silence ne déclenche aucun versement.</p></li><li><strong>Vos coordonnées sont vérifiées.</strong><p class="muted small">Complétez le formulaire pour permettre à l’administrateur de vérifier le compte destinataire.</p></li><li><strong>L’administrateur traite le versement.</strong><p class="muted small">Il vérifie les éventuels blocages et valide l’opération. Suivez son état sur cette page.</p></li></ol>
        <p class="muted small"><strong>Disponible ne signifie pas versé.</strong> La commission appliquée est celle convenue lors de la commande.</p>
      </aside>
    </div>

    <section class="card earnings-orders" aria-labelledby="earnings-orders-title">
      <h2 class="t-h2" id="earnings-orders-title">Détail par commande</h2>
      @forelse($d['rows'] as $r)
        <article class="earnings-order">
          <div class="earnings-order-head"><div><a class="earnings-order-link" href="{{ route('orders.show', $r['reference']) }}">{{ $r['title'] }}</a><p class="muted small">{{ $r['reference'] }} @if($r['bucket'] === 'test')<span class="tag-demo">Test — aucun argent réel</span>@endif</p></div><span class="badge">{{ $r['cat'] === 'unknown' ? 'Montant à vérifier' : $cats[$r['cat']] }}</span></div>
          <dl class="earnings-order-amounts"><div><dt>Payé par le client</dt><dd>{{ $m($r['paid']) }}</dd></div>@if($r['refunded'] > 0)<div><dt>Remboursé</dt><dd>{{ $m($r['refunded']) }}</dd></div>@endif @if($r['due'] !== null)<div><dt>Commission ({{ $r['bp'] / 100 }} %)</dt><dd>{{ $m((int) $r['commission']) }}</dd></div><div><dt>Votre part{{ $r['bucket'] === 'test' ? ' de test' : '' }}</dt><dd><strong>{{ $m((int) $r['due']) }}</strong></dd></div>@endif</dl>
          @if($r['state_label'])<p class="small">{{ $r['state_label'] }}</p>@endif
          @if($r['cat'] === 'unknown')<p class="muted small">Le taux de commission de cette ancienne commande n’est pas défini. Contactez l’assistance pour vérifier le montant.</p>
          @elseif($r['cat'] !== 'paid' && $r['cat'] !== 'processing')
            @if($r['cat'] === 'available' && !$verified)<p class="muted small">{{ $beneficiary ? 'Versement en attente de la vérification de vos coordonnées par l’administrateur.' : 'Complétez vos coordonnées ci-dessus pour préparer le versement.' }}</p>@endif
            @if(count($r['reasons']))<ul class="earnings-reasons muted small">@foreach($r['reasons'] as $x)@unless($r['cat'] === 'available' && !$verified && $x === \App\Modules\Finance\Support\PayoutEligibility::REASONS['beneficiary_missing'])<li>{{ $x }}</li>@endunless@endforeach</ul>@endif
          @endif
        </article>
      @empty<p class="muted earnings-empty">Aucune commande payée pour l’instant. Retrouvez ici le détail de vos gains dès votre première commande payée.</p>@endforelse
    </section>
  </div>
</x-layouts.account>

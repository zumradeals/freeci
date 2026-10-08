@php
  $m = fn (int $n) => \App\Shared\Money::xof($n)->formatted().' FCFA';
  $cats = ['upcoming' => 'À venir', 'blocked' => 'Bloqué', 'available' => 'Disponible', 'processing' => 'En cours de versement', 'paid' => 'Versé'];
  $hints = ['upcoming' => 'Prestation à valider', 'blocked' => 'Une vérification est nécessaire', 'available' => 'Pas encore versé', 'processing' => 'Reversement en traitement', 'paid' => 'Reversement confirmé'];
  $beneficiary = $d['beneficiary'];
  $verified = $beneficiary && $beneficiary['status'] === 'verified';
  $hasTest = array_sum($d['totals']['test']) > 0;
@endphp
<x-layouts.account title="Revenus" space="freelancer">
  <div class="page-body">
    <header class="sx-head"><div><p class="sx-kicker">Espace freelance</p><h1>Mes revenus</h1><p class="muted">Suivez vos gains et préparez vos versements.</p></div></header>

    <section class="ed-card" aria-labelledby="real-earnings-title">
      <div class="fn-head"><h2 id="real-earnings-title">Revenus réels</h2><span class="muted small">Après commission</span></div>
      <dl class="fn-flow">
        @foreach(['upcoming', 'available', 'processing', 'paid'] as $k)
          <div class="fn-m {{ $k === 'available' ? 'hot' : '' }}"><dt>{{ $cats[$k] }}</dt><dd class="v">{{ $m($d['totals']['real'][$k]) }}</dd><dd class="h">{{ $hints[$k] }}</dd></div>
        @endforeach
      </dl>
      <p class="fn-sub"><span>{{ $cats['blocked'] }} : <b>{{ $m($d['totals']['real']['blocked']) }}</b> ({{ mb_strtolower($hints['blocked']) }})</span><span aria-hidden="true">·</span><span><b>Disponible ne signifie pas versé.</b> La commission appliquée est celle convenue lors de la commande.</span></p>
      @if(array_sum($d['totals']['real']) === 0)<p class="muted small">Vous n’avez pas encore de revenus réels.@if($hasTest) Vos commandes de test sont présentées séparément ci-dessous.@endif</p>@endif
    </section>

    @if($hasTest)
      <section class="fn-test" aria-labelledby="test-earnings-title">
        <div class="fn-head"><h2 id="test-earnings-title">Montants de test</h2><span class="tag-demo">Aucun argent réel</span></div>
        <dl class="fn-testrow">
          @foreach(['upcoming', 'available', 'processing', 'paid'] as $k)<div><dt>{{ $cats[$k] }}</dt><dd>{{ $m($d['totals']['test'][$k]) }}</dd></div>@endforeach
        </dl>
        <p class="muted small">Ces montants servent à tester le parcours. Ils ne peuvent pas être versés en argent réel.</p>
      </section>
    @endif

    <div class="fn-split">
      <section class="ed-card" aria-labelledby="beneficiary-title">
        <div><h2 id="beneficiary-title">Où recevoir mes versements ?</h2><p class="muted">Indiquez votre compte Mobile Money ou vos coordonnées bancaires.</p></div>
        @if($beneficiary)
          <div class="notice tone-{{ $verified ? 'success' : 'info' }}"><x-fc.icon name="info" /><div><strong>{{ $verified ? 'Coordonnées vérifiées' : 'Coordonnées en attente de vérification' }}</strong><p>{{ ['mobile_money' => 'Mobile Money', 'bank_transfer' => 'Virement bancaire', 'other' => 'Autre moyen'][$beneficiary['method']] }} · {{ $beneficiary['holder'] }}</p><p class="small">{{ $verified ? 'L’administrateur peut préparer vos versements éligibles.' : 'L’administrateur doit les vérifier avant tout versement.' }}</p></div></div>
        @else
          <div class="notice tone-info"><x-fc.icon name="info" /><p><strong>Coordonnées à compléter.</strong> Même si un montant est disponible, son versement attend la vérification de votre compte par l’administrateur.</p></div>
        @endif
        @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>{{ $errors->first() }}</p></div>@endif
        <form method="post" action="{{ route('freelance.earnings.beneficiary') }}" class="form-grid">@csrf
          <div class="field"><label for="payout-method">Moyen de réception</label><select class="select" id="payout-method" name="method">@foreach(['mobile_money' => 'Mobile Money', 'bank_transfer' => 'Virement bancaire', 'other' => 'Autre moyen'] as $value => $label)<option value="{{ $value }}" @selected(old('method', $beneficiary['method'] ?? 'mobile_money') === $value)>{{ $label }}</option>@endforeach</select></div>
          <div class="field"><label for="payout-holder">Nom du titulaire du compte</label><input class="input" id="payout-holder" name="holder" value="{{ old('holder', $beneficiary['holder'] ?? '') }}" minlength="2" maxlength="120" autocomplete="name" aria-describedby="holder-hint" aria-invalid="{{ $errors->has('holder') ? 'true' : 'false' }}" required><p class="hint" id="holder-hint">Le nom enregistré sur votre compte Mobile Money ou bancaire.</p></div>
          <div class="field span-all"><label for="payout-destination">Numéro Mobile Money ou coordonnées bancaires</label><input class="input" id="payout-destination" name="destination" minlength="6" maxlength="120" autocomplete="off" aria-describedby="destination-hint" aria-invalid="{{ $errors->has('destination') ? 'true' : 'false' }}" required><p class="hint" id="destination-hint">Mobile Money : précisez l’opérateur et le numéro. Banque : précisez la banque et le numéro de compte. Ces coordonnées ne sont pas réaffichées après l’enregistrement.</p></div>
          <p class="muted small span-all">{{ $beneficiary ? 'Enregistrer de nouvelles coordonnées remplace les précédentes et nécessite une nouvelle vérification par l’administrateur.' : 'L’administrateur vérifiera vos coordonnées avant tout versement.' }} Enregistrer ne déclenche aucun transfert d’argent.</p>
          <div class="span-all"><button class="btn btn-primary" type="submit">{{ $beneficiary ? 'Remplacer mes coordonnées' : 'Enregistrer mes coordonnées' }}</button></div>
        </form>
      </section>
      <aside class="ed-ck fn-guide" aria-labelledby="payout-guide-title">
        <h3 id="payout-guide-title">Comment recevoir mes gains ?</h3>
        <ol class="ed-tl"><li><strong>La prestation est validée.</strong><p class="muted small">Le client valide votre livraison. Son silence ne déclenche aucun versement.</p></li><li><strong>Vos coordonnées sont vérifiées.</strong><p class="muted small">Complétez le formulaire pour permettre à l’administrateur de vérifier le compte destinataire.</p></li><li><strong>L’administrateur traite le versement.</strong><p class="muted small">Il vérifie les éventuels blocages et valide l’opération. Suivez son état sur cette page.</p></li></ol>
        <p class="muted small"><strong>Disponible ne signifie pas versé.</strong> La commission appliquée est celle convenue lors de la commande.</p>
      </aside>
    </div>

    <section class="ed-card" aria-labelledby="orders-title">
      <h2 id="orders-title">Détail par commande</h2><p class="muted small">Les totaux couvrent tout votre historique. Le détail est affiché par pages de 20 commandes.</p>
      @if(count($d['rows']))
      <div class="fn-table"><div class="fn-row head" aria-hidden="true"><span>Commande</span><span>Statut</span><span class="r">Payé par le client</span><span class="r">Commission</span><span class="r">Votre part</span></div>
      @foreach($d['rows'] as $r)
        <div class="fn-row">
          <div><a class="record-link" href="{{ route('orders.show', $r['reference']) }}">{{ $r['title'] }}</a><small>{{ $r['reference'] }} @if($r['bucket'] === 'test')<span class="tag-demo">Test — aucun argent réel</span>@endif</small></div>
          <div><span class="badge">{{ $r['cat'] === 'unknown' ? 'Montant à vérifier' : $cats[$r['cat']] }}</span></div>
          <div class="r" data-label="Payé par le client">{{ $m($r['paid']) }}@if($r['refunded'] > 0)<small>dont remboursé : {{ $m($r['refunded']) }}</small>@endif</div>
          <div class="r" data-label="Commission">@if($r['due'] !== null)−{{ $m((int) $r['commission']) }}<small>({{ $r['bp'] / 100 }} %)</small>@else —@endif</div>
          <div class="r part" data-label="Votre part{{ $r['bucket'] === 'test' ? ' de test' : '' }}">@if($r['due'] !== null){{ $m((int) $r['due']) }}@else —@endif</div>
          @php($why = collect($r['reasons'])->reject(fn ($x) => $r['cat'] === 'available' && ! $verified && $x === \App\Modules\Finance\Support\PayoutEligibility::REASONS['beneficiary_missing'])->all())
          @if($r['state_label'] || $r['cat'] === 'unknown' || ($r['cat'] === 'available' && ! $verified) || (count($why) && $r['cat'] !== 'paid' && $r['cat'] !== 'processing'))
          <div class="fn-why">
            @if($r['state_label'])<p>{{ $r['state_label'] }}</p>@endif
            @if($r['cat'] === 'unknown')<p>Le taux de commission de cette ancienne commande n’est pas défini. Contactez l’assistance pour vérifier le montant.</p>
            @elseif($r['cat'] !== 'paid' && $r['cat'] !== 'processing')
              @if($r['cat'] === 'available' && ! $verified)<p>{{ $beneficiary ? 'Versement en attente de la vérification de vos coordonnées par l’administrateur.' : 'Complétez vos coordonnées ci-dessus pour préparer le versement.' }}</p>@endif
              @if(count($why))<ul class="note-list">@foreach($why as $x)<li>{{ $x }}</li>@endforeach</ul>@endif
            @endif
          </div>
          @endif
        </div>
      @endforeach
      </div>
      @else<p class="muted empty-note">Aucune commande payée pour l’instant. Retrouvez ici le détail de vos gains dès votre première commande payée.</p>@endif
      @include('partials.finance-pagination', ['pagination' => $d['pagination']])
    </section>
  </div>
</x-layouts.account>

@php($m = fn (int $n) => \App\Shared\Money::xof($n)->formatted().' FCFA')
@php($uuid = fn () => (string) \Illuminate\Support\Str::uuid())
<x-layouts.admin title="Finances">
  <div class="page-body">
    <header class="page-head"><div class="row-top"><div><p class="eyebrow">Administration</p><h1 class="t-h1">Finances : remboursements et reversements</h1><p class="lead">Opérations préparées, confirmées et exécutées par un seul administrateur ; réel et test séparés.</p></div></div></header>
    <div class="notice tone-warning"><x-fc.icon name="warn" /><p><strong>Une décision ne rembourse ni ne verse rien.</strong> Parcours en quatre temps, par un seul administrateur : <em>préparer</em> l’opération (les fonds sont réservés), <em>relire le récapitulatif</em> (montant, bénéficiaire, environnement), <em>confirmer explicitement</em>, puis <em>exécuter ou enregistrer</em>. Rien n’est « effectué » avant un résultat établi. <strong>Genius Pay ne documente aucune API de reversement</strong> : un reversement n’est jamais exécuté par API ; il est enregistré manuellement avec référence externe et justificatif. Les remboursements partiels ne sont pas davantage exécutés par API (règles non établies).</p></div>

    <section class="card" aria-labelledby="h-tot"><h2 class="t-h2" id="h-tot">Totaux — réel et test séparés</h2>
      <div class="table-wrap"><table class="list"><caption class="sr-only">Totaux financiers</caption><thead><tr><th scope="col">Montant</th><th scope="col">Réel (live)</th><th scope="col">Test (non réel)</th></tr></thead><tbody>
        @foreach(['paid' => 'Encaissé confirmé', 'refunded' => 'Remboursé (confirmé)', 'refund_open' => 'Remboursements en cours ou réservés', 'commission' => 'Commission acquise (reversements confirmés)', 'paid_out' => 'Reversé aux freelances (confirmé)', 'payout_open' => 'Reversements en cours ou réservés', 'provider_fees' => 'Frais du prestataire (informatifs, répartition non décidée)'] as $k => $label)
        <tr><td data-label="Montant">{{ $label }}</td><td data-label="Réel">{{ $m($d['totals']['real'][$k]) }}</td><td data-label="Test">{{ $m($d['totals']['test'][$k]) }}</td></tr>
        @endforeach
      </tbody></table></div>
      <p class="muted small">Un paiement, un remboursement ou un reversement de test n’alimente jamais un revenu réel. Le taux de commission et la répartition des frais sont des <strong>propositions non validées</strong>.</p></section>

    <section class="card" aria-labelledby="h-appr"><h2 class="t-h2" id="h-appr">À confirmer ({{ count($d['toConfirm']) }})</h2>
      @if(count($d['toConfirm']))
      <div class="table-wrap"><table class="list"><caption class="sr-only">Opérations à confirmer</caption><thead><tr><th scope="col">Opération</th><th scope="col">Commande</th><th scope="col">Montant</th><th scope="col">Préparée le</th></tr></thead><tbody>
        @foreach($d['toConfirm'] as $o)<tr><td data-label="Opération"><a href="{{ route('admin.finance.show', $o['reference']) }}">{{ $o['reference'] }}</a> · {{ $o['kind'] }} @if($o['simulated'])<span class="tag-demo">Test</span>@endif</td><td data-label="Commande">{{ $o['order'] }}</td><td data-label="Montant">{{ $m($o['amount']) }}</td><td data-label="Préparée le">{{ $o['when'] }}</td></tr>@endforeach
      </tbody></table></div>
      @else<p class="muted">Aucune opération en attente de confirmation.</p>@endif</section>

    <section class="card" aria-labelledby="h-open"><h2 class="t-h2" id="h-open">Confirmées (à exécuter), en cours ou à vérifier ({{ count($d['open']) }})</h2>
      @if(count($d['open']))
      <div class="table-wrap"><table class="list"><caption class="sr-only">Opérations ouvertes</caption><thead><tr><th scope="col">Opération</th><th scope="col">Commande</th><th scope="col">Montant</th><th scope="col">État</th></tr></thead><tbody>
        @foreach($d['open'] as $o)<tr><td data-label="Opération"><a href="{{ route('admin.finance.show', $o['reference']) }}">{{ $o['reference'] }}</a> · {{ $o['kind'] }} @if($o['simulated'])<span class="tag-demo">Test</span>@endif</td><td data-label="Commande">{{ $o['order'] }}</td><td data-label="Montant">{{ $m($o['amount']) }}</td><td data-label="État"><span class="badge tone-{{ $o['tone'] }}">{{ $o['label'] }}</span>@if($o['reason'])<br><span class="muted small">{{ $o['reason'] }}</span>@endif</td></tr>@endforeach
      </tbody></table></div>
      @else<p class="muted">Aucune opération ouverte.</p>@endif</section>

    <section class="card" aria-labelledby="h-dec"><h2 class="t-h2" id="h-dec">Décisions du support à traiter financièrement ({{ count($d['decisions']) }})</h2>
      @forelse($d['decisions'] as $x)
        <div class="card" style="margin-top:12px"><p><strong>{{ $x['need_label'] }}</strong> · dossier <a href="{{ route('admin.support.show', $x['case']) }}">{{ $x['case'] }}</a> · commande {{ $x['order'] }} @if($x['simulated'])<span class="tag-demo">Test</span>@endif</p>
          @if($x['note'])<p class="muted small">{{ $x['note'] }}</p>@endif
          @if(in_array($x['need'], ['refund', 'partial'], true))
            <p class="muted small">Encaissé : {{ $m($x['paid']) }} · plafond remboursable (fonds non engagés) : <strong>{{ $m($x['cap']) }}</strong></p>
            <form method="post" action="{{ route('admin.finance.refund', $x['id']) }}" class="stack-sm">@csrf<input type="hidden" name="operation_key" value="{{ $uuid() }}">
              <div class="field"><label for="f11">Montant à rembourser (FCFA, laisser vide = total)</label><input id="f11" type="number" name="amount" min="1" inputmode="numeric"></div>
              <div class="field"><label for="f12">Motif</label><textarea id="f12" name="reason" rows="2" minlength="10" maxlength="500" required></textarea></div>
              <button class="btn btn-primary" type="submit">Préparer le remboursement</button></form>
          @else
            <p class="muted small">Reversement à autoriser : voir « Reversements » ci-dessous (commande {{ $x['order'] }}).</p>
          @endif</div>
      @empty<p class="muted">Aucune décision en attente d’opération.</p>@endforelse</section>

    <section class="card" aria-labelledby="h-pay"><h2 class="t-h2" id="h-pay">Reversements : commandes validées sans reversement ({{ count($d['eligible']) }})</h2>
      <p class="muted small">Éligibilité calculée : validation explicite ou décision motivée, aucun blocage ni litige, fonds rapprochés, bénéficiaire vérifié, conditions financières figées dans l’accord. Le silence du client ne rend jamais un reversement éligible. Montants calculés au taux <strong>figé dans l’accord</strong> (taux de 10 % : proposition provisoire non approuvée) (commission = (base × taux + 5 000) ÷ 10 000, arrondi au franc).</p>
      @forelse($d['eligible'] as $e)
        <div class="card" style="margin-top:12px"><p><strong>Commande {{ $e['order'] }}</strong> @if($e['simulated'])<span class="tag-demo">Test</span>@endif
          @if($e['due'] !== null) — base {{ $m($e['base']) }} · commission {{ $m($e['commission']) }} ({{ $e['bp'] / 100 }} %) · <strong>part du freelance {{ $m($e['due']) }}</strong>@else — conditions financières non figées dans l’accord @endif</p>
          @if($e['eligible'])
            <form method="post" action="{{ route('admin.finance.payout', $e['order']) }}" class="stack-sm">@csrf<input type="hidden" name="operation_key" value="{{ $uuid() }}">
              <div class="field"><label for="f13">Motif</label><textarea id="f13" name="reason" rows="2" minlength="10" maxlength="500" required></textarea></div>
              <button class="btn btn-primary" type="submit">Préparer le reversement (réserve les fonds)</button></form>
          @else
            <ul class="muted small">@foreach($e['reasons'] as $r)<li>{{ $r }}</li>@endforeach</ul>
          @endif</div>
      @empty<p class="muted">Aucune commande validée en attente de reversement.</p>@endforelse</section>

    <section class="card" aria-labelledby="h-ben"><h2 class="t-h2" id="h-ben">Destinations de reversement à vérifier ({{ count($d['beneficiaries']) }})</h2>
      @forelse($d['beneficiaries'] as $b)
        <form method="post" action="{{ route('admin.finance.beneficiary', [$b['id'], 'verifier']) }}" class="stack-sm" style="margin-top:8px">@csrf
          <p>{{ $b['user'] }} · {{ ['mobile_money' => 'Mobile money', 'bank_transfer' => 'Virement bancaire', 'other' => 'Autre'][$b['method']] }} · titulaire « {{ $b['holder'] }} » · déclaré le {{ $b['when'] }} <span class="muted small">(la destination chiffrée n’est jamais affichée ici)</span></p>
          <div class="field"><label for="f14">Comment avez-vous vérifié ?</label><input id="f14" name="note" minlength="10" maxlength="300" required></div>
          <button class="btn btn-secondary" type="submit">Marquer comme vérifiée</button></form>
      @empty<p class="muted">Aucune déclaration en attente.</p>@endforelse</section>

    <section class="card" aria-labelledby="h-his"><h2 class="t-h2" id="h-his">Historique</h2>
      @if(count($d['history']))
      <div class="table-wrap"><table class="list"><caption class="sr-only">Historique des opérations</caption><thead><tr><th scope="col">Opération</th><th scope="col">Commande</th><th scope="col">Montant</th><th scope="col">État</th><th scope="col">Date</th></tr></thead><tbody>
        @foreach($d['history'] as $o)<tr><td data-label="Opération"><a href="{{ route('admin.finance.show', $o['reference']) }}">{{ $o['reference'] }}</a> · {{ $o['kind'] }} @if($o['simulated'])<span class="tag-demo">Test</span>@endif</td><td data-label="Commande">{{ $o['order'] }}</td><td data-label="Montant">{{ $m($o['amount']) }}</td><td data-label="État"><span class="badge tone-{{ $o['tone'] }}">{{ $o['label'] }}</span> @if($o['mode'])<span class="muted small">({{ $o['mode'] === 'api' ? 'API' : 'manuel' }})</span>@endif</td><td data-label="Date">{{ $o['when'] }}</td></tr>@endforeach
      </tbody></table></div>
      @else<p class="muted">Aucune opération terminée.</p>@endif</section>
  </div>
</x-layouts.admin>

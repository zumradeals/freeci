@php($m = fn (int $n) => \App\Shared\Money::xof($n)->formatted().' FCFA')
<x-layouts.account title="Paiements et remboursements" space="client">
  <header class="sx-head"><div><p class="sx-kicker">Espace client</p><h1>Paiements et remboursements</h1><p class="muted">Ce que vous avez payé et les remboursements confirmés, commande par commande.</p></div></header>
  @php($sum = fn (string $env, string $k) => $totals[$env][$k])
  @php($hasTest = $totals['test']['paid'] > 0)
  <div class="page-body">
    <section class="ed-card" aria-labelledby="h-real"><div class="fn-head"><h2 id="h-real">Paiements réels</h2><span class="muted small">Commandes réelles uniquement</span></div>
      <dl class="fn-flow fn-flow-3">
        <div class="fn-m hot"><dt>Payé</dt><dd class="v">{{ $m($sum('real', 'paid')) }}</dd><dd class="h">Paiements confirmés</dd></div>
        <div class="fn-m"><dt>Remboursé</dt><dd class="v">{{ $m($sum('real', 'refunded')) }}</dd><dd class="h">Remboursements confirmés</dd></div>
        <div class="fn-m"><dt>Remboursement en cours</dt><dd class="v">{{ $m($sum('real', 'refund_open')) }}</dd><dd class="h">Pas encore confirmé</dd></div>
      </dl>
      @if($sum('real', 'paid') === 0)<p class="muted small">Vous n’avez pas encore de paiement réel.@if($hasTest) Vos commandes de test sont présentées séparément ci-dessous.@endif</p>@endif
      <p class="rq-info"><x-fc.icon name="info" :size="18" /><span>Un remboursement n’est affiché « confirmé » qu’après confirmation établie. Une décision du support ou une demande n’est <strong>pas</strong> un remboursement effectué.</span></p></section>
    @if($hasTest)
    <section class="fn-test" aria-labelledby="h-test"><div class="fn-head"><h2 id="h-test">Montants de test</h2><span class="tag-demo">Aucun argent réel</span></div>
      <dl class="fn-testrow fn-testrow-3">
        <div><dt>Payé</dt><dd>{{ $m($sum('test', 'paid')) }}</dd></div>
        <div><dt>Remboursé</dt><dd>{{ $m($sum('test', 'refunded')) }}</dd></div>
        <div><dt>Remboursement en cours</dt><dd>{{ $m($sum('test', 'refund_open')) }}</dd></div>
      </dl>
      <p class="muted small">Ces montants servent à tester le parcours : ils ne représentent aucun argent réel.</p></section>
    @endif
    <section class="ed-card" aria-labelledby="h-orders"><h2 id="h-orders">Détail par commande</h2>
      <p class="muted small">Les totaux couvrent tout votre historique. Le détail est affiché par pages de 20 commandes.</p>
      @if(count($rows))
      <div class="fn-table"><div class="fn-row c head" aria-hidden="true"><span>Commande</span><span class="r">Payé</span><span class="r">Remboursé (confirmé)</span><span class="r">Remboursement en cours</span></div>
      @foreach($rows as $r)
        <div class="fn-row c">
          <div><a class="record-link" href="{{ route('orders.show', $r['reference']) }}">{{ $r['title'] }}</a><small>{{ $r['reference'] }} @if($r['environment'] === 'test')<span class="tag-demo">Test — aucun argent réel</span>@elseif($r['environment'] === 'legacy')<span class="tag-demo">Ancienne commande — hors totaux réels</span>@endif</small></div>
          <div class="r" data-label="Payé">{{ $m($r['paid']) }}</div>
          <div class="r" data-label="Remboursé (confirmé)">{{ $m($r['refunded']) }}</div>
          <div class="r" data-label="Remboursement en cours">{{ $m($r['refund_open']) }}</div>
          @if(count($r['refunds']))<div class="fn-why">@foreach($r['refunds'] as $x)<p><span class="badge tone-{{ $x['tone'] }}">{{ $x['label'] }}</span> {{ $m($x['amount']) }} · {{ $x['reference'] }} <span class="muted">{{ $x['when'] }}</span></p>@endforeach</div>@endif
        </div>
      @endforeach
      </div>
      @else<p class="muted empty-note">Aucun paiement confirmé pour l’instant. Le détail de vos paiements apparaîtra ici dès la première commande payée.</p>@endif
      @include('partials.finance-pagination', ['pagination' => $pagination])
    </section>
  </div>
</x-layouts.account>

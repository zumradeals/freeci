@php($m = fn (int $n) => \App\Shared\Money::xof($n)->formatted().' FCFA')
<x-layouts.account title="Paiements et remboursements" space="client">
  <header class="page-head"><div class="row-top"><div><p class="eyebrow">Espace client</p><h1 class="t-h1">Paiements et remboursements</h1><p class="lead">Ce que vous avez payé et les remboursements confirmés, commande par commande.</p></div></div></header>
  @php($sum = fn (string $env, string $k) => collect($rows)->filter(fn ($r) => ($env === 'test') === ($r['environment'] === 'test'))->sum($k))
  @php($hasTest = collect($rows)->contains(fn ($r) => $r['environment'] === 'test'))
  <div class="page-body">
    <section class="card panel" aria-labelledby="h-real"><div class="card-head"><h2 class="t-h2" id="h-real">Paiements réels</h2><span class="meta-r">Commandes réelles uniquement</span></div>
      <dl class="metrics">
        <div class="metric metric-featured"><dt>Payé</dt><dd>{{ $m($sum('real', 'paid')) }}</dd><dd class="metric-hint">Paiements confirmés</dd></div>
        <div class="metric"><dt>Remboursé</dt><dd>{{ $m($sum('real', 'refunded')) }}</dd><dd class="metric-hint">Remboursements confirmés</dd></div>
        <div class="metric"><dt>Remboursement en cours</dt><dd>{{ $m($sum('real', 'refund_open')) }}</dd><dd class="metric-hint">Pas encore confirmé</dd></div>
      </dl>
      @if($sum('real', 'paid') === 0)<p class="muted small">Vous n’avez pas encore de paiement réel.@if($hasTest) Vos commandes de test sont présentées séparément ci-dessous.@endif</p>@endif
      <p class="muted small">Un remboursement n’est affiché « confirmé » qu’après confirmation établie. Une décision du support ou une demande n’est <strong>pas</strong> un remboursement effectué.</p></section>
    @if($hasTest)
    <section class="card panel metrics-dashed" aria-labelledby="h-test"><div class="card-head"><h2 class="t-h2" id="h-test">Montants de test</h2><span class="tag-demo">Aucun argent réel</span></div>
      <dl class="metrics">
        <div class="metric"><dt>Payé</dt><dd>{{ $m($sum('test', 'paid')) }}</dd></div>
        <div class="metric"><dt>Remboursé</dt><dd>{{ $m($sum('test', 'refunded')) }}</dd></div>
        <div class="metric"><dt>Remboursement en cours</dt><dd>{{ $m($sum('test', 'refund_open')) }}</dd></div>
      </dl>
      <p class="muted small">Ces montants servent à tester le parcours : ils ne représentent aucun argent réel.</p></section>
    @endif
    <section class="card panel" aria-labelledby="h-orders"><h2 class="t-h2" id="h-orders">Détail par commande</h2>
      @forelse($rows as $r)
        <article class="record">
          <div class="record-head"><div><a class="record-link" href="{{ route('orders.show', $r['reference']) }}">{{ $r['title'] }}</a><p class="muted small">{{ $r['reference'] }} @if($r['environment'] === 'test')<span class="tag-demo">Test — aucun argent réel</span>@endif</p></div></div>
          <dl class="record-amounts"><div><dt>Payé</dt><dd>{{ $m($r['paid']) }}</dd></div><div><dt>Remboursé (confirmé)</dt><dd>{{ $m($r['refunded']) }}</dd></div>@if($r['refund_open'] > 0)<div><dt>Remboursement en cours</dt><dd>{{ $m($r['refund_open']) }}</dd></div>@endif</dl>
          @foreach($r['refunds'] as $x)<p class="small"><span class="badge tone-{{ $x['tone'] }}">{{ $x['label'] }}</span> {{ $m($x['amount']) }} · {{ $x['reference'] }} <span class="muted">{{ $x['when'] }}</span></p>@endforeach
        </article>
      @empty
        <p class="muted empty-note">Aucun paiement confirmé pour l’instant. Le détail de vos paiements apparaîtra ici dès la première commande payée.</p>
      @endforelse
    </section>
  </div>
</x-layouts.account>

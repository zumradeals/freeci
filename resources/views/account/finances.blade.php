@php($m = fn (int $n) => \App\Shared\Money::xof($n)->formatted().' FCFA')
<x-layouts.account title="Paiements et remboursements" space="client">
  <header class="page-head"><div class="row-top"><div><p class="eyebrow">Espace client</p><h1 class="t-h1">Paiements et remboursements</h1></div></div></header>
  <div class="notice tone-info"><x-fc.icon name="info" /><p>Un remboursement n’est affiché « confirmé » qu’après confirmation établie. Une décision du support ou une demande n’est <strong>pas</strong> un remboursement effectué. Les commandes de test ne représentent aucun argent réel.</p></div>
  @forelse($rows as $r)
    <section class="card" style="margin-top:16px"><h2 class="t-h3"><a href="{{ route('orders.show', $r['reference']) }}">{{ $r['title'] }}</a> <span class="muted small">· {{ $r['reference'] }}</span> @if($r['environment'] === 'test')<span class="tag-demo">Commande de test — aucun argent réel</span>@endif</h2>
      <dl class="defs"><div><dt>Payé</dt><dd>{{ $m($r['paid']) }}</dd></div><div><dt>Remboursé (confirmé)</dt><dd>{{ $m($r['refunded']) }}</dd></div>@if($r['refund_open'] > 0)<div><dt>Remboursement en cours</dt><dd>{{ $m($r['refund_open']) }}</dd></div>@endif</dl>
      @foreach($r['refunds'] as $x)<p><span class="badge tone-{{ $x['tone'] }}">{{ $x['label'] }}</span> {{ $m($x['amount']) }} · {{ $x['reference'] }} <span class="muted small">{{ $x['when'] }}</span></p>@endforeach</section>
  @empty
    <div class="card empty"><span class="ico-lg"><x-fc.icon name="clipboard" :size="26" /></span><p style="font-weight:600">Aucun paiement confirmé pour l’instant.</p></div>
  @endforelse
</x-layouts.account>

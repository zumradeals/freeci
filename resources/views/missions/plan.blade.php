<x-layouts.account :title="'Plan de jalons : '.$p['title']" :space="$space">
  <div class="page-body">
    <nav class="sd-crumbs" aria-label="Fil d’Ariane">@if($p['isClient'])<a href="{{ route('client.missions.show', $p['missionId']) }}">{{ $p['title'] }}</a>@else<a href="{{ route('freelance.proposals') }}">Mes propositions</a>@endif › <span aria-current="page">Plan de jalons</span></nav>
    <div class="sx-head"><div><p class="sx-kicker">Mission</p><h1>Plan de paiement par jalons</h1><p class="muted">{{ $p['title'] }} · {{ $p['total'] }} au total · <span class="badge tone-{{ $p['tone'] }}">{{ $p['stateLabel'] }}</span></p></div></div>
    @if(session('error'))<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>{{ session('error') }}</p></div>@endif
    <div class="ac-grid"><div class="ac-main">
      <section class="ed-card" aria-labelledby="h-pl">
        <div class="row" style="justify-content:space-between;gap:8px"><b id="h-pl">{{ $p['validatedCount'] }} jalon{{ $p['validatedCount'] > 1 ? 's' : '' }} validé{{ $p['validatedCount'] > 1 ? 's' : '' }} sur {{ $p['count'] }}</b><span class="muted">{{ $p['validatedSum'] }} / {{ $p['total'] }}</span></div>
        <div class="jl-bar" role="progressbar" aria-valuenow="{{ $p['percent'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Part du montant total validée"><i style="width:{{ $p['percent'] }}%"></i></div>
        <ol class="jl-tl">@foreach($p['items'] as $i)
          @php($cls = $i['state'] === 'validated' ? 'done' : ($i['state'] === 'open' ? 'cur' : ''))
          <li class="{{ $cls }}"><span class="dot" aria-hidden="true">{{ $i['state'] === 'validated' ? '✓' : $i['rank'] }}</span>
            <div><b>{{ $i['rank'] }} · {{ $i['title'] }}</b>
              <small>{{ $i['days'] }} jours · {{ $i['price'] }}@if($i['reference']) · commande {{ $i['reference'] }}@endif
                @if($i['state'] === 'validated') · validé@if($i['closed']) le {{ $i['closed'] }}@endif
                @elseif($i['state'] === 'open' && $i['orderState'] === 'awaiting_payment') · <span class="badge tone-warning">En attente de paiement</span>@if($i['payBefore']) à régler avant le {{ $i['payBefore'] }}@endif
                @elseif($i['state'] === 'open' && in_array($i['orderState'], ['expired', 'cancelled'], true)) · <span class="badge tone-neutral">Paiement non effectué</span>
                @elseif($i['state'] === 'open') · en cours de réalisation
                @elseif($i['state'] === 'cancelled') · annulé, jamais dû
                @else · s’ouvre après validation du jalon {{ $i['rank'] - 1 }}@endif</small></div>
            <span>@if($i['reference'] && $i['state'] !== 'cancelled')<a href="{{ route('orders.show', $i['reference']) }}">Ouvrir la commande<span class="sr-only"> du jalon {{ $i['rank'] }}</span></a>@else<span class="muted">{{ $i['price'] }}</span>@endif</span></li>
        @endforeach</ol>
        @if($p['canReopen'])<form method="post" action="{{ route('milestones.reopen', $p['missionId']) }}" data-once class="notice tone-warning" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">@csrf<x-fc.icon name="warn" /><p style="flex:1;min-width:220px">Le jalon n’a pas été payé à temps. Vous pouvez <strong>rouvrir son paiement jusqu’au {{ $p['reopenUntil'] }}</strong> ; passé ce délai, le plan s’arrête.</p><button class="btn btn-primary" type="submit">Rouvrir le paiement</button></form>@endif
        @if($p['state'] === 'paused' && ! $p['isClient'])<p class="muted">Le client n’a pas payé le jalon à temps. Il peut rouvrir le paiement jusqu’au {{ $p['reopenUntil'] }}.</p>@endif
        @if($p['state'] === 'stopped')<p class="muted">{{ ['client' => 'Le client a arrêté le plan : les jalons non ouverts ne sont jamais dus.', 'unpaid' => 'Le jalon n’a pas été payé dans le délai de reprise : le plan est arrêté.', 'support' => 'Un jalon a été annulé par décision du support : le plan est arrêté.'][$p['stopReason']] ?? 'Plan arrêté.' }}</p>@endif
      </section>
      @if($p['canStop'])<section class="ed-card" aria-labelledby="h-stop"><h2 id="h-stop" style="margin:0">Arrêter le plan</h2><p class="muted" style="margin:0">Les jalons non ouverts sont annulés sans paiement. Les jalons déjà validés restent acquis. Cette action est définitive.</p><div><a class="btn btn-secondary" href="{{ route('milestones.stop', $p['missionId']) }}">Arrêter le plan après le jalon {{ $p['validatedCount'] }}</a></div></section>@endif
    </div>
    <aside class="ac-side"><section class="ed-ck"><h3>Comment ça marche</h3><ul class="av-tl">
      <li><x-fc.icon name="check" :size="18" /><span>Le client ne paie <b>qu’un jalon à la fois</b> : le suivant s’ouvre à la validation du précédent.</span></li>
      <li><x-fc.icon name="check" :size="18" /><span>Chaque jalon est une <b>commande ordinaire</b> : paiement, livraison, corrections, litige et reversement.</span></li>
      <li><x-fc.icon name="check" :size="18" /><span>Un jalon non payé dans les 24 h met le plan <b>en pause</b> ; le client peut rouvrir le paiement pendant {{ config('freeci.missions.milestones.reopen_days') }} jours.</span></li>
      <li><x-fc.icon name="check" :size="18" /><span>Un <b>avis unique</b> est proposé à la validation du dernier jalon (ou à l’arrêt du plan).</span></li></ul></section></aside></div>
  </div>
</x-layouts.account>

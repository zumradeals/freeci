@php($crit = ['price' => 'Prix ferme', 'days' => 'Délai', 'revisions' => 'Corrections', 'deliverables' => 'Livrables', 'validUntil' => 'Valable jusqu’au', 'number' => 'Version'])
<x-layouts.account title="Propositions" space="client">
  <div class="page-body">
    <nav class="ed-crumbs" aria-label="Fil d’Ariane"><a href="{{ route('client.missions') }}">Mes missions</a><span aria-hidden="true">/</span><a href="{{ route('client.missions.show', $mission->getKey()) }}">{{ $live?->title ?? 'Mission' }}</a><span aria-hidden="true">/</span><span aria-current="page">Propositions</span></nav>
    <header class="sx-head"><div><p class="sx-kicker">Espace client</p><h1>Propositions reçues</h1><p class="muted">{{ $live?->title }} · {{ count($items) }} proposition{{ count($items) > 1 ? 's' : '' }} · triées par prix, sans classement de qualité.</p></div></header>
    @if(! $selectionOpen)<div class="notice tone-warning" role="note"><x-fc.icon name="warn" /><p>La sélection n’est pas possible dans l’état actuel de la mission ({{ ['reserved' => 'une proposition est déjà retenue', 'awarded' => 'mission attribuée', 'selection_ended' => 'sélection terminée : rouvrez la mission', 'closed' => 'mission fermée', 'expired' => 'mission expirée'][$mission->status] ?? 'mission non ouverte' }}).</p></div>@endif
    @if(count($compare) >= 2)
      <section class="card" aria-labelledby="h-cmp"><h2 class="t-h2 card-title" id="h-cmp">Comparaison</h2>
        <div class="cmp-table" role="region" aria-label="Tableau comparatif" tabindex="0"><table class="cmp"><thead><tr><th scope="col"><span class="sr-only">Critère</span></th>@foreach($compare as $c)<th scope="col">{{ $c['author'] }}</th>@endforeach</tr></thead><tbody>
          @foreach($crit as $k => $label)<tr><th scope="row">{{ $label }}</th>@foreach($compare as $c)<td>@if($k === 'price'){{ $c['price']->formatted() }} FCFA @elseif($k === 'days'){{ $c['days'] }} j @elseif($k === 'deliverables')<ul>@foreach($c['deliverables'] as $d)<li>{{ $d }}</li>@endforeach</ul>@elseif($k === 'number')v{{ $c['number'] }}@else{{ $c[$k] }}@endif</td>@endforeach</tr>@endforeach
          <tr><th scope="row">Livraison</th>@foreach($compare as $c)<td>{{ $c['mode'] }}</td>@endforeach</tr></tbody></table></div>
        <div class="cmp-stack">@foreach($crit as $k => $label)<div class="cmp-row"><h3 class="t-h3">{{ $label }}</h3><dl>@foreach($compare as $c)<div><dt>{{ $c['author'] }}</dt><dd>@if($k === 'price'){{ $c['price']->formatted() }} FCFA @elseif($k === 'days'){{ $c['days'] }} j @elseif($k === 'deliverables'){{ implode(' ; ', $c['deliverables']) }}@elseif($k === 'number')v{{ $c['number'] }}@else{{ $c[$k] }}@endif</dd></div>@endforeach</dl></div>@endforeach</div>
      </section>
    @endif
    @if(count($items))
    <form method="get" action="{{ route('client.missions.proposals', $mission->getKey()) }}" class="pr-form-list">
      <div class="pr-cmpbar"><span>Cochez 2 ou 3 propositions pour les comparer côte à côte.</span><button class="btn btn-secondary" type="submit">Comparer la sélection (2 ou 3)</button></div>
      <div class="pr-grid">
      @foreach($items as $i)
        <article class="pr-card {{ $i['selected'] ? 'sel' : '' }}" aria-labelledby="p-{{ $i['proposalId'] }}">
          <div class="pr-top"><div class="pr-who"><span class="avatar avatar-lg" aria-hidden="true">{{ mb_strtoupper(mb_substr($i['author'], 0, 1)) }}</span><div><b id="p-{{ $i['proposalId'] }}">{{ $i['author'] }}</b><small>{{ $i['headline'] }}@if($i['profileSlug']) · <a href="{{ route('freelances.show', $i['profileSlug']) }}">Profil</a>@endif</small></div></div><span class="pr-price"><x-fc.money :amount="$i['price']" /></span></div>
          <div class="pr-facts"><div><b>{{ $i['days'] }} j</b><small>Délai</small></div><div><b>{{ $i['revisions'] }}</b><small>Corrections</small></div><div><b>{{ count($i['deliverables']) }}</b><small>Livrables</small></div></div>
          <p>{{ $i['scope'] }}</p>
          @if($i['message'])<p class="pr-q">« {{ $i['message'] }} »</p>@endif
          <p class="pr-meta">Version {{ $i['number'] }} · déposée le {{ $i['submittedAt'] }} · valable jusqu’au {{ $i['validUntil'] }} · livraison : {{ mb_strtolower($i['mode']) }}</p>
          @if(count($i['history']) > 1)<details class="fold inner"><summary><span>Versions précédentes ({{ count($i['history']) - 1 }})</span><x-fc.icon name="chev-down" class="chev" /></summary><div class="fold-body"><ul>@foreach($i['history'] as $h)<li>v{{ $h['number'] }} · {{ $h['price']->formatted() }} FCFA · {{ $h['days'] }} j · {{ $h['when'] }}</li>@endforeach</ul></div></details>@endif
          @if($i['block'] && ! $i['selected'])<p class="note-line"><x-fc.icon name="warn" :size="16" /><span>{{ $i['block'] }}</span></p>@endif
          <div class="pr-acts"><label class="check"><input type="checkbox" name="comparer[]" value="{{ $i['proposalId'] }}" @checked(collect($compare)->contains('proposalId', $i['proposalId']))> <span>Comparer<span class="sr-only"> {{ $i['author'] }}</span></span></label>
            @if($i['selectable'])<a class="btn btn-primary" href="{{ route('client.missions.select', [$mission->getKey(), $i['versionId']]) }}">Retenir cette proposition<span class="sr-only"> de {{ $i['author'] }}</span></a>@elseif($i['selected'])<span class="badge tone-success"><x-fc.icon name="check-circle" :size="16" />Retenue</span>@endif
            <a class="btn btn-link" href="{{ route('messages.start.proposal', $i['proposalId']) }}">Poser une question<span class="sr-only"> à {{ $i['author'] }}</span></a></div>
        </article>
      @endforeach
      </div>
    </form>
    @else<div class="card empty"><span class="ico-lg"><x-fc.icon name="inbox" :size="26" /></span><p style="font-weight:600">Aucune proposition pour l’instant.</p><p class="muted" style="max-width:36em">Les freelances peuvent candidater jusqu’à la date limite. Vous êtes le seul, avec chaque auteur, à voir une proposition.</p></div>@endif
  </div>
</x-layouts.account>

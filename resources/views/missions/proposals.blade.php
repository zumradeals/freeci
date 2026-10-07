@php($crit = ['price' => 'Prix ferme', 'days' => 'Délai', 'revisions' => 'Corrections', 'deliverables' => 'Livrables', 'validUntil' => 'Valable jusqu’au', 'number' => 'Version'])
<x-layouts.account title="Propositions" space="client">
  <div class="page-body">
    <nav class="crumbs" aria-label="Fil d’Ariane"><a class="back-m" href="{{ route('client.missions.show', $mission->getKey()) }}"><x-fc.icon name="arrow-right" :size="16" class="flip" />Retour à la mission</a><a class="hide-m" href="{{ route('client.missions') }}">Mes missions</a><span class="sep hide-m" aria-hidden="true">›</span><span class="hide-m" aria-current="page">Propositions</span></nav>
    <h1 class="t-h1">Propositions reçues</h1><p class="muted">{{ $live?->title }} · {{ count($items) }} proposition{{ count($items) > 1 ? 's' : '' }} · triées par prix, sans classement de qualité.</p>
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
    <form method="get" action="{{ route('client.missions.proposals', $mission->getKey()) }}" class="stack-lg">
      @foreach($items as $i)
        <article class="card" aria-labelledby="p-{{ $i['proposalId'] }}" style="{{ $i['selected'] ? 'border-left:4px solid var(--success-700)' : '' }}">
          <div class="row" style="justify-content:space-between;align-items:flex-start"><div><h2 class="t-h3" id="p-{{ $i['proposalId'] }}">{{ $i['author'] }}</h2><p class="muted small">{{ $i['headline'] }}@if($i['profileSlug']) · <a href="{{ route('freelances.show', $i['profileSlug']) }}">Profil</a>@endif</p></div><x-fc.money :amount="$i['price']" /></div>
          <ul class="facts-row" style="margin-top:10px"><li><x-fc.icon name="clock" /><span><b>{{ $i['days'] }} j</b><small>Délai</small></span></li><li><x-fc.icon name="pencil" /><span><b>{{ $i['revisions'] }}</b><small>Corrections</small></span></li><li><x-fc.icon name="package" /><span><b>{{ count($i['deliverables']) }}</b><small>Livrables</small></span></li></ul>
          <p style="margin-top:8px">{{ $i['scope'] }}</p>
          <p class="muted small" style="margin-top:6px">Version {{ $i['number'] }} · déposée le {{ $i['submittedAt'] }} · valable jusqu’au {{ $i['validUntil'] }} · livraison : {{ mb_strtolower($i['mode']) }}</p>
          @if($i['message'])<p class="quote">« {{ $i['message'] }} »</p>@endif
          @if(count($i['history']) > 1)<details class="fold inner"><summary><span>Versions précédentes ({{ count($i['history']) - 1 }})</span><x-fc.icon name="chev-down" class="chev" /></summary><div class="fold-body"><ul>@foreach($i['history'] as $h)<li>v{{ $h['number'] }} · {{ $h['price']->formatted() }} FCFA · {{ $h['days'] }} j · {{ $h['when'] }}</li>@endforeach</ul></div></details>@endif
          @if($i['block'] && ! $i['selected'])<p class="note-line" style="margin-top:8px"><x-fc.icon name="warn" :size="16" /><span>{{ $i['block'] }}</span></p>@endif
          <div class="row" style="margin-top:12px"><label class="check"><input type="checkbox" name="comparer[]" value="{{ $i['proposalId'] }}" @checked(collect($compare)->contains('proposalId', $i['proposalId']))> <span>Comparer<span class="sr-only"> {{ $i['author'] }}</span></span></label>
            @if($i['selectable'])<a class="btn btn-primary" href="{{ route('client.missions.select', [$mission->getKey(), $i['versionId']]) }}">Retenir cette proposition<span class="sr-only"> de {{ $i['author'] }}</span></a>@elseif($i['selected'])<span class="badge tone-success"><x-fc.icon name="check-circle" :size="16" />Retenue</span>@endif <a class="btn btn-link" href="{{ route('messages.start.proposal', $i['proposalId']) }}">Poser une question<span class="sr-only"> à {{ $i['author'] }}</span></a></div>
        </article>
      @endforeach
      <div><button class="btn btn-secondary" type="submit">Comparer la sélection (2 ou 3)</button></div>
    </form>
    @else<div class="card empty"><span class="ico-lg"><x-fc.icon name="inbox" :size="26" /></span><p style="font-weight:600">Aucune proposition pour l’instant.</p><p class="muted" style="max-width:36em">Les freelances peuvent candidater jusqu’à la date limite. Vous êtes le seul, avec chaque auteur, à voir une proposition.</p></div>@endif
  </div>
</x-layouts.account>

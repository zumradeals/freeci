<x-layouts.account title="Mes propositions" space="freelancer">
  <header class="page-head"><div class="row-top"><div><p class="eyebrow">Espace freelance</p><h1 class="t-h1">Mes propositions</h1></div><a class="btn btn-secondary" href="{{ route('missions.index') }}">Missions ouvertes</a></div></header>
  @if(count($proposals))
    <div class="stack-lg">@foreach($proposals as $p)
      <article class="card"><div class="row" style="justify-content:space-between;align-items:flex-start"><h2 class="t-h3"><a href="{{ route('missions.show', $p['missionSlug']) }}">{{ $p['missionTitle'] }}</a></h2><x-fc.money :amount="$p['price']" /></div>
        <p style="margin-top:8px"><span class="badge tone-{{ $p['tone'] }}">{{ $p['label'] }}</span> <span class="muted small">version {{ $p['number'] }} · {{ $p['days'] }} j · valable jusqu’au {{ $p['validUntil'] }}</span></p>
        <div class="row" style="margin-top:12px">@if($p['missionOpen'] && in_array($p['state'], ['active', 'withdrawn', 'released']))<a class="btn btn-secondary" href="{{ route('missions.proposal', $p['missionSlug']) }}">{{ $p['stale'] ? 'Reconfirmer' : 'Réviser' }}</a>@endif @if($p['state'] === 'active')<a class="btn btn-link" href="{{ route('freelance.proposals.withdraw', $p['id']) }}">Retirer</a>@endif <a class="btn btn-link" href="{{ route('messages.start.proposal', $p['id']) }}">Écrire au client</a> @if($p['state'] === 'selected')<span class="muted">La commande est dans « Demandes et commandes ».</span>@endif</div></article>
    @endforeach</div>
  @else
    <div class="card empty"><span class="ico-lg"><x-fc.icon name="briefcase" :size="26" /></span><p style="font-weight:600">Aucune proposition pour l’instant.</p><p class="muted" style="max-width:36em">Parcourez les missions ouvertes et proposez un prix ferme. Seul le client voit votre proposition.</p><a class="btn btn-primary" href="{{ route('missions.index') }}">Voir les missions ouvertes</a></div>
  @endif
</x-layouts.account>

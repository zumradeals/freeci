@php
  $all = collect($proposals);
  $isSent = fn ($p) => $p['state'] === 'active' && ! $p['stale'];
  $isStale = fn ($p) => $p['stale'];
  $isSel = fn ($p) => $p['state'] === 'selected';
  $isDone = fn ($p) => in_array($p['state'], ['withdrawn', 'released', 'closed'], true);
  $tabs = ['toutes' => ['Toutes', $all->count()], 'envoyees' => ['Envoyées', $all->filter($isSent)->count()], 'a-reconfirmer' => ['À reconfirmer', $all->filter($isStale)->count()], 'retenues' => ['Retenues', $all->filter($isSel)->count()], 'terminees' => ['Terminées', $all->filter($isDone)->count()]];
  $cur = array_key_exists(request('statut'), $tabs) ? request('statut') : 'toutes';
  $shown = match ($cur) { 'envoyees' => $all->filter($isSent), 'a-reconfirmer' => $all->filter($isStale), 'retenues' => $all->filter($isSel), 'terminees' => $all->filter($isDone), default => $all };
  $shown = $shown->sortBy(fn ($p) => $p['stale'] ? 0 : 1)->values();
@endphp
<x-layouts.account title="Mes propositions" space="freelancer">
  <header class="sx-head">
    <div><p class="sx-kicker">Espace freelance</p><h1>Mes propositions</h1><p class="muted">Les propositions que vous avez envoyées et leur état.</p></div>
    <div class="sx-acts"><a class="btn btn-primary" href="{{ route('missions.index') }}"><x-fc.icon name="search" /> Missions ouvertes</a></div>
  </header>
  @if(count($proposals))
    <section class="sx-metrics sx-metrics-3" aria-label="En un coup d’œil">
      <x-fc.metric-card label="À reconfirmer" :n="$tabs['a-reconfirmer'][1]" ico="warn" :hot="$tabs['a-reconfirmer'][1] > 0" :href="route('freelance.proposals', ['statut' => 'a-reconfirmer'])" />
      <x-fc.metric-card label="Propositions envoyées" :n="$tabs['envoyees'][1]" ico="inbox" :href="route('freelance.proposals', ['statut' => 'envoyees'])" />
      <x-fc.metric-card label="Missions retenues" :n="$tabs['retenues'][1]" ico="check-circle" :href="route('freelance.proposals', ['statut' => 'retenues'])" />
    </section>
    <nav class="sv-tabs" aria-label="Filtrer les propositions">@foreach($tabs as $k => [$label, $n])<a class="sv-tab {{ $cur === $k ? 'on' : '' }}" href="{{ route('freelance.proposals', $k === 'toutes' ? [] : ['statut' => $k]) }}" @if($cur === $k) aria-current="page" @endif>{{ $label }} <span class="n">{{ $n }}</span></a>@endforeach</nav>
    @if($shown->count())
    <div class="sv-list">@foreach($shown as $p)
      <article class="ms2-card {{ $p['stale'] ? 'attn' : '' }}" aria-labelledby="pr-{{ $p['id'] }}">
        <span class="ms2-ico" aria-hidden="true"><x-fc.icon name="pencil" :size="26" /></span>
        <div class="ms2-body">
          @if($p['category'])<p class="ms2-cat">{{ $p['category'] }}</p>@endif
          <h2 id="pr-{{ $p['id'] }}"><a href="{{ route('missions.show', $p['missionSlug']) }}">{{ $p['missionTitle'] }}</a></h2>
          <div class="sv-meta"><span class="badge tone-{{ $p['tone'] }}">{{ $p['label'] }}</span><span>Version {{ $p['number'] }}</span><span>{{ $p['days'] }} j</span><span>Valable jusqu’au {{ $p['validUntil'] }}</span></div>
          @if($p['stale'])<div class="sv-note"><b>Besoin modifié depuis votre proposition.</b> Reconfirmez-la pour qu’elle puisse être retenue.</div>@endif
          @if($p['state'] === 'selected')<p class="sv-plain">La commande est dans « Demandes et commandes ».</p>@endif
        </div>
        <div class="ms2-side"><span class="ms2-budget"><x-fc.money :amount="$p['price']" /></span></div>
        <div class="ms2-acts">
          @if($p['missionOpen'] && in_array($p['state'], ['active', 'withdrawn', 'released']))<a class="btn btn-primary" href="{{ route('missions.proposal', $p['missionSlug']) }}">{{ $p['stale'] ? 'Reconfirmer' : ($p['state'] === 'active' ? 'Réviser' : 'Proposer à nouveau') }}</a>@endif
          @if($p['state'] === 'selected')<a class="btn btn-primary" href="{{ route('freelance.orders') }}">Voir la commande</a>@endif
          <a class="btn btn-secondary" href="{{ route('missions.show', $p['missionSlug']) }}">Voir la mission</a>
          @if($p['state'] === 'active')<a class="btn btn-link" href="{{ route('freelance.proposals.withdraw', $p['id']) }}">Retirer</a>@endif
          <a class="btn btn-link" href="{{ route('messages.start.proposal', $p['id']) }}">Écrire au client</a>
        </div>
      </article>
    @endforeach</div>
    @else<div class="card empty"><p class="muted">Aucune proposition dans cette catégorie.</p></div>@endif
  @else
    <div class="card empty"><span class="ico-lg"><x-fc.icon name="briefcase" :size="26" /></span><p style="font-weight:600">Aucune proposition pour l’instant.</p><p class="muted" style="max-width:36em">Parcourez les missions ouvertes et proposez un prix ferme. Seul le client voit votre proposition.</p><a class="btn btn-primary" href="{{ route('missions.index') }}">Voir les missions ouvertes</a></div>
  @endif
  <p class="sv-info"><x-fc.icon name="lock" :size="18" /><span>Seul le client voit votre proposition. Si elle est retenue, ses conditions deviennent l’accord figé de la commande.</span></p>
</x-layouts.account>

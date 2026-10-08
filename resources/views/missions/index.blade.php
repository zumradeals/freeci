@php
  $all = collect($missions);
  $isTodo = fn ($m) => $m['needsAction'];
  $isOpen = fn ($m) => $m['state'] === 'open';
  $isPrep = fn ($m) => in_array($m['state'], ['draft', 'in_review'], true);
  $isDone = fn ($m) => in_array($m['state'], ['reserved', 'awarded', 'closed', 'cancelled', 'expired', 'suspended'], true);
  $tabs = ['toutes' => ['Toutes', $all->count()], 'a-traiter' => ['À traiter', $all->filter($isTodo)->count()], 'ouvertes' => ['Ouvertes', $all->filter($isOpen)->count()], 'en-preparation' => ['En préparation', $all->filter($isPrep)->count()], 'terminees' => ['Attribuées et terminées', $all->filter($isDone)->count()]];
  $cur = array_key_exists(request('statut'), $tabs) ? request('statut') : 'toutes';
  $shown = match ($cur) { 'a-traiter' => $all->filter($isTodo), 'ouvertes' => $all->filter($isOpen), 'en-preparation' => $all->filter($isPrep), 'terminees' => $all->filter($isDone), default => $all };
  $shown = $shown->sortBy(fn ($m) => $m['needsAction'] ? 0 : 1)->values();
@endphp
<x-layouts.account title="Mes missions" space="client">
  <header class="sx-head">
    <div><p class="sx-kicker">Espace client</p><h1>Mes missions</h1><p class="muted">Vos missions publiées ou en préparation et les propositions reçues.</p></div>
    <div class="sx-acts"><a class="btn btn-primary" href="{{ route('client.missions.new') }}"><x-fc.icon name="pencil" /> Publier une mission</a></div>
  </header>
  @if(count($missions))
    <section class="sx-metrics sx-metrics-3" aria-label="En un coup d’œil">
      <x-fc.metric-card label="Missions à traiter" :n="$tabs['a-traiter'][1]" ico="warn" :hot="$tabs['a-traiter'][1] > 0" :href="route('client.missions', ['statut' => 'a-traiter'])" />
      <x-fc.metric-card label="Missions ouvertes" :n="$tabs['ouvertes'][1]" ico="briefcase" :href="route('client.missions', ['statut' => 'ouvertes'])" />
      <x-fc.metric-card label="Propositions reçues" :n="$all->sum('proposals')" ico="inbox" :href="route('client.missions')" />
    </section>
    <nav class="sv-tabs" aria-label="Filtrer les missions">@foreach($tabs as $k => [$label, $n])<a class="sv-tab {{ $cur === $k ? 'on' : '' }}" href="{{ route('client.missions', $k === 'toutes' ? [] : ['statut' => $k]) }}" @if($cur === $k) aria-current="page" @endif>{{ $label }} <span class="n">{{ $n }}</span></a>@endforeach</nav>
    @if($shown->count())
    <div class="sv-list">@foreach($shown as $m)
      <article class="ms2-card {{ $m['needsAction'] ? 'attn' : '' }}" aria-labelledby="m-{{ $m['id'] }}">
        <span class="ms2-ico" aria-hidden="true"><x-fc.icon name="briefcase" :size="26" /></span>
        <div class="ms2-body">
          @if($m['category'])<p class="ms2-cat">{{ $m['category'] }}</p>@endif
          <h2 id="m-{{ $m['id'] }}"><a href="{{ route('client.missions.show', $m['id']) }}">{{ $m['title'] }}</a></h2>
          <div class="sv-meta"><span class="badge tone-{{ $m['tone'] }}"><x-fc.icon :name="$m['icon']" :size="16" />{{ $m['status'] }}</span>@if($m['deadline'])<span>Date limite de candidature : {{ $m['deadline'] }}</span>@endif</div>
          @if($m['needsAction'])<div class="sv-note"><b>Action attendue de votre part.</b> {{ $m['note'] }}</div>@elseif($m['note'] && ! $m['proposals'] && in_array($m['state'], ['suspended', 'expired'], true))<p class="sv-plain">{{ $m['note'] }}</p>@endif
        </div>
        <div class="ms2-side">@if($m['budget'])<span class="ms2-budget"><x-fc.money :amount="$m['budget']" /></span>@endif
          @if($m['daysLeft'] !== null)<span class="ms2-left">{{ $m['daysLeft'] === 0 ? 'Dernier jour' : 'J-'.$m['daysLeft'] }}</span>@endif
          @if($m['proposals'])<span class="ms2-props"><x-fc.icon name="user" :size="16" /> {{ $m['proposals'] }} proposition{{ $m['proposals'] > 1 ? 's' : '' }}</span>@endif</div>
        <div class="ms2-acts">
          @if($m['state'] === 'selection_ended' && $m['proposals'])<a class="btn btn-primary" href="{{ route('client.missions.proposals', $m['id']) }}">Choisir un freelance</a>
          @elseif($m['proposals'] && ! in_array($m['state'], ['reserved', 'awarded'], true))<a class="btn btn-primary" href="{{ route('client.missions.proposals', $m['id']) }}">Voir les propositions</a>
          @elseif($m['orderReference'])<a class="btn btn-primary" href="{{ route('orders.show', $m['orderReference']) }}">Voir la commande</a>
          @elseif($m['workingState'] === 'changes_requested')<a class="btn btn-primary" href="{{ route('client.missions.edit', $m['id']) }}">Corriger<span class="sr-only"> {{ $m['title'] }}</span></a>
          @elseif($m['state'] === 'draft')<a class="btn btn-primary" href="{{ route('client.missions.edit', $m['id']) }}">Continuer<span class="sr-only"> {{ $m['title'] }}</span></a>@endif
          <a class="btn btn-secondary" href="{{ route('client.missions.show', $m['id']) }}">Ouvrir<span class="sr-only"> {{ $m['title'] }}</span></a>
          @if($m['workingState'] && in_array($m['workingState'], ['draft', 'changes_requested', 'in_review'], true))<a class="btn btn-secondary" href="{{ route('client.missions.preview', $m['id']) }}">Aperçu</a>@endif
        </div>
      </article>
    @endforeach</div>
    @else<div class="card empty"><p class="muted">Aucune mission dans cette catégorie.</p></div>@endif
  @else
    <div class="card empty"><span class="ico-lg"><x-fc.icon name="briefcase" :size="26" /></span><p style="font-weight:600">Aucune mission pour l’instant.</p><p class="muted" style="max-width:36em">Décrivez votre besoin : des freelances vous envoient des propositions à prix ferme, vous en comparez puis en retenez une. Votre mission reste invisible tant que la modération ne l’a pas approuvée.</p><a class="btn btn-primary" href="{{ route('client.missions.new') }}">Publier une mission</a></div>
  @endif
  <p class="sv-info"><x-fc.icon name="info" :size="18" /><span>Votre mission reste invisible tant que la modération ne l’a pas approuvée. Seul vous voyez les propositions reçues ; vous en retenez une, et l’accord est figé à ce moment.</span></p>
</x-layouts.account>

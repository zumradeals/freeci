<x-layouts.admin title="Modération">
  <div class="page-body">
    <div class="sx-head"><div><p class="sx-kicker">Administration</p><h1>Modération</h1><p class="muted">Services et missions à examiner avant leur mise en ligne, et contenus en ligne.</p></div></div>
    <nav class="sv-tabs" aria-label="Files de modération">
      <a class="sv-tab {{ $tab === 'services' ? 'on' : '' }}" href="{{ route('admin.moderation') }}" @if($tab === 'services') aria-current="page" @endif>Services à modérer <span class="n">{{ $counts['services'] }}</span></a>
      <a class="sv-tab {{ $tab === 'missions' ? 'on' : '' }}" href="{{ route('admin.moderation', ['onglet' => 'missions']) }}" @if($tab === 'missions') aria-current="page" @endif>Missions à modérer <span class="n">{{ $counts['missions'] }}</span></a>
      <a class="sv-tab {{ $tab === 'en-ligne' ? 'on' : '' }}" href="{{ route('admin.moderation', ['onglet' => 'en-ligne', 'type' => $kind]) }}" @if($tab === 'en-ligne') aria-current="page" @endif>En ligne</a>
      <a class="sv-tab {{ $tab === 'suspendus' ? 'on' : '' }}" href="{{ route('admin.moderation', ['onglet' => 'suspendus', 'type' => $kind]) }}" @if($tab === 'suspendus') aria-current="page" @endif>Suspendus</a>
    </nav>
    @if(in_array($tab, ['services', 'missions']))
      @if(count($rows))
        <section class="ed-card" aria-label="{{ $tab === 'services' ? 'Services' : 'Missions' }} en attente de contrôle"><div class="md-t"><div class="md-r h" aria-hidden="true"><span>Titre</span><span>Auteur</span><span>Nature</span><span>Soumis le</span><span></span></div>
          @foreach($rows as $r)<a class="md-r" href="{{ route($tab === 'services' ? 'admin.moderation.service' : 'admin.moderation.mission', $r['id']) }}"><div><b>{{ $r['title'] ?: 'Sans titre' }}</b>@if($r['own'])<span class="badge tone-warning">Votre contenu</span>@endif</div><div><b>{{ $r['owner'] }}</b></div><div><span class="badge {{ str_starts_with($r['kind'], 'Modification') ? 'tone-info' : 'tone-neutral' }}">{{ $r['kind'] }}</span></div><div><b>{{ $r['since'] }}</b><small>depuis {{ $r['ago'] }}</small></div><x-fc.icon name="arrow-right" :size="16" /></a>@endforeach
        </div></section>
      @else
        <div class="ed-card empty" style="justify-items:center;text-align:center"><span class="ico-lg"><x-fc.icon name="check-circle" :size="26" /></span><p style="font-weight:600">Aucun {{ $tab === 'services' ? 'service' : 'mission' }} en attente.</p><p class="muted">Les nouvelles soumissions apparaîtront ici.</p></div>
      @endif
    @else
      <form method="get" class="ed-card" style="margin-bottom:16px" role="search"><input type="hidden" name="onglet" value="{{ $tab }}">
        <div class="ac-r2"><div class="field"><label for="f-type">Type</label><select class="select" id="f-type" name="type"><option value="service" @selected($kind === 'service')>Services</option><option value="mission" @selected($kind === 'mission')>Missions</option></select></div>
        <div class="field"><label for="f-q">Recherche (titre ou auteur)</label><input class="input" id="f-q" name="q" value="{{ $q }}" maxlength="80"></div></div>
        <div class="ac-acts"><button class="btn btn-primary" type="submit">Filtrer</button><a class="btn btn-link" href="{{ route('admin.moderation', ['onglet' => $tab]) }}">Réinitialiser</a></div>
      </form>
      @if($page->count())
        <section class="ed-card" aria-label="{{ $tab === 'suspendus' ? 'Contenus suspendus' : 'Contenus en ligne' }}"><div class="md-t md-live"><div class="md-r h" aria-hidden="true"><span>Titre</span><span>Auteur</span><span>État</span><span></span><span></span></div>
          @foreach($page as $r)<a class="md-r" href="{{ route($kind === 'service' ? 'admin.moderation.live.service' : 'admin.moderation.live.mission', $r['id']) }}"><div><b>{{ $r['title'] }}</b>@if($r['own'])<span class="badge tone-warning">Votre contenu</span>@endif</div><div><b>{{ $r['owner'] }}</b></div><div><span class="badge {{ $r['suspended'] ? 'tone-warning' : 'tone-success' }}">{{ $r['suspended'] ? 'Suspendu' : 'En ligne' }}</span></div><div></div><x-fc.icon name="arrow-right" :size="16" /></a>@endforeach
        </div></section>
        <x-admin.pager :p="$page" />
      @else
        <div class="ed-card empty" style="justify-items:center;text-align:center"><span class="ico-lg"><x-fc.icon name="search" :size="26" /></span><p style="font-weight:600">Aucun résultat.</p><p class="muted">Modifiez la recherche ou le type de contenu.</p></div>
      @endif
    @endif
  </div>
</x-layouts.admin>

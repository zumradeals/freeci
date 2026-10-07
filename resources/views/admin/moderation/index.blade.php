<x-layouts.admin title="Modération">
  <div class="page-body">
    <header class="page-head"><div class="row-top"><div><p class="eyebrow">Administration</p><h1 class="t-h1">Modération</h1><p class="lead">Services et missions à examiner avant leur mise en ligne, et contenus en ligne.</p></div></div></header>
    <nav class="tabs" aria-label="Files de modération">
      <a href="{{ route('admin.moderation') }}" @if($tab === 'services') aria-current="page" @endif>Services à modérer ({{ $counts['services'] }})</a>
      <a href="{{ route('admin.moderation', ['onglet' => 'missions']) }}" @if($tab === 'missions') aria-current="page" @endif>Missions à modérer ({{ $counts['missions'] }})</a>
      <a href="{{ route('admin.moderation', ['onglet' => 'en-ligne', 'type' => $kind]) }}" @if($tab === 'en-ligne') aria-current="page" @endif>En ligne</a>
      <a href="{{ route('admin.moderation', ['onglet' => 'suspendus', 'type' => $kind]) }}" @if($tab === 'suspendus') aria-current="page" @endif>Suspendus</a>
    </nav>
    @if(in_array($tab, ['services', 'missions']))
      @if(count($rows))
        <div class="table-wrap"><table class="list"><caption class="sr-only">{{ $tab === 'services' ? 'Services' : 'Missions' }} en attente de contrôle</caption>
          <thead><tr><th scope="col">Titre</th><th scope="col">Auteur</th><th scope="col">Nature</th><th scope="col">Soumis le</th></tr></thead><tbody>
          @foreach($rows as $r)<tr><td class="c-title"><a class="ttl" href="{{ route($tab === 'services' ? 'admin.moderation.service' : 'admin.moderation.mission', $r['id']) }}">{{ $r['title'] ?: 'Sans titre' }}</a></td><td data-label="Auteur">{{ $r['owner'] }}@if($r['own']) <span class="badge tone-warning">Votre contenu</span>@endif</td><td data-label="Nature">{{ $r['kind'] }}</td><td data-label="Soumis le">{{ $r['since'] }}</td></tr>@endforeach
        </tbody></table></div>
      @else
        <div class="card empty"><span class="ico-lg"><x-fc.icon name="check-circle" :size="26" /></span><p style="font-weight:600">Aucun {{ $tab === 'services' ? 'service' : 'mission' }} en attente.</p><p class="muted">Les nouvelles soumissions apparaîtront ici.</p></div>
      @endif
    @else
      <form method="get" class="filters card" style="margin-bottom:16px" role="search"><input type="hidden" name="onglet" value="{{ $tab }}">
        <div class="field"><label for="f-type">Type</label><select class="select" id="f-type" name="type"><option value="service" @selected($kind === 'service')>Services</option><option value="mission" @selected($kind === 'mission')>Missions</option></select></div>
        <div class="field"><label for="f-q">Recherche (titre ou auteur)</label><input class="input" id="f-q" name="q" value="{{ $q }}" maxlength="80"></div>
        <div class="row"><button class="btn btn-primary" type="submit">Filtrer</button><a class="btn btn-link" href="{{ route('admin.moderation', ['onglet' => $tab]) }}">Réinitialiser</a></div>
      </form>
      @if($page->count())
        <div class="table-wrap"><table class="list"><caption class="sr-only">{{ $tab === 'suspendus' ? 'Contenus suspendus' : 'Contenus en ligne' }}</caption>
          <thead><tr><th scope="col">Titre</th><th scope="col">Auteur</th><th scope="col">État</th></tr></thead><tbody>
          @foreach($page as $r)<tr><td class="c-title"><a class="ttl" href="{{ route($kind === 'service' ? 'admin.moderation.live.service' : 'admin.moderation.live.mission', $r['id']) }}">{{ $r['title'] }}</a></td><td data-label="Auteur">{{ $r['owner'] }}@if($r['own']) <span class="badge tone-warning">Votre contenu</span>@endif</td><td data-label="État">{{ $r['suspended'] ? 'Suspendu' : 'En ligne' }}</td></tr>@endforeach
        </tbody></table></div>
        <x-admin.pager :p="$page" />
      @else
        <div class="card empty"><span class="ico-lg"><x-fc.icon name="search" :size="26" /></span><p style="font-weight:600">Aucun résultat.</p><p class="muted">Modifiez la recherche ou le type de contenu.</p></div>
      @endif
    @endif
  </div>
</x-layouts.admin>

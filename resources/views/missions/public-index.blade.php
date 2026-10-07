<x-layouts.public title="Missions ouvertes" description="Besoins publiés par des clients : consultez-les et proposez vos services à prix ferme.">
<div class="container catalog mission-catalog">
  <header class="mission-catalog-head">
    <div><p class="eyebrow">Trouvez votre prochain projet</p><h1 class="t-h1">Missions ouvertes</h1><p class="muted">Découvrez les besoins des clients et proposez votre savoir-faire.</p></div>
    <a class="btn btn-primary" href="{{ route('client.missions.new') }}">Publier une mission</a>
  </header>
  <form method="get" action="{{ route('missions.index') }}" class="card catalog-filters mission-filters" role="search">
    <div class="field"><label for="q">Rechercher</label><input class="input" id="q" type="search" name="q" value="{{ $q }}" maxlength="100" placeholder="Mot-clé : plans, traduction…"></div>
    <div class="field"><label for="cat">Catégorie</label><select class="select" id="cat" name="categorie"><option value="">Toutes les catégories</option>@foreach($categories as $c)<option value="{{ $c['slug'] }}" @selected($categorie === $c['slug'])>{{ $c['name'] }}</option>@endforeach</select></div>
    <div class="field"><label for="tri">Trier par</label><select class="select" id="tri" name="tri"><option value="echeance" @selected($f['tri'] === 'echeance')>Date limite la plus proche</option><option value="recentes" @selected($f['tri'] === 'recentes')>Plus récentes</option><option value="budget-croissant" @selected($f['tri'] === 'budget-croissant')>Budget croissant</option><option value="budget-decroissant" @selected($f['tri'] === 'budget-decroissant')>Budget décroissant</option></select></div>
    <div class="mission-search-submit"><button class="btn btn-primary" type="submit">Rechercher</button></div>
    <details class="more-filters" @if($f['budget_min'] !== '' || $f['budget_max'] !== '' || $f['delai'] !== '') open @endif><summary>Affiner par budget ou date de candidature</summary>
      <div class="more-grid">
        <div class="field"><label for="bmin">Budget minimum (FCFA)</label><input class="input" id="bmin" name="budget_min" type="number" inputmode="numeric" min="1" value="{{ $f['budget_min'] }}"></div>
        <div class="field"><label for="bmax">Budget maximum (FCFA)</label><input class="input" id="bmax" name="budget_max" type="number" inputmode="numeric" min="1" value="{{ $f['budget_max'] }}"></div>
        <div class="field"><label for="dl">Candidatures closes dans (jours max)</label><input class="input" id="dl" name="delai" type="number" inputmode="numeric" min="1" max="365" value="{{ $f['delai'] }}"></div>
      </div></details>
    <div class="mission-filter-reset">@if($q !== '' || $categorie !== '' || $f['budget_min'] !== '' || $f['budget_max'] !== '' || $f['delai'] !== '' || $f['tri'] !== 'echeance')<a class="btn btn-link" href="{{ route('missions.index') }}">Réinitialiser</a>@endif</div>
  </form>
  <p class="muted small mission-result-count" role="status">{{ $results->total() }} mission{{ $results->total() > 1 ? 's' : '' }} ouverte{{ $results->total() > 1 ? 's' : '' }}</p>
  @if($results->count())
    <div class="mission-grid">@foreach($results as $m)
      <article class="card mission-card" aria-labelledby="mission-{{ $loop->index }}">
        <p class="mission-category"><x-fc.icon name="briefcase" :size="18" /><span>{{ $m['category'] }}</span></p>
        <h2 class="t-h3" id="mission-{{ $loop->index }}"><a href="{{ route('missions.show', $m['slug']) }}">{{ $m['title'] }}</a></h2>
        <p class="mission-excerpt">{{ $m['excerpt'] }}</p>
        <div class="mission-card-bottom">
          <dl class="mission-facts">
            <div><dt>Budget prévu</dt><dd><x-fc.money :amount="$m['budget']" /></dd></div>
            <div><dt>Candidatures jusqu’au</dt><dd>{{ $m['deadline'] }}</dd></div>
          </dl>
          <a class="btn btn-secondary" href="{{ route('missions.show', $m['slug']) }}">Voir la mission<span class="sr-only"> : {{ $m['title'] }}</span></a>
        </div>
      </article>
    @endforeach</div>
    @if($results->hasPages())<nav class="pager" aria-label="Pagination">@if($results->previousPageUrl())<a class="btn btn-secondary" href="{{ $results->previousPageUrl() }}">Page précédente</a>@else<span></span>@endif @if($results->nextPageUrl())<a class="btn btn-secondary" href="{{ $results->nextPageUrl() }}">Page suivante</a>@endif</nav>@endif
  @else
    <div class="card empty" style="margin-top:12px"><span class="ico-lg"><x-fc.icon name="briefcase" :size="26" /></span><p style="font-weight:600">Aucune mission ouverte ne correspond.</p><p class="muted" style="max-width:36em">Retirez un filtre (budget, délai, catégorie) ou revenez plus tard : les missions n’apparaissent qu’après approbation.</p></div>
  @endif
</div>
</x-layouts.public>

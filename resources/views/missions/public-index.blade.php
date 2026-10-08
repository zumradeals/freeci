@php
  $freelanceUrl = \App\Modules\Admin\Navigation\Destinations::url((string) config('freeci.home.freelance_btn_dest')) ?? route('freelance.activate');
  $featuredCats = collect($categories)->filter(fn ($c) => $c->featured)->take(4)->values();
  if ($featuredCats->isEmpty()) { $featuredCats = collect($categories)->take(4)->values(); }
  $otherCats = collect($categories)->reject(fn ($c) => $featuredCats->contains('slug', $c->slug))->values();
  $otherActive = $otherCats->firstWhere('slug', $categorie);
  $base = ['q' => $q, 'categorie' => $categorie, 'budget_min' => $f['budget_min'], 'budget_max' => $f['budget_max'], 'delai' => $f['delai'], 'tri' => $f['tri'] === 'echeance' ? '' : $f['tri']];
  $url = fn (array $over = []) => route('missions.index', array_filter(array_merge($base, $over), fn ($v) => $v !== null && $v !== ''));
  $hasFilters = $q !== '' || $categorie !== '' || $f['budget_min'] !== '' || $f['budget_max'] !== '' || $f['delai'] !== '';
@endphp
<x-layouts.public title="Missions ouvertes" description="Besoins publiés par des clients : consultez-les et proposez vos services à prix ferme." main-class="catalog-page">
<div class="service-directory mission-directory">
  <form class="sd-form" method="get" action="{{ route('missions.index') }}" role="search" data-autosubmit>
    <input type="hidden" name="categorie" value="{{ $categorie }}">
    <section class="sd-band" aria-labelledby="sd-title">
      <div class="container">
        <nav class="sd-crumbs" aria-label="Fil d’Ariane"><a href="{{ url('/') }}">Accueil</a><span aria-hidden="true">/</span><span aria-current="page">Missions</span></nav>
        <div class="sd-band-row">
          <div><h1 id="sd-title">Trouvez votre prochain projet.</h1><p>Découvrez les besoins des clients et proposez votre savoir-faire.</p></div>
          <div class="home-search-box sd-search">
            <div class="search-field"><x-fc.icon name="search" :size="22" /><label class="sr-only" for="q">Rechercher une mission</label><input class="input" id="q" name="q" type="search" value="{{ $q }}" placeholder="Un plan, une traduction, un logo…" maxlength="100" autocomplete="off"></div>
            <button class="btn btn-accent" type="submit">Rechercher</button>
          </div>
        </div>
      </div>
    </section>

    <div class="sd-cats"><div class="container">
      <ul class="sd-cat-list" aria-label="Catégories">
        <li><a class="sd-cat {{ $categorie === '' ? 'is-active' : '' }}" href="{{ $url(['categorie' => '']) }}" @if($categorie === '') aria-current="true" @endif><x-fc.icon name="grid" :size="20" /><span>Tout voir</span></a></li>
        @foreach($featuredCats as $c)
        <li><a class="sd-cat {{ $categorie === $c->slug ? 'is-active' : '' }}" href="{{ $url(['categorie' => $c->slug]) }}" @if($categorie === $c->slug) aria-current="true" @endif><x-fc.icon :name="$c->icon" :size="20" /><span>{{ $c->name }}</span></a></li>
        @endforeach
        @if($otherCats->isNotEmpty())
        <li class="sd-pop-wrap"><details class="sd-pop sd-pop-cats">
          <summary class="sd-cat {{ $otherActive ? 'is-active' : '' }}"><x-fc.icon name="more" :size="20" /><span>{{ $otherActive ? $otherActive->name : 'Autres catégories' }}</span></summary>
          <ul class="sd-pop-panel">@foreach($otherCats as $c)<li><a href="{{ $url(['categorie' => $c->slug]) }}">{{ $c->name }}</a></li>@endforeach</ul>
        </details></li>
        @endif
      </ul>
    </div></div>

    <div class="container sd-filters">
      <div class="sd-filter-row">
        <details class="sd-pop" @if($f['budget_min'] !== '' || $f['budget_max'] !== '') data-active @endif>
          <summary class="sd-filter">Budget<x-fc.icon name="chev-down" :size="16" /></summary>
          <div class="sd-pop-panel">
            <ul>
              <li><a href="{{ $url(['budget_min' => '', 'budget_max' => 50000]) }}">Moins de 50 000 FCFA</a></li>
              <li><a href="{{ $url(['budget_min' => 50000, 'budget_max' => 150000]) }}">50 000 à 150 000 FCFA</a></li>
              <li><a href="{{ $url(['budget_min' => 150000, 'budget_max' => '']) }}">Plus de 150 000 FCFA</a></li>
            </ul>
            <div class="sd-range">
              <div class="field"><label for="bmin">Minimum (FCFA)</label><input class="input" id="bmin" name="budget_min" type="number" inputmode="numeric" min="1" value="{{ $f['budget_min'] }}"></div>
              <div class="field"><label for="bmax">Maximum (FCFA)</label><input class="input" id="bmax" name="budget_max" type="number" inputmode="numeric" min="1" value="{{ $f['budget_max'] }}"></div>
            </div>
          </div>
        </details>
        <details class="sd-pop" @if($f['delai'] !== '') data-active @endif>
          <summary class="sd-filter">Date limite<x-fc.icon name="chev-down" :size="16" /></summary>
          <div class="sd-pop-panel">
            <ul>@foreach([3 => 'Candidatures closes sous 3 jours', 7 => 'Candidatures closes sous 7 jours', 14 => 'Candidatures closes sous 14 jours'] as $d => $label)<li><a href="{{ $url(['delai' => $d]) }}">{{ $label }}</a></li>@endforeach</ul>
            <div class="sd-range"><div class="field"><label for="dl">Jours maximum avant la date limite</label><input class="input" id="dl" name="delai" type="number" inputmode="numeric" min="1" max="365" value="{{ $f['delai'] }}"></div></div>
          </div>
        </details>
        <button class="btn btn-secondary sd-apply" type="submit">Appliquer</button>
        @if($hasFilters || $f['tri'] !== 'echeance')<a class="btn btn-link" href="{{ route('missions.index') }}">Réinitialiser</a>@endif
        <div class="sd-select sd-sort"><label class="sr-only" for="tri">Trier par</label>
          <select class="select" id="tri" name="tri">
            <option value="echeance" @selected($f['tri'] === 'echeance')>Trier : Date limite la plus proche</option>
            <option value="recentes" @selected($f['tri'] === 'recentes')>Plus récentes</option>
            <option value="budget-croissant" @selected($f['tri'] === 'budget-croissant')>Budget croissant</option>
            <option value="budget-decroissant" @selected($f['tri'] === 'budget-decroissant')>Budget décroissant</option>
          </select></div>
      </div>
      <div class="sd-count">
        <p class="sd-count-main" role="status">{{ $results->total() }} mission{{ $results->total() > 1 ? 's' : '' }} ouverte{{ $results->total() > 1 ? 's' : '' }}@if($hasFilters) pour votre recherche @endif</p>
        <p class="muted small">Budgets en FCFA <span aria-hidden="true">·</span> Candidatures jusqu’à la date indiquée</p>
      </div>
    </div>
  </form>

  <div class="container">
  @if($results->count())
    <div class="mission-grid mg-new">@foreach($results as $m)
      <article class="card mission-card mc-new" aria-labelledby="mission-{{ $loop->index }}">
        <div class="mc-top"><p class="mission-category"><x-fc.icon name="briefcase" :size="18" /><span>{{ $m['category'] }}</span></p><span class="mc-left">{{ $m['daysLeft'] === 0 ? 'Dernier jour' : 'J-'.$m['daysLeft'] }}</span></div>
        <h2 class="t-h3" id="mission-{{ $loop->index }}"><a href="{{ route('missions.show', $m['slug']) }}">{{ $m['title'] }}</a></h2>
        <p class="mission-excerpt">{{ $m['excerpt'] }}</p>
        <div class="mission-card-bottom">
          <dl class="mission-facts">
            <div><dt>Budget prévu</dt><dd class="mc-budget"><x-fc.money :amount="$m['budget']" /></dd></div>
            <div><dt>Candidatures jusqu’au</dt><dd>{{ $m['deadline'] }}</dd></div>
          </dl>
          <a class="btn btn-secondary" href="{{ route('missions.show', $m['slug']) }}">Voir la mission<span class="sr-only"> : {{ $m['title'] }}</span></a>
        </div>
      </article>
    @endforeach</div>
    @if($results->hasPages())
    <nav class="pager" aria-label="Pagination">
      <p class="muted">Page {{ $results->currentPage() }} sur {{ $results->lastPage() }}</p>
      <ul>
        @if($results->onFirstPage())<li><span class="btn btn-secondary is-disabled" aria-disabled="true">Précédent</span></li>@else<li><a class="btn btn-secondary" href="{{ $results->previousPageUrl() }}">Précédent</a></li>@endif
        @foreach($results->getUrlRange(1, $results->lastPage()) as $page => $pageUrl)<li><a class="btn {{ $page === $results->currentPage() ? 'btn-primary' : 'btn-secondary' }}" href="{{ $pageUrl }}" @if($page === $results->currentPage()) aria-current="page" @endif aria-label="Page {{ $page }}">{{ $page }}</a></li>@endforeach
        @if($results->hasMorePages())<li><a class="btn btn-secondary" href="{{ $results->nextPageUrl() }}">Suivant</a></li>@else<li><span class="btn btn-secondary is-disabled" aria-disabled="true">Suivant</span></li>@endif
      </ul>
    </nav>
    @endif
  @else
    <div class="card empty" style="margin-top:12px"><span class="ico-lg"><x-fc.icon name="briefcase" :size="26" /></span><p style="font-weight:600">Aucune mission ouverte ne correspond.</p><p class="muted" style="max-width:36em">Retirez un filtre (budget, délai, catégorie) ou revenez plus tard : les missions n’apparaissent qu’après approbation.</p></div>
  @endif
  </div>

  <section class="sd-cta" aria-label="Proposer vos services"><div class="container">
    <span class="sd-cta-ico"><x-fc.icon name="pen" :size="30" /></span>
    <div><h2>Vous avez un savoir-faire à proposer ?</h2><p>Créez votre espace freelance et publiez vos services à prix et délai annoncés.</p></div>
    <a class="btn btn-primary" href="{{ $freelanceUrl }}">Devenir freelance</a>
  </div></section>
</div>
</x-layouts.public>

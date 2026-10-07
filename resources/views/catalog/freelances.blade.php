<x-layouts.public title="Freelances" description="Profils publiés : compétences, services et avis réels." main-class="catalog-page">
<div class="container catalog talent-directory">
  <header class="catalog-head">
    <p class="eyebrow">Des compétences pour vos projets</p><h1 class="t-h1">Freelances</h1><p class="muted">Découvrez les profils et les services proposés sur FreeCI.</p>
    <p class="muted" role="status" aria-live="polite">@if($results->total() === 0) Aucun freelance ne correspond. @else {{ $results->total() }} {{ $results->total() > 1 ? 'profils publiés' : 'profil publié' }} @endif</p>
  </header>
  <form class="catalog-filters card talent-filters" method="get" action="{{ route('freelances.index') }}" role="search">
    <div class="field"><label for="fq">Recherche</label><div class="search-field"><x-fc.icon name="search" :size="22" /><input class="input" id="fq" name="q" type="search" value="{{ $f['q'] }}" maxlength="100" placeholder="Un nom, un métier, une compétence"></div></div>
    <div class="field"><label for="fc">Catégorie de service</label><select class="select" id="fc" name="categorie"><option value="">Toutes</option>@foreach($categories as $c)<option value="{{ $c->slug }}" @selected($f['categorie'] === $c->slug)>{{ $c->name }}</option>@endforeach</select></div>
    <div class="field"><label for="ft">Trier par</label><select class="select" id="ft" name="tri"><option value="recents" @selected($f['tri'] === 'recents')>Plus récents</option><option value="nom" @selected($f['tri'] === 'nom')>Nom (A–Z)</option><option value="mieux-notes" @selected($f['tri'] === 'mieux-notes')>Mieux notés (moyenne des avis publiés)</option></select></div>
    <div class="talent-search-submit"><button class="btn btn-primary" type="submit">Rechercher</button></div>
    <details class="more-filters" @if($f['competence'] !== '' || $f['prix_max'] !== '' || $f['delai_max'] !== '') open @endif><summary>Plus de filtres : compétence, prix, délai</summary>
      <div class="more-grid">
        <div class="field"><label for="fk">Compétence</label><select class="select" id="fk" name="competence"><option value="">Toutes</option>@foreach($skills as $sk)<option value="{{ $sk['name'] }}" @selected(mb_strtolower($f['competence']) === mb_strtolower($sk['name']))>{{ $sk['name'] }}</option>@endforeach</select></div>
        <div class="field"><label for="fp">Prix maximum d’un service (FCFA)</label><input class="input" id="fp" name="prix_max" type="number" inputmode="numeric" min="1" value="{{ $f['prix_max'] }}" placeholder="Prix maximum"></div>
        <div class="field"><label for="fd">Un service livré en (jours max)</label><input class="input" id="fd" name="delai_max" type="number" inputmode="numeric" min="1" max="365" value="{{ $f['delai_max'] }}" placeholder="Délai maximum"></div>
      </div></details>
    @if($f['tri'] === 'mieux-notes')<p class="muted small" style="grid-column:1/-1">Règle du tri : moyenne des avis publiés (services et missions), puis nombre d’avis ; les freelances sans avis sont placés en dernier.</p>@endif
    <div class="talent-filter-reset">@if($f['q'] !== '' || $f['categorie'] !== '' || $f['competence'] !== '' || $f['prix_max'] !== '' || $f['delai_max'] !== '')<a class="btn btn-link" href="{{ route('freelances.index') }}">Tout effacer</a>@endif</div>
  </form>

  @if($results->total() > 0)
    <div class="talent-grid">
      @foreach($results as $r)
        <article class="card talent-card" aria-labelledby="talent-{{ $loop->index }}">
          <div class="talent-card-top"><span class="avatar avatar-lg" aria-hidden="true">{{ $r['initials'] }}</span><x-fc.fav-button kind="freelance" :slug="$r['slug']" :on="$r['favorited']" /></div>
          <div class="talent-identity"><h2 class="t-h3" id="talent-{{ $loop->index }}"><a href="{{ route('freelances.show', $r['slug']) }}">{{ $r['name'] }}</a></h2><p>{{ $r['headline'] }}</p>@if($r['city'])<p class="muted small">{{ $r['city'] }}</p>@endif</div>
          @if(count($r['skills']))<ul class="talent-skills" aria-label="Compétences">@foreach(array_slice($r['skills'], 0, 3) as $sk)<li><span class="badge tone-neutral">{{ $sk }}</span></li>@endforeach</ul>@endif
          <div class="talent-card-bottom"><p class="small muted">{{ $r['servicesCount'] }} service{{ $r['servicesCount'] > 1 ? 's' : '' }} publié{{ $r['servicesCount'] > 1 ? 's' : '' }}</p>
          @if($r['ratingCount'] > 0)<p><x-fc.rating :avg="$r['ratingAvg']" :count="$r['ratingCount']" /></p>@endif
          <a class="btn btn-secondary" href="{{ route('freelances.show', $r['slug']) }}">Voir le profil<span class="sr-only"> de {{ $r['name'] }}</span></a></div>
        </article>
      @endforeach
    </div>
    @if($results->hasPages())
    <nav class="pager" aria-label="Pagination"><p class="muted">Page {{ $results->currentPage() }} sur {{ $results->lastPage() }}</p><ul>
      <li>@if($results->onFirstPage())<span class="btn btn-secondary is-disabled" aria-disabled="true">Précédent</span>@else<a class="btn btn-secondary" href="{{ $results->previousPageUrl() }}">Précédent</a>@endif</li>
      <li>@if($results->hasMorePages())<a class="btn btn-secondary" href="{{ $results->nextPageUrl() }}">Suivant</a>@else<span class="btn btn-secondary is-disabled" aria-disabled="true">Suivant</span>@endif</li></ul></nav>
    @endif
  @else
    <div class="card empty" style="margin-top:16px"><span class="ico-lg"><x-fc.icon name="user" :size="26" /></span>
      @if($f['q'] !== '' || $f['categorie'] !== '' || $f['competence'] !== '' || $f['prix_max'] !== '' || $f['delai_max'] !== '')
        <p style="font-weight:600">Aucun freelance ne correspond à ces filtres.</p><p class="muted">Retirez un filtre (compétence, prix, délai, catégorie) ou essayez un autre mot.</p><a class="btn btn-secondary" href="{{ route('freelances.index') }}">Tout effacer</a>
      @else<p style="font-weight:600">Les premiers profils seront publiés ici.</p>@endif</div>
  @endif
</div>
</x-layouts.public>

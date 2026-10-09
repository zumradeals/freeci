@php
  $featuredCats = collect($categories)->filter(fn ($c) => $c->featured)->take(4)->values();
  if ($featuredCats->isEmpty()) { $featuredCats = collect($categories)->take(4)->values(); }
  $otherCats = collect($categories)->reject(fn ($c) => $featuredCats->contains('slug', $c->slug))->values();
  $otherActive = $otherCats->firstWhere('slug', $f['categorie']);
  $base = ['q' => $f['q'], 'categorie' => $f['categorie'], 'competence' => $f['competence'], 'prix_max' => $f['prix_max'], 'delai_max' => $f['delai_max'], 'tri' => $f['tri'] === 'recents' ? '' : $f['tri']];
  $url = fn (array $over = []) => route('freelances.index', array_filter(array_merge($base, $over), fn ($v) => $v !== null && $v !== ''));
  $hasFilters = $f['q'] !== '' || $f['categorie'] !== '' || $f['competence'] !== '' || $f['prix_max'] !== '' || $f['delai_max'] !== '';
@endphp
<x-layouts.public title="Freelances" description="Profils publiés : compétences, services et avis réels." main-class="catalog-page">
<div class="service-directory talent-directory">
  <form class="sd-form" method="get" action="{{ route('freelances.index') }}" role="search" data-autosubmit>
    <input type="hidden" name="categorie" value="{{ $f['categorie'] }}">
    <section class="sd-band" aria-labelledby="sd-title">
      <div class="container">
        <nav class="sd-crumbs" aria-label="Fil d’Ariane"><a href="{{ url('/') }}">Accueil</a><span aria-hidden="true">/</span><span aria-current="page">Freelances</span></nav>
        <div class="sd-band-row">
          <div><h1 id="sd-title">Trouvez le talent qu’il vous faut.</h1><p>Découvrez les profils et les services proposés sur FreeCI.</p></div>
          <div class="home-search-box sd-search">
            <div class="search-field"><x-fc.icon name="search" :size="22" /><label class="sr-only" for="fq">Rechercher un freelance</label><input class="input" id="fq" name="q" type="search" value="{{ $f['q'] }}" maxlength="100" placeholder="Un nom, un métier, une compétence…" autocomplete="off"></div>
            <button class="btn btn-accent" type="submit">Rechercher</button>
          </div>
        </div>
      </div>
    </section>

    <div class="sd-cats"><div class="container">
      <ul class="sd-cat-list" aria-label="Catégories de service">
        <li><a class="sd-cat {{ $f['categorie'] === '' ? 'is-active' : '' }}" href="{{ $url(['categorie' => '']) }}" @if($f['categorie'] === '') aria-current="true" @endif><x-fc.icon name="grid" :size="20" /><span>Tout voir</span></a></li>
        @foreach($featuredCats as $c)
        <li><a class="sd-cat {{ $f['categorie'] === $c->slug ? 'is-active' : '' }}" href="{{ $url(['categorie' => $c->slug]) }}" @if($f['categorie'] === $c->slug) aria-current="true" @endif><x-fc.icon :name="$c->icon" :size="20" /><span>{{ $c->name }}</span></a></li>
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
        <div class="sd-select @if($f['competence'] !== '') is-set @endif"><label class="sr-only" for="fk">Compétence</label>
          <select class="select" id="fk" name="competence"><option value="">Compétence</option>@foreach($skills as $sk)<option value="{{ $sk['name'] }}" @selected(mb_strtolower($f['competence']) === mb_strtolower($sk['name']))>{{ $sk['name'] }}</option>@endforeach</select></div>
        <details class="sd-pop" @if($f['prix_max'] !== '') data-active @endif>
          <summary class="sd-filter">Prix d’un service<x-fc.icon name="chev-down" :size="16" /></summary>
          <div class="sd-pop-panel">
            <ul>@foreach([25000, 50000, 100000] as $v)<li><a href="{{ $url(['prix_max' => $v]) }}">Un service jusqu’à {{ number_format($v, 0, ',', "\u{202F}") }} FCFA</a></li>@endforeach</ul>
            <div class="sd-range"><div class="field"><label for="fp">Prix maximum d’un service (FCFA)</label><input class="input" id="fp" name="prix_max" type="number" inputmode="numeric" min="1" value="{{ $f['prix_max'] }}"></div></div>
          </div>
        </details>
        <details class="sd-pop" @if($f['delai_max'] !== '') data-active @endif>
          <summary class="sd-filter">Délai<x-fc.icon name="chev-down" :size="16" /></summary>
          <div class="sd-pop-panel">
            <ul>@foreach([3 => 'Un service livré en 3 jours', 7 => 'Un service livré en 7 jours', 14 => 'Un service livré en 14 jours'] as $d => $label)<li><a href="{{ $url(['delai_max' => $d]) }}">{{ $label }}</a></li>@endforeach</ul>
            <div class="sd-range"><div class="field"><label for="fd">Délai maximum (jours)</label><input class="input" id="fd" name="delai_max" type="number" inputmode="numeric" min="1" max="365" value="{{ $f['delai_max'] }}"></div></div>
          </div>
        </details>
        <button class="btn btn-secondary sd-apply" type="submit">Appliquer</button>
        @if($hasFilters || $f['tri'] !== 'recents')<a class="btn btn-link" href="{{ route('freelances.index') }}">Tout effacer</a>@endif
        <div class="sd-select sd-sort"><label class="sr-only" for="ft">Trier par</label>
          <select class="select" id="ft" name="tri">
            <option value="recents" @selected($f['tri'] === 'recents')>Trier : Plus récents</option>
            <option value="nom" @selected($f['tri'] === 'nom')>Nom (A–Z)</option>
            <option value="mieux-notes" @selected($f['tri'] === 'mieux-notes')>Mieux notés</option>
          </select></div>
      </div>
      @if($f['tri'] === 'mieux-notes')<p class="muted small">Règle du tri : moyenne des avis publiés (services et missions), puis nombre d’avis ; les freelances sans avis sont placés en dernier.</p>@endif
      <div class="sd-count">
        <p class="sd-count-main" role="status" aria-live="polite">@if($results->total() === 0) Aucun freelance ne correspond. @else {{ $results->total() }} {{ $results->total() > 1 ? 'freelances' : 'freelance' }}@if($hasFilters) pour votre recherche @endif @endif</p>
        <p class="muted small">Prix en FCFA <span aria-hidden="true">·</span> Seuls les profils publiés et actifs sont affichés</p>
      </div>
    </div>
  </form>

  <div class="container">
  @if($results->total() > 0)
    <div class="talent-grid tg-new">
      @foreach($results as $r)
        <article class="card talent-card tc-new" aria-labelledby="talent-{{ $loop->index }}">
          <div class="talent-card-top"><x-fc.avatar :name="$r['name']" :user="$r['userId']" size="lg" /><x-fc.fav-button kind="freelance" :slug="$r['slug']" :on="$r['favorited']" /></div>
          <div class="talent-identity"><h2 class="t-h3" id="talent-{{ $loop->index }}"><a href="{{ route('freelances.show', $r['slug']) }}">{{ $r['name'] }}</a></h2><p>{{ $r['headline'] }}</p>@if($r['city'])<p class="muted small"><x-fc.icon name="pin" :size="14" /> {{ $r['city'] }}</p>@endif<p style="margin:6px 0 0"><x-fc.seller-pill :user="$r['userId']" short /></p></div>
          @if(count($r['skills']))<ul class="talent-skills" aria-label="Compétences">@foreach(array_slice($r['skills'], 0, 3) as $sk)<li><span class="badge tone-neutral">{{ $sk }}</span></li>@endforeach</ul>@endif
          <div class="talent-card-bottom">
            <div class="tc-line"><p class="small muted">{{ $r['servicesCount'] }} service{{ $r['servicesCount'] > 1 ? 's' : '' }} publié{{ $r['servicesCount'] > 1 ? 's' : '' }}</p>
              @if($r['ratingCount'] > 0)<p class="tc-rate"><x-fc.rating :avg="$r['ratingAvg']" :count="$r['ratingCount']" /></p>@else<p class="tc-rate muted">Pas encore d’avis</p>@endif</div>
            @if($r['minPrice'])<p class="tc-from">Services dès <strong><x-fc.money :amount="$r['minPrice']" /></strong></p>@endif
            <a class="btn btn-secondary" href="{{ route('freelances.show', $r['slug']) }}">Voir le profil<span class="sr-only"> de {{ $r['name'] }}</span></a>
          </div>
        </article>
      @endforeach
    </div>
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
    <div class="card empty" style="margin-top:16px"><span class="ico-lg"><x-fc.icon name="user" :size="26" /></span>
      @if($hasFilters)
        <p style="font-weight:600">Aucun freelance ne correspond à ces filtres.</p><p class="muted">Retirez un filtre (compétence, prix, délai, catégorie) ou essayez un autre mot.</p><a class="btn btn-secondary" href="{{ route('freelances.index') }}">Tout effacer</a>
      @else<p style="font-weight:600">Les premiers profils seront publiés ici.</p>@endif</div>
  @endif
  </div>

  <section class="sd-cta" aria-label="Publier une mission"><div class="container">
    <span class="sd-cta-ico"><x-fc.icon name="briefcase" :size="30" /></span>
    <div><h2>Vous ne trouvez pas le bon profil ?</h2><p>Publiez une mission : les freelances intéressés vous envoient leurs propositions.</p></div>
    <a class="btn btn-primary" href="{{ \App\Modules\Admin\Navigation\Destinations::url((string) config('freeci.home.mission_btn_dest')) ?? route('client.missions.new') }}">Publier une mission</a>
  </div></section>
</div>
</x-layouts.public>

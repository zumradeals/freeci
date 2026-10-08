<div class="container catalog service-directory">
  <header class="catalog-head">
    <p class="eyebrow">Des prestations à prix et délai annoncés</p><h1 class="t-h1">Services</h1><p class="muted">Comparez, puis envoyez votre demande au freelance de votre choix.</p>
    <p class="muted" role="status" aria-live="polite">
      @if($results->total() === 0) Aucun service ne correspond.
      @else {{ $results->total() }} {{ $results->total() > 1 ? 'services publiés' : 'service publié' }}@if($criteria->hasFilters()) correspondant à votre recherche @endif
      @endif
    </p>
  </header>

  <form class="catalog-filters card talent-filters" method="get" action="{{ route('services.index') }}" role="search" wire:submit.prevent>
    <div class="field">
      <label for="cq">Recherche</label>
      <div class="search-field"><x-fc.icon name="search" :size="22" /><input class="input" id="cq" name="q" type="search" wire:model.live.debounce.400ms="q" placeholder="Un plan, un logo, un site web" autocomplete="off" maxlength="100"></div>
    </div>
    <div class="field">
      <label for="cc">Catégorie</label>
      <select class="select" id="cc" name="categorie" wire:model.live="categorie">
        <option value="">Toutes les catégories</option>
        @foreach($categories as $c)<option value="{{ $c->slug }}">{{ $c->name }}</option>@endforeach
      </select>
    </div>
    <div class="field">
      <label for="ct">Trier par</label>
      <select class="select" id="ct" name="tri" wire:model.live="tri">
        <option value="pertinence">Pertinence</option>
        <option value="prix-croissant">Prix croissant</option>
        <option value="prix-decroissant">Prix décroissant</option>
        <option value="delai-court">Délai le plus court</option>
        <option value="recents">Plus récents</option>
        <option value="mieux-notes">Mieux notés (moyenne des avis publiés)</option>
      </select>
    </div>
    <div class="talent-search-submit"><button class="btn btn-primary" type="submit">Rechercher</button></div>
    <details class="more-filters" @if($criteria->priceMin || $criteria->priceMax || $criteria->delayMax || $criteria->skill) open @endif>
      <summary>Plus de filtres : prix, délai, compétence</summary>
      <div class="more-grid">
        <div class="field"><label for="pmin">Prix minimum (FCFA)</label><input class="input" id="pmin" name="prix_min" type="number" inputmode="numeric" min="1" wire:model.live.debounce.600ms="prixMin"></div>
        <div class="field"><label for="pmax">Prix maximum (FCFA)</label><input class="input" id="pmax" name="prix_max" type="number" inputmode="numeric" min="1" wire:model.live.debounce.600ms="prixMax"></div>
        <div class="field"><label for="dmax">Délai maximum (jours)</label><input class="input" id="dmax" name="delai_max" type="number" inputmode="numeric" min="1" max="365" wire:model.live.debounce.600ms="delaiMax"></div>
        <div class="field"><label for="csk">Compétence du freelance</label>
          <select class="select" id="csk" name="competence" wire:model.live="competence"><option value="">Toutes</option>@foreach($skills as $sk)<option value="{{ $sk['name'] }}">{{ $sk['name'] }}</option>@endforeach</select></div>
      </div>
    </details>
    @if($criteria->sort === 'mieux-notes')<p class="muted small" style="grid-column:1/-1">Règle du tri : moyenne des avis publiés issus de ce service, puis nombre d’avis ; les services sans avis sont placés en dernier.</p>@endif
  </form>

  @if($criteria->hasFilters())
  <div class="chips applied" role="group" aria-label="Filtres appliqués">
    @if($criteria->query)<button type="button" class="chip" wire:click="removeFilter('q')" aria-label="Retirer la recherche « {{ $criteria->query }} »">« {{ $criteria->query }} » <x-fc.icon name="close" :size="16" /></button>@endif
    @if($currentCategory)<button type="button" class="chip" wire:click="removeFilter('categorie')" aria-label="Retirer la catégorie {{ $currentCategory->name }}">{{ $currentCategory->name }} <x-fc.icon name="close" :size="16" /></button>@endif
    @if($criteria->priceMin)<button type="button" class="chip" wire:click="removeFilter('prixMin')" aria-label="Retirer le prix minimum">dès {{ number_format($criteria->priceMin, 0, ',', "\u{202F}") }} FCFA <x-fc.icon name="close" :size="16" /></button>@endif
    @if($criteria->priceMax)<button type="button" class="chip" wire:click="removeFilter('prixMax')" aria-label="Retirer le prix maximum">jusqu’à {{ number_format($criteria->priceMax, 0, ',', "\u{202F}") }} FCFA <x-fc.icon name="close" :size="16" /></button>@endif
    @if($criteria->delayMax)<button type="button" class="chip" wire:click="removeFilter('delaiMax')" aria-label="Retirer le délai maximum">{{ $criteria->delayMax }} j max <x-fc.icon name="close" :size="16" /></button>@endif
    @if($criteria->skill)<button type="button" class="chip" wire:click="removeFilter('competence')" aria-label="Retirer la compétence {{ $criteria->skill }}">{{ $criteria->skill }} <x-fc.icon name="close" :size="16" /></button>@endif
    <button type="button" class="btn btn-link" wire:click="clear">Tout effacer</button>
  </div>
  @endif

  @if($results->total() > 0)
    <div class="svc-grid service-grid" wire:loading.class="is-loading">
      @foreach($results as $service)<x-fc.service-card :service="$service" :level="2" wire:key="svc-{{ $service->slug }}" />@endforeach
    </div>
    @if($results->hasPages())
    <nav class="pager" aria-label="Pagination">
      <p class="muted">Page {{ $results->currentPage() }} sur {{ $results->lastPage() }}</p>
      <ul>
        @if($results->onFirstPage())<li><span class="btn btn-secondary is-disabled" aria-disabled="true">Précédent</span></li>
        @else<li><a class="btn btn-secondary" href="{{ $results->previousPageUrl() }}" wire:click.prevent="previousPage">Précédent</a></li>@endif
        @foreach($results->getUrlRange(1, $results->lastPage()) as $page => $url)
          <li><a class="btn {{ $page === $results->currentPage() ? 'btn-primary' : 'btn-secondary' }}" href="{{ $url }}" wire:click.prevent="gotoPage({{ $page }})" @if($page === $results->currentPage()) aria-current="page" @endif aria-label="Page {{ $page }}">{{ $page }}</a></li>
        @endforeach
        @if($results->hasMorePages())<li><a class="btn btn-secondary" href="{{ $results->nextPageUrl() }}" wire:click.prevent="nextPage">Suivant</a></li>
        @else<li><span class="btn btn-secondary is-disabled" aria-disabled="true">Suivant</span></li>@endif
      </ul>
    </nav>
    @endif
  @else
    <div class="card empty">
      <span class="ico-lg"><x-fc.icon name="inbox" :size="26" /></span>
      @if($criteria->hasFilters())
        <p style="font-weight:600">Aucun service ne correspond{{ $criteria->query ? ' à « '.$criteria->query.' »' : '' }} avec ces filtres.</p>
        <p class="muted">Retirez un filtre (prix, délai, compétence, catégorie) ou essayez un autre mot. Seuls les services actuellement publiés sont listés.</p>
        <button class="btn btn-secondary" type="button" wire:click="clear">Tout effacer</button>
      @else
        <p style="font-weight:600">Les premiers services seront publiés ici.</p>
      @endif
    </div>
  @endif
</div>

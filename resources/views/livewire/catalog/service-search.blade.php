<div class="container catalog">
  <header class="catalog-head">
    <h1 class="t-h1">Services</h1>
    <p class="muted" role="status" aria-live="polite">
      @if($results->total() === 0) Aucun service ne correspond.
      @else {{ $results->total() }} {{ $results->total() > 1 ? 'services publiés' : 'service publié' }}@if($criteria->hasFilters()) correspondant à votre recherche @endif
      @endif
    </p>
  </header>

  <form class="catalog-filters card" method="get" action="{{ route('services.index') }}" role="search" wire:submit.prevent>
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
        <option value="recents">Plus récents</option>
      </select>
    </div>
    <div class="catalog-submit"><button class="btn btn-primary" type="submit">Rechercher</button></div>
  </form>

  @if($criteria->hasFilters())
  <div class="chips applied" role="group" aria-label="Filtres appliqués">
    @if($criteria->query)<button type="button" class="chip" wire:click="removeQuery" aria-label="Retirer la recherche « {{ $criteria->query }} »">« {{ $criteria->query }} » <x-fc.icon name="close" :size="16" /></button>@endif
    @if($currentCategory)<button type="button" class="chip" wire:click="removeCategory" aria-label="Retirer la catégorie {{ $currentCategory->name }}">{{ $currentCategory->name }} <x-fc.icon name="close" :size="16" /></button>@endif
    <button type="button" class="btn btn-link" wire:click="clear">Tout effacer</button>
  </div>
  @endif

  @if($results->total() > 0)
    <div class="svc-grid" wire:loading.class="is-loading">
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
        <p class="muted">Retirez un filtre ou essayez un autre mot.</p>
        <button class="btn btn-secondary" type="button" wire:click="clear">Tout effacer</button>
      @else
        <p style="font-weight:600">Les premiers services seront publiés ici.</p>
      @endif
    </div>
  @endif
</div>

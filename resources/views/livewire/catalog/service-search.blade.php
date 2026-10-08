@php
  $missionUrl = \App\Modules\Admin\Navigation\Destinations::url((string) config('freeci.home.mission_btn_dest')) ?? route('client.missions.new');
  $featuredCats = collect($categories)->filter(fn ($c) => $c->featured)->take(4)->values();
  if ($featuredCats->isEmpty()) { $featuredCats = collect($categories)->take(4)->values(); }
  $otherCats = collect($categories)->reject(fn ($c) => $featuredCats->contains('slug', $c->slug))->values();
  $query = fn (array $over = []) => array_filter(array_merge(['q' => $criteria->query, 'categorie' => $criteria->categorySlug, 'prix_min' => $criteria->priceMin, 'prix_max' => $criteria->priceMax, 'delai_max' => $criteria->delayMax, 'competence' => $criteria->skill, 'tri' => $criteria->sort === 'pertinence' ? null : $criteria->sort], $over), fn ($v) => $v !== null && $v !== '');
  $fmt = fn ($n) => number_format($n, 0, ',', "\u{202F}");
@endphp
<div class="service-directory">
  <form class="sd-form" method="get" action="{{ route('services.index') }}" role="search" wire:submit.prevent>
    <input type="hidden" name="categorie" value="{{ $criteria->categorySlug }}">
    <section class="sd-band" aria-labelledby="sd-title">
      <div class="container">
        <nav class="sd-crumbs" aria-label="Fil d’Ariane"><a href="{{ url('/') }}">Accueil</a><span aria-hidden="true">/</span><span aria-current="page">Services</span></nav>
        <div class="sd-band-row">
          <div><h1 id="sd-title">Un savoir-faire pour chaque projet.</h1><p>Comparez les prestations et trouvez celle qui vous convient.</p></div>
          <div class="home-search-box sd-search">
            <div class="search-field"><x-fc.icon name="search" :size="22" /><label class="sr-only" for="cq">Rechercher un service</label><input class="input" id="cq" name="q" type="search" wire:model.live.debounce.400ms="q" placeholder="Un logo, un site web, des plans…" autocomplete="off" maxlength="100"></div>
            <button class="btn btn-accent" type="submit">Rechercher</button>
          </div>
        </div>
      </div>
    </section>

    <div class="sd-cats"><div class="container">
      <ul class="sd-cat-list" aria-label="Catégories">
        <li><a class="sd-cat {{ $criteria->categorySlug ? '' : 'is-active' }}" href="{{ route('services.index', $query(['categorie' => null])) }}" wire:click.prevent="$set('categorie', '')" @if(! $criteria->categorySlug) aria-current="true" @endif><x-fc.icon name="grid" :size="20" /><span>Tout voir</span></a></li>
        @foreach($featuredCats as $c)
        <li><a class="sd-cat {{ $criteria->categorySlug === $c->slug ? 'is-active' : '' }}" href="{{ route('services.index', $query(['categorie' => $c->slug])) }}" wire:click.prevent="$set('categorie', '{{ $c->slug }}')" @if($criteria->categorySlug === $c->slug) aria-current="true" @endif><x-fc.icon :name="$c->icon" :size="20" /><span>{{ $c->name }}</span></a></li>
        @endforeach
        @if($otherCats->isNotEmpty())
        @php $otherActive = $otherCats->firstWhere('slug', $criteria->categorySlug); @endphp
        <li class="sd-pop-wrap"><details class="sd-pop sd-pop-cats">
          <summary class="sd-cat {{ $otherActive ? 'is-active' : '' }}"><x-fc.icon name="more" :size="20" /><span>{{ $otherActive ? $otherActive->name : 'Autres catégories' }}</span></summary>
          <ul class="sd-pop-panel">@foreach($otherCats as $c)<li><a href="{{ route('services.index', $query(['categorie' => $c->slug])) }}" wire:click.prevent="$set('categorie', '{{ $c->slug }}')">{{ $c->name }}</a></li>@endforeach</ul>
        </details></li>
        @endif
      </ul>
    </div></div>

    <div class="container sd-filters">
      <div class="sd-filter-row">
        <details class="sd-pop" @if($criteria->priceMin || $criteria->priceMax) data-active @endif>
          <summary class="sd-filter">Budget<x-fc.icon name="chev-down" :size="16" /></summary>
          <div class="sd-pop-panel">
            <ul>
              <li><a href="{{ route('services.index', $query(['prix_min' => null, 'prix_max' => 25000])) }}" wire:click.prevent="setBudget('', '25000')">Moins de 25 000 FCFA</a></li>
              <li><a href="{{ route('services.index', $query(['prix_min' => 25000, 'prix_max' => 75000])) }}" wire:click.prevent="setBudget('25000', '75000')">25 000 à 75 000 FCFA</a></li>
              <li><a href="{{ route('services.index', $query(['prix_min' => 75000, 'prix_max' => null])) }}" wire:click.prevent="setBudget('75000', '')">Plus de 75 000 FCFA</a></li>
            </ul>
            <div class="sd-range">
              <div class="field"><label for="pmin">Minimum (FCFA)</label><input class="input" id="pmin" name="prix_min" type="number" inputmode="numeric" min="1" wire:model.live.debounce.600ms="prixMin"></div>
              <div class="field"><label for="pmax">Maximum (FCFA)</label><input class="input" id="pmax" name="prix_max" type="number" inputmode="numeric" min="1" wire:model.live.debounce.600ms="prixMax"></div>
            </div>
          </div>
        </details>
        <details class="sd-pop" @if($criteria->delayMax) data-active @endif>
          <summary class="sd-filter">Délai<x-fc.icon name="chev-down" :size="16" /></summary>
          <div class="sd-pop-panel">
            <ul>@foreach([3 => 'Jusqu’à 3 jours', 7 => 'Jusqu’à 7 jours', 14 => 'Jusqu’à 14 jours'] as $d => $label)<li><a href="{{ route('services.index', $query(['delai_max' => $d])) }}" wire:click.prevent="setDelay('{{ $d }}')">{{ $label }}</a></li>@endforeach</ul>
            <div class="sd-range"><div class="field"><label for="dmax">Délai maximum (jours)</label><input class="input" id="dmax" name="delai_max" type="number" inputmode="numeric" min="1" max="365" wire:model.live.debounce.600ms="delaiMax"></div></div>
          </div>
        </details>
        <div class="sd-select @if($criteria->skill) is-set @endif"><label class="sr-only" for="csk">Compétence du freelance</label>
          <select class="select" id="csk" name="competence" wire:model.live="competence"><option value="">Compétence</option>@foreach($skills as $sk)<option value="{{ $sk['name'] }}">{{ $sk['name'] }}</option>@endforeach</select></div>
        <button class="btn btn-secondary sd-apply" type="submit">Appliquer</button>
        <div class="sd-select sd-sort"><label class="sr-only" for="ct">Trier par</label>
          <select class="select" id="ct" name="tri" wire:model.live="tri">
            <option value="pertinence">Trier : Pertinence</option>
            <option value="prix-croissant">Prix croissant</option>
            <option value="prix-decroissant">Prix décroissant</option>
            <option value="delai-court">Délai le plus court</option>
            <option value="recents">Plus récents</option>
            <option value="mieux-notes">Mieux notés</option>
          </select></div>
      </div>
      @if($criteria->sort === 'mieux-notes')<p class="muted small">Règle du tri : moyenne des avis publiés issus de ce service, puis nombre d’avis ; les services sans avis sont placés en dernier.</p>@endif
      <div class="sd-count">
        <p class="sd-count-main" role="status" aria-live="polite">
          @if($results->total() === 0) Aucun service ne correspond.
          @else {{ $results->total() }} {{ $results->total() > 1 ? 'services disponibles' : 'service disponible' }}@if($criteria->hasFilters()) pour votre recherche @endif
          @endif
        </p>
        <p class="muted small">Prix en FCFA <span aria-hidden="true">·</span> Délais annoncés par les freelances</p>
      </div>
    </div>
  </form>

  <div class="container">
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

  <section class="sd-cta" aria-label="Besoin sur mesure"><div class="container">
    <span class="sd-cta-ico"><x-fc.icon name="briefcase" :size="30" /></span>
    <div><h2>Vous avez un besoin sur mesure ?</h2><p>Publiez une mission et recevez des propositions de freelances qualifiés.</p></div>
    <a class="btn btn-primary" href="{{ $missionUrl }}">Publier une mission</a>
  </div></section>
</div>

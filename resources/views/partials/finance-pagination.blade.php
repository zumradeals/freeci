@if($pagination->hasPages())
<nav class="row row-gap" aria-label="Pages des opérations financières">
  @if($pagination->previousPageUrl())<a class="btn btn-secondary" href="{{ $pagination->previousPageUrl() }}">Page précédente</a>@endif
  <span class="muted small">Page {{ $pagination->currentPage() }} sur {{ $pagination->lastPage() }} · {{ $pagination->total() }} commandes</span>
  @if($pagination->nextPageUrl())<a class="btn btn-secondary" href="{{ $pagination->nextPageUrl() }}">Page suivante</a>@endif
</nav>
@endif

@props(['p'])
@if($p->total() > 0)
<nav class="pager" aria-label="Pagination"><span class="muted small">{{ $p->total() }} résultat{{ $p->total() > 1 ? 's' : '' }} · page {{ $p->currentPage() }} sur {{ $p->lastPage() }}</span>
  <span class="row">@if($p->previousPageUrl())<a class="btn btn-secondary" href="{{ $p->previousPageUrl() }}">Page précédente</a>@endif @if($p->nextPageUrl())<a class="btn btn-secondary" href="{{ $p->nextPageUrl() }}">Page suivante</a>@endif</span></nav>
@endif

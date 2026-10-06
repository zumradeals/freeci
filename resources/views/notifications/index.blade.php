<x-layouts.account title="Notifications" :space="$space">
  <header class="page-head"><div class="row-top"><div><p class="eyebrow">{{ $space === 'freelancer' ? 'Espace freelance' : 'Espace client' }}</p><h1 class="t-h1">Notifications</h1><p class="lead">Les événements qui vous concernent, du plus récent au plus ancien.</p></div>
    <div class="row">@if($unread > 0)<form method="post" action="{{ route('notifications.read-all') }}" data-once>@csrf<button class="btn btn-secondary" type="submit">Tout marquer comme lu ({{ $unread }})</button></form>@endif<a class="btn btn-link" href="{{ route('notifications.preferences') }}">Préférences</a></div></div></header>
  @if($page->count())
    <section class="card panel" aria-labelledby="h-list"><div class="card-head"><h2 class="t-h2" id="h-list">Vos notifications</h2>@if($unread > 0)<span class="meta-r">{{ $unread }} non lue{{ $unread > 1 ? 's' : '' }}</span>@endif</div><div class="order-list">@foreach($page as $n)
      <article class="order-card" @if($n['unread']) style="border-left:4px solid var(--info-700, #1d5fa8)" @endif><h2 class="ttl"><a class="stretch" href="{{ route('notifications.open', $n['id']) }}">{{ $n['title'] }}@if($n['unread'])<span class="sr-only"> (non lue)</span>@endif</a></h2>
        <p class="am">@if($n['unread'])<span class="badge tone-info">Nouveau</span>@endif</p><p class="mt">@if($n['body']){{ $n['body'] }} · @endif{{ $n['when'] }}@if($n['optional']) · facultative @endif</p><span class="chev" aria-hidden="true"><x-fc.icon name="arrow-right" :size="20" /></span></article>
    @endforeach</div></section>
    <nav class="row" style="margin-top:16px;justify-content:space-between" aria-label="Pagination">@if($page->previousPageUrl())<a class="btn btn-secondary" href="{{ $page->previousPageUrl() }}">Plus récentes</a>@else<span></span>@endif @if($page->nextPageUrl())<a class="btn btn-secondary" href="{{ $page->nextPageUrl() }}">Plus anciennes</a>@endif</nav>
  @else
    <div class="card empty"><span class="ico-lg"><x-fc.icon name="inbox" :size="26" /></span><p style="font-weight:600">Aucune notification.</p><p class="muted" style="max-width:36em">Les demandes, propositions, livraisons, corrections, reports et décisions de modération apparaîtront ici. Chaque lien revérifie vos droits.</p></div>
  @endif
</x-layouts.account>

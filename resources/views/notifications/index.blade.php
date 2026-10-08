<x-layouts.account title="Notifications" :space="$space">
  <div class="page-body">
    <div class="sx-head"><div><p class="sx-kicker">{{ $space === 'freelancer' ? 'Espace freelance' : 'Espace client' }}</p><h1>Notifications</h1><p class="muted">Les événements qui vous concernent, du plus récent au plus ancien.</p></div>
      <div class="ac-acts">@if($unread > 0)<form method="post" action="{{ route('notifications.read-all') }}" data-once>@csrf<button class="btn btn-secondary" type="submit">Tout marquer comme lu ({{ $unread }})</button></form>@endif<a class="btn btn-link" href="{{ route('notifications.preferences') }}">Préférences</a></div></div>
    <nav class="sv-tabs" aria-label="Filtrer les notifications"><a class="sv-tab {{ $only ? '' : 'on' }}" href="{{ route('notifications.index') }}" @unless($only) aria-current="page" @endunless>Toutes <span class="n">{{ $total }}</span></a><a class="sv-tab {{ $only ? 'on' : '' }}" href="{{ route('notifications.index', ['statut' => 'non-lues']) }}" @if($only) aria-current="page" @endif>Non lues <span class="n">{{ $unread }}</span></a></nav>
    @if($page->count())
      <section class="ed-card" aria-label="Vos notifications"><div class="ac-nl">@foreach($page as $n)
        <a class="ac-n {{ $n['unread'] ? 'un' : '' }}" href="{{ route('notifications.open', $n['id']) }}"><span class="ac-ni" aria-hidden="true"><x-fc.icon :name="$n['icon']" :size="22" /></span>
          <div><b>{{ $n['title'] }}@if($n['unread'])<span class="sr-only"> (non lue)</span>@endif</b><small>@if($n['body']){{ $n['body'] }}@endif @if($n['optional'])<span class="muted">· facultative</span>@endif</small></div>
          <span class="ac-w">@if($n['unread'])<span class="badge tone-info">Nouveau</span> @endif{{ $n['when'] }}</span><x-fc.icon name="arrow-right" :size="18" /></a>
      @endforeach</div></section>
      <nav class="ac-pg" aria-label="Pagination">@if($page->previousPageUrl())<a class="btn btn-secondary" href="{{ $page->previousPageUrl() }}">Plus récentes</a>@else<span></span>@endif @if($page->nextPageUrl())<a class="btn btn-secondary" href="{{ $page->nextPageUrl() }}">Plus anciennes</a>@endif</nav>
    @else
      <div class="ed-card empty" style="justify-items:center;text-align:center"><span class="ico-lg"><x-fc.icon name="inbox" :size="26" /></span><p style="font-weight:600">{{ $only ? 'Aucune notification non lue.' : 'Aucune notification.' }}</p><p class="muted" style="max-width:36em">Les demandes, propositions, livraisons, corrections, reports et décisions de modération apparaîtront ici. Chaque lien revérifie vos droits.</p></div>
    @endif
  </div>
</x-layouts.account>

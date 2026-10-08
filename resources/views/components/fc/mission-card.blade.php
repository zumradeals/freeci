@props(['m'])
<article class="card mission-card mc-new" aria-labelledby="mission-{{ $m['slug'] }}">
  <div class="mc-top"><p class="mission-category"><x-fc.icon name="briefcase" :size="18" /><span>{{ $m['category'] }}</span></p><span class="mc-left">{{ $m['daysLeft'] === 0 ? 'Dernier jour' : 'J-'.$m['daysLeft'] }}</span></div>
  <h2 class="t-h3" id="mission-{{ $m['slug'] }}"><a href="{{ route('missions.show', $m['slug']) }}">{{ $m['title'] }}</a></h2>
  <p class="mission-excerpt">{{ $m['excerpt'] }}</p>
  <div class="mission-card-bottom">
    <dl class="mission-facts">
      <div><dt>Budget prévu</dt><dd class="mc-budget"><x-fc.money :amount="$m['budget']" /></dd></div>
      <div><dt>Candidatures jusqu’au</dt><dd>{{ $m['deadline'] }}</dd></div>
    </dl>
    <a class="btn btn-secondary" href="{{ route('missions.show', $m['slug']) }}">Voir la mission<span class="sr-only"> : {{ $m['title'] }}</span></a>
  </div>
</article>

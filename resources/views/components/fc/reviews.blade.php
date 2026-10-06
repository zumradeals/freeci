{{-- Liste d'avis PUBLIÉS (commande réelle, publication atteinte, non masqués). `$source` : étiquette d'origine affichée sur le profil (service / mission). --}}
@props(['reviews', 'param' => 'avis'])
@foreach($reviews as $r)
  <article class="review" id="avis-{{ $r['id'] }}">
    <p><span class="stars" aria-label="Note {{ $r['rating'] }} sur 5">{{ str_repeat('★', $r['rating']) }}{{ str_repeat('☆', 5 - $r['rating']) }}</span> <strong>{{ $r['rating'] }}/5</strong></p>
    <p style="margin-top:6px">{!! nl2br(e($r['comment'])) !!}</p>
    <p class="rmeta">Client d’une commande validée · {{ $r['when'] }}@if($r['source']) · {{ $r['source'] }}@endif @auth · <a href="{{ route('support.report.form', ['review', $r['id']]) }}">Signaler</a>@endauth</p>
    @if($r['reply'])<div class="reply"><p class="rmeta"><strong>Réponse du freelance</strong> · {{ $r['reply']['when'] }}@auth · <a href="{{ route('support.report.form', ['reply', $r['id']]) }}">Signaler</a>@endauth</p><p>{!! nl2br(e($r['reply']['body'])) !!}</p></div>@endif
  </article>
@endforeach
@if($reviews->hasPages())
  <nav class="pager" aria-label="Pagination des avis"><ul><li>@if($reviews->onFirstPage())<span class="btn btn-secondary is-disabled" aria-disabled="true">Avis plus récents</span>@else<a class="btn btn-secondary" href="{{ $reviews->previousPageUrl() }}#avis">Avis plus récents</a>@endif</li><li>@if($reviews->hasMorePages())<a class="btn btn-secondary" href="{{ $reviews->nextPageUrl() }}#avis">Avis plus anciens</a>@else<span class="btn btn-secondary is-disabled" aria-disabled="true">Avis plus anciens</span>@endif</li></ul></nav>
@endif

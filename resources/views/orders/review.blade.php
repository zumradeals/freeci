<x-layouts.account title="Avis" :space="$space">
  <nav class="crumbs" aria-label="Fil d’Ariane"><a href="{{ route('orders.show', $panel['reference']) }}">Commande {{ $panel['reference'] }}</a><span class="sep" aria-hidden="true">›</span><span aria-current="page">Avis</span></nav>
  <div class="sx-head"><div><p class="sx-kicker">Commande {{ $panel['reference'] }}</p><h1>Avis sur la commande</h1>@if(($panel['closed'] ?? true) && ($panel['review'] ?? null) === null && ($panel['canSubmit'] ?? false))<p class="muted">Un seul avis par commande ; il ne pourra être modifié ni supprimé.</p>@endif</div></div>
  @if(! ($panel['closed'] ?? true))
    <div class="ed-card empty" style="justify-items:center;text-align:center"><span class="ico-lg"><x-fc.icon name="lock" :size="26" /></span><h2>Pas encore d’avis possible</h2><p class="muted">Un avis n’est possible qu’après la validation explicite de la livraison par le client et la clôture de la commande.</p><a class="btn btn-primary" href="{{ route('orders.show', $panel['reference']) }}">Voir la commande</a></div>
  @else
  @if($panel['test'])<div class="rq-info" role="note" style="margin-bottom:16px"><x-fc.icon name="flag" :size="18" /><span><strong>Commande de test (sandbox) : aperçu uniquement.</strong> Un avis déposé ici n’est <strong>jamais public</strong> et n’a aucun effet sur la note ni la réputation du freelance. Il n’est visible que des deux parties de cette commande.</span></div>@endif

  @if($panel['review'] === null)
    @if($panel['canSubmit'])
      <div class="ac-grid"><form method="post" action="{{ route('orders.review.store', $panel['reference']) }}" data-once novalidate>@csrf<input type="hidden" name="operation_key" value="{{ $key }}">
        <section class="ed-card" aria-labelledby="h-form"><h2 id="h-form">Votre avis</h2>
          @if($errors->any())<div class="rq-info" role="alert"><x-fc.icon name="error" :size="18" /><span>{{ $errors->first() }}</span></div>@endif
          <fieldset style="border:0;padding:0;margin:0;min-width:0"><legend style="font-weight:650;margin-bottom:8px">Note</legend><div class="dl-stars">@foreach([1, 2, 3, 4, 5] as $n)<label class="dl-pick"><input class="dl-star-in" type="radio" name="rating" value="{{ $n }}" required @checked((int) old('rating') === $n)><span class="dl-star">{{ $n }} ★</span></label>@endforeach</div></fieldset>
          <div class="field"><label for="rc">Commentaire ({{ config('freeci.reviews.comment_min') }} à {{ config('freeci.reviews.comment_max') }} caractères)</label><textarea class="textarea" id="rc" name="comment" rows="5" minlength="{{ config('freeci.reviews.comment_min') }}" maxlength="{{ config('freeci.reviews.comment_max') }}" required>{{ old('comment') }}</textarea></div>
          <div><button class="btn btn-primary btn-lg" type="submit" data-once-label="Envoi…">Déposer mon avis</button></div></section></form>
        <aside class="ac-side"><section class="ed-ck" aria-labelledby="h-bef"><h3 id="h-bef">Avant de publier</h3><ul class="ac-ess">
          <li><x-fc.icon name="lock" :size="18" /><div><b>Non modifiable</b><small>Ni par vous ni par un administrateur.</small></div></li>
          @unless($panel['test'])<li><x-fc.icon name="clock" :size="18" /><div><b>Publication différée</b><small>Public après un délai de {{ config('freeci.reviews.publication_days') }} jours suivant la clôture (délai provisoire).</small></div></li>@endunless</ul></section></aside></div>
    @elseif($panel['isClient'])
      <div class="ed-card"><p>{{ $panel['eligibleReason'] ?? 'Aucun avis ne peut être déposé pour cette commande.' }}</p></div>
    @else
      <div class="ed-card"><p class="muted">Le client n’a pas encore déposé d’avis.</p></div>
    @endif
  @elseif($panel['review']['pending'] ?? false)
    <div class="ed-card"><p>Un avis a été déposé ; il sera visible à sa date de publication.</p></div>
  @else
    @php($rv = $panel['review'])
    <div class="ac-grid"><div class="ac-main">
    <section class="dl-rev"><p style="margin:0;display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;align-items:center"><span><span class="dl-rv" aria-label="Note {{ $rv['rating'] }} sur 5">{{ str_repeat('★', $rv['rating']) }}{{ str_repeat('☆', 5 - $rv['rating']) }}</span> <strong>{{ $rv['rating'] }}/5</strong></span>
      <span>@if($panel['test'])<span class="tag-demo">Aperçu de test — non public</span>@elseif($rv['hidden'])<span class="badge tone-warning">Masqué par la modération</span>@elseif($rv['public'])<span class="badge tone-success">Publié</span>@else<span class="badge tone-neutral">Publication le {{ $rv['visibleAt'] }}</span>@endif</span></p>
      <p style="margin:0">{!! nl2br(e($rv['comment'])) !!}</p>
      @if($panel['reply'])<div class="dl-reply"><b>Réponse du freelance</b><span class="muted small">{{ $panel['reply']['when'] }}@if($panel['reply']['hidden']) · <span class="badge tone-warning">masquée</span>@endif</span><p style="margin:0">{!! nl2br(e($panel['reply']['body'])) !!}</p></div>@endif
    </section>
    @if($panel['canReply'])
      <form method="post" action="{{ route('reviews.reply', $rv['id']) }}" data-once novalidate>@csrf<input type="hidden" name="reference" value="{{ $panel['reference'] }}">
        <section class="ed-card" aria-labelledby="h-rep"><h2 id="h-rep">Répondre publiquement</h2>
          <p class="muted small" style="margin-top:-8px">Une seule réponse, visible avec l’avis{{ $panel['test'] ? ' (aperçu de test : non public)' : '' }}. Elle ne pourra pas être modifiée.</p>
          @if($errors->any())<div class="rq-info" role="alert"><x-fc.icon name="error" :size="18" /><span>{{ $errors->first() }}</span></div>@endif
          <div class="field"><label for="rr">Votre réponse (10 à {{ config('freeci.reviews.reply_max') }} caractères)</label><textarea class="textarea" id="rr" name="body" rows="4" minlength="10" maxlength="{{ config('freeci.reviews.reply_max') }}" required>{{ old('body') }}</textarea></div>
          <div><button class="btn btn-primary" type="submit" data-once-label="Envoi…">Publier ma réponse</button></div></section></form>
    @endif
    </div></div>
  @endif
  @endif
</x-layouts.account>

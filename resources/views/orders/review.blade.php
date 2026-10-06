<x-layouts.account title="Avis" :space="$space">
  <nav class="crumbs" aria-label="Fil d’Ariane"><a href="{{ route('orders.show', $panel['reference']) }}">Commande {{ $panel['reference'] }}</a><span class="sep" aria-hidden="true">›</span><span aria-current="page">Avis</span></nav>
  <h1 class="t-h1">Avis sur la commande</h1>
  @if(! ($panel['closed'] ?? true))
    <div class="card empty" style="max-width:640px"><span class="ico-lg"><x-fc.icon name="lock" :size="26" /></span><h2 class="t-h2">Pas encore d’avis possible</h2><p class="muted">Un avis n’est possible qu’après la validation explicite de la livraison par le client et la clôture de la commande.</p><a class="btn btn-primary" href="{{ route('orders.show', $panel['reference']) }}">Voir la commande</a></div>
  @else
  @if($panel['test'])<div class="notice tone-warning" role="note"><x-fc.icon name="flag" /><p><strong>Commande de test (sandbox) : aperçu uniquement.</strong> Un avis déposé ici n’est <strong>jamais public</strong> et n’a aucun effet sur la note ni la réputation du freelance. Il n’est visible que des deux parties de cette commande.</p></div>@endif

  @if($panel['review'] === null)
    @if($panel['canSubmit'])
      <section class="card" style="max-width:640px" aria-labelledby="h-form"><h2 class="t-h2" id="h-form">Votre avis</h2>
        <p class="muted small">Un seul avis par commande ; il ne pourra être modifié ni supprimé par vous ni par un administrateur. {{ $panel['test'] ? '' : 'Il sera public après un délai de '.config('freeci.reviews.publication_days').' jours suivant la clôture (délai provisoire).' }}</p>
        @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>{{ $errors->first() }}</p></div>@endif
        <form method="post" action="{{ route('orders.review.store', $panel['reference']) }}" data-once class="stack-sm" novalidate>@csrf<input type="hidden" name="operation_key" value="{{ $key }}">
          <fieldset class="rating-pick"><legend class="lbl">Note</legend>@foreach([1, 2, 3, 4, 5] as $n)<label><input type="radio" name="rating" value="{{ $n }}" required @checked((int) old('rating') === $n)> {{ $n }} ★</label>@endforeach</fieldset>
          <div class="field"><label for="rc">Commentaire ({{ config('freeci.reviews.comment_min') }} à {{ config('freeci.reviews.comment_max') }} caractères)</label><textarea class="input" id="rc" name="comment" rows="5" minlength="{{ config('freeci.reviews.comment_min') }}" maxlength="{{ config('freeci.reviews.comment_max') }}" required>{{ old('comment') }}</textarea></div>
          <button class="btn btn-primary" type="submit" data-once-label="Envoi…">Déposer mon avis</button></form></section>
    @elseif($panel['isClient'])
      <div class="card" style="max-width:640px"><p>{{ $panel['eligibleReason'] ?? 'Aucun avis ne peut être déposé pour cette commande.' }}</p></div>
    @else
      <div class="card" style="max-width:640px"><p class="muted">Le client n’a pas encore déposé d’avis.</p></div>
    @endif
  @elseif($panel['review']['pending'] ?? false)
    <div class="card" style="max-width:640px"><p>Un avis a été déposé ; il sera visible à sa date de publication.</p></div>
  @else
    @php($rv = $panel['review'])
    <section class="card review" style="max-width:640px"><p><span class="stars" aria-label="Note {{ $rv['rating'] }} sur 5">{{ str_repeat('★', $rv['rating']) }}{{ str_repeat('☆', 5 - $rv['rating']) }}</span> <strong>{{ $rv['rating'] }}/5</strong>
      @if($panel['test'])<span class="tag-demo">Aperçu de test — non public</span>@elseif($rv['hidden'])<span class="badge tone-warning">Masqué par la modération</span>@elseif($rv['public'])<span class="badge tone-success">Publié</span>@else<span class="badge tone-neutral">Publication le {{ $rv['visibleAt'] }}</span>@endif</p>
      <p style="margin-top:6px">{!! nl2br(e($rv['comment'])) !!}</p>
      @if($panel['reply'])<div class="reply"><p class="rmeta"><strong>Réponse du freelance</strong> · {{ $panel['reply']['when'] }}@if($panel['reply']['hidden']) · <span class="badge tone-warning">masquée</span>@endif</p><p>{!! nl2br(e($panel['reply']['body'])) !!}</p></div>@endif
    </section>
    @if($panel['canReply'])
      <section class="card" style="max-width:640px;margin-top:16px" aria-labelledby="h-rep"><h2 class="t-h2" id="h-rep">Répondre publiquement</h2>
        <p class="muted small">Une seule réponse, visible avec l’avis{{ $panel['test'] ? ' (aperçu de test : non public)' : '' }}. Elle ne pourra pas être modifiée.</p>
        @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>{{ $errors->first() }}</p></div>@endif
        <form method="post" action="{{ route('reviews.reply', $rv['id']) }}" class="stack-sm" data-once novalidate>@csrf<input type="hidden" name="reference" value="{{ $panel['reference'] }}">
          <div class="field"><label for="rr">Votre réponse (10 à {{ config('freeci.reviews.reply_max') }} caractères)</label><textarea class="input" id="rr" name="body" rows="4" minlength="10" maxlength="{{ config('freeci.reviews.reply_max') }}" required>{{ old('body') }}</textarea></div>
          <button class="btn btn-primary" type="submit" data-once-label="Envoi…">Publier ma réponse</button></form></section>
    @endif
  @endif
  @endif
</x-layouts.account>

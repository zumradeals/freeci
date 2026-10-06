<x-layouts.account :title="'Conversation : '.$t['with']" :space="$space">
  <nav class="crumbs" aria-label="Fil d’Ariane"><a class="back-m" href="{{ route('messages.index') }}"><x-fc.icon name="arrow-right" :size="16" class="flip" />Messages</a><a class="hide-m" href="{{ route('messages.index') }}">Messages</a><span class="sep hide-m" aria-hidden="true">›</span><span class="hide-m" aria-current="page">{{ $t['with'] }}</span></nav>
  <section class="card" aria-labelledby="h-conv"><h1 class="t-h2" id="h-conv">{{ $t['with'] }}</h1><p class="muted small">{{ $t['context'] }}@if($t['orderReference']) · <a href="{{ route('orders.show', $t['orderReference']) }}">Ouvrir la commande {{ $t['orderReference'] }}</a>@endif</p>
    @if($t['orderReference'] && $t['kind'] !== 'order')<p class="note-line"><x-fc.icon name="info" :size="16" /><span>Cette conversation a été rattachée à la commande : le contexte est conservé. Les échanges des autres candidats ne sont pas accessibles.</span></p>@endif
    <p class="note-line"><x-fc.icon name="shield" :size="16" /><span><strong>Les messages et fichiers échangés ici ne valent ni livraison, ni modification de l’accord, ni acceptation d’un report, ni validation.</strong> Ces actions se font depuis la commande.</span></p></section>
  <livewire:conversation-freshness :conversation="$t['id']" :after="$t['lastId']" />
  <div class="thread" style="display:grid;gap:10px;margin-top:12px" aria-live="off">
    @if($t['olderBefore'])<p><a class="btn btn-secondary" href="{{ route('messages.show', ['conversation' => $t['id'], 'avant' => $t['olderBefore']]) }}">Messages plus anciens</a></p>@endif
    @forelse($t['messages'] as $m)
      <article class="msg {{ $m['mine'] ? 'msg-mine' : 'msg-theirs' }}" id="m-{{ $m['id'] }}" aria-label="Message de {{ $m['who'] }}">
        <p class="msg-meta"><strong>{{ $m['who'] }}</strong> · {{ $m['when'] }}@if($m['unread']) <span class="badge tone-info">Nouveau</span>@endif</p>
        @if($m['body'] !== '')<p>{!! nl2br(e($m['body'])) !!}</p>@endif
        @if($m['file'])<div class="file-line"><x-fc.icon name="file" :size="22" class="fi" /><span class="fn">{{ $m['file']['name'] }}<span class="meta-f">{{ $m['file']['size'] }}</span><span class="sec"><x-fc.icon :name="$m['file']['clean'] ? 'shield' : ($m['file']['rejected'] ? 'error' : 'clock')" :size="16" />{{ $m['file']['label'] }}</span>@if(! $m['file']['clean'] && ! $m['file']['rejected'])<small class="muted">Non téléchargeable avant la fin du contrôle de sécurité.</small>@endif</span>
          <span class="acts">@if($m['file']['url'])<a class="btn btn-secondary" href="{{ $m['file']['url'] }}"><x-fc.icon name="download" :size="18" />Télécharger<span class="sr-only"> {{ $m['file']['name'] }}</span></a>@endif</span></div>@endif
        @unless($m['mine'])<p class="small" style="margin-top:6px"><a href="{{ route('support.report.form', ['message', $m['id']]) }}">Signaler ce message</a></p>@endunless
      </article>
    @empty<p class="muted">Aucun message.</p>@endforelse
  </div>

  @if($t['canSend'])
    @if($t['iBlocked'])<div class="notice tone-warning" role="note"><x-fc.icon name="warn" /><p>Vous avez bloqué ce contact. Cette conversation reste possible parce qu’elle est liée à une commande active.</p></div>@endif
    @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>{{ $errors->first() }} Votre saisie est conservée.</p></div>@endif
    <form method="post" action="{{ route('messages.send', $t['id']) }}" enctype="multipart/form-data" data-once class="card" style="display:grid;gap:12px;margin-top:12px" novalidate>@csrf
      <input type="hidden" name="client_key" value="{{ $operationKey }}">
      <div class="field"><label for="body">Votre message</label><textarea class="textarea" id="body" name="body" rows="4" maxlength="{{ config('freeci.messaging.body_max') }}" @error('body') aria-invalid="true" aria-describedby="e-body" @enderror>{{ old('body') }}</textarea>@error('body')<p class="field-error" id="e-body"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
      <div class="field"><label for="file">Pièce jointe (facultatif)</label><input class="input" id="file" type="file" name="file" @error('file') aria-invalid="true" aria-describedby="e-file" @enderror><p class="hint">Un fichier par message ; il n’est téléchargeable qu’après le contrôle de sécurité.</p>@error('file')<p class="field-error" id="e-file"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
      <div><button class="btn btn-primary btn-lg" type="submit" data-once-label="Envoi…">Envoyer</button></div></form>
  @else
    <div class="notice tone-info" role="note" style="margin-top:12px"><x-fc.icon name="lock" /><p>{{ $t['iBlocked'] ? 'Vous avez bloqué ce contact : les nouveaux échanges sont suspendus. L’historique reste consultable.' : 'Cette conversation n’accepte plus de nouveaux messages. L’historique reste consultable.' }}</p></div>
  @endif
  <div class="row" style="margin-top:12px">@if($t['iBlocked'])<form method="post" action="{{ route('messages.unblock', $t['id']) }}" data-once>@csrf<button class="btn btn-secondary" type="submit">Débloquer ce contact</button></form>
    @else<form method="post" action="{{ route('messages.block', $t['id']) }}" data-once>@csrf<button class="btn btn-link" type="submit">Bloquer ce contact</button></form>@endif</div>
</x-layouts.account>

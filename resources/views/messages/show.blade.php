<x-layouts.account :title="'Conversation : '.$t['with']" :space="$space">
  <div class="ms-shell is-conv">
    <x-messages.list-pane :conversations="$conversations" :blocked="$blocked" :space="$space" :current="$t['id']" />
    <section class="ms-conv" aria-labelledby="h-conv">
      <header class="ms-ch">
        <a class="ms-back" href="{{ route('messages.index', array_filter(['espace' => $space === 'freelancer' ? 'freelance' : null])) }}" aria-label="Retour aux messages"><x-fc.icon name="arrow-right" :size="20" class="flip" /></a>
        <span class="avatar avatar-lg" aria-hidden="true">{{ mb_strtoupper(mb_substr($t['with'], 0, 1)) }}</span>
        <div><h1 class="t-h3" id="h-conv">{{ $t['with'] }}</h1><small>{{ $t['context'] }}</small></div>
        @if($t['orderReference'])<a class="btn btn-secondary" href="{{ route('orders.show', $t['orderReference']) }}">Ouvrir la commande<span class="sr-only"> {{ $t['orderReference'] }}</span></a>@endif
        @if($t['iBlocked'])<form method="post" action="{{ route('messages.unblock', $t['id']) }}" data-once>@csrf<button class="btn btn-link" type="submit">Débloquer ce contact</button></form>
        @else<form method="post" action="{{ route('messages.block', $t['id']) }}" data-once>@csrf<button class="btn btn-link" type="submit">Bloquer ce contact</button></form>@endif
      </header>
      <div class="ms-notice"><x-fc.icon name="shield" :size="16" /><span><strong>Les messages et fichiers échangés ici ne valent ni livraison, ni modification de l’accord, ni acceptation d’un report, ni validation.</strong> Ces actions se font depuis la commande.@if($t['orderReference'] && $t['kind'] !== 'order') Cette conversation a été rattachée à la commande : le contexte est conservé. Les échanges des autres candidats ne sont pas accessibles.@endif</span></div>
      <livewire:conversation-freshness :conversation="$t['id']" :after="$t['lastId']" />
      <div class="ms-thread" data-thread aria-live="off">
        @if($t['olderBefore'])<p><a class="btn btn-secondary" href="{{ route('messages.show', array_filter(['conversation' => $t['id'], 'avant' => $t['olderBefore'], 'espace' => $space === 'freelancer' ? 'freelance' : null])) }}">Messages plus anciens</a></p>@endif
        @forelse($t['messages'] as $m)
          <article class="ms-msg {{ $m['mine'] ? 'mine' : 'theirs' }} msg {{ $m['mine'] ? 'msg-mine' : 'msg-theirs' }}" id="m-{{ $m['id'] }}" aria-label="Message de {{ $m['who'] }}">
            <div class="b">
              @if($m['body'] !== '')<p>{!! nl2br(e($m['body'])) !!}</p>@endif
              @if($m['file'])<div class="file-line"><x-fc.icon name="file" :size="22" class="fi" /><span class="fn">{{ $m['file']['name'] }}<span class="meta-f">{{ $m['file']['size'] }}</span><span class="sec"><x-fc.icon :name="$m['file']['clean'] ? 'shield' : ($m['file']['rejected'] ? 'error' : 'clock')" :size="16" />{{ $m['file']['label'] }}</span>@if(! $m['file']['clean'] && ! $m['file']['rejected'])<small class="muted">Non téléchargeable avant la fin du contrôle de sécurité.</small>@endif</span>
                <span class="acts">@if($m['file']['url'])<a class="btn btn-secondary" href="{{ $m['file']['url'] }}"><x-fc.icon name="download" :size="18" />Télécharger<span class="sr-only"> {{ $m['file']['name'] }}</span></a>@endif</span></div>@endif
            </div>
            <p class="m"><strong>{{ $m['who'] }}</strong> · {{ $m['when'] }}@if($m['unread']) <span class="badge tone-info">Nouveau</span>@endif @unless($m['mine'])· <a class="rep" href="{{ route('support.report.form', ['message', $m['id']]) }}">Signaler ce message</a>@endunless</p>
          </article>
        @empty<p class="muted">Aucun message.</p>@endforelse
      </div>

      @if($t['canSend'])
        @if($t['iBlocked'])<div class="notice tone-warning" role="note"><x-fc.icon name="warn" /><p>Vous avez bloqué ce contact. Cette conversation reste possible parce qu’elle est liée à une commande active.</p></div>@endif
        @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>{{ $errors->first() }} Votre saisie est conservée.</p></div>@endif
        <form method="post" action="{{ route('messages.send', array_filter(['conversation' => $t['id'], 'espace' => $space === 'freelancer' ? 'freelance' : null])) }}" enctype="multipart/form-data" data-once class="ms-comp" novalidate>@csrf
          <input type="hidden" name="client_key" value="{{ $operationKey }}">
          <div class="field"><label class="sr-only" for="body">Votre message</label><textarea class="textarea" id="body" name="body" rows="3" placeholder="Votre message…" maxlength="{{ config('freeci.messaging.body_max') }}" @error('body') aria-invalid="true" aria-describedby="e-body" @enderror>{{ old('body') }}</textarea>@error('body')<p class="field-error" id="e-body"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
          <div class="field"><label for="file">Pièce jointe (facultatif)</label><input class="input" id="file" type="file" name="file" @error('file') aria-invalid="true" aria-describedby="e-file" @enderror>@error('file')<p class="field-error" id="e-file"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
          <div class="ms-send"><button class="btn btn-primary btn-lg" type="submit" data-once-label="Envoi…">Envoyer</button></div>
          <p class="ms-hint">Un fichier par message ; il n’est téléchargeable qu’après le contrôle de sécurité.</p></form>
      @else
        <div class="notice tone-info" role="note" style="margin:12px 16px"><x-fc.icon name="lock" /><p>{{ $t['iBlocked'] ? 'Vous avez bloqué ce contact : les nouveaux échanges sont suspendus. L’historique reste consultable.' : 'Cette conversation n’accepte plus de nouveaux messages. L’historique reste consultable.' }}</p></div>
      @endif
    </section>
  </div>
</x-layouts.account>

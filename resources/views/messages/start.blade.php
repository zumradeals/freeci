<x-layouts.account title="Nouveau message" :space="$space">
  <section class="card form-card" aria-labelledby="h-st"><h1 class="t-h1" id="h-st">Poser une question</h1><p class="muted" style="margin-top:6px">{{ $context }}</p>
    <div class="notice tone-info" style="margin-top:12px"><x-fc.icon name="lock" /><p>Conversation <strong>privée</strong> entre vous et votre interlocuteur. Elle n’engage à rien : seule une demande, une proposition retenue ou une action de la commande a un effet.</p></div>
    @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>{{ $errors->first() }} Votre saisie est conservée.</p></div>@endif
    <form method="post" action="{{ $action }}" enctype="multipart/form-data" data-once style="display:grid;gap:12px;margin-top:12px" novalidate>@csrf
      <input type="hidden" name="client_key" value="{{ $operationKey }}">
      <div class="field"><label for="body">Votre message</label><textarea class="textarea" id="body" name="body" rows="5" maxlength="{{ config('freeci.messaging.body_max') }}" required>{{ old('body') }}</textarea></div>
      <div class="field"><label for="file">Pièce jointe (facultatif)</label><input class="input" id="file" type="file" name="file"><p class="hint">Téléchargeable seulement après le contrôle de sécurité.</p></div>
      <div class="row"><button class="btn btn-primary btn-lg" type="submit" data-once-label="Envoi…">Envoyer</button><a class="btn btn-link" href="{{ url()->previous() }}">Annuler</a></div></form></section>
</x-layouts.account>

<x-layouts.account title="Contacter le support">
  <div class="page-body">
    <nav class="crumbs" aria-label="Fil d’Ariane"><a class="back-m" href="{{ route('support.index') }}"><x-fc.icon name="arrow-right" :size="16" class="flip" />Assistance</a><a class="hide-m" href="{{ route('support.index') }}">Assistance</a><span class="sep hide-m" aria-hidden="true">›</span><span class="hide-m" aria-current="page">Contacter le support</span></nav>
    <div class="sx-head"><div><p class="sx-kicker">Assistance</p><h1>Contacter le support</h1><p class="muted">Décrivez votre demande : elle est suivie avec une référence.</p></div></div>
    <div class="ac-grid"><form method="post" action="{{ route('support.store') }}">@csrf
      <input type="hidden" name="operation_key" value="{{ $key }}">
      <section class="ed-card" aria-labelledby="h-req"><h2 id="h-req">Votre demande</h2>
        <div class="ac-r2">
          <div class="field"><label for="f-category">Sujet</label><select class="select" id="f-category" name="category" required>@foreach($categories as $k => $l)<option value="{{ $k }}" @selected(old('category', $order !== '' ? 'order' : '') === $k)>{{ $l }}</option>@endforeach</select></div>
          <div class="field"><label for="f-order">Référence de la commande (facultatif)</label><input class="input" id="f-order" name="order" value="{{ old('order', $order) }}" maxlength="30" placeholder="FC-2610-00001">@error('order')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
        </div>
        <div class="field"><label for="f-subject">Objet</label><input class="input" id="f-subject" name="subject" value="{{ old('subject') }}" required maxlength="160">@error('subject')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
        <div class="field"><label for="f-body">Votre message</label><textarea class="textarea" id="f-body" name="body" required minlength="10" maxlength="4000">{{ old('body') }}</textarea>@error('body')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror<p class="hint">10 caractères au moins.</p></div>
        <div class="ac-acts"><button class="btn btn-primary btn-lg" type="submit" data-once>Envoyer</button><a class="btn btn-link" href="{{ route('support.index') }}">Annuler</a></div></section>
    </form>
    <aside class="ac-side"><section class="ed-ck" aria-labelledby="h-priv"><h3 id="h-priv">Avant d’écrire</h3><ul class="ac-ess">
      <li><x-fc.icon name="lock" :size="18" /><div><b>Lu par l’équipe habilitée uniquement</b></div></li>
      <li><x-fc.icon name="shield" :size="18" /><div><b>Ne communiquez jamais</b><small>un mot de passe ni un code de sécurité.</small></div></li>
      <li><x-fc.icon name="file" :size="18" /><div><b>Pièces jointes</b><small>Vous pourrez en joindre une fois le dossier ouvert.</small></div></li></ul></section>
      <section class="ed-ck"><h3>Un autre besoin ?</h3><ul class="ac-short"><li><a href="{{ route('orders.index') }}"><x-fc.icon name="flag" /><span>Un litige s’ouvre depuis la commande</span><x-fc.icon name="arrow-right" :size="16" /></a></li><li><span class="ac-note"><x-fc.icon name="flag" /><span>Un signalement s’ouvre depuis le contenu concerné</span></span></li></ul></section></aside></div>
  </div>
</x-layouts.account>

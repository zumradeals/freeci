<x-layouts.account title="Contacter le support">
  <header class="page-head"><div class="row-top"><div><p class="eyebrow"><a href="{{ route('support.index') }}">← Assistance</a></p><h1 class="t-h1">Contacter le support</h1></div></div></header>
  <form method="post" action="{{ route('support.store') }}" class="card stack" style="max-width:46em">@csrf
    <input type="hidden" name="operation_key" value="{{ $key }}">
    <div class="field"><label for="f-category">Sujet</label><select class="select" id="f-category" name="category" required>@foreach($categories as $k => $l)<option value="{{ $k }}" @selected(old('category', $order !== '' ? 'order' : '') === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="field"><label for="f-order">Référence de la commande (facultatif)</label><input class="input" id="f-order" name="order" value="{{ old('order', $order) }}" maxlength="30" placeholder="FC-2610-00001">@error('order')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
    <div class="field"><label for="f-subject">Objet</label><input class="input" id="f-subject" name="subject" value="{{ old('subject') }}" required maxlength="160">@error('subject')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
    <div class="field"><label for="f-body">Votre message</label><textarea class="textarea" id="f-body" name="body" required minlength="10" maxlength="4000">{{ old('body') }}</textarea>@error('body')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
    <p class="muted small">Votre message est lu par l’équipe habilitée uniquement. Ne communiquez jamais un mot de passe ni un code de sécurité. Vous pourrez joindre des pièces une fois le dossier ouvert.</p>
    <div class="row"><button class="btn btn-primary btn-lg" type="submit" data-once>Envoyer</button><a class="btn btn-link" href="{{ route('support.index') }}">Annuler</a></div>
  </form>
</x-layouts.account>

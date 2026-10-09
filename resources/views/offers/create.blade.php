<x-layouts.account title="Nouvelle offre personnalisée" :space="$space">
  <div class="page-body">
    <nav class="sd-crumbs" aria-label="Fil d’Ariane"><a href="{{ route('messages.show', ['conversation' => $conversation, 'espace' => 'freelance']) }}">Messages</a> › <span aria-current="page">Offre personnalisée</span></nav>
    <div class="sx-head"><div><p class="sx-kicker">Messages</p><h1>Nouvelle offre personnalisée</h1><p class="muted">Pour {{ $with }} · à partir de la conversation « {{ $context }} ».</p></div></div>
    @if(session('error'))<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>{{ session('error') }}</p></div>@endif
    @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>Vérifiez les champs signalés ci-dessous. Votre saisie est conservée.</p></div>@endif
    <div class="ac-grid"><section class="ed-card">
      <form method="post" action="{{ route('offers.store', $conversation) }}" class="stack-sm" data-once>@csrf
        <input type="hidden" name="operation_key" value="{{ $operationKey }}">
        <div class="field"><label for="of-title">Titre de l’offre</label><input class="input" id="of-title" name="title" value="{{ old('title') }}" maxlength="160" required @error('title') aria-invalid="true" @enderror>@error('title')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
        <div class="field"><label for="of-scope">Ce qui est inclus (périmètre)</label><textarea class="textarea" id="of-scope" name="scope" rows="5" maxlength="3000" required @error('scope') aria-invalid="true" @enderror>{{ old('scope') }}</textarea><p class="muted small" style="margin:0">50 à 3 000 caractères. Soyez précis : c’est ce qui sera figé.</p>@error('scope')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
        <div class="field"><label for="of-del">Livrables (un par ligne, 10 au plus)</label><textarea class="textarea" id="of-del" name="deliverables" rows="4" required @error('deliverables') aria-invalid="true" @enderror>{{ old('deliverables') }}</textarea>@error('deliverables')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
        <div class="ac-r2">
          <div class="field"><label for="of-price">Prix (FCFA)</label><input class="input" id="of-price" name="price_xof" inputmode="numeric" value="{{ old('price_xof') }}" required @error('price_xof') aria-invalid="true" @enderror>@error('price_xof')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
          <div class="field"><label for="of-days">Délai (jours)</label><input class="input" id="of-days" name="delivery_days" inputmode="numeric" value="{{ old('delivery_days') }}" required @error('delivery_days') aria-invalid="true" @enderror>@error('delivery_days')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div></div>
        <div class="ac-r2">
          <div class="field"><label for="of-rev">Corrections incluses</label><input class="input" id="of-rev" name="revisions_included" inputmode="numeric" value="{{ old('revisions_included', '1') }}" required @error('revisions_included') aria-invalid="true" @enderror>@error('revisions_included')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
          <div class="field"><label for="of-valid">Offre valable</label><select class="select" id="of-valid" name="valid_days">@foreach([3 => '3 jours', 7 => '7 jours', 14 => '14 jours', 30 => '30 jours'] as $v => $l)<option value="{{ $v }}" @selected((string) old('valid_days', '7') === (string) $v)>{{ $l }}</option>@endforeach</select></div></div>
        <div class="field"><label for="of-in">Ce que le client doit vous fournir (un élément par ligne, facultatif)</label><textarea class="textarea" id="of-in" name="client_inputs" rows="3" @error('client_inputs') aria-invalid="true" @enderror>{{ old('client_inputs') }}</textarea><p class="muted small" style="margin:0">Le client répondra à chaque élément en acceptant l’offre.</p>@error('client_inputs')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
        <label><input type="checkbox" name="delivery_files" value="1" @checked(old('delivery_files', '1') === '1')> La livraison doit comporter au moins un fichier.</label>
        <div class="of-comp" style="margin:0;border:0;padding:0"><button class="btn btn-primary" type="submit" data-once>Envoyer l’offre</button><a class="btn btn-link" href="{{ route('messages.show', ['conversation' => $conversation, 'espace' => 'freelance']) }}">Annuler</a></div>
      </form></section>
      <aside class="ac-side"><section class="ed-ck"><h3>Ce qui est figé à l’acceptation</h3><ul class="of-tl">
        <li><x-fc.icon name="check" :size="18" /><span>Prix, délai, périmètre, livrables et corrections : <b>tels que vous les envoyez</b>.</span></li>
        <li><x-fc.icon name="check" :size="18" /><span>La commission de FreeCI est celle des conditions en vigueur à ce moment ; vous la retrouvez dans vos revenus.</span></li>
        <li><x-fc.icon name="check" :size="18" /><span>Aucune modification possible : pour changer, retirez l’offre et envoyez-en une autre.</span></li></ul></section>
      <section class="ed-ck"><h3>Règles</h3><ul class="of-tl"><li><x-fc.icon name="check" :size="18" /><span>Pas de coordonnées privées ni de liens dans l’offre.</span></li><li><x-fc.icon name="check" :size="18" /><span>L’offre n’engage le client qu’après son acceptation, puis le paiement.</span></li></ul></section></aside></div>
  </div>
</x-layouts.account>

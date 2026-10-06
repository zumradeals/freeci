@php
  $v = $version; $L = $limits; $editable = $v->isEditable();
  $val = fn ($k, $d = '') => old($k, $d);
  $lines = fn ($a) => implode("\n", $a ?? []);
  $err = fn ($k) => $errors->first($k);
  $mode = old('delivery_mode', $v->delivery_requires_files ? 'files' : 'message');
@endphp
<x-layouts.account :title="'Modifier : '.($v->title ?: 'service')" space="freelancer">
  <nav class="crumbs" aria-label="Fil d’Ariane"><a class="back-m" href="{{ route('freelance.services') }}"><x-fc.icon name="arrow-right" :size="16" class="flip" />Mes services</a><a class="hide-m" href="{{ route('freelance.services') }}">Mes services</a><span class="sep hide-m" aria-hidden="true">›</span><span class="hide-m" aria-current="page">{{ $v->title ?: 'Nouveau service' }}</span></nav>
  <header class="page-head"><div class="row-top"><div><p class="eyebrow">Version {{ $v->number }} · {{ ['draft' => 'brouillon', 'changes_requested' => 'à corriger', 'in_review' => 'en contrôle'][$v->state] }}</p><h1 class="t-h1">{{ $v->title ?: 'Nouveau service' }}</h1></div>
    <a class="btn btn-secondary" href="{{ route('freelance.services.preview', $service->getKey()) }}" target="_blank" rel="noopener">Aperçu enregistré ↗</a></div></header>

  @if($v->state === 'changes_requested')<div class="notice tone-warning" role="note"><x-fc.icon name="warn" /><div><p><strong>La modération demande une correction.</strong></p><p class="quote" style="margin-top:6px">« {{ $v->decision_note }} »</p><p style="margin-top:6px">Corrigez puis soumettez de nouveau.</p></div></div>
  @elseif($v->state === 'in_review')<div class="notice tone-info" role="note"><x-fc.icon name="clock" /><p><strong>Cette version est en contrôle : elle n’est pas modifiable.</strong> <a href="{{ route('freelance.services.confirm', [$service->getKey(), 'retirer-soumission']) }}">Retirer la soumission</a> pour la modifier.</p></div>
  @elseif($live)<div class="notice tone-info" role="note"><x-fc.icon name="info" /><p>La <strong>version publiée (v{{ $live->number }})</strong> reste en ligne tant que celle-ci n’est pas approuvée. Les commandes déjà passées gardent leur accord.</p></div>
  @else<div class="notice tone-info" role="note"><x-fc.icon name="lock" /><p>Brouillon <strong>invisible du public</strong> : il ne devient visible qu’après approbation par la modération.</p></div>@endif
  @unless($profilePublished)<div class="notice tone-warning" role="note"><x-fc.icon name="warn" /><p>Votre profil n’est pas publié : il faut le publier pour soumettre ce service. <a href="{{ route('freelance.profile') }}">Compléter mon profil</a></p></div>@endunless
  @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>Vérifiez les champs signalés ci-dessous. Vos saisies sont conservées.</p></div>@endif

  <form method="post" action="{{ route('freelance.services.update', $service->getKey()) }}" enctype="multipart/form-data" id="service-editor" class="service-editor" data-service-editor data-once novalidate>@csrf
    <input type="hidden" name="revision_no" value="{{ old('revision_no', $v->revision_no) }}">
    <input type="hidden" name="editor_form" value="1">
    <nav class="editor-steps" aria-label="Étapes de création" hidden>
      @foreach(['presentation' => 'Présentation', 'offre' => 'Votre offre', 'images' => 'Images', 'besoin' => 'Avant de publier'] as $step => $label)
      <button class="btn btn-secondary" type="button" data-editor-go="{{ $step }}" aria-controls="editor-{{ $step }}"><span>{{ $loop->iteration }}</span> {{ $label }}</button>
      @endforeach
    </nav>
    <div class="editor-layout"><div class="editor-main">
    <fieldset @disabled(! $editable) style="border:0;padding:0;margin:0;display:grid;gap:20px;min-width:0">
      <section class="card" id="editor-presentation" data-editor-panel="presentation" aria-labelledby="h-ess"><h2 class="t-h2 card-title" id="h-ess">Présentez votre service</h2><div style="display:grid;gap:16px">
        <x-fc.field name="title" label="Que proposez-vous ?" :value="$v->title" :hint="$L['title'][0].' à '.$L['title'][1].' caractères.'" />
        <div class="field"><label for="f-category_id">Catégorie</label><select class="select" id="f-category_id" name="category_id" required @if($err('category_id')) aria-invalid="true" aria-describedby="e-category_id" @endif>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id', $v->category_id) === $c->id)>{{ $c->name }}</option>@endforeach</select>@if($err('category_id'))<p class="field-error" id="e-category_id"><x-fc.icon name="error" :size="16" />{{ $err('category_id') }}</p>@endif</div>
        <div class="field"><label for="f-summary">Résumé</label><p class="hint" id="h-summary">{{ $L['summary'][0] }} à {{ $L['summary'][1] }} caractères. Affiché sur les cartes du catalogue.</p>
          <textarea class="textarea" id="f-summary" name="summary" rows="3" maxlength="{{ $L['summary'][1] + 100 }}" aria-describedby="h-summary @if($err('summary')) e-summary @endif" @if($err('summary')) aria-invalid="true" @endif>{{ $val('summary', $v->summary) }}</textarea>@if($err('summary'))<p class="field-error" id="e-summary"><x-fc.icon name="error" :size="16" />{{ $err('summary') }}</p>@endif</div>
        <div class="field"><label for="f-scope">Décrivez votre prestation</label><p class="hint" id="h-scope">{{ $L['scope'][0] }} à {{ $L['scope'][1] }} caractères : ce qui est inclus, les limites. Aucune adresse e-mail ni numéro de téléphone.</p>
          <textarea class="textarea" id="f-scope" name="scope" rows="5" aria-describedby="h-scope @if($err('scope')) e-scope @endif" @if($err('scope')) aria-invalid="true" @endif>{{ $val('scope', $v->scope) }}</textarea>@if($err('scope'))<p class="field-error" id="e-scope"><x-fc.icon name="error" :size="16" />{{ $err('scope') }}</p>@endif</div>
      </div></section>

      <section class="card" id="editor-offre" data-editor-panel="offre" aria-labelledby="h-off"><h2 class="t-h2 card-title" id="h-off">L’offre</h2><div style="display:grid;gap:16px">
        <x-fc.field name="price_xof" label="Prix (FCFA)" type="text" :value="$v->price_xof ?: ''" :required="false" :hint="'De '.number_format($L['price_xof'][0], 0, ',', ' ').' à '.number_format($L['price_xof'][1], 0, ',', ' ').' FCFA, prix fixe pour le périmètre décrit.'" />
        <x-fc.field name="delivery_days" label="Délai de réalisation (jours)" type="text" :value="$v->delivery_days ?: ''" :required="false" :hint="$L['delivery_days'][0].' à '.$L['delivery_days'][1].' jours, après paiement confirmé et réception des éléments nécessaires.'" />
        <x-fc.field name="revisions_included" label="Retouches incluses" type="text" :value="$v->revisions_included" :required="false" :hint="'De '.$L['revisions'][0].' à '.$L['revisions'][1].'. Nombre de demandes de retouche comprises dans le prix.'" />
        @foreach(['deliverables' => ['Que recevra le client ?', 'Exemple : une photo restaurée en JPG haute qualité. Un élément par ligne.', 3], 'exclusions' => ['Ce qui n’est pas inclus', 'Une ligne par exclusion (facultatif).', 3]] as $f => [$label, $hint, $rows])
        <div class="field"><label for="f-{{ $f }}">{{ $label }}</label><p class="hint" id="h-{{ $f }}">{{ $hint }}</p>
          <textarea class="textarea" id="f-{{ $f }}" name="{{ $f }}" rows="{{ $rows }}" aria-describedby="h-{{ $f }} @if($err($f)) e-{{ $f }} @endif" @if($err($f)) aria-invalid="true" @endif>{{ old($f, $lines($v->{$f})) }}</textarea>@if($err($f))<p class="field-error" id="e-{{ $f }}"><x-fc.icon name="error" :size="16" />{{ $err($f) }}</p>@endif</div>
        @endforeach
      </div></section>

      <section class="card" id="editor-images" data-editor-panel="images" aria-labelledby="h-img"><div class="row" style="justify-content:space-between"><h2 class="t-h2" id="h-img">Images de votre service</h2><span class="muted small">{{ count($images) }} sur {{ $L['images_max'] }}</span></div>
        <p class="note-line"><x-fc.icon name="shield" :size="16" /><span>L’image principale apparaîtra dans le catalogue et en tête de votre service. JPG, PNG ou WebP, {{ $L['image_max_mb'] }} Mo maximum, {{ $L['image_min_width'] }} px de large au minimum.</span></p>
        @if($err('cover_image'))<p class="field-error">{{ $err('cover_image') }}</p>@endif
        @if($errors->has('images'))<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $errors->first('images') }}</p>@endif
        @foreach($images as $k => $img)
          @php($id = $imageIds[$k])
          <div class="file-line" style="align-items:flex-start;margin-top:12px">@if($id)<img src="{{ $img['card'] }}" alt="" width="96" height="64" style="border-radius:var(--r-sm);flex:none;object-fit:cover">@else<x-fc.icon name="file" :size="22" class="fi" />@endif
            <span class="fn" style="display:grid;gap:8px">@if($id)
              <label class="check"><input type="radio" name="cover_image" value="{{ $id }}" data-cover-src="{{ $img['card'] }}" @checked(old('cover_image', $imageIds[0] ?? '') === $id)> <span>Image principale</span></label>
              <label class="small" for="alt-{{ $k }}">Décrivez cette image en quelques mots</label><input class="input" id="alt-{{ $k }}" name="image_alt[{{ $id }}]" value="{{ old('image_alt.'.$id, $img['alt']) }}" maxlength="160">
              <label class="small" for="cap-{{ $k }}">Légende (facultatif)</label><input class="input" id="cap-{{ $k }}" name="image_caption[{{ $id }}]" value="{{ old('image_caption.'.$id, $img['caption']) }}" maxlength="160">
              <span><button class="btn btn-link" type="submit" formaction="{{ route('freelance.services.images.destroy', [$service->getKey(), $id]) }}" formnovalidate>Retirer cette image<span class="sr-only"> : {{ $img['alt'] }}</span></button></span>
            @else<span>{{ $img['alt'] }} <small class="muted">(image d’exemple existante)</small></span>@endif</span></div>
        @endforeach
        @if(! $imagesEnabled)<p class="note-line" style="margin-top:12px"><x-fc.icon name="info" :size="16" /><span>Le dépôt d’images est <strong>désactivé</strong> sur cette installation (extension GD absente). Vous pouvez soumettre le service sans image.</span></p>
        @elseif(count($images) < $L['images_max'])
          <div style="display:grid;gap:12px;margin-top:16px"><div class="field"><label for="f-image">Ajouter une image</label><input class="input" id="f-image" type="file" name="image" accept="image/jpeg,image/png,image/webp"></div>
            <div class="field"><label for="f-alt">Que montre cette image ?</label><input class="input" id="f-alt" name="alt" maxlength="160" value="{{ old('alt') }}" placeholder="Ex. : photo ancienne avant et après restauration"></div>
            @error('image')<p class="field-error">{{ $message }}</p>@enderror @error('alt')<p class="field-error">{{ $message }}</p>@enderror
            <div><button class="btn btn-secondary" type="submit" formaction="{{ route('freelance.services.images.store', $service->getKey()) }}" formnovalidate>Ajouter l’image</button><p class="hint" style="margin-top:6px">Ajouter ou retirer une image enregistre aussi vos textes. La première image devient automatiquement l’image principale.</p></div></div>
        @endif
      </section>
      <section class="card" id="editor-besoin" data-editor-panel="besoin" aria-labelledby="h-con"><h2 class="t-h2 card-title" id="h-con">Avant de publier</h2><div style="display:grid;gap:16px">
        @foreach(['client_inputs' => ['De quoi avez-vous besoin pour commencer ?', 'Exemple : la photo à restaurer et les retouches souhaitées. Un élément par ligne (facultatif).', 3]] as $f => [$label, $hint, $rows])
        <div class="field"><label for="f-{{ $f }}">{{ $label }}</label><p class="hint" id="h-{{ $f }}">{{ $hint }}</p>
          <textarea class="textarea" id="f-{{ $f }}" name="{{ $f }}" rows="{{ $rows }}" aria-describedby="h-{{ $f }} @if($err($f)) e-{{ $f }} @endif" @if($err($f)) aria-invalid="true" @endif>{{ old($f, $lines($v->{$f})) }}</textarea>@if($err($f))<p class="field-error" id="e-{{ $f }}"><x-fc.icon name="error" :size="16" />{{ $err($f) }}</p>@endif</div>

        @endforeach
        <fieldset class="field" style="border:0;padding:0;min-width:0"><legend class="label" style="font-weight:600">Mode de livraison</legend>
          <label class="check"><input type="radio" name="delivery_mode" value="files" @checked($mode === 'files')> <span><strong>Au moins un fichier</strong> par livraison (recommandé : vos livrables sont des fichiers). La livraison exige un fichier ayant passé le contrôle de sécurité.</span></label>
          <label class="check"><input type="radio" name="delivery_mode" value="message" @checked($mode === 'message')> <span><strong>Par message seul</strong> : aucun fichier n’est exigé. À choisir uniquement si vos livrables ne sont pas des fichiers.</span></label>
          <p class="hint">Ce choix est figé dans chaque commande : il ne peut pas être changé après coup.</p></fieldset>
        <input type="hidden" name="brief_requires_files" value="0"><label class="check"><input type="checkbox" name="brief_requires_files" value="1" @checked(old('brief_requires_files', $v->brief_requires_files))> <span>Le client doit envoyer un fichier pour que je puisse commencer.</span></label>
      </div></section>

    </fieldset>
    <div class="editor-pagination" hidden><button type="button" class="btn btn-secondary" data-editor-prev>Précédent</button><p class="hint" data-editor-progress aria-live="polite"></p><button type="button" class="btn btn-primary" data-editor-next>Continuer</button></div>
    @if($editable)
    <p class="hint">Vous pouvez enregistrer votre brouillon à tout moment. Votre service sera publié après validation par l’administration.</p>
    <div class="row" style="gap:12px"><button class="btn btn-secondary btn-lg" type="submit" name="intent" value="save" data-once-label="Enregistrement…">Enregistrer le brouillon</button>
      <button class="btn btn-primary btn-lg" type="submit" name="intent" value="submit" data-editor-final data-once-label="Vérification…">Vérifier et envoyer pour validation</button></div>
    @endif
    </div>
    <aside class="editor-preview card" aria-label="Aperçu de la carte du service">
      <p class="eyebrow">Votre vitrine</p>
      <div class="editor-cover"><img data-preview-image src="{{ $images[0]['card'] ?? '' }}" alt="Image principale du service" @if(empty($images)) hidden @endif><p data-preview-empty @if(count($images)) hidden @endif>Ajoutez une image pour présenter votre travail.</p></div>
      <p class="muted small" data-preview-category></p>
      <h2 class="t-h2" data-preview-title>{{ $val('title', $v->title) }}</h2>
      <p class="muted" data-preview-summary>{{ $val('summary', $v->summary) }}</p>
      <div class="row" style="justify-content:space-between"><strong data-preview-price></strong><span class="small" data-preview-delay></span></div>
      <p class="hint">Aperçu de vos saisies. Enregistrez pour les conserver.</p>
    </aside></div>
  </form>

  @if(count($history))<section class="card form-card" style="margin-top:24px" aria-labelledby="h-hist"><h2 class="t-h2 card-title" id="h-hist">Historique</h2>
    <ol class="timeline">@foreach($history as $h)<li><span class="pt" aria-hidden="true"></span><div><p class="tt">{{ ['created' => 'Brouillon créé', 'submitted' => 'Soumis à modération', 'submission_withdrawn' => 'Soumission retirée', 'approved' => 'Approuvé et publié', 'changes_requested' => 'Correction demandée par la modération', 'revision_started' => 'Nouvelle version démarrée', 'withdrawn_by_owner' => 'Retiré du catalogue par vous', 'restored_by_owner' => 'Remis en ligne par vous', 'suspended' => 'Suspendu par la modération', 'reinstated' => 'Remis en ligne par la modération'][$h['type']] ?? $h['type'] }}</p><p class="when">{{ $h['when'] }}@if($h['by']) · {{ $h['by'] }}@endif</p>@if($h['note'])<p class="why">« {{ $h['note'] }} »</p>@endif</div></li>@endforeach</ol></section>@endif
</x-layouts.account>

@php
  $v = $working; $L = $limits; $editable = $v->isEditable(); $err = fn ($k) => $errors->first($k);
  $deadline = old('application_deadline', $v->application_deadline?->copy()->timezone('Africa/Abidjan')->format('Y-m-d'));
@endphp
<x-layouts.account :title="'Modifier : '.($v->title ?: 'mission')" space="client">
  <div class="page-body">
    <nav class="crumbs" aria-label="Fil d’Ariane"><a class="back-m" href="{{ route('client.missions.show', $mission->getKey()) }}"><x-fc.icon name="arrow-right" :size="16" class="flip" />Retour à la mission</a><a class="hide-m" href="{{ route('client.missions') }}">Mes missions</a><span class="sep hide-m" aria-hidden="true">›</span><span class="hide-m" aria-current="page">{{ $v->title ?: 'Nouvelle mission' }}</span></nav>
    <header class="page-head"><div class="row-top"><div><p class="eyebrow">Version {{ $v->number }} · {{ ['draft' => 'brouillon', 'changes_requested' => 'à corriger', 'in_review' => 'en contrôle'][$v->state] }}</p><h1 class="t-h1">{{ $v->title ?: 'Nouvelle mission' }}</h1></div><a class="btn btn-secondary" href="{{ route('client.missions.preview', $mission->getKey()) }}">Aperçu</a></div></header>
    @if($v->state === 'changes_requested')<div class="notice tone-warning" role="note"><x-fc.icon name="warn" /><div><p><strong>La modération demande une correction.</strong></p><p class="quote mt-6">« {{ $v->decision_note }} »</p></div></div>
    @elseif($live)<div class="notice tone-info" role="note"><x-fc.icon name="info" /><p>La <strong>version publiée (v{{ $live->number }})</strong> reste en ligne tant que celle-ci n’est pas approuvée. @if($proposals)<strong>Les {{ $proposals }} proposition{{ $proposals > 1 ? 's' : '' }} reçue{{ $proposals > 1 ? 's' : '' }} devront être reconfirmée{{ $proposals > 1 ? 's' : '' }} par leurs auteurs</strong> avant de pouvoir être retenue{{ $proposals > 1 ? 's' : '' }}.@endif</p></div>
    @else<div class="notice tone-info" role="note"><x-fc.icon name="lock" /><p>Brouillon <strong>invisible du public</strong> jusqu’à son approbation par la modération.</p></div>@endif
    @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>Vérifiez les champs signalés ci-dessous. Vos saisies sont conservées.</p></div>@endif

    <form method="post" action="{{ route('client.missions.update', $mission->getKey()) }}" data-once style="display:grid;gap:20px;max-width:720px" novalidate>@csrf
      <input type="hidden" name="revision_no" value="{{ $v->revision_no }}">
      <fieldset class="fieldset-bare" @disabled(! $editable)>
        <section class="card" aria-labelledby="h-b"><h2 class="t-h2 card-title" id="h-b">Le besoin (public)</h2><div class="fields">
          <x-fc.field name="title" label="Titre" :value="$v->title" :hint="$L['title'][0].' à '.$L['title'][1].' caractères.'" />
          <div class="field"><label for="f-category_id">Catégorie</label><select class="select" id="f-category_id" name="category_id" required>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id', $v->category_id) === $c->id)>{{ $c->name }}</option>@endforeach</select>@if($err('category_id'))<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $err('category_id') }}</p>@endif</div>
          <div class="field"><label for="f-description">Description</label><p class="hint" id="h-description">{{ $L['description'][0] }} à {{ $L['description'][1] }} caractères : le besoin, les formats attendus, les limites. Texte <strong>public</strong> : aucune adresse e-mail ni numéro de téléphone, aucune donnée privée.</p>
            <textarea class="textarea" id="f-description" name="description" rows="8" aria-describedby="h-description @if($err('description')) e-description @endif" @if($err('description')) aria-invalid="true" @endif>{{ old('description', $v->description) }}</textarea>@if($err('description'))<p class="field-error" id="e-description"><x-fc.icon name="error" :size="16" />{{ $err('description') }}</p>@endif</div>
          <x-fc.field name="budget_xof" label="Budget (FCFA)" type="text" :value="$v->budget_xof" :required="false" :hint="'Votre enveloppe, de '.number_format($L['budget_xof'][0], 0, ',', ' ').' à '.number_format($L['budget_xof'][1], 0, ',', ' ').' FCFA (valeurs provisoires). Les propositions sont à prix ferme.'" />
          <div class="field"><label for="f-application_deadline">Date limite de candidature</label><p class="hint" id="h-dl">Entre demain et dans {{ $L['deadline_max_days'] }} jours. Après cette date, plus de proposition ; vous disposez ensuite de {{ $L['selection_days'] }} jours pour choisir.</p>
            <input class="input" type="date" id="f-application_deadline" name="application_deadline" value="{{ $deadline }}" aria-describedby="h-dl @if($err('application_deadline')) e-application_deadline @endif" @if($err('application_deadline')) aria-invalid="true" @endif>@if($err('application_deadline'))<p class="field-error" id="e-application_deadline"><x-fc.icon name="error" :size="16" />{{ $err('application_deadline') }}</p>@endif</div>
        </div></section>
        <section class="card" aria-labelledby="h-brief"><h2 class="t-h2 card-title" id="h-brief">Brief de la commande (privé)</h2><div class="fields">
          <div class="field"><label for="f-client_inputs">Éléments que vous fournirez</label><p class="hint" id="h-ci">Une ligne par élément (facultatif). Seuls ces <strong>intitulés</strong> sont publics ; vos réponses sont saisies au moment où vous retenez une proposition et ne sont communiquées qu’au freelance retenu.</p>
            <textarea class="textarea" id="f-client_inputs" name="client_inputs" rows="4" aria-describedby="h-ci @if($err('client_inputs')) e-client_inputs @endif" @if($err('client_inputs')) aria-invalid="true" @endif>{{ old('client_inputs', implode("\n", $v->client_inputs ?? [])) }}</textarea>@if($err('client_inputs'))<p class="field-error" id="e-client_inputs"><x-fc.icon name="error" :size="16" />{{ $err('client_inputs') }}</p>@endif</div>
          <label class="check"><input type="checkbox" name="brief_requires_files" value="1" @checked(old('brief_requires_files', $v->brief_requires_files))> <span>Je fournirai au moins un fichier à la commande (nécessite le contrôle de sécurité).</span></label>
          <p class="note-line"><x-fc.icon name="lock" :size="16" /><span><strong>Aucune pièce jointe n’est publiée avec la mission.</strong> Les fichiers se joignent plus tard, au brief de la commande, et ne sont jamais rendus publics.</span></p>
        </div></section>
      </fieldset>
      @if($editable)<div class="row row-gap"><button class="btn btn-secondary btn-lg" type="submit" name="intent" value="save" data-once-label="Enregistrement…">Enregistrer le brouillon</button><button class="btn btn-primary btn-lg" type="submit" name="intent" value="submit" data-once-label="Vérification…">Enregistrer et soumettre à modération</button></div>@endif
    </form>
  </div>
</x-layouts.account>

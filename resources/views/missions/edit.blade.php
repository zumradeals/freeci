@php
  $v = $working; $L = $limits; $editable = $v->isEditable(); $err = fn ($k) => $errors->first($k);
  $deadline = old('application_deadline', $v->application_deadline?->copy()->timezone('Africa/Abidjan')->format('Y-m-d'));
@endphp
<x-layouts.account :title="'Modifier : '.($v->title ?: 'mission')" space="client">
  <div class="page-body">
    <nav class="crumbs" aria-label="Fil d’Ariane"><a class="back-m" href="{{ route('client.missions.show', $mission->getKey()) }}"><x-fc.icon name="arrow-right" :size="16" class="flip" />Retour à la mission</a><a class="hide-m" href="{{ route('client.missions') }}">Mes missions</a><span class="sep hide-m" aria-hidden="true">›</span><span class="hide-m" aria-current="page">{{ $v->title ?: 'Nouvelle mission' }}</span></nav>
    <header class="page-head"><div class="row-top"><div><p class="eyebrow">Version {{ $v->number }} · {{ ['draft' => 'brouillon', 'changes_requested' => 'à corriger', 'in_review' => 'en contrôle'][$v->state] }}</p><h1 class="t-h1">{{ $v->title ?: 'Nouvelle mission' }}</h1><p class="lead">Décrivez votre projet, fixez votre budget et recevez des propositions.</p></div><a class="btn btn-secondary" href="{{ route('client.missions.preview', $mission->getKey()) }}" target="_blank" rel="noopener">Aperçu enregistré ↗</a></div></header>
    @if($v->state === 'changes_requested')<div class="notice tone-warning" role="note"><x-fc.icon name="warn" /><div><p><strong>La modération demande une correction.</strong></p><p class="quote mt-6">« {{ $v->decision_note }} »</p></div></div>
    @elseif(!$editable)<div class="notice tone-info" role="note"><x-fc.icon name="clock" /><p>Cette version est en cours de modération. Elle ne peut pas être modifiée pour le moment.</p></div>
    @elseif($live)<div class="notice tone-info" role="note"><x-fc.icon name="info" /><p>La <strong>version publiée (v{{ $live->number }})</strong> reste en ligne tant que celle-ci n’est pas approuvée. @if($proposals)<strong>Les {{ $proposals }} proposition{{ $proposals > 1 ? 's' : '' }} reçue{{ $proposals > 1 ? 's' : '' }} devront être reconfirmée{{ $proposals > 1 ? 's' : '' }} par leurs auteurs</strong> avant de pouvoir être retenue{{ $proposals > 1 ? 's' : '' }}.@endif</p></div>
    @else<div class="notice tone-info" role="note"><x-fc.icon name="lock" /><p>Brouillon <strong>invisible du public</strong> jusqu’à son approbation par la modération.</p></div>@endif
    @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>Vérifiez les champs signalés ci-dessous. Vos saisies sont conservées. {{ $errors->first() }}</p></div>@endif

    <form method="post" action="{{ route('client.missions.update', $mission->getKey()) }}" data-once class="mission-editor stack" data-mission-editor novalidate>@csrf
      <input type="hidden" name="revision_no" value="{{ old('revision_no', $v->revision_no) }}">
      <nav class="editor-steps mission-steps" aria-label="Étapes de votre mission" hidden>
        @foreach(['besoin' => 'Votre besoin', 'budget' => 'Budget et dates', 'elements' => 'Avant de publier'] as $step => $label)
          <button class="btn btn-secondary" type="button" data-mission-go="{{ $step }}" aria-controls="mission-{{ $step }}"><span>{{ $loop->iteration }}</span> {{ $label }}</button>
        @endforeach
      </nav>
      <div class="split"><div class="editor-main">
      <fieldset class="fieldset-bare" @disabled(! $editable)>
        <section class="card panel" id="mission-besoin" data-mission-panel="besoin" aria-labelledby="h-b"><div class="card-head"><h2 class="t-h2" id="h-b">Quel est votre projet ?</h2></div><p class="muted">Ces informations seront visibles par les freelances après publication.</p><div class="fields">
          <x-fc.field name="title" label="Donnez un titre à votre mission" :value="$v->title" :hint="'Exemple : Création d’un logo pour ma boutique. '.$L['title'][0].' à '.$L['title'][1].' caractères.'" />
          <div class="field"><label for="f-category_id">Catégorie</label><select class="select" id="f-category_id" name="category_id" required @if($err('category_id')) aria-invalid="true" aria-describedby="e-category_id" @endif>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id', $v->category_id) === $c->id)>{{ $c->name }}</option>@endforeach</select>@if($err('category_id'))<p class="field-error" id="e-category_id"><x-fc.icon name="error" :size="16" />{{ $err('category_id') }}</p>@endif</div>
          <div class="field"><label for="f-description">Décrivez le résultat attendu</label><p class="hint" id="h-description">Expliquez ce que vous souhaitez obtenir, le style et les formats attendus. {{ $L['description'][0] }} à {{ $L['description'][1] }} caractères. Ne mettez ni coordonnées personnelles ni informations confidentielles.</p>
            <textarea class="textarea" id="f-description" name="description" rows="6" placeholder="Exemple : Je cherche un logo pour une boutique de vêtements à Abidjan. Je souhaite un style sobre, en bleu et blanc, livré en PNG et en format modifiable…" aria-describedby="h-description @if($err('description')) e-description @endif" @if($err('description')) aria-invalid="true" @endif>{{ old('description', $v->description) }}</textarea>@if($err('description'))<p class="field-error" id="e-description"><x-fc.icon name="error" :size="16" />{{ $err('description') }}</p>@endif</div>
        </div></section>
        <section class="card panel" id="mission-budget" data-mission-panel="budget" aria-labelledby="h-budget"><div class="card-head"><h2 class="t-h2" id="h-budget">Votre budget et les candidatures</h2></div><div class="form-grid">
          <x-fc.field name="budget_xof" label="Budget prévu (FCFA)" type="text" :value="$v->budget_xof" :required="false" :hint="'Votre enveloppe, de '.number_format($L['budget_xof'][0], 0, ',', ' ').' à '.number_format($L['budget_xof'][1], 0, ',', ' ').' FCFA. Chaque freelance vous proposera un prix fixe.'" />
          <div class="field"><label for="f-application_deadline">Jusqu’à quand recevoir des propositions ?</label><p class="hint" id="h-dl">Choisissez une date entre demain et dans {{ $L['deadline_max_days'] }} jours. Vous aurez ensuite {{ $L['selection_days'] }} jours pour choisir un freelance. Ce n’est pas la date de livraison du travail.</p>
            <input class="input" type="date" id="f-application_deadline" name="application_deadline" value="{{ $deadline }}" aria-describedby="h-dl @if($err('application_deadline')) e-application_deadline @endif" @if($err('application_deadline')) aria-invalid="true" @endif>@if($err('application_deadline'))<p class="field-error" id="e-application_deadline"><x-fc.icon name="error" :size="16" />{{ $err('application_deadline') }}</p>@endif</div>
        </div></section>
        <section class="card panel" id="mission-elements" data-mission-panel="elements" aria-labelledby="h-brief"><div class="card-head"><h2 class="t-h2" id="h-brief">Préparez la suite avec le freelance</h2></div><div class="fields">
          <div class="field"><label for="f-client_inputs">Ce que vous fournirez au freelance (facultatif)</label><p class="hint" id="h-ci">Listez seulement les intitulés, un par ligne : « Nom de la marque », « Couleurs souhaitées »… Ces intitulés seront publics. Vous renseignerez les réponses privées lors du choix du freelance ; lui seul les recevra.</p>
            <textarea class="textarea" id="f-client_inputs" name="client_inputs" rows="4" placeholder="Nom de la marque&#10;Couleurs souhaitées&#10;Exemples de styles appréciés" aria-describedby="h-ci @if($err('client_inputs')) e-client_inputs @endif" @if($err('client_inputs')) aria-invalid="true" @endif>{{ old('client_inputs', implode("\n", $v->client_inputs ?? [])) }}</textarea>@if($err('client_inputs'))<p class="field-error" id="e-client_inputs"><x-fc.icon name="error" :size="16" />{{ $err('client_inputs') }}</p>@endif</div>
          <input type="hidden" name="brief_requires_files" value="0">
          <label class="check"><input type="checkbox" name="brief_requires_files" value="1" @checked(old('brief_requires_files', $v->brief_requires_files))> <span>Un fichier de ma part sera nécessaire pour commencer le travail.</span></label>
          <p class="note-line"><x-fc.icon name="lock" :size="16" /><span><strong>Les fichiers seront ajoutés plus tard dans la commande, en privé.</strong> Si vous cochez cette option, le travail attendra au moins un fichier accepté après contrôle de sécurité, ainsi que la confirmation du paiement.</span></p>
        </div></section>
      </fieldset>
      <div class="editor-pagination" hidden><button type="button" class="btn btn-secondary" data-mission-prev>Précédent</button><span class="hint" data-mission-progress aria-live="polite"></span><button type="button" class="btn btn-primary" data-mission-next>Continuer</button></div>
      @if($editable)<div class="row row-gap"><button class="btn btn-secondary btn-lg" type="submit" name="intent" value="save" data-once-label="Enregistrement…">Enregistrer le brouillon</button><button class="btn btn-primary btn-lg" type="submit" name="intent" value="submit" data-mission-final data-once-label="Vérification…">Continuer vers la modération</button></div>@endif
      @if($editable)<p class="muted small">Vous pouvez enregistrer votre brouillon à tout moment. La soumission à la modération sera confirmée sur l’écran suivant.</p>@endif
      </div><aside class="card panel mission-preview" aria-labelledby="h-preview">
        <div class="card-head"><h2 class="t-h2" id="h-preview">Votre mission en résumé</h2><span class="badge">{{ $editable ? 'Brouillon' : 'En contrôle' }}</span></div>
        <div class="stack" data-mission-preview hidden><p class="eyebrow" data-mission-category></p><h3 class="t-h3" data-mission-title></h3><p class="muted mission-summary" data-mission-description></p><dl class="defs"><div><dt>Budget prévu</dt><dd data-mission-budget></dd></div><div><dt>Candidatures jusqu’au</dt><dd data-mission-deadline></dd></div></dl><p class="muted small" data-mission-files></p><p class="muted small">{{ $editable ? 'Résumé de votre saisie, pas encore enregistré.' : 'Résumé de la version soumise.' }}</p></div>
        <noscript><p class="muted">Enregistrez votre brouillon puis ouvrez l’aperçu pour voir votre mission.</p></noscript>
        <div class="mission-help"><h3 class="t-h3">Après la publication</h3><p class="muted small">Les freelances vous proposent un prix et un délai. Vous choisissez une proposition, complétez les informations privées et passez au paiement.</p></div>
      </aside></div>
    </form>
  </div>
</x-layouts.account>

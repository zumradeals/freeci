<x-layouts.account title="Publier une mission" space="client">
  <nav class="crumbs" aria-label="Fil d’Ariane"><a class="back-m" href="{{ route('client.missions') }}"><x-fc.icon name="arrow-right" :size="16" class="flip" />Mes missions</a><a class="hide-m" href="{{ route('client.missions') }}">Mes missions</a><span class="sep hide-m" aria-hidden="true">›</span><span class="hide-m" aria-current="page">Nouvelle mission</span></nav>
  <section class="card form-card" aria-labelledby="h-new"><h1 class="t-h1" id="h-new">Publier une mission</h1>
    <p class="muted" style="margin-top:6px">Deux informations pour commencer. Le reste se complète dans le brouillon, invisible du public jusqu’à son approbation.</p>
    @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>Vérifiez les champs signalés ci-dessous. Vos saisies sont conservées.</p></div>@endif
    <form method="post" action="{{ route('client.missions.store') }}" data-once style="display:grid;gap:16px;margin-top:16px" novalidate>@csrf
      <x-fc.field name="title" label="Titre du besoin" :hint="'De '.$limits['title'][0].' à '.$limits['title'][1].' caractères. Ex. « Conversion de 12 plans PDF en fichiers DWG »'" />
      <div class="field"><label for="f-category_id">Catégorie</label>
        <select class="select" id="f-category_id" name="category_id" required @error('category_id') aria-invalid="true" aria-describedby="e-category_id" @enderror><option value="">Choisir…</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id') === $c->id)>{{ $c->name }}</option>@endforeach</select>
        @error('category_id')<p class="field-error" id="e-category_id"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
      <div class="row"><button class="btn btn-primary btn-lg" type="submit" data-once-label="Création…">Créer le brouillon</button><a class="btn btn-link" href="{{ route('client.missions') }}">Annuler</a></div>
    </form></section>
</x-layouts.account>

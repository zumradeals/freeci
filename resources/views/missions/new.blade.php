<x-layouts.account title="Publier une mission" space="client">
  <div class="page-body">
    <nav class="crumbs" aria-label="Fil d’Ariane"><a class="back-m" href="{{ route('client.missions') }}"><x-fc.icon name="arrow-right" :size="16" class="flip" />Mes missions</a><a class="hide-m" href="{{ route('client.missions') }}">Mes missions</a><span class="sep hide-m" aria-hidden="true">›</span><span class="hide-m" aria-current="page">Nouvelle mission</span></nav>
    <header class="page-head"><div class="row-top"><div><p class="eyebrow">Mes missions</p><h1 class="t-h1">Publier une mission</h1><p class="lead">Un brouillon à compléter, puis à soumettre pour approbation.</p></div></div></header>
    <div class="split"><section class="card panel" aria-labelledby="h-new"><div class="card-head"><h2 class="t-h2" id="h-new">Pour commencer</h2></div>
      @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>Vérifiez les champs signalés ci-dessous. Vos saisies sont conservées.</p></div>@endif
      <form method="post" action="{{ route('client.missions.store') }}" data-once class="stack" novalidate>@csrf
        <x-fc.field name="title" label="Titre du besoin" :hint="'De '.$limits['title'][0].' à '.$limits['title'][1].' caractères. Ex. « Conversion de 12 plans PDF en fichiers DWG »'" />
        <div class="field"><label for="f-category_id">Catégorie</label>
          <select class="select" id="f-category_id" name="category_id" required @error('category_id') aria-invalid="true" aria-describedby="e-category_id" @enderror><option value="">Choisir…</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id') === $c->id)>{{ $c->name }}</option>@endforeach</select>
          @error('category_id')<p class="field-error" id="e-category_id"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
        <div class="row"><button class="btn btn-primary btn-lg" type="submit" data-once-label="Création…">Créer le brouillon</button><a class="btn btn-link" href="{{ route('client.missions') }}">Annuler</a></div>
      </form></section>
      <aside class="card panel guide" aria-labelledby="h-next"><h2 class="t-h2" id="h-next">Ensuite</h2><p class="muted">Deux informations pour commencer. Le reste se complète dans le brouillon, invisible du public jusqu’à son approbation.</p></aside></div>
  </div>
</x-layouts.account>

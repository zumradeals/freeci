<x-layouts.admin title="Pages légales">
  <header class="page-head"><div class="row-top"><div><p class="eyebrow">Administration</p><h1 class="t-h1">Pages légales et d’information</h1><p class="lead">Rédigez, relisez et publiez vous-même les textes du site. Une page n’est « adoptée » que lorsque vous la publiez.</p></div></div></header>
  <div class="page-body">
    <div class="notice tone-info"><x-fc.icon name="info" /><p>Tant qu’une page n’est pas adoptée, le public voit le texte de départ avec un bandeau « brouillon ». L’identité de l’exploitant se saisit dans <a href="{{ route('admin.settings') }}">Paramètres</a>.</p></div>
    <section class="card panel" aria-labelledby="h-pages"><div class="card-head"><h2 class="t-h2" id="h-pages">Les pages</h2></div>
      @foreach($pages as $p)
        <article class="record"><div class="record-head"><div><a class="record-link" href="{{ route('admin.legal.edit', $p['slug']) }}">{{ $p['title'] }}</a>
            <p class="muted small">@if($p['state'] === 'adopted')Adoptée, version {{ $p['version'] }}, le {{ $p['publishedAt'] }}@elseif($p['state'] === 'draft')Brouillon enregistré le {{ $p['draftAt'] }} (non public)@elseif($p['state'] === 'withdrawn')Adoption retirée ; version {{ $p['version'] }} conservée@else Texte de départ, jamais modifié @endif</p></div>
          <span class="badge {{ $p['state'] === 'adopted' ? 'tone-success' : 'tone-warning' }}">{{ $p['state'] === 'adopted' ? 'Adoptée' : 'Brouillon' }}</span></div></article>
      @endforeach
    </section>
  </div>
</x-layouts.admin>

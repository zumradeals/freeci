<x-layouts.admin title="Menus du site">
  <header class="page-head"><div class="row-top"><div><p class="eyebrow">Administration</p><h1 class="t-h1">Menus du site</h1><p class="lead">Le menu de l’en-tête et les trois colonnes du pied de page : texte, ordre, visibilité et page de destination de chaque lien.</p></div></div></header>
  <div class="page-body">
    <div class="notice tone-info"><x-fc.icon name="info" /><p>Chaque lien mène vers une <strong>page du site choisie dans une liste</strong> : aucun lien extérieur n’est possible ici. Tant qu’un menu n’est pas personnalisé, les liens d’origine s’appliquent ; « Rétablir » y revient. Les titres des colonnes du pied de page sont fixes.</p></div>
    @foreach($areas as $area => $a)
      <section class="card panel" aria-labelledby="h-{{ $area }}"><div class="card-head"><h2 class="t-h2" id="h-{{ $area }}">{{ $a['title'] }}</h2><span class="badge {{ $a['customized'] ? 'tone-info' : 'tone-neutral' }}">{{ $a['customized'] ? 'Personnalisé' : 'Menu d’origine' }}</span></div>
        @if(! $a['customized'])
          <ul class="stack-sm">@foreach($a['items'] as $it)<li>{{ $it['label'] }} <span class="muted small">→ {{ $destinations[$it['destination']] ?? '—' }}</span></li>@endforeach</ul>
          <form method="post" action="{{ route('admin.navigation.customize', $area) }}" data-once>@csrf<button class="btn btn-secondary" type="submit">Personnaliser ce menu</button></form>
        @else
          @foreach($a['items'] as $it)
            <article class="record">
              <form method="post" action="{{ route('admin.navigation.update', $it['id']) }}" class="form-grid" data-once>@csrf
                <div class="field"><label for="l-{{ $it['id'] }}">Texte du lien</label><input class="input" id="l-{{ $it['id'] }}" name="label" value="{{ $it['label'] }}" minlength="2" maxlength="40" required></div>
                <div class="field"><label for="d-{{ $it['id'] }}">Mène vers</label><select class="select" id="d-{{ $it['id'] }}" name="destination">@foreach($destinations as $k => $l)<option value="{{ $k }}" @selected($it['destination'] === $k)>{{ $l }}</option>@endforeach</select></div>
                <label class="check"><input type="checkbox" name="visible" value="1" @checked($it['visible'])> <span>Afficher ce lien</span></label>
                <div class="row row-gap"><button class="btn btn-secondary" type="submit">Enregistrer</button></div>
              </form>
              <div class="row row-gap">
                <form method="post" action="{{ route('admin.navigation.move', [$it['id'], 'up']) }}">@csrf<button class="btn btn-link" type="submit" aria-label="Monter {{ $it['label'] }}">↑ Monter</button></form>
                <form method="post" action="{{ route('admin.navigation.move', [$it['id'], 'down']) }}">@csrf<button class="btn btn-link" type="submit" aria-label="Descendre {{ $it['label'] }}">↓ Descendre</button></form>
                <form method="post" action="{{ route('admin.navigation.delete', $it['id']) }}" data-once>@csrf<button class="btn btn-link" type="submit">Supprimer ce lien</button></form>
              </div>
            </article>
          @endforeach
          @if(count($a['items']) < $a['max'])
          <details><summary>Ajouter un lien</summary>
            <form method="post" action="{{ route('admin.navigation.add', $area) }}" class="form-grid mt-8" data-once>@csrf
              <div class="field"><label for="n-l-{{ $area }}">Texte du lien</label><input class="input" id="n-l-{{ $area }}" name="label" minlength="2" maxlength="40" required></div>
              <div class="field"><label for="n-d-{{ $area }}">Mène vers</label><select class="select" id="n-d-{{ $area }}" name="destination">@foreach($destinations as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
              <div><button class="btn btn-primary" type="submit">Ajouter</button></div>
            </form></details>
          @endif
          <form method="post" action="{{ route('admin.navigation.reset', $area) }}" data-once>@csrf<button class="btn btn-link" type="submit">Rétablir le menu d’origine</button></form>
        @endif
      </section>
    @endforeach
  </div>
</x-layouts.admin>

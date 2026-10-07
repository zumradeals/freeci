<x-layouts.admin title="Catégories">
  <header class="page-head"><div class="row-top"><div><p class="eyebrow">Administration</p><h1 class="t-h1">Catégories</h1><p class="lead">Les rubriques dans lesquelles les freelances publient leurs services et les clients leurs missions.</p></div></div></header>
  <div class="page-body">
    <div class="notice tone-info"><x-fc.icon name="info" /><p>Une catégorie <strong>archivée</strong> n’est plus proposée, mais les services et missions déjà rattachés sont conservés. Une catégorie ne peut être <strong>supprimée</strong> que si rien ne l’utilise. Au moins une catégorie doit rester proposée. L’adresse web d’une catégorie est figée à sa création.</p></div>

    <section class="card panel" aria-labelledby="h-new"><div class="card-head"><h2 class="t-h2" id="h-new">Nouvelle catégorie</h2></div>
      <form method="post" action="{{ route('admin.categories.store') }}" class="form-grid" data-once>@csrf
        <div class="field"><label for="n-name">Nom</label><input class="input" id="n-name" name="name" value="{{ old('name') }}" minlength="3" maxlength="60" required></div>
        <div class="field"><label for="n-icon">Icône</label><select class="select" id="n-icon" name="icon" required>@foreach($icons as $k => $l)<option value="{{ $k }}" @selected(old('icon', 'cog') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div><button class="btn btn-primary" type="submit">Créer la catégorie</button></div>
      </form>
    </section>

    <section class="card panel" aria-labelledby="h-list"><div class="card-head"><h2 class="t-h2" id="h-list">Les catégories</h2><span class="muted small">{{ count($rows) }} au total</span></div>
      @forelse($rows as $r)
        <article class="record">
          <div class="record-head"><div><strong>{{ $r['name'] }}</strong>
            <p class="muted small"><span class="num">/{{ $r['slug'] }}</span> · {{ $r['services'] }} service{{ $r['services'] > 1 ? 's' : '' }} ({{ $r['published'] }} publié{{ $r['published'] > 1 ? 's' : '' }}) · {{ $r['missions'] }} mission{{ $r['missions'] > 1 ? 's' : '' }}</p></div>
            <span class="badge {{ $r['archived'] ? 'tone-neutral' : 'tone-success' }}">{{ $r['archived'] ? 'Archivée' : 'Proposée' }}</span></div>
          <form method="post" action="{{ route('admin.categories.update', $r['id']) }}" class="form-grid" data-once>@csrf
            <div class="field"><label for="c-name-{{ $r['id'] }}">Nom</label><input class="input" id="c-name-{{ $r['id'] }}" name="name" value="{{ $r['name'] }}" minlength="3" maxlength="60" required></div>
            <div class="field"><label for="c-icon-{{ $r['id'] }}">Icône</label><select class="select" id="c-icon-{{ $r['id'] }}" name="icon">@foreach($icons as $k => $l)<option value="{{ $k }}" @selected($r['icon'] === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="row"><button class="btn btn-secondary" type="submit">Enregistrer</button></div>
          </form>
          <div class="row row-gap">
            @unless($r['archived'])
              <form method="post" action="{{ route('admin.categories.move', [$r['id'], 'up']) }}">@csrf<button class="btn btn-link" type="submit" aria-label="Monter {{ $r['name'] }}">↑ Monter</button></form>
              <form method="post" action="{{ route('admin.categories.move', [$r['id'], 'down']) }}">@csrf<button class="btn btn-link" type="submit" aria-label="Descendre {{ $r['name'] }}">↓ Descendre</button></form>
            @endunless
          </div>
          @if($r['archived'])
            <form method="post" action="{{ route('admin.categories.restore', $r['id']) }}" data-once>@csrf<button class="btn btn-secondary" type="submit">Rétablir cette catégorie</button></form>
          @else
            <details><summary>Archiver cette catégorie</summary>
              <form method="post" action="{{ route('admin.categories.archive', $r['id']) }}" class="stack-sm mt-8" data-once>@csrf
                <div class="field"><label for="a-{{ $r['id'] }}">Motif (10 caractères minimum)</label><textarea class="textarea" id="a-{{ $r['id'] }}" name="reason" rows="2" minlength="10" maxlength="1000" required></textarea></div>
                <button class="btn btn-secondary" type="submit">Archiver</button></form></details>
          @endif
          @if($r['services'] === 0 && $r['missions'] === 0)
            <details><summary>Supprimer définitivement</summary>
              <form method="post" action="{{ route('admin.categories.delete', $r['id']) }}" class="stack-sm mt-8" data-once>@csrf
                <div class="field"><label for="d-{{ $r['id'] }}">Motif (10 caractères minimum)</label><textarea class="textarea" id="d-{{ $r['id'] }}" name="reason" rows="2" minlength="10" maxlength="1000" required></textarea></div>
                <button class="btn btn-primary" type="submit">Supprimer</button></form></details>
          @endif
        </article>
      @empty
        <div class="card empty"><span class="ico-lg"><x-fc.icon name="list" :size="26" /></span><p style="font-weight:600">Aucune catégorie pour l’instant.</p><p class="muted">Créez-en une ci-dessus : sans catégorie, personne ne peut publier de service ni de mission.</p></div>
      @endforelse
    </section>
  </div>
</x-layouts.admin>

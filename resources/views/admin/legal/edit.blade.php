<x-layouts.admin :title="$title">
  <nav class="crumbs" aria-label="Fil d’Ariane"><a href="{{ route('admin.legal') }}">Pages légales</a><span class="sep" aria-hidden="true">›</span><span aria-current="page">{{ $title }}</span></nav>
  <header class="page-head"><div class="row-top"><div><p class="eyebrow">Administration</p><h1 class="t-h1">{{ $title }}</h1><p class="lead">@if($state === 'adopted')Adoptée, version {{ $row->published_version }}. Toute modification doit être republiée.@else Pas encore adoptée : le public voit le texte de départ avec un bandeau « brouillon ».@endif</p></div>
    <a class="btn btn-secondary" href="{{ route('info', $slug) }}" target="_blank" rel="noopener">Voir la page publique</a></div></header>
  @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>{{ $errors->first() }}</p></div>@endif
  <div class="page-body"><div class="split">
    <div class="stack-col">
      <section class="card panel" aria-labelledby="h-edit"><div class="card-head"><h2 class="t-h2" id="h-edit">Texte</h2><span class="meta-r">Brouillon : jamais public avant publication</span></div>
        <form method="post" action="{{ route('admin.legal.draft', $slug) }}" class="stack-sm" data-once>@csrf
          <div class="field"><label for="body">Contenu</label><textarea class="input" id="body" name="body" rows="22" maxlength="{{ \App\Modules\Admin\Legal\LegalPages::MAX }}" required style="font-family:ui-monospace,monospace">{{ $body }}</textarea></div>
          <p class="muted small">Mise en forme : une ligne vide sépare les paragraphes ; <code>## Titre</code> pour un titre ; <code>- </code> pour une liste ; <code>**gras**</code> et <code>*italique*</code>. Le HTML est ignoré. Jetons remplacés automatiquement par vos Paramètres : <code>{exploitant}</code> <code>{adresse}</code> <code>{immatriculation}</code> <code>{directeur}</code> <code>{hebergeur}</code> <code>{contact}</code>.</p>
          <div><button class="btn btn-secondary" type="submit">Enregistrer le brouillon et voir l’aperçu</button></div></form></section>
      <section class="card panel" aria-labelledby="h-prev"><div class="card-head"><h2 class="t-h2" id="h-prev">Aperçu</h2><span class="meta-r">Rendu du texte enregistré</span></div>
        <div class="prose">{!! $html !!}</div></section>
    </div>
    <aside class="stack-col">
      <section class="card panel" aria-labelledby="h-pub"><h2 class="t-h2" id="h-pub">Publier comme adopté</h2>
        <p class="muted small">Publier remplace le texte public par cette version et retire le bandeau « brouillon ». Vous engagez l’exploitant : relisez l’aperçu. Version suivante : {{ ($row->published_version ?? 0) + 1 }}.</p>
        <form method="post" action="{{ route('admin.legal.publish', $slug) }}" class="stack-sm" data-once>@csrf
          <div class="field"><label for="pub-r">Motif (10 caractères minimum)</label><textarea class="input" id="pub-r" name="reason" rows="2" minlength="10" maxlength="1000" required></textarea></div>
          <label class="check"><input type="checkbox" name="confirm" value="1" required> <span>Ce texte est adopté et peut être présenté comme définitif.</span></label>
          <div><button class="btn btn-primary" type="submit">Publier</button></div></form></section>
      @if($state === 'adopted')
      <section class="card panel" aria-labelledby="h-wd"><h2 class="t-h2" id="h-wd">Retirer l’adoption</h2>
        <form method="post" action="{{ route('admin.legal.withdraw', $slug) }}" class="stack-sm" data-once>@csrf
          <div class="field"><label for="wd-r">Motif (10 caractères minimum)</label><textarea class="input" id="wd-r" name="reason" rows="2" minlength="10" maxlength="1000" required></textarea></div>
          <div><button class="btn btn-secondary" type="submit">Repasser en brouillon public</button></div></form></section>
      @endif
      <section class="card panel" aria-labelledby="h-his"><h2 class="t-h2" id="h-his">Historique</h2>
        @forelse($history as $h)<p class="small"><strong>Version {{ $h['version'] }} · {{ $h['action'] }}</strong><br><span class="muted">{{ $h['when'] }} · {{ $h['actor'] }} — {{ $h['reason'] }}</span></p>@empty<p class="muted small">Aucune publication pour l’instant.</p>@endforelse</section>
    </aside>
  </div></div>
</x-layouts.admin>

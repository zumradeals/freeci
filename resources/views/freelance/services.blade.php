@php
  $all = collect($services);
  $attention = fn ($s) => $s['working'] === 'changes_requested' ? 0 : (($s['working'] === 'draft' && ! $s['published']) ? 1 : 2);
  $tabs = [
    'tous' => ['Tous', $all->count()],
    'publies' => ['Publiés', $all->where('published', true)->count()],
    'en-controle' => ['En contrôle', $all->where('working', 'in_review')->count()],
    'a-corriger' => ['À corriger', $all->where('working', 'changes_requested')->count()],
    'brouillons' => ['Brouillons', $all->where('working', 'draft')->count()],
    'retires' => ['Retirés', $all->where('canRestore', true)->count()],
  ];
  $cur = array_key_exists(request('statut'), $tabs) ? request('statut') : 'tous';
  $shown = match ($cur) {
    'publies' => $all->where('published', true), 'en-controle' => $all->where('working', 'in_review'), 'a-corriger' => $all->where('working', 'changes_requested'),
    'brouillons' => $all->where('working', 'draft'), 'retires' => $all->where('canRestore', true), default => $all,
  };
  $shown = $shown->sortBy($attention)->values();
@endphp
<x-layouts.account title="Mes services" space="freelancer">
  <header class="sx-head">
    <div><p class="sx-kicker">Espace freelance</p><h1>Mes services</h1><p class="muted">Vos services, leur état de publication et les actions possibles.</p></div>
    <div class="sx-acts"><a class="btn btn-primary" href="{{ route('freelance.services.new') }}"><x-fc.icon name="pencil" /> Créer un service</a></div>
  </header>
  @if(! ($profile?->published_at))<div class="notice tone-warning" role="note"><x-fc.icon name="warn" /><p><strong>Votre profil n’est pas publié.</strong> Il faut le publier avant de soumettre un service. <a href="{{ route('freelance.profile') }}">Compléter mon profil</a></p></div>@endif
  @if(count($services))
    <nav class="sv-tabs" aria-label="Filtrer les services">@foreach($tabs as $k => [$label, $n])<a class="sv-tab {{ $cur === $k ? 'on' : '' }}" href="{{ route('freelance.services', $k === 'tous' ? [] : ['statut' => $k]) }}" @if($cur === $k) aria-current="page" @endif>{{ $label }} <span class="n">{{ $n }}</span></a>@endforeach</nav>
    @if($shown->count())
    <div class="sv-list">@foreach($shown as $s)
      <article class="sv-card {{ $attention($s) < 2 ? 'attn' : '' }}" aria-labelledby="s-{{ $s['id'] }}">
        <div class="sv-thumb">@if($s['thumb'])<img src="{{ $s['thumb'] }}" alt="" loading="lazy" width="320" height="200">@else<div class="sv-ph">Pas encore d’image de couverture</div>@endif</div>
        <div class="sv-body">
          <div class="sv-top"><div><p class="sv-cat">{{ $s['category'] }}</p><h2 id="s-{{ $s['id'] }}">{{ $s['title'] ?: 'Sans titre' }}</h2></div>@if($s['price'])<span class="sv-price"><x-fc.money :amount="$s['price']" /></span>@endif</div>
          <div class="sv-meta"><span class="badge tone-{{ $s['tone'] }}"><x-fc.icon :name="$s['icon']" :size="16" />{{ $s['status'] }}</span>
            @if($s['deliveryDays'])<span>{{ $s['deliveryDays'] }} {{ $s['deliveryDays'] > 1 ? 'jours' : 'jour' }}</span>@endif
            @if($s['workingNumber'])<span>Version {{ $s['workingNumber'] }} en rédaction ou en contrôle</span>@elseif($s['liveNumber'])<span>Version {{ $s['liveNumber'] }} en ligne</span>@endif
            @if($s['toComplete'] > 0)<span>{{ $s['toComplete'] }} point{{ $s['toComplete'] > 1 ? 's' : '' }} à compléter avant de pouvoir soumettre</span>@endif</div>
          @if($s['note'])<div class="{{ $s['working'] === 'changes_requested' ? 'sv-note' : 'sv-plain' }}">@if($s['working'] === 'changes_requested')<b>Motif de la modération :</b> « {{ $s['note'] }} »@else{{ $s['note'] }}@endif</div>@endif
          <div class="sv-acts">
            @if($s['canEdit'])<a class="btn btn-primary" href="{{ route('freelance.services.edit', $s['id']) }}">{{ $s['working'] === 'changes_requested' ? 'Corriger' : ($s['working'] === 'draft' && ! $s['published'] ? 'Continuer' : 'Modifier') }}<span class="sr-only"> {{ $s['title'] }}</span></a>@endif
            @if($s['canRevise'])<a class="btn btn-secondary" href="{{ route('freelance.services.confirm', [$s['id'], 'nouvelle-version']) }}">Modifier<span class="sr-only"> {{ $s['title'] }}</span></a>@endif
            @if($s['canRestore'])<a class="btn btn-primary" href="{{ route('freelance.services.confirm', [$s['id'], 'remettre-en-ligne']) }}">Remettre en ligne</a>@endif
            @if($s['canPreview'])<a class="btn btn-secondary" href="{{ route('freelance.services.preview', $s['id']) }}">Aperçu</a>@endif
            @if($s['published'])<a class="btn btn-link" href="{{ route('services.show', $s['slug']) }}">Voir la page publique</a>@endif
            @if($s['canUnsubmit'])<a class="btn btn-link" href="{{ route('freelance.services.confirm', [$s['id'], 'retirer-soumission']) }}">Retirer la soumission</a>@endif
            @if($s['canWithdraw'])<details class="sd-pop sv-more"><summary class="sd-filter">Plus<x-fc.icon name="chev-down" :size="16" /></summary><div class="sd-pop-panel"><ul><li><a href="{{ route('freelance.services.confirm', [$s['id'], 'retirer-du-catalogue']) }}">Retirer du catalogue</a></li></ul></div></details>@endif
          </div>
        </div>
      </article>
    @endforeach</div>
    @else<div class="card empty"><p class="muted">Aucun service dans cette catégorie.</p></div>@endif
  @else
    <div class="card empty"><span class="ico-lg"><x-fc.icon name="briefcase" :size="26" /></span><p style="font-weight:600">Aucun service à votre nom.</p><p class="muted" style="max-width:36em">Créez un brouillon : il reste invisible tant que la modération ne l’a pas approuvé.</p><a class="btn btn-primary" href="{{ route('freelance.services.new') }}">Créer un service</a></div>
  @endif
  <p class="sv-info"><x-fc.icon name="info" :size="18" /><span>Modifier un service publié crée une nouvelle version : la version en ligne ne change qu’après approbation, et les commandes déjà passées gardent leur accord d’origine.</span></p>
</x-layouts.account>

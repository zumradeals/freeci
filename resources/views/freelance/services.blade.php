<x-layouts.account title="Mes services" space="freelancer">
  <div class="page-body">
    <header class="page-head"><div class="row-top"><div><p class="eyebrow">Espace freelance</p><h1 class="t-h1">Mes services</h1><p class="lead">Vos services, leur état de publication et les actions possibles.</p></div>
      <a class="btn btn-primary" href="{{ route('freelance.services.new') }}">Créer un service</a></div></header>
    @if(! ($profile?->published_at))<div class="notice tone-warning" role="note"><x-fc.icon name="warn" /><p><strong>Votre profil n’est pas publié.</strong> Il faut le publier avant de soumettre un service. <a href="{{ route('freelance.profile') }}">Compléter mon profil</a></p></div>@endif
    @if(count($services))
      <div class="stack-lg">@foreach($services as $s)
        <article class="card" aria-labelledby="s-{{ $s['id'] }}">
          <div class="row" style="justify-content:space-between;gap:8px 16px;align-items:flex-start"><div style="min-width:0"><p class="muted small">{{ $s['category'] }}</p><h2 class="t-h3" id="s-{{ $s['id'] }}">{{ $s['title'] ?: 'Sans titre' }}</h2></div>
            @if($s['price'])<x-fc.money :amount="$s['price']" />@endif</div>
          <p style="margin-top:8px"><span class="badge tone-{{ $s['tone'] }}"><x-fc.icon :name="$s['icon']" :size="16" />{{ $s['status'] }}</span>
            @if($s['workingNumber'])<span class="muted small"> · version {{ $s['workingNumber'] }} en rédaction ou en contrôle</span>@endif</p>
          @if($s['note'])<p class="{{ $s['working'] === 'changes_requested' ? 'quote' : 'muted' }}" style="margin-top:8px">@if($s['working'] === 'changes_requested')<strong>Motif de la modération :</strong> « {{ $s['note'] }} »@else{{ $s['note'] }}@endif</p>@endif
          <div class="row" style="margin-top:12px;gap:8px">
            @if($s['canEdit'])<a class="btn btn-secondary" href="{{ route('freelance.services.edit', $s['id']) }}">{{ $s['working'] === 'changes_requested' ? 'Corriger' : 'Modifier' }}<span class="sr-only"> {{ $s['title'] }}</span></a>@endif
            @if($s['canPreview'])<a class="btn btn-secondary" href="{{ route('freelance.services.preview', $s['id']) }}">Aperçu</a>@endif
            @if($s['canRevise'])<a class="btn btn-secondary" href="{{ route('freelance.services.confirm', [$s['id'], 'nouvelle-version']) }}">Modifier<span class="sr-only"> {{ $s['title'] }}</span></a>@endif
            @if($s['canUnsubmit'])<a class="btn btn-secondary" href="{{ route('freelance.services.confirm', [$s['id'], 'retirer-soumission']) }}">Retirer la soumission</a>@endif
            @if($s['published'])<a class="btn btn-link" href="{{ route('services.show', $s['slug']) }}">Voir la page publique</a>@endif
            @if($s['canWithdraw'])<a class="btn btn-link" href="{{ route('freelance.services.confirm', [$s['id'], 'retirer-du-catalogue']) }}">Retirer du catalogue</a>@endif
            @if($s['canRestore'])<a class="btn btn-secondary" href="{{ route('freelance.services.confirm', [$s['id'], 'remettre-en-ligne']) }}">Remettre en ligne</a>@endif
          </div>
        </article>
      @endforeach</div>
    @else
      <div class="card empty"><span class="ico-lg"><x-fc.icon name="briefcase" :size="26" /></span><p style="font-weight:600">Aucun service à votre nom.</p><p class="muted" style="max-width:36em">Créez un brouillon : il reste invisible tant que la modération ne l’a pas approuvé.</p><a class="btn btn-primary" href="{{ route('freelance.services.new') }}">Créer un service</a></div>
    @endif
    <p class="note-line"><x-fc.icon name="info" :size="16" /><span>Modifier un service publié crée une nouvelle version : la version en ligne ne change qu’après approbation, et les commandes déjà passées gardent leur accord d’origine.</span></p>
  </div>
</x-layouts.account>

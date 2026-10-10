<x-layouts.account title="Invitations à des missions" space="freelancer">
  <div class="page-body">
    <nav class="sd-crumbs" aria-label="Fil d’Ariane"><a href="{{ route('freelance.dashboard') }}">Espace freelance</a> › <span aria-current="page">Invitations</span></nav>
    <div class="sx-head"><div><p class="sx-kicker">Espace freelance</p><h1>Invitations à des missions</h1><p class="muted">Des clients vous invitent à proposer vos services. Vous décidez librement : une invitation ne vous engage à rien.</p></div></div>
    @if(session('error'))<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>{{ session('error') }}</p></div>@endif
    @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>{{ $errors->first() }}</p></div>@endif
    @if($items)
    <div class="mr-list">@foreach($items as $i)
      <article class="mr-card"><header><div><b style="font-size:1.0625rem">{{ $i['title'] }}</b><div class="muted small">Reçue le {{ $i['received'] }} · Budget {{ $i['budget'] }} · Candidature avant le {{ $i['deadline'] }}</div></div><span class="badge tone-{{ $i['tone'] ?: 'neutral' }}">{{ $i['label'] }}</span></header>
        @if($i['message'])<div class="iv-q">« {{ $i['message'] }} »</div>@endif
        @if($i['key'] === 'pending')
          <div class="row" style="gap:10px"><a class="btn btn-secondary" href="{{ route('missions.show', $i['slug']) }}">Voir la mission</a><a class="btn btn-primary" href="{{ route('missions.proposal', $i['slug']) }}">Faire une proposition</a></div>
          <details class="rv-act"><summary><span>Décliner cette invitation</span><x-fc.icon name="chev-down" :size="16" /></summary><form class="in" method="post" action="{{ route('freelance.invitations.decline', $i['id']) }}" data-once>@csrf
            <div class="field"><label for="rs-{{ $i['id'] }}">Motif (le client le voit)</label><select class="select" id="rs-{{ $i['id'] }}" name="reason">@foreach($reasons as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select><p class="muted small" style="margin:0">Pas de texte libre : le client est informé poliment, sans échange de coordonnées.</p></div>
            <button class="btn btn-secondary" type="submit">Confirmer le refus</button></form></details>
        @elseif($i['key'] === 'proposed')
          <div><a class="btn btn-secondary" href="{{ route('freelance.proposals') }}">Voir mes propositions</a></div>
        @elseif($i['key'] === 'declined')
          <p class="muted small" style="margin:0">Motif indiqué : « {{ $i['reason'] }} »</p>
        @elseif($i['key'] === 'expired')
          <p class="muted small" style="margin:0">La mission est fermée ou sa date limite est passée.</p>
        @elseif($i['key'] === 'withdrawn')
          <p class="muted small" style="margin:0">Le client a retiré cette invitation.</p>
        @endif
      </article>
    @endforeach</div>
    @else
      <div class="card empty"><span class="ico-lg"><x-fc.icon name="inbox" :size="26" /></span><p style="font-weight:600">Aucune invitation pour le moment.</p><p class="muted" style="max-width:36em">Un client qui trouve votre profil peut vous inviter à l’une de ses missions ouvertes. Un profil complet et publié, et une disponibilité à jour, augmentent vos chances.</p></div>
    @endif
  </div>
</x-layouts.account>

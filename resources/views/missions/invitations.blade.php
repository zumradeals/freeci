<x-layouts.account :title="'Invitations : '.$title" space="client">
  <div class="page-body">
    <nav class="sd-crumbs" aria-label="Fil d’Ariane"><a href="{{ route('client.missions') }}">Mes missions</a> › <a href="{{ route('client.missions.show', $missionId) }}">{{ $title }}</a> › <span aria-current="page">Invitations</span></nav>
    <div class="sx-head"><div><p class="sx-kicker">Mission</p><h1>Invitations</h1><p class="muted">{{ count($items) }} invitation{{ count($items) > 1 ? 's' : '' }} envoyée{{ count($items) > 1 ? 's' : '' }} sur {{ $max }} possibles.</p></div><div class="sx-acts"><a class="btn btn-secondary" href="{{ route('freelances.index') }}">Trouver un freelance à inviter</a></div></div>
    @if(session('error'))<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>{{ session('error') }}</p></div>@endif
    <div class="ac-grid"><div class="ac-main"><section class="ed-card" aria-labelledby="h-iv"><h2 id="h-iv" style="margin:0">Freelances invités</h2>
      @forelse($items as $i)
        <div class="iv-row"><div><b><a href="{{ route('freelances.show', $i['profile']) }}">{{ $i['name'] }}</a></b><small>Invité le {{ $i['sent'] }}@if($i['reason']) · motif : « {{ $i['reason'] }} »@endif</small></div><span class="badge tone-{{ $i['tone'] ?: 'neutral' }}">{{ $i['key'] === 'pending' ? 'En attente' : ($i['key'] === 'proposed' ? 'Proposition reçue' : $i['label']) }}</span>
          <span>@if($i['key'] === 'pending')<form method="post" action="{{ route('client.invitations.withdraw', $i['id']) }}" data-once>@csrf<button class="btn btn-link" type="submit">Retirer l’invitation</button></form>@elseif($i['key'] === 'proposed')<a href="{{ route('client.missions.proposals', $missionId) }}">Voir les propositions</a>@endif</span></div>
      @empty
        <p class="muted" style="margin:0">Aucune invitation pour cette mission. Ouvrez le profil d’un freelance et choisissez « Inviter à une mission ».</p>
      @endforelse
    </section><p class="muted small">Les invitations ne modifient pas la mission : les autres freelances peuvent toujours proposer. Une invitation expire quand la mission est fermée, attribuée ou échue.</p></div>
    <aside class="ac-side"><section class="ed-ck"><h3>Ce que voit l’invité</h3><ul class="av-tl"><li><x-fc.icon name="check" :size="18" /><span>Le titre, le budget, la date limite et votre message.</span></li><li><x-fc.icon name="check" :size="18" /><span>Jamais votre nom ni vos coordonnées.</span></li></ul></section></aside></div>
  </div>
</x-layouts.account>

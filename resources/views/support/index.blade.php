<x-layouts.account title="Assistance" :space="request('espace') === 'freelance' ? 'freelancer' : 'client'">
  <div class="page-body">
    <div class="sx-head"><div><p class="sx-kicker">Assistance</p><h1>Assistance</h1><p class="muted">Contacter l’équipe, signaler un contenu et suivre chaque dossier.</p></div><a class="btn btn-primary btn-lg" href="{{ route('support.new') }}">Contacter le support</a></div>
    <div class="sv-info"><x-fc.icon name="info" :size="18" /><span>Vous pouvez contacter l’équipe, signaler un contenu (depuis sa page ou un message) et suivre ici chaque dossier. <b>Un litige s’ouvre depuis la commande concernée.</b> L’équipe répond dans l’ordre d’arrivée ; aucun délai de réponse n’est garanti à ce stade.</span></div>
    @php($tabs = ['tous' => 'Tous', 'en-cours' => 'En cours', 'clos' => 'Clos'])
    <nav class="sv-tabs" aria-label="Filtrer les dossiers" style="margin-top:16px">@foreach($tabs as $k => $label)<a class="sv-tab {{ $filter === $k ? 'on' : '' }}" href="{{ route('support.index', array_filter(['statut' => $k === 'tous' ? null : $k, 'espace' => request('espace') === 'freelance' ? 'freelance' : null])) }}" @if($filter === $k) aria-current="page" @endif>{{ $label }} <span class="n">{{ $counts[$k] }}</span></a>@endforeach</nav>
    @if(count($cases))
      <div class="as-list">@foreach($cases as $c)
        <a class="as-case" href="{{ route('support.show', $c['reference']) }}"><span class="as-ci" aria-hidden="true"><x-fc.icon :name="in_array($c['kindKey'], ['support', 'follow_up'], true) ? 'message' : 'flag'" :size="22" /></span>
          <div><b>{{ $c['subject'] }}</b><small>{{ $c['reference'] }} · {{ $c['kind'] }}@if($c['role'] === 'counterparty') · vous êtes l’autre partie @endif</small></div>
          <span class="as-st"><span class="badge {{ $c['statusKey'] === 'awaiting_requester' ? 'tone-warning' : ($c['live'] ? 'tone-info' : 'tone-neutral') }}">{{ $c['status'] }}</span><small>Mis à jour {{ $c['when'] }}</small></span><x-fc.icon name="arrow-right" :size="18" /></a>
      @endforeach</div>
    @else
      <div class="ed-card empty" style="margin-top:16px;justify-items:center;text-align:center"><span class="ico-lg"><x-fc.icon name="info" :size="26" /></span><p style="font-weight:600">{{ $counts['tous'] > 0 ? 'Aucun dossier dans cette catégorie.' : 'Aucun dossier pour l’instant.' }}</p><p class="muted" style="max-width:36em">Une question, un problème sur une commande ou un contenu à signaler ? Écrivez-nous : vous suivrez la réponse ici.</p><a class="btn btn-primary" href="{{ route('support.new') }}">Contacter le support</a></div>
    @endif
  </div>
</x-layouts.account>

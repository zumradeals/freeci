<x-layouts.account title="Assistance" :space="request('espace') === 'freelance' ? 'freelancer' : 'client'">
  <header class="page-head"><div class="row-top"><div><p class="eyebrow">Assistance</p><h1 class="t-h1">Assistance</h1><p class="lead">Contacter l’équipe, signaler un contenu et suivre chaque dossier.</p></div><a class="btn btn-primary btn-lg" href="{{ route('support.new') }}">Contacter le support</a></div></header>
  <p class="note-line"><x-fc.icon name="info" :size="16" /><span>Vous pouvez contacter l’équipe, signaler un contenu (depuis sa page ou un message) et suivre ici chaque dossier. <strong>Un litige s’ouvre depuis la commande concernée.</strong> L’équipe répond dans l’ordre d’arrivée ; aucun délai de réponse n’est garanti à ce stade.</span></p>
  @if(count($cases))
    <section class="card panel" aria-labelledby="h-list" style="margin-top:16px"><div class="card-head"><h2 class="t-h2" id="h-list">Vos dossiers</h2><span class="meta-r">{{ count($cases) }} au total</span></div><div class="order-list">@foreach($cases as $c)
      <article class="order-card"><h2 class="ttl"><a class="stretch" href="{{ route('support.show', $c['reference']) }}">{{ $c['subject'] }}</a></h2>
        <p class="am">{{ $c['reference'] }}</p>
        <p class="mt">{{ $c['kind'] }}@if($c['role'] === 'counterparty') · vous êtes l’autre partie @endif</p>
        <div class="st"><span class="badge {{ $c['live'] ? 'tone-info' : 'tone-neutral' }}">{{ $c['status'] }}</span><span class="muted small">Mis à jour {{ $c['when'] }}</span></div><span class="chev" aria-hidden="true"><x-fc.icon name="arrow-right" :size="20" /></span></article>
    @endforeach</div></section>
  @else
    <div class="card empty" style="margin-top:16px"><span class="ico-lg"><x-fc.icon name="info" :size="26" /></span><p style="font-weight:600">Aucun dossier pour l’instant.</p><p class="muted" style="max-width:36em">Une question, un problème sur une commande ou un contenu à signaler ? Écrivez-nous : vous suivrez la réponse ici.</p><a class="btn btn-primary" href="{{ route('support.new') }}">Contacter le support</a></div>
  @endif
</x-layouts.account>

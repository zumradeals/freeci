<x-layouts.account title="Espace client" space="client">
  <header class="page-head">
    <div class="row-top"><div><p class="eyebrow">Espace client</p><h1 class="t-h1">Bienvenue, {{ $user->firstName() }}</h1></div>
      <a class="btn btn-secondary btn-lg" href="{{ route('services.index') }}">Parcourir les services</a></div>
    <div class="row">@if($user->hasRole('freelance'))<div class="segmented" role="group" aria-label="Espace actif" style="max-width:260px"><a href="{{ route('account.dashboard') }}" aria-current="true">Client</a><a href="{{ route('freelance.dashboard') }}">Freelance</a></div>@else<a class="btn btn-link" href="{{ route('freelance.activate') }}">Vous proposez des services ? Activer l’espace freelance</a>@endif
      @if($user->is_demo)<p class="demo-time"><x-fc.icon name="flag" :size="18" />Compte de démonstration</p>@endif</div>
  </header>
  <div class="cols">
    <div class="stack-lg">
      <section aria-labelledby="h-todo"><div class="sect-head"><h2 class="t-h2" id="h-todo">À faire maintenant</h2>@if(count($o['tasks']) > 1)<span class="small muted">Échéances d’abord</span>@endif</div>
        @if(count($o['tasks']))
          <div class="tasks">@foreach($o['tasks'] as $t)<x-orders.task :t="$t" />@endforeach</div>
        @else
          <div class="action-card"><div class="empty"><span class="ico-lg"><x-fc.icon name="check-circle" :size="26" /></span>
            <div><h3 class="t-h3">Rien à faire pour l’instant.</h3><p class="muted" style="margin-top:4px;max-width:36em">Dès qu’une action sera attendue de votre part, elle apparaîtra ici avec son échéance.</p></div>
            <div class="row"><a class="btn btn-primary btn-lg" href="{{ route('services.index') }}">Parcourir les services</a></div></div></div>
        @endif
      </section>
      <section aria-labelledby="h-orders"><div class="sect-head"><h2 class="t-h2" id="h-orders">Commandes</h2>@if(count($o['orders']) > 3)<a class="btn btn-link" href="{{ route('orders.index') }}">Toutes <x-fc.icon name="arrow-right" :size="18" /></a>@endif</div>
        @if(count($o['orders']))
          <div class="order-list">@foreach(array_slice($o['orders'], 0, 5) as $c)<x-orders.card :c="$c" />@endforeach</div>
        @else
          <div class="card empty"><span class="ico-lg"><x-fc.icon name="inbox" :size="26" /></span><p style="font-weight:600">Aucune commande pour l’instant.</p><p class="muted" style="max-width:36em">Choisissez un service dans le catalogue et envoyez votre demande : elle apparaîtra ici avec son état.</p></div>
        @endif
      </section>
    </div>
    <div class="stack-lg">
      <section aria-labelledby="h-info"><div class="sect-head"><h2 class="t-h2" id="h-info">Autres informations</h2></div>
        <div class="card"><ol class="empty-steps">
          <li><div><b>Choisissez un service</b><span>Prix, délai et corrections sont annoncés sur chaque service.</span></div></li>
          <li><div><b>Décrivez votre besoin</b><span>Le freelance accepte ou refuse avec un motif.</span></div></li>
          <li><div><b>Réglez la commande</b><span>Bientôt : le paiement n’est pas encore ouvert. Le travail ne commence qu’après paiement confirmé et brief complet.</span></div></li></ol></div></section>
      <section aria-labelledby="h-stats"><h2 class="sr-only" id="h-stats">Chiffres de synthèse</h2>
        <div class="stats-row">@foreach($o['counts'] as $label => $n)<a href="{{ route('orders.index') }}"><b>{{ $n }}</b><span>{{ $label }}</span></a>@endforeach</div></section>
    </div>
  </div>
</x-layouts.account>

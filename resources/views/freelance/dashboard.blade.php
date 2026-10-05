<x-layouts.account title="Espace freelance" space="freelancer">
  <header class="page-head">
    <div class="row-top"><div><p class="eyebrow">Espace freelance</p><h1 class="t-h1">{{ $profile['display_name'] ?? $user->name }}</h1></div></div>
    <div class="row"><div class="segmented" role="group" aria-label="Espace actif" style="max-width:260px"><a href="{{ route('account.dashboard') }}">Client</a><a href="{{ route('freelance.dashboard') }}" aria-current="true">Freelance</a></div>
      @if($user->is_demo)<p class="demo-time"><x-fc.icon name="flag" :size="18" />Compte de démonstration</p>@endif</div>
  </header>
  <div class="cols">
    <div class="stack-lg">
      <section aria-labelledby="h-todo"><div class="sect-head"><h2 class="t-h2" id="h-todo">À faire maintenant</h2>@if(count($o['tasks']) > 1)<span class="small muted">Échéances d’abord</span>@endif</div>
        @if(count($o['tasks']))
          <div class="tasks">@foreach($o['tasks'] as $t)<x-orders.task :t="$t" />@endforeach</div>
        @else
          <div class="action-card"><div class="empty"><span class="ico-lg"><x-fc.icon name="check-circle" :size="26" /></span>
            <div><h3 class="t-h3">Aucune demande à traiter.</h3><p class="muted" style="margin-top:4px;max-width:36em">Les demandes reçues sur vos services apparaîtront ici, avec le délai pour répondre.</p></div></div></div>
        @endif
      </section>
      <section aria-labelledby="h-wait"><div class="sect-head"><h2 class="t-h2" id="h-wait">En attente du client</h2></div>
        @if(count($o['waiting']))
          <div class="order-list">@foreach($o['waiting'] as $c)<x-orders.card :c="$c" />@endforeach</div>
        @else
          <div class="card empty"><span class="ico-lg"><x-fc.icon name="inbox" :size="26" /></span><p style="font-weight:600">Aucune commande en attente du client.</p><p class="muted" style="max-width:36em">Paiement, brief, examen d’une livraison ou réponse à un report : ce que le client doit faire apparaît ici.</p></div>
        @endif
      </section>
    </div>
    <div class="stack-lg">
      <section aria-labelledby="h-svc"><div class="sect-head"><h2 class="t-h2" id="h-svc">Vos services</h2></div>
        @if(count($services))
          <div class="card"><ul class="stack-sm">@foreach($services as $s)<li class="row" style="justify-content:space-between;gap:8px 16px"><span style="min-width:0">@if($s['published'])<a href="{{ route('services.show', $s['slug']) }}">{{ $s['title'] }}</a>@else{{ $s['title'] }}@endif<br><small class="muted">{{ $s['status'] }}</small></span><x-fc.money :amount="$s['price']" /></li>@endforeach</ul></div>
        @else
          <div class="card empty"><span class="ico-lg"><x-fc.icon name="briefcase" :size="26" /></span><p style="font-weight:600">Aucun service à votre nom.</p><p class="muted">La création et la publication de services arrivent dans un prochain lot.</p></div>
        @endif
      </section>
      <section aria-labelledby="h-stats"><h2 class="sr-only" id="h-stats">Chiffres de synthèse</h2>
        <div class="stats-row">@foreach($o['counts'] as $label => $n)<a href="{{ route('freelance.orders') }}"><b>{{ $n }}</b><span>{{ $label }}</span></a>@endforeach</div></section>
    </div>
  </div>
</x-layouts.account>

<x-layouts.account title="Espace client">
  <header class="page-head">
    <div class="row-top"><div><p class="eyebrow">Espace client</p><h1 class="t-h1">Bienvenue, {{ $user->firstName() }}</h1></div></div>
    @if($user->is_demo)<div class="row"><p class="demo-time"><x-fc.icon name="flag" :size="18" />Compte de démonstration</p></div>@endif
  </header>
  <div class="cols">
    <div class="stack-lg">
      <section aria-labelledby="h-todo"><div class="sect-head"><h2 class="t-h2" id="h-todo">À faire maintenant</h2></div>
        <div class="action-card"><div class="empty"><span class="ico-lg"><x-fc.icon name="check-circle" :size="26" /></span>
          <div><h3 class="t-h3">Rien à faire pour l’instant.</h3><p class="muted" style="margin-top:4px;max-width:36em">Les commandes ne sont pas encore ouvertes dans cette version. Dès qu’une action sera attendue de votre part, elle apparaîtra ici avec son échéance.</p></div>
          <div class="row"><a class="btn btn-primary btn-lg" href="{{ route('services.index') }}">Parcourir les services</a></div></div></div></section>
      <section aria-labelledby="h-orders"><div class="sect-head"><h2 class="t-h2" id="h-orders">Commandes et missions</h2></div>
        <div class="card empty"><span class="ico-lg"><x-fc.icon name="inbox" :size="26" /></span><p style="font-weight:600">Aucune commande ni mission pour l’instant.</p><p class="muted" style="max-width:36em">Elles apparaîtront ici lorsque les commandes et les missions seront ouvertes.</p></div></section>
    </div>
    <div class="stack-lg">
      <section aria-labelledby="h-start"><div class="sect-head"><h2 class="t-h2" id="h-start">Pour démarrer</h2></div>
        <div class="card"><ol class="empty-steps">
          <li><div><b>Parcourez le catalogue</b><span>Prix, délai et corrections sont annoncés sur chaque service.</span></div></li>
          <li><div><b>Convenez d’un accord clair</b><span>Bientôt : prix, périmètre, délai et corrections figés avant paiement.</span></div></li>
          <li><div><b>Suivez la commande ici</b><span>Bientôt : livraisons, échéances, situation financière.</span></div></li></ol></div></section>
    </div>
  </div>
</x-layouts.account>

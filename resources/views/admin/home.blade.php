<x-layouts.admin title="Administration">
  <header class="page-head"><div class="row-top"><div><p class="eyebrow">Administration</p><h1 class="t-h1">Tableau de bord</h1></div></div></header>
  <div class="stack-lg">
    <section aria-labelledby="h-kpi"><h2 class="t-h2" id="h-kpi">À traiter</h2>
      <div class="kpis" style="margin-top:12px">
        <a class="kpi" href="{{ route('admin.moderation') }}"><span class="n">{{ $d['servicesInReview'] }}</span><span class="l">service{{ $d['servicesInReview'] > 1 ? 's' : '' }} à modérer</span></a>
        <a class="kpi" href="{{ route('admin.moderation', ['onglet' => 'missions']) }}"><span class="n">{{ $d['missionsInReview'] }}</span><span class="l">mission{{ $d['missionsInReview'] > 1 ? 's' : '' }} à modérer</span></a>
        <a class="kpi" href="{{ route('admin.users') }}"><span class="n">{{ $d['users'] }}</span><span class="l">utilisateur{{ $d['users'] > 1 ? 's' : '' }}</span></a>
        <a class="kpi" href="{{ route('admin.users', ['statut' => 'suspended']) }}"><span class="n">{{ $d['suspended'] }}</span><span class="l">compte{{ $d['suspended'] > 1 ? 's' : '' }} suspendu{{ $d['suspended'] > 1 ? 's' : '' }}</span></a>
        <a class="kpi" href="{{ route('admin.audit.security') }}"><span class="n">{{ $d['securityAlerts'] }}</span><span class="l">alerte{{ $d['securityAlerts'] > 1 ? 's' : '' }} de sécurité (24 h)</span></a>
      </div></section>
    <section aria-labelledby="h-fu"><h2 class="t-h2" id="h-fu">Besoins de suivi enregistrés ({{ $d['followUps'] }})</h2>
      <div class="notice tone-info" style="margin-top:12px"><x-fc.icon name="info" /><p><strong>Ce ne sont pas des litiges pris en charge.</strong> Il s’agit de simples enregistrements (silence d’un client, désaccord signalé) : aucune procédure de litige n’existe encore, aucune action n’est déclenchée et vous n’avez pas accès au contenu des commandes depuis cet écran.</p></div>
      @if(count($d['followUpList']))
        <div class="table-wrap" style="margin-top:12px"><table class="list"><caption class="sr-only">Derniers besoins de suivi</caption><thead><tr><th scope="col">Commande</th><th scope="col">Nature</th><th scope="col">Enregistré</th></tr></thead><tbody>
          @foreach($d['followUpList'] as $f)<tr><td data-label="Commande">{{ $f['reference'] }}</td><td data-label="Nature">{{ $f['kind'] }}</td><td data-label="Enregistré">{{ $f['when'] }}</td></tr>@endforeach
        </tbody></table></div>
        @if($d['followUps'] > count($d['followUpList']))<p class="muted small" style="margin-top:8px">{{ count($d['followUpList']) }} derniers affichés sur {{ $d['followUps'] }}.</p>@endif
      @else
        <div class="card empty" style="margin-top:12px"><span class="ico-lg"><x-fc.icon name="check-circle" :size="26" /></span><p style="font-weight:600">Aucun besoin de suivi enregistré.</p></div>
      @endif
    </section>
    <section aria-labelledby="h-rec"><div class="sect-head"><h2 class="t-h2" id="h-rec">Dernières actions administratives</h2><a class="btn btn-link" href="{{ route('admin.audit') }}">Journal complet <x-fc.icon name="arrow-right" :size="18" /></a></div>
      @if(count($d['recent']))
        <div class="table-wrap"><table class="list"><caption class="sr-only">Dernières actions</caption><thead><tr><th scope="col">Date</th><th scope="col">Auteur</th><th scope="col">Action</th><th scope="col">Cible</th><th scope="col">Résultat</th></tr></thead><tbody>
          @foreach($d['recent'] as $a)<tr><td data-label="Date">{{ $a['when'] }}</td><td data-label="Auteur">{{ $a['actor'] }}</td><td data-label="Action">{{ $a['action'] }}</td><td data-label="Cible">{{ $a['target'] }}</td><td data-label="Résultat">{{ $a['result'] === 'done' ? 'Effectuée' : 'Refusée' }}</td></tr>@endforeach
        </tbody></table></div>
      @else
        <div class="card empty"><span class="ico-lg"><x-fc.icon name="clipboard" :size="26" /></span><p style="font-weight:600">Aucune action enregistrée.</p></div>
      @endif
    </section>
    <p class="muted small">Connecté : {{ auth()->user()->email }}. Aucun accès aux conversations, briefs ou fichiers privés n’est ouvert aux administrateurs depuis cet espace.</p>
  </div>
</x-layouts.admin>

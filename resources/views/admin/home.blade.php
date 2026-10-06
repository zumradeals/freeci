<x-layouts.admin title="Administration">
  <header class="page-head"><div class="row-top"><div><p class="eyebrow">Administration</p><h1 class="t-h1">Tableau de bord</h1><p class="lead">Ce qui demande une action maintenant, puis l’activité récente.</p></div></div></header>
  <div class="page-body">
    <section class="card panel" aria-labelledby="h-pay"><div class="card-head"><h2 class="t-h2" id="h-pay">Paiements Genius Pay</h2></div>
      <div class="notice {{ $d['paymentMode']['live'] ? 'tone-warning' : 'tone-info' }}"><x-fc.icon name="{{ $d['paymentMode']['live'] ? 'warn' : 'info' }}" /><p><strong>Mode {{ $d['paymentMode']['live'] ? 'LIVE (argent réel)' : 'TEST (sandbox, aucun argent réel)' }}.</strong> {{ $d['paymentMode']['message'] }}
        Commandes : {{ $d['ordersByEnv']['live'] ?? 0 }} réelle(s) · {{ $d['ordersByEnv']['test'] ?? 0 }} de test · {{ $d['ordersByEnv']['legacy'] ?? 0 }} ancienne(s). Encaissé réel : {{ \App\Shared\Money::xof($d['confirmedLiveXof'])->formatted() }} FCFA · encaissé de test (non réel, exclu des totaux) : {{ \App\Shared\Money::xof($d['confirmedTestXof'])->formatted() }} FCFA.</p></div></section>
    <section class="card panel" aria-labelledby="h-kpi"><div class="card-head"><h2 class="t-h2" id="h-kpi">À traiter</h2><span class="meta-r">Chaque tuile ouvre la liste concernée</span></div>
      <div class="metrics">
        <a class="metric" href="{{ route('admin.moderation') }}"><span class="metric-label">service{{ $d['servicesInReview'] > 1 ? 's' : '' }} à modérer</span><span class="metric-value">{{ $d['servicesInReview'] }}</span></a>
        <a class="metric" href="{{ route('admin.moderation', ['onglet' => 'missions']) }}"><span class="metric-label">mission{{ $d['missionsInReview'] > 1 ? 's' : '' }} à modérer</span><span class="metric-value">{{ $d['missionsInReview'] }}</span></a>
        <a class="metric" href="{{ route('admin.users') }}"><span class="metric-label">utilisateur{{ $d['users'] > 1 ? 's' : '' }}</span><span class="metric-value">{{ $d['users'] }}</span></a>
        <a class="metric" href="{{ route('admin.users', ['statut' => 'suspended']) }}"><span class="metric-label">compte{{ $d['suspended'] > 1 ? 's' : '' }} suspendu{{ $d['suspended'] > 1 ? 's' : '' }}</span><span class="metric-value">{{ $d['suspended'] }}</span></a>
        <a class="metric" href="{{ route('admin.payments') }}"><span class="metric-label">paiement{{ $d['paymentsToReview'] > 1 ? 's' : '' }} à vérifier</span><span class="metric-value">{{ $d['paymentsToReview'] }}</span></a>
        <a class="metric" href="{{ route('admin.finance') }}"><span class="metric-label">opération{{ $d['financeToApprove'] > 1 ? 's' : '' }} financière{{ $d['financeToApprove'] > 1 ? 's' : '' }} à approuver</span><span class="metric-value">{{ $d['financeToApprove'] }}</span></a>
        <a class="metric" href="{{ route('admin.finance') }}"><span class="metric-label">opération{{ $d['financeToVerify'] > 1 ? 's' : '' }} à vérifier</span><span class="metric-value">{{ $d['financeToVerify'] }}</span></a>
        <a class="metric" href="{{ route('admin.support') }}"><span class="metric-label">dossier{{ $d['cases']['unassigned'] > 1 ? 's' : '' }} d’assistance non affecté{{ $d['cases']['unassigned'] > 1 ? 's' : '' }}</span><span class="metric-value">{{ $d['cases']['unassigned'] }}</span></a>
        <a class="metric" href="{{ route('admin.support', ['onglet' => 'financier']) }}"><span class="metric-label">à traiter financièrement</span><span class="metric-value">{{ $d['cases']['toProcess'] }}</span></a>
        <a class="metric" href="{{ route('admin.audit.security') }}"><span class="metric-label">alerte{{ $d['securityAlerts'] > 1 ? 's' : '' }} de sécurité (24 h)</span><span class="metric-value">{{ $d['securityAlerts'] }}</span></a>
      </div></section>
    <section class="card panel" aria-labelledby="h-fu"><div class="card-head"><h2 class="t-h2" id="h-fu">Besoins de suivi enregistrés</h2><span class="meta-r">{{ $d['followUps'] }} au total</span></div>
      <div class="notice tone-info"><x-fc.icon name="info" /><p><strong>Ce ne sont pas des litiges pris en charge.</strong> Il s’agit de simples enregistrements (silence d’un client, désaccord signalé) : les litiges ne s’ouvrent que par les parties. Ouvrir un dossier depuis un besoin de suivi est une action explicite (onglet « Besoins de suivi » de l’assistance) ; elle ne modifie pas la commande.</p></div>
      @if(count($d['followUpList']))
        <div class="table-wrap"><table class="list"><caption class="sr-only">Derniers besoins de suivi</caption><thead><tr><th scope="col">Commande</th><th scope="col">Nature</th><th scope="col">Enregistré</th></tr></thead><tbody>
          @foreach($d['followUpList'] as $f)<tr><td data-label="Commande">{{ $f['reference'] }}</td><td data-label="Nature">{{ $f['kind'] }}</td><td data-label="Enregistré">{{ $f['when'] }}</td></tr>@endforeach
        </tbody></table></div>
        @if($d['followUps'] > count($d['followUpList']))<p class="muted small" style="margin-top:8px">{{ count($d['followUpList']) }} derniers affichés sur {{ $d['followUps'] }}.</p>@endif
      @else
        <p class="muted empty-note">Aucun besoin de suivi enregistré.</p>
      @endif
    </section>
    <section class="card panel" aria-labelledby="h-rec"><div class="card-head"><h2 class="t-h2" id="h-rec">Dernières actions administratives</h2><a class="btn btn-link" href="{{ route('admin.audit') }}">Journal complet <x-fc.icon name="arrow-right" :size="18" /></a></div>
      @if(count($d['recent']))
        <div class="table-wrap"><table class="list"><caption class="sr-only">Dernières actions</caption><thead><tr><th scope="col">Date</th><th scope="col">Auteur</th><th scope="col">Action</th><th scope="col">Cible</th><th scope="col">Résultat</th></tr></thead><tbody>
          @foreach($d['recent'] as $a)<tr><td data-label="Date">{{ $a['when'] }}</td><td data-label="Auteur">{{ $a['actor'] }}</td><td data-label="Action">{{ $a['action'] }}</td><td data-label="Cible">{{ $a['target'] }}</td><td data-label="Résultat">{{ $a['result'] === 'done' ? 'Effectuée' : 'Refusée' }}</td></tr>@endforeach
        </tbody></table></div>
      @else
        <p class="muted empty-note">Aucune action enregistrée.</p>
      @endif
    </section>
    <p class="muted small">Connecté : {{ auth()->user()->email }}. Aucun accès aux conversations, briefs ou fichiers privés n’est ouvert aux administrateurs depuis cet espace.</p>
  </div>
</x-layouts.admin>

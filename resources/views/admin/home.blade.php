@php
  $live = $d['paymentMode']['live'];
  $plural = fn (int $n, string $one, string $many) => $n > 1 ? $many : $one;
  $row = fn (string $label, int $n, string $url) => compact('label', 'n', 'url');
  $groups = [
    ['shield', 'Modération', [
      $row('Service'.($d['servicesInReview'] > 1 ? 's' : '').' à modérer', $d['servicesInReview'], route('admin.moderation')),
      $row('Mission'.($d['missionsInReview'] > 1 ? 's' : '').' à modérer', $d['missionsInReview'], route('admin.moderation', ['onglet' => 'missions'])),
    ]],
    ['card', 'Paiements et finances', [
      $row('Paiement'.($d['paymentsToReview'] > 1 ? 's' : '').' à vérifier', $d['paymentsToReview'], route('admin.payments')),
      $row('Opération'.($d['financeToApprove'] > 1 ? 's' : '').' financière'.($d['financeToApprove'] > 1 ? 's' : '').' à approuver', $d['financeToApprove'], route('admin.finance')),
      $row('Opération'.($d['financeToVerify'] > 1 ? 's' : '').' à vérifier', $d['financeToVerify'], route('admin.finance')),
    ]],
    ['message', 'Assistance', [
      $row('Dossier'.($d['cases']['unassigned'] > 1 ? 's' : '').' d’assistance non affecté'.($d['cases']['unassigned'] > 1 ? 's' : ''), $d['cases']['unassigned'], route('admin.support')),
      $row('À traiter financièrement', $d['cases']['toProcess'], route('admin.support', ['onglet' => 'financier'])),
    ]],
    ['lock', 'Sécurité et comptes', [
      $row('Alerte'.($d['securityAlerts'] > 1 ? 's' : '').' de sécurité (24 h)', $d['securityAlerts'], route('admin.audit.security')),
      $row('Compte'.($d['suspended'] > 1 ? 's' : '').' suspendu'.($d['suspended'] > 1 ? 's' : ''), $d['suspended'], route('admin.users', ['statut' => 'suspended'])),
    ]],
  ];
@endphp
<x-layouts.admin title="Administration">
  <div class="page-body">
    <div class="sx-head"><div><p class="sx-kicker">Administration</p><h1>Tableau de bord</h1><p class="muted">Ce qui demande une action maintenant, puis l’activité récente.</p></div></div>
    <div style="display:grid;gap:22px">
      <section class="pl-mode {{ $live ? 'live' : '' }}" aria-labelledby="h-pay"><div class="pl-badge"><b>{{ $live ? 'LIVE' : 'TEST' }}</b><small>{{ $live ? 'argent réel' : 'sandbox' }}</small></div>
        <div style="display:grid;gap:12px"><div><b id="h-pay">Paiements Genius Pay en mode {{ $live ? 'LIVE' : 'TEST' }}</b><p class="muted" style="margin:2px 0 0"><strong>{{ $live ? 'LIVE (argent réel)' : 'TEST (sandbox, aucun argent réel)' }}.</strong> {{ $d['paymentMode']['message'] }}</p></div>
          <div class="pl-stats">
            <div class="pl-stat"><small>Commandes réelles</small><b>{{ $d['ordersByEnv']['live'] ?? 0 }}</b></div>
            <div class="pl-stat dim"><small>Commandes de test</small><b>{{ $d['ordersByEnv']['test'] ?? 0 }}</b></div>
            <div class="pl-stat dim"><small>Anciennes commandes</small><b>{{ $d['ordersByEnv']['legacy'] ?? 0 }}</b></div>
            <div class="pl-stat"><small>Encaissé réel</small><b>{{ \App\Shared\Money::xof($d['confirmedLiveXof'])->formatted() }} FCFA</b></div>
          </div>
          <p class="pl-note">Encaissé de test (non réel, exclu des totaux) : {{ \App\Shared\Money::xof($d['confirmedTestXof'])->formatted() }} FCFA.</p></div></section>

      <section class="pl-sec" aria-labelledby="h-kpi"><div class="pl-sech"><h2 id="h-kpi">À traiter</h2><span>Chaque ligne ouvre la liste concernée</span></div>
        <div class="pl-groups">@foreach($groups as [$icon, $title, $rows])
          <section class="pl-g {{ collect($rows)->contains(fn ($r) => $r['n'] > 0) ? 'alert' : '' }}" aria-label="{{ $title }}"><div class="pl-gh"><span class="pl-gi" aria-hidden="true"><x-fc.icon :name="$icon" :size="20" /></span><h3>{{ $title }}</h3></div>
            @foreach($rows as $r)<a class="pl-row {{ $r['n'] > 0 ? 'hot' : '' }}" href="{{ $r['url'] }}"><span>{{ $r['label'] }}</span><span class="n">{{ $r['n'] }}</span><x-fc.icon name="arrow-right" :size="16" /></a>@endforeach</section>
        @endforeach</div>
        <p class="pl-ctx"><span>Utilisateurs : <b>{{ $d['users'] }}</b></span><span>Adresses non vérifiées : <b>{{ $d['unverified'] }}</b></span></p></section>

      <div class="pl-two">
        <section class="ed-card" aria-labelledby="h-fu"><div class="pl-sech"><h2 id="h-fu">Besoins de suivi enregistrés</h2><span>{{ $d['followUps'] }} au total</span></div>
          <div class="rq-info"><x-fc.icon name="info" :size="18" /><span><b>Ce ne sont pas des litiges pris en charge.</b> Il s’agit de simples enregistrements (silence d’un client, désaccord signalé) : les litiges ne s’ouvrent que par les parties. Ouvrir un dossier depuis un besoin de suivi est une action explicite (onglet « Besoins de suivi » de l’assistance) ; elle ne modifie pas la commande.</span></div>
          @if(count($d['followUpList']))
            <div class="table-wrap"><table class="list"><caption class="sr-only">Derniers besoins de suivi</caption><thead><tr><th scope="col">Commande</th><th scope="col">Nature</th><th scope="col">Enregistré</th></tr></thead><tbody>
              @foreach($d['followUpList'] as $f)<tr><td data-label="Commande"><b>{{ $f['reference'] }}</b></td><td data-label="Nature">{{ $f['kind'] }}</td><td data-label="Enregistré">{{ $f['when'] }}</td></tr>@endforeach
            </tbody></table></div>
            @if($d['followUps'] > count($d['followUpList']))<p class="muted small">{{ count($d['followUpList']) }} derniers affichés sur {{ $d['followUps'] }}.</p>@endif
          @else
            <p class="muted empty-note">Aucun besoin de suivi enregistré.</p>
          @endif
        </section>
        <section class="ed-card" aria-labelledby="h-rec"><div class="pl-sech"><h2 id="h-rec">Dernières actions administratives</h2><a class="btn btn-link" href="{{ route('admin.audit') }}">Journal complet <x-fc.icon name="arrow-right" :size="16" /></a></div>
          @if(count($d['recent']))
            <ul class="pl-tl">@foreach($d['recent'] as $a)<li><i class="{{ $a['result'] === 'done' ? '' : 'no' }}" aria-hidden="true"></i><div><b>{{ $a['action'] }}</b> · {{ $a['target'] }}<small>{{ $a['when'] }} · {{ $a['actor'] }} · {{ $a['result'] === 'done' ? 'Effectuée' : 'Refusée' }}</small></div></li>@endforeach</ul>
          @else
            <p class="muted empty-note">Aucune action enregistrée.</p>
          @endif
        </section>
      </div>
      <p class="muted small">Connecté : {{ auth()->user()->email }}. Aucun accès aux conversations, briefs ou fichiers privés n’est ouvert aux administrateurs depuis cet espace.</p>
    </div>
  </div>
</x-layouts.admin>

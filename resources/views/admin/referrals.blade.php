<x-layouts.admin title="Campagnes et parrainages">
  <div class="page-body">
    <nav class="sd-crumbs" aria-label="Fil d’Ariane"><a href="{{ route('admin.home') }}">Administration</a> › <span aria-current="page">Campagnes et parrainages</span></nav>
    <div class="sx-head"><div><p class="sx-kicker">Administration</p><h1>Campagnes et parrainages</h1><p class="muted">Commissions offertes : codes promotionnels, attributions et décomptes. Le prix payé par le client ne change jamais.</p></div></div>
    @if(session('error'))<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>{{ session('error') }}</p></div>@endif
    @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>{{ $errors->first() }}</p></div>@endif
    <div class="rf-g4">
      <div class="rf-c"><small>Attributions actives</small><b>{{ $d['stats']['active'] }}</b></div>
      <div class="rf-c"><small>Commandes offertes restantes</small><b>{{ $d['stats']['remaining'] }}</b></div>
      <div class="rf-c"><small>Commissions offertes (réel, 30 j)</small><b>{{ $d['stats']['offered'] }}</b></div>
      <div class="rf-c"><small>Parrainages qualifiés</small><b>{{ $d['stats']['qualified'] }}</b></div>
    </div>
    <div class="ac-grid" style="margin-top:14px"><div class="ac-main">
      <section class="ed-card" aria-labelledby="h-camp"><h2 id="h-camp" style="margin:0">Campagnes</h2>
        @if($d['campaigns'])
        <div style="overflow-x:auto"><table class="rf-tb"><thead><tr><th>Code</th><th>Offre</th><th>Période</th><th>Utilisations</th><th>État</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody>
          @foreach($d['campaigns'] as $c)
          <tr><td><b>{{ $c['code'] }}</b>@if($c['note'])<br><small class="muted">{{ $c['note'] }}</small>@endif</td><td>{{ $c['rate'] }} % · {{ $c['orders'] }} commande{{ $c['orders'] > 1 ? 's' : '' }}</td><td>{{ $c['fromLabel'] }} → {{ $c['toLabel'] }}</td><td>{{ $c['uses'] }} / {{ $c['max'] }}</td><td><span class="badge tone-{{ $c['tone'] }}">{{ $c['label'] }}</span></td>
            <td><form method="post" action="{{ route('admin.referrals.state', [$c['id'], $c['suspended'] ? 'reprendre' : 'suspendre']) }}" data-once>@csrf<button class="btn btn-link" type="submit">{{ $c['suspended'] ? 'Réactiver' : 'Suspendre' }}<span class="sr-only"> {{ $c['code'] }}</span></button></form></td></tr>
          @if($c['editable'])<tr><td colspan="6"><details class="rv-act"><summary><span>Modifier {{ $c['code'] }} (jamais utilisé)</span><x-fc.icon name="chev-down" :size="16" /></summary>
            <form class="in" method="post" action="{{ route('admin.referrals.update', $c['id']) }}" data-once>@csrf @include('admin._campaign-fields', ['v' => ['code' => $c['code'], 'rate' => $c['rateRaw'], 'free_orders' => $c['orders'], 'starts_on' => $c['from'], 'ends_on' => $c['to'], 'max_uses' => $c['max'], 'note' => $c['note']], 'p' => 'e'.$loop->index.'-'])<button class="btn btn-secondary" type="submit">Enregistrer</button></form></details></td></tr>@endif
          @endforeach</tbody></table></div>
        @else<p class="muted" style="margin:0">Aucune campagne. Créez un premier code ci-dessous.</p>@endif
      </section>
      <section class="ed-card" aria-labelledby="h-new"><h2 id="h-new" style="margin:0">Nouvelle campagne</h2>
        <form method="post" action="{{ route('admin.referrals.create') }}" class="stack" data-once>@csrf @include('admin._campaign-fields', ['v' => ['code' => old('code'), 'rate' => old('rate', '0'), 'free_orders' => old('free_orders', 3), 'starts_on' => old('starts_on'), 'ends_on' => old('ends_on'), 'max_uses' => old('max_uses', 100), 'note' => old('note')], 'p' => 'n-'])<div><button class="btn btn-primary" type="submit">Créer la campagne</button></div></form>
        <p class="muted small" style="margin:0">Le taux appliqué doit être inférieur à la commission normale ({{ $d['settings']['base'] }} %). Un code est utilisable une fois par compte, à l’activation ou sur le profil freelance. Les confirmations d’identité récentes sont exigées.</p></section>
      <section class="ed-card" aria-labelledby="h-grants"><h2 id="h-grants" style="margin:0">Attributions récentes</h2>
        @if($d['grants'])
        <div style="overflow-x:auto"><table class="rf-tb"><thead><tr><th>Bénéficiaire</th><th>Origine</th><th>Solde</th><th>Date</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody>
          @foreach($d['grants'] as $g)
          <tr><td>{{ $g['who'] }}</td><td>{{ $g['origin'] }} · {{ $g['rate'] }} %</td><td>{{ $g['balance'] }} / {{ $g['total'] }}@unless($g['active']) <span class="badge tone-neutral">Révoquée</span>@endunless</td><td>{{ $g['when'] }}</td>
            <td>@if($g['active'])<details class="rv-act"><summary><span>Révoquer</span><x-fc.icon name="chev-down" :size="16" /></summary><form class="in" method="post" action="{{ route('admin.referrals.revoke', $g['id']) }}" data-once>@csrf<div class="field"><label for="rv-{{ $g['id'] }}">Motif (10 à 1000 caractères, visible du bénéficiaire)</label><textarea class="textarea" id="rv-{{ $g['id'] }}" name="reason" rows="2" required minlength="10" maxlength="1000"></textarea></div><button class="btn btn-secondary" type="submit">Révoquer l’attribution</button></form></details>@else<span class="muted small">{{ $g['reason'] }}</span>@endif</td></tr>
          @endforeach</tbody></table></div>
        <p class="muted small" style="margin:0">Révoquer demande un motif et la confirmation récente d’identité, et est inscrit au journal d’audit. Les accords déjà figés ne changent jamais.</p>
        @else<p class="muted" style="margin:0">Aucune attribution pour le moment.</p>@endif
      </section>
    </div>
    <aside class="ac-side"><section class="ed-ck"><h3>Réglages du parrainage</h3><dl class="rf-dl"><div><dt>Parrainage</dt><dd><span class="badge tone-{{ $d['settings']['enabled'] ? 'success' : 'neutral' }}">{{ $d['settings']['enabled'] ? 'Actif' : 'Désactivé' }}</span></dd></div>
      <div><dt>Commandes offertes (parrain et filleul)</dt><dd>{{ $d['settings']['orders'] }}</dd></div><div><dt>Taux appliqué</dt><dd>{{ $d['settings']['rate'] }} %</dd></div><div><dt>Filleuls qualifiés par parrain</dt><dd>{{ $d['settings']['max'] }}</dd></div></dl>
      <p class="muted small">Modifiés dans <a href="{{ route('admin.settings') }}">Paramètres › Parrainage</a> ; un changement ne touche que l’avenir.</p></section></aside></div>
  </div>
</x-layouts.admin>

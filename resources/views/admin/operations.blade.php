<x-layouts.admin title="Exploitation">
  <header class="page-head"><div class="row-top"><div><p class="eyebrow">Administration</p><h1 class="t-h1">Exploitation et préparation à l’ouverture</h1><p class="lead">État des tâches, de la file, des courriels, des fichiers et des sauvegardes.</p></div></div></header>
  <div class="page-body">
  <div class="notice tone-info"><x-fc.icon name="info" /><p>Lecture seule. Cette liste est <strong>informative</strong> : elle n’active ni ne bloque le paiement, et n’empêche pas l’usage normal du bac à sable. Aucun secret n’est affiché.</p></div>

  <section class="card panel"><div class="card-head"><h2 class="t-h2">Préparation à l’ouverture</h2></div>
    <ul class="stack-sm" style="list-style:none;padding:0">@foreach($d['readiness'] as $r)
      <li><span class="badge {{ ['ok' => 'tone-success', 'todo' => 'tone-warning', 'info' => 'tone-neutral'][$r['state']] }}">{{ ['ok' => 'Remplie', 'todo' => 'À traiter', 'info' => 'Information'][$r['state']] }}</span> <strong>{{ $r['label'] }}</strong>@if(isset($r['link']) && $r['state'] === 'todo') · <a href="{{ $r['link'] }}">Compléter</a>@endif<br><span class="muted small">{{ $r['detail'] }}</span></li>@endforeach</ul>
    <p class="muted small">Règle validée : un seul administrateur peut préparer, confirmer et exécuter une opération financière (aucun second approbateur).</p></section>

  @php($demoLeft = $d['demo']['services'] + $d['demo']['missions'] + $d['demo']['profiles'] + $d['demo']['users'])
  <section class="card panel" aria-labelledby="h-demo"><div class="card-head"><h2 class="t-h2" id="h-demo">Données de démonstration</h2><span class="badge {{ $demoLeft === 0 ? 'tone-success' : 'tone-warning' }}">{{ $demoLeft === 0 ? 'Aucune' : $demoLeft.' élément(s)' }}</span></div>
    @if($demoLeft === 0)<p class="muted">Aucune donnée de démonstration n’est présente.</p>@else
    <p>Présents : <strong>{{ $d['demo']['services'] }}</strong> service(s), <strong>{{ $d['demo']['missions'] }}</strong> mission(s), <strong>{{ $d['demo']['profiles'] }}</strong> profil(s), <strong>{{ $d['demo']['users'] }}</strong> compte(s)@if($d['demo']['orders']) ; {{ $d['demo']['orders'] }} commande(s) de démonstration sont conservées (historique)@endif.</p>
    <p class="muted small">Seules les données marquées « démonstration » sont concernées : jamais un compte, un service ou une commande réels, ni les catégories. Ce qui est référencé par une commande, un message ou une proposition est <strong>conservé</strong> ; un service conservé est archivé (il quitte le catalogue public). L’opération est définitive : faites une sauvegarde avant.</p>
    <form method="post" action="{{ route('admin.operations.purge-demo') }}" class="stack-sm" data-once>@csrf
      <div class="field"><label for="dm-r">Motif (10 caractères minimum)</label><textarea class="input" id="dm-r" name="reason" rows="2" minlength="10" maxlength="1000" required></textarea></div>
      <div class="field"><label for="dm-p">Pour confirmer, saisissez : RETIRER LA DEMO</label><input class="input" id="dm-p" name="phrase" autocomplete="off" maxlength="40" required></div>
      <label class="check"><input type="checkbox" name="confirm" value="1" required> <span>J’ai fait une sauvegarde et je veux retirer ces données définitivement.</span></label>
      <div><button class="btn btn-primary" type="submit">Retirer les données de démonstration</button></div></form>@endif</section>
  <section class="card panel"><div class="card-head"><h2 class="t-h2">Tâches planifiées</h2></div>
    <div class="table-wrap"><table class="table"><thead><tr><th>Tâche</th><th>État</th><th>Dernier succès</th><th>Dernier échec</th></tr></thead><tbody>@foreach($d['tasks'] as $t)
      <tr><td>{{ $t['label'] }}</td><td><span class="badge {{ ['ok' => 'tone-success', 'never' => 'tone-neutral', 'late' => 'tone-warning', 'failed' => 'tone-error'][$t['state']] }}">{{ ['ok' => 'À jour', 'never' => 'Jamais exécutée', 'late' => 'En retard', 'failed' => 'Dernier passage en échec'][$t['state']] }}</span></td><td>{{ $t['ok'] ?? '—' }}</td><td>{{ $t['failed'] ?? '—' }}</td></tr>@endforeach</tbody></table></div>
    <p class="muted small">« Jamais exécutée » : le cron <code>schedule:run</code> n’a pas encore tourné depuis cette mise à jour, ou n’est pas installé.</p></section>

  <section class="card panel"><div class="card-head"><h2 class="t-h2">File d’attente et courriels</h2></div>
    <ul><li>File : <strong>{{ $d['queue']['driver'] }}</strong>{{ $d['queue']['driver'] === 'sync' ? ' (synchrone : aucun traitement différé)' : '' }} · en attente : {{ $d['queue']['pending'] }}@if($d['queue']['oldestSeconds'] !== null) · plus ancienne : {{ intdiv($d['queue']['oldestSeconds'], 60) }} min @endif · échouées : {{ $d['queue']['failed'] }}</li>
    @if($d['queue']['stuck'])<li><span class="badge tone-warning">Attention</span> Des tâches attendent depuis plus de 10 minutes : le worker est peut-être arrêté.</li>@endif
    <li>Courrier réel : <strong>{{ $d['mail']['configured'] ? 'configuré' : 'non configuré' }}</strong> · courriels en échec : {{ $d['mail']['failed'] }} · en attente depuis plus de 15 min : {{ $d['mail']['stale'] }}</li></ul></section>

  <section class="card panel"><div class="card-head"><h2 class="t-h2">Fichiers</h2></div>
    <p>Service de contrôle : <strong>{{ $d['files']['scanner'] }}</strong> — {{ $d['files']['operational'] ? 'opérationnel' : 'indisponible (dépôt de fichiers désactivé)' }} · en attente d’analyse : {{ $d['files']['waiting'] }} (dont {{ $d['files']['stale'] }} depuis plus de 15 min, {{ $d['files']['errors'] }} avec erreur d’analyse) · refusés : {{ $d['files']['rejected'] }}</p></section>

  <section class="card panel"><div class="card-head"><h2 class="t-h2">Paiements et rapprochements</h2></div>
    <p>Mode : <strong>{{ $d['payments']['mode'] }}</strong>{{ $d['payments']['live'] ? ' (réel)' : ' (aucun argent réel)' }} · nouveaux paiements {{ $d['payments']['creationOpen'] ? 'ouverts' : 'fermés' }} · cas de rapprochement ouverts : <a href="{{ route('admin.payments') }}">{{ $d['payments']['cases'] }}</a> · opérations « à vérifier » : <a href="{{ route('admin.finance') }}">{{ $d['payments']['toVerify'] }}</a> · paiements ouverts depuis plus de 2 h : {{ $d['payments']['pendingOld'] }}</p></section>

  <section class="card panel"><div class="card-head"><h2 class="t-h2">Sauvegardes</h2></div>
    @if(! $d['backup']['known'])<p class="muted">État inconnu : le fichier d’état des sauvegardes est absent ou illisible pour l’application (voir docs/22).</p>@else
    <ul><li>Dernière sauvegarde locale : {{ $d['backup']['local']?->diffForHumans() ?? 'inconnue' }}</li><li>Copie hors VPS : {{ ['ok' => 'confirmée '.($d['backup']['offsiteAt']?->diffForHumans() ?? ''), 'disabled' => 'NON ACTIVÉE', 'unknown' => 'inconnue'][$d['backup']['offsite']] ?? 'EN ÉCHEC ('.$d['backup']['offsite'].')' }}</li><li>Dernier test de restauration : {{ $d['backup']['restoreAt']?->diffForHumans() ?? 'aucun' }}</li></ul>@endif</section>
  </div>
</x-layouts.admin>

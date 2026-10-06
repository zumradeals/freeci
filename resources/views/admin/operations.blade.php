<x-layouts.admin title="Exploitation">
  <header class="page-head"><div class="row-top"><div><p class="eyebrow">Administration</p><h1 class="t-h1">Exploitation et préparation à l’ouverture</h1><p class="lead">État des tâches, de la file, des courriels, des fichiers et des sauvegardes.</p></div></div></header>
  <div class="page-body">
  <div class="notice tone-info"><x-fc.icon name="info" /><p>Lecture seule. Cette liste est <strong>informative</strong> : elle n’active ni ne bloque le paiement, et n’empêche pas l’usage normal du bac à sable. Aucun secret n’est affiché.</p></div>

  <section class="card panel"><div class="card-head"><h2 class="t-h2">Préparation à l’ouverture</h2></div>
    <ul class="stack-sm" style="list-style:none;padding:0">@foreach($d['readiness'] as $r)
      <li><span class="badge {{ ['ok' => 'tone-success', 'todo' => 'tone-warning', 'info' => 'tone-neutral'][$r['state']] }}">{{ ['ok' => 'Remplie', 'todo' => 'À traiter', 'info' => 'Information'][$r['state']] }}</span> <strong>{{ $r['label'] }}</strong><br><span class="muted small">{{ $r['detail'] }}</span></li>@endforeach</ul>
    <p class="muted small">Règle validée : un seul administrateur peut préparer, confirmer et exécuter une opération financière (aucun second approbateur).</p></section>

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

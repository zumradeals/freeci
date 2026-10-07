<x-layouts.admin title="Dossier d’assistance">
  <div class="page-body">
    <header class="page-head"><div class="row-top"><div><p class="eyebrow"><a href="{{ route('admin.support') }}">← Assistance</a></p><h1 class="t-h1">{{ $m['subject'] }}</h1>
      <p class="muted">{{ $m['reference'] }} · {{ $m['kindLabel'] }} · ouvert le {{ $m['opened'] }}@if($m['priority'] === 'high') · <strong>prioritaire</strong>@endif</p></div><span class="badge {{ $m['live'] ? 'tone-info' : 'tone-neutral' }}">{{ $m['statusLabel'] }}</span></div></header>

    @if($m['conflict'])<div class="notice tone-error"><x-fc.icon name="error" /><p><strong>Vous êtes partie prenante de ce dossier</strong> (demandeur, autre partie, partie de la commande ou auteur du contenu) : vous ne pouvez ni l’affecter, ni l’ouvrir, ni le traiter. Un autre membre du personnel doit s’en charger.</p></div>@endif

    <div class="cols">
    <div class="stack-lg">
      <section class="card stack" aria-labelledby="h-meta"><h2 class="t-h3" id="h-meta">Dossier</h2>
        <dl class="stack-sm">
          <div><dt class="muted small">Demandeur</dt><dd>{{ $m['requester'] }}</dd></div>
          @if($m['counterparty'])<div><dt class="muted small">Autre partie</dt><dd>{{ $m['counterparty'] }}</dd></div>@endif
          @if($m['orderReference'])<div><dt class="muted small">Commande</dt><dd>{{ $m['orderReference'] }} · état : {{ $m['orderState'] }}</dd></div>@endif
          @if($m['target'] && $m['targetType'] !== 'order')<div><dt class="muted small">Contenu visé</dt><dd>{{ $m['target'] }}</dd></div>@endif
          <div><dt class="muted small">Affecté à</dt><dd>{{ $m['assignee'] ?? 'Non affecté' }}</dd></div>
          @if($m['origin'] === 'follow_up')<div><dt class="muted small">Origine</dt><dd>Besoin de suivi enregistré par FreeCI — <strong>pas un litige</strong></dd></div>@endif
          @if($m['hold'])<div><dt class="muted small">Blocage interne des reversements</dt><dd>{{ $m['hold']->released_at ? 'Levé' : 'Actif' }} <span class="muted small">(interne à FreeCI ; pas un blocage chez un prestataire de paiement)</span></dd></div>@endif
        </dl>
      </section>

      @if($m['decision'])
        <section class="card stack"><h2 class="t-h3">Décision</h2>
          <p><strong>{{ $m['decision']['outcome'] }}</strong> · {{ $m['decision']['when'] }}</p><p style="white-space:pre-line">{{ $m['decision']['reason'] }}</p>
          @if($m['decision']['to'])<p><strong>Effet sur la commande :</strong> {{ $m['decision']['from'] }} → {{ $m['decision']['to'] }}</p>@endif
          @if($m['decision']['financial'])<div class="notice tone-warning"><x-fc.icon name="clock" /><p><strong>À traiter financièrement : {{ $m['decision']['financial'] }}.</strong> @if($m['decision']['note'])<br>{{ $m['decision']['note'] }}@endif<br>Aucune opération financière n’est exécutée par cette décision.</p></div>@endif</section>
      @endif

      @if($content)
        <section class="card stack" aria-labelledby="h-ech"><h2 class="t-h3" id="h-ech">Échanges et notes internes</h2>
          <p class="muted small">Accès ouvert pour : « {{ $content['accessReason'] }} ». Vos consultations sont journalisées ; l’accès prend fin avec l’affectation.</p>
          @if($content['snapshot'])<div class="notice tone-info"><x-fc.icon name="info" /><p><strong>Message signalé (copie de ce seul message) :</strong><br><span style="white-space:pre-line;overflow-wrap:anywhere">{{ $content['snapshot'] }}</span></p></div>@endif
          @foreach($content['thread'] as $t)
            <article class="msg {{ $t['visibility'] === 'internal' ? 'tone-warning' : '' }}"><p class="small"><strong>{{ $t['who'] }}</strong> · {{ $t['when'] }} · <span class="badge {{ $t['visibility'] === 'internal' ? 'tone-warning' : 'tone-neutral' }}">{{ ['requester' => 'visible du demandeur', 'parties' => 'partagé entre les parties', 'internal' => 'note interne (équipe seule)'][$t['visibility']] }}</span></p>
              <p style="white-space:pre-line;overflow-wrap:anywhere">{{ $t['body'] }}</p>@foreach($t['files'] as $f)<p class="small">📎 @if($f['url'])<a href="{{ $f['url'] }}">{{ $f['name'] }}</a>@else{{ $f['name'] }} (en contrôle, non téléchargeable)@endif</p>@endforeach</article>
          @endforeach
          @if($m['live'])
          <form method="post" action="{{ route('admin.support.reply', $m['reference']) }}" enctype="multipart/form-data" class="stack">@csrf<input type="hidden" name="client_key" value="{{ $key }}">
            <div class="field"><label for="f-body">Message</label><textarea class="textarea" id="f-body" name="body" required maxlength="4000"></textarea></div>
            <div class="field"><label for="f-vis">Canal</label><select class="select" id="f-vis" name="visibility"><option value="requester">Au demandeur</option>@if($m['disputeLike'])<option value="parties">Aux deux parties</option>@endif<option value="internal">Note interne (équipe seule)</option></select></div>
            <div class="field"><label for="f-file">Pièce (facultatif)</label><input class="input" id="f-file" type="file" name="file"></div>
            <button class="btn btn-secondary" type="submit" data-once>Envoyer</button></form>
          @endif
        </section>

        @if($content['order'])
        @php($o = $content['order'])
        <section class="card stack" aria-labelledby="h-ord"><h2 class="t-h3" id="h-ord">Commande {{ $o['reference'] }} — extrait pour trancher</h2>
          <p class="muted small">Accord figé, livraisons, corrections et historique. La conversation de la commande n’est pas accessible.</p>
          <div><h3 class="t-h3" style="font-size:1rem">Accord : {{ $o['agreement']['title'] }}</h3><p>{{ $o['agreement']['price'] }} · {{ $o['agreement']['deliveryDays'] }} j · {{ $o['agreement']['revisions'] }} correction(s) incluse(s) · accepté le {{ $o['agreement']['acceptedAt'] }}</p>
            <p style="white-space:pre-line">{{ $o['agreement']['scope'] }}</p>
            @if(count($o['agreement']['deliverables']))<p><strong>Livrables :</strong> {{ implode(' ; ', $o['agreement']['deliverables']) }}</p>@endif
            @if(count($o['agreement']['exclusions']))<p><strong>Exclusions :</strong> {{ implode(' ; ', $o['agreement']['exclusions']) }}</p>@endif
            @if($o['dates']['due'])<p><strong>Échéance :</strong> {{ $o['dates']['due'] }} <span class="muted small">(non modifiée par le dossier)</span></p>@endif</div>
          @if($o['brief'])<div><h3 class="t-h3" style="font-size:1rem">Brief du client</h3>@foreach($o['brief']['answers'] as $a)<p><strong>{{ $a['label'] }}</strong> : {{ $a['answer'] }}</p>@endforeach @if($o['brief']['notes'])<p>{{ $o['brief']['notes'] }}</p>@endif
            @foreach($o['brief']['files'] as $f)<p class="small">📎 @if($f['url'])<a href="{{ $f['url'] }}">{{ $f['name'] }}</a>@else{{ $f['name'] }} (non téléchargeable)@endif</p>@endforeach</div>@endif
          <div><h3 class="t-h3" style="font-size:1rem">Livraisons soumises</h3>@forelse($o['deliveries'] as $d)<p><strong>v{{ $d['version'] }}</strong> · {{ $d['when'] }}@if($d['message'])<br><span style="white-space:pre-line">{{ $d['message'] }}</span>@endif @foreach($d['files'] as $f)<br>📎 @if($f['url'])<a href="{{ $f['url'] }}">{{ $f['name'] }}</a>@else{{ $f['name'] }}@endif @endforeach</p>@empty<p class="muted">Aucune livraison soumise.</p>@endforelse</div>
          <div><h3 class="t-h3" style="font-size:1rem">Corrections demandées</h3>@forelse($o['corrections'] as $r)<p><strong>n° {{ $r['number'] }}</strong> · {{ $r['when'] }}<br>{{ $r['reason'] }}</p>@empty<p class="muted">Aucune.</p>@endforelse</div>
          @if(count($o['extensions']))<div><h3 class="t-h3" style="font-size:1rem">Reports</h3>@foreach($o['extensions'] as $r)<p>{{ $r['state'] }} · proposé : {{ $r['proposed'] }} — {{ $r['reason'] }}</p>@endforeach</div>@endif
          <div><h3 class="t-h3" style="font-size:1rem">Historique</h3><ul class="hist">@foreach($o['events'] as $e)<li>{{ $e['what'] }} · <span class="muted">{{ $e['when'] }}</span></li>@endforeach</ul></div>
        </section>
        @endif
      @elseif($m['isAssignee'] && ! $m['conflict'])
        <section class="card stack"><h2 class="t-h3">Ouvrir le dossier</h2>
          <p>Le contenu des échanges, les pièces et l’extrait de la commande ne sont accessibles qu’après ouverture, avec un motif. Votre mot de passe peut être redemandé.</p>
          <form method="post" action="{{ route('admin.support.open', $m['reference']) }}" class="stack">@csrf<div class="field"><label for="f-reason">Motif de l’ouverture (10 à 500 caractères)</label><textarea class="textarea" id="f-reason" name="reason" required minlength="10" maxlength="500">{{ old('reason') }}</textarea></div>
            <button class="btn btn-primary" type="submit" data-once>Ouvrir le dossier (accès journalisé)</button></form></section>
      @else
        <section class="card"><p class="muted">Le contenu de ce dossier n’est visible que de la personne à qui il est affecté, après ouverture motivée.</p></section>
      @endif

      @if($content && $m['live'] && $m['disputeLike'] && $m['isAssignee'])
        <section class="card stack" aria-labelledby="h-dc"><h2 class="t-h2" id="h-dc">Décision</h2>
          <div class="notice tone-info"><x-fc.icon name="info" /><p>La décision est <strong>humaine, motivée et unique</strong>. Elle sépare : <strong>(1) la décision</strong>, <strong>(2) son effet sur la commande</strong> et <strong>(3) la suite financière « à traiter »</strong>, qui n’est jamais exécutée ici. FreeCI ne calcule aucune répartition : si une répartition est nécessaire, indiquez-la en toutes lettres.</p></div>
          <form method="post" action="{{ route('admin.support.decide', $m['reference']) }}" class="stack" onsubmit="return confirm('Rendre cette décision ? Elle est définitive et notifiée aux parties.')">@csrf
            <input type="hidden" name="operation_key" value="{{ $key }}"><input type="hidden" name="version" value="{{ $m['version'] }}">
            <div class="field"><label for="f-out">Décision</label><select class="select" id="f-out" name="outcome" required>@if($m['kind'] === 'claim')<option value="answered">Réclamation examinée</option>@else
              <option value="continue">Poursuite de la prestation (retour à l’état d’avant)</option><option value="validate_delivery">Résolution du désaccord : livraison jugée conforme → commande clôturée</option><option value="cancel">Annulation motivée de la commande</option>@endif</select></div>
            <div class="field"><label for="f-fin">Suite financière à traiter (choix explicite)</label><select class="select" id="f-fin" name="financial_need" required><option value="" disabled selected>— choisir —</option>@foreach($financial as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
              <p class="hint">« Remboursement » et « répartition » restent « à traiter financièrement » : rien n’est exécuté. « Reversement à autoriser » lève le blocage interne. « Aucune suite » aussi.</p></div>
            <div class="field"><label for="f-fn">Précision financière (obligatoire pour une répartition ; 500 caractères max.)</label><textarea class="textarea" id="f-fn" name="financial_note" maxlength="500"></textarea></div>
            <div class="field"><label for="f-rs">Motif de la décision (20 à 2000 caractères, communiqué aux parties)</label><textarea class="textarea" id="f-rs" name="reason" required minlength="20" maxlength="2000"></textarea></div>
            <label class="check"><input type="checkbox" name="confirm" value="1" required> Je confirme cette décision.</label>
            <button class="btn btn-danger btn-lg" type="submit" data-once>Rendre la décision</button></form></section>
      @endif
    </div>

    <div class="stack">
      <section class="card stack"><h2 class="t-h3">Traitement</h2>
        @if($m['live'] && ! $m['conflict'])
          @if($m['unassigned'])<form method="post" action="{{ route('admin.support.claim', $m['reference']) }}">@csrf<button class="btn btn-primary" type="submit" data-once>M’affecter ce dossier</button></form>@endif
          @if($m['isAssignee'])
            <form method="post" action="{{ route('admin.support.status', $m['reference']) }}" class="stack">@csrf<input type="hidden" name="version" value="{{ $m['version'] }}">
              <div class="field"><label for="f-st">État</label><select class="select" id="f-st" name="status">@foreach(array_slice($statuses, 0, 4, true) as $k => $l)<option value="{{ $k }}" @selected($m['status'] === $k)>{{ $l }}</option>@endforeach</select></div>
              <div class="field"><label for="f-pr">Priorité</label><select class="select" id="f-pr" name="priority"><option value="normal" @selected($m['priority'] === 'normal')>Normale</option><option value="high" @selected($m['priority'] === 'high')>Prioritaire</option></select></div>
              <button class="btn btn-secondary" type="submit" data-once>Mettre à jour</button></form>
            <form method="post" action="{{ route('admin.support.release', $m['reference']) }}" onsubmit="return confirm('Libérer ce dossier ? Votre accès à son contenu prend fin.')">@csrf<button class="btn btn-link" type="submit">Libérer (fin d’affectation)</button></form>
          @endif
        @elseif($m['conflict'])<p class="muted">Aucune action possible : vous êtes partie prenante.</p>@else<p class="muted">Dossier terminé.</p>@endif
        @if($isAdmin && $m['live'] && count($staff))
          <form method="post" action="{{ route('admin.support.assign', $m['reference']) }}" class="stack">@csrf<div class="field"><label for="f-as">Affecter à (administrateur)</label><select class="select" id="f-as" name="assignee">@foreach($staff as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></div><button class="btn btn-secondary" type="submit" data-once>Affecter</button></form>
        @endif
      </section>
      @if($m['isAssignee'] && $m['live'] && ! $m['disputeLike'] && ! $m['conflict'])
        <section class="card stack"><h2 class="t-h3">Clore le dossier</h2>
          <form method="post" action="{{ route('admin.support.close', $m['reference']) }}" class="stack" onsubmit="return confirm('Clore ce dossier ?')">@csrf<div class="field"><label for="f-cl">Conclusion (10 à 1000 caractères){{ $m['requester'] !== 'Équipe (besoin de suivi)' ? ' — envoyée au demandeur' : ' — note interne' }}</label><textarea class="textarea" id="f-cl" name="note" required minlength="10" maxlength="1000"></textarea></div><button class="btn btn-secondary" type="submit" data-once>Clore</button></form></section>
      @endif
      <section class="card stack"><h2 class="t-h3">Historique</h2>
        @if(count($m['history']))<ul class="hist">@foreach($m['history'] as $h)<li>{{ $h['what'] }}<br><span class="muted small">{{ $h['when'] }} · {{ $h['who'] }}</span></li>@endforeach</ul>@else<p class="muted">Aucun événement.</p>@endif</section>
    </div></div>
  </div>
</x-layouts.admin>

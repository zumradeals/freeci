@php($m = fn (int $n) => \App\Shared\Money::xof($n)->formatted().' FCFA')
@php($op = $d['op'])
<x-layouts.admin :title="'Opération '.$op->reference">
  <nav class="crumbs" aria-label="Fil d’Ariane"><a href="{{ route('admin.finance') }}">Finances</a><span class="sep" aria-hidden="true">›</span><span aria-current="page">{{ $op->reference }}</span></nav>
  <h1 class="t-h1">{{ $d['kind'] }} {{ $op->reference }} <span class="badge tone-{{ $d['tone'] }}">{{ $d['label'] }}</span> @if($d['simulated'])<span class="tag-demo">Test — aucun argent réel</span>@endif</h1>
  @if($op->state === 'to_verify')<div class="notice tone-warning"><x-fc.icon name="warn" /><p><strong>Résultat incertain ({{ $op->uncertain_reason }}).</strong> Rien n’est renvoyé automatiquement. Le paiement doit d’abord être relu chez le prestataire : « Reprendre » ou « Constater l’échec » ne sont possibles que si le paiement y est encore « complété ».</p></div>@endif
  @if($op->state === 'requested' || $op->state === 'approved')<div class="notice tone-info"><x-fc.icon name="info" /><p>Les fonds sont <strong>réservés</strong> dans le registre : ils ne peuvent servir à aucune autre opération. Rien n’est encore {{ $op->kind === 'refund' ? 'remboursé' : 'versé' }}.</p></div>@endif

  <section class="card" style="margin-top:16px"><h2 class="t-h2">Opération</h2>
    <dl class="defs"><div><dt>Commande</dt><dd>{{ $op->order_reference }} <span class="muted small">(client {{ $d['client'] }} · freelance {{ $d['freelancer'] }})</span></dd></div>
      <div><dt>Montant</dt><dd>{{ $m((int) $op->amount_xof) }} <span class="muted small">({{ $op->scope === 'total' ? 'total' : ($op->scope === 'partial' ? 'partiel' : 'part du freelance') }})</span></dd></div>
      @if($op->kind === 'payout')<div><dt>Décomposition</dt><dd>base {{ $m((int) $op->base_xof) }} − commission {{ $m((int) $op->commission_xof) }} ({{ $op->commission_bp / 100 }} %, taux figé dans l’accord) = {{ $m((int) $op->amount_xof) }}</dd></div>
      <div><dt>Destination</dt><dd>{{ $d['beneficiary'] ? (['mobile_money' => 'Mobile money', 'bank_transfer' => 'Virement bancaire', 'other' => 'Autre'][$d['beneficiary']->method].' · '.$d['beneficiary']->holder_name.' · '.$d['beneficiary']->status) : '—' }}</dd></div>@endif
      <div><dt>Environnement</dt><dd>{{ $op->environment }}</dd></div>
      <div><dt>Demandée par</dt><dd>{{ $d['requester'] }} — {{ $op->requested_reason }}</dd></div>
      <div><dt>Exécution</dt><dd>@if($op->execution_mode === 'api')Par API Genius Pay (réf. {{ $op->provider_reference }}@if($op->provider_refund_reference) · {{ $op->provider_refund_reference }}@endif)@elseif($op->execution_mode === 'manual')Manuelle par {{ $d['executor'] }} — référence externe <strong>{{ $op->external_reference }}</strong> — justificatif : {{ $op->proof_note }}@else Pas encore exécutée @endif</dd></div>
      <div><dt>Approbations</dt><dd>{{ $d['approved'] }} reçue(s) / {{ $d['required'] }} exigée(s) @if($d['required'] > 1)<span class="muted small">(seuil de double validation : proposition non validée)</span>@endif</dd></div></dl></section>

  <section class="card" style="margin-top:16px"><h2 class="t-h2">Actions</h2>
    @if($d['isParty'])<p class="muted">Vous êtes partie à cette commande : aucune action possible.</p>@endif
    @if($d['canApprove'])
      <form method="post" action="{{ route('admin.finance.act', [$op->reference, 'approuver']) }}" class="stack-sm">@csrf<label>Note d’approbation (vous approuvez l’action exacte ci-dessus)<input name="note" minlength="10" maxlength="500" required></label><button class="btn btn-primary" type="submit">Approuver</button></form>
      <form method="post" action="{{ route('admin.finance.act', [$op->reference, 'refuser']) }}" class="stack-sm" style="margin-top:8px">@csrf<label>Motif du refus<input name="note" minlength="10" maxlength="500" required></label><button class="btn btn-secondary" type="submit">Refuser (libère la réservation)</button></form>
    @elseif($op->state === 'requested' && ! $d['isParty'])<p class="muted">{{ $d['alreadyDecided'] ? 'Vous avez déjà pris position.' : 'Vous êtes le demandeur : une autre personne doit approuver.' }}</p>@endif
    @if($d['canExecuteApi'])
      <form method="post" action="{{ route('admin.finance.act', [$op->reference, 'executer-api']) }}">@csrf<button class="btn btn-primary" type="submit">Exécuter par API Genius Pay (remboursement total)</button> <span class="muted small">Envoi unique : en cas de délai dépassé, rien n’est renvoyé automatiquement.</span></form>
    @elseif($op->state === 'approved' && $op->kind === 'refund' && $op->scope === 'partial')<p class="muted small">Remboursement partiel : non exécuté par API (règles des remboursements successifs et idempotence non établies par la documentation). Effectuez-le chez le prestataire, puis enregistrez-le manuellement ci-dessous.</p>@endif
    @if($op->state === 'approved' && $op->kind === 'payout')<p class="muted small">Exécution du reversement via Genius Pay : <strong>indisponible</strong> (aucune API de reversement documentée). Effectuez le transfert hors FreeCI puis enregistrez-le ci-dessous.</p>@endif
    @if($d['canRecord'])
      <form method="post" action="{{ route('admin.finance.act', [$op->reference, 'enregistrer']) }}" class="stack-sm" style="margin-top:12px">@csrf
        <h3 class="t-h3">Enregistrer une exécution manuelle déjà effectuée</h3>
        <label>Référence externe du transfert<input name="external_reference" maxlength="80" required></label>
        <label>Justificatif (description : relevé, capture, numéro de transaction…)<textarea name="proof_note" rows="2" minlength="10" maxlength="500" required></textarea></label>
        <label>Retapez le montant ({{ (int) $op->amount_xof }})<input type="number" name="amount_confirm" required></label>
        <label class="check"><input type="checkbox" name="confirm" value="1" required> <span>Je confirme que ce transfert a réellement été effectué{{ $d['simulated'] ? ' (opération de test : aucun argent réel)' : '' }}.</span></label>
        <button class="btn btn-primary" type="submit">Enregistrer comme effectué</button></form>
    @endif
    @if($d['canVerify'])
      <form method="post" action="{{ route('admin.finance.act', [$op->reference, 'reprendre']) }}" class="stack-sm" style="margin-top:12px">@csrf<label>Note (lecture du paiement chez le prestataire effectuée à l’instant)<input name="note" minlength="10" maxlength="500" required></label><button class="btn btn-secondary" type="submit">Vérifier puis reprendre l’envoi</button></form>
      <form method="post" action="{{ route('admin.finance.act', [$op->reference, 'marquer-echec']) }}" class="stack-sm" style="margin-top:8px">@csrf<label>Note<input name="note" minlength="10" maxlength="500" required></label><button class="btn btn-secondary" type="submit">Vérifier puis constater l’échec (libère la réservation)</button></form>
    @endif
    @if($d['canCancel'])
      <form method="post" action="{{ route('admin.finance.act', [$op->reference, 'annuler']) }}" class="stack-sm" style="margin-top:12px">@csrf<label>Motif d’annulation<input name="note" minlength="10" maxlength="500" required></label><button class="btn btn-secondary" type="submit">Annuler l’opération (libère la réservation)</button></form>
    @endif</section>

  <section class="card" style="margin-top:16px"><h2 class="t-h2">Registre</h2>
    @forelse($d['batches'] as $b)<p><strong>{{ $b['kind'] }}</strong> @if($b['corrects'])<span class="badge tone-neutral">écriture correctrice</span>@endif <span class="muted small">{{ $b['when'] }}{{ $b['memo'] ? ' — '.$b['memo'] : '' }}</span></p>
      <ul class="small">@foreach($b['lines'] as $l)<li>{{ $l['account'] }} : {{ $l['amount'] > 0 ? '+' : '' }}{{ \App\Shared\Money::xof($l['amount'])->formatted() }}</li>@endforeach</ul>
    @empty<p class="muted">Aucune écriture.</p>@endforelse</section>

  <section class="card" style="margin-top:16px"><h2 class="t-h2">Approbations et historique</h2>
    @foreach($d['approvals'] as $a)<p>{{ $a['when'] }} — {{ $a['who'] }} : <strong>{{ $a['decision'] === 'approved' ? 'approuvé' : 'refusé' }}</strong> — {{ $a['note'] }}</p>@endforeach
    <ol class="timeline">@foreach($d['events'] as $e)<li>{{ $e['when'] }} — {{ $e['who'] }} : {{ $e['note'] ?? $e['type'] }}</li>@endforeach</ol></section>
</x-layouts.admin>

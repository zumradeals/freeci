@php($m = fn (int $n) => \App\Shared\Money::xof($n)->formatted().' FCFA')
@php($op = $d['op'])
@php($destLabels = ['mobile_money' => 'Mobile money', 'bank_transfer' => 'Virement bancaire', 'other' => 'Autre'])
<x-layouts.admin :title="'Opération '.$op->reference">
  <nav class="crumbs" aria-label="Fil d’Ariane"><a href="{{ route('admin.finance') }}">Finances</a><span class="sep" aria-hidden="true">›</span><span aria-current="page">{{ $op->reference }}</span></nav>
  <h1 class="t-h1">{{ $d['kind'] }} {{ $op->reference }} <span class="badge tone-{{ $d['tone'] }}">{{ $d['label'] }}</span> @if($d['simulated'])<span class="tag-demo">Test — aucun argent réel</span>@endif</h1>

  @if($d['conflictBlocked'])<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p><strong>Conflit d’intérêts.</strong> Vous êtes client ou freelance de cette commande <strong>réelle</strong> : vous ne pouvez ni la rembourser, ni la verser, ni la confirmer vous-même. Ce blocage ne concerne que vos propres commandes réelles ; il n’empêche pas la gestion des commandes des autres utilisateurs.</p></div>
  @elseif($d['sandboxParty'])<div class="notice tone-warning"><x-fc.icon name="flag" /><p><strong>Vous êtes partie à cette commande de TEST (sandbox).</strong> Le parcours financier vous est ouvert pour tester avec votre propre compte ; aucun argent réel n’est en jeu et chaque action est auditée.</p></div>@endif
  @if($op->state === 'to_verify')<div class="notice tone-warning"><x-fc.icon name="warn" /><p><strong>Résultat incertain ({{ $op->uncertain_reason }}).</strong> Rien n’est renvoyé automatiquement et les fonds restent réservés. Un statut lu chez le prestataire (« remboursé » ou « complété ») ne prouve ni le montant remboursé, ni son rattachement à cette opération, ni un échec : constatez le résultat vous-même dans le tableau de bord du prestataire et documentez-le ci-dessous.</p></div>@endif
  @if($op->state === 'requested' || $op->state === 'approved')<div class="notice tone-info"><x-fc.icon name="info" /><p>Les fonds sont <strong>réservés</strong> dans le registre : ils ne peuvent servir à aucune autre opération. Rien n’est encore {{ $op->kind === 'refund' ? 'remboursé' : 'versé' }}.</p></div>@endif

  <section class="card" style="margin-top:16px"><h2 class="t-h2">Récapitulatif</h2>
    <dl class="defs"><div><dt>Montant</dt><dd><strong>{{ $m((int) $op->amount_xof) }}</strong> <span class="muted small">({{ $op->scope === 'total' ? 'total' : ($op->scope === 'partial' ? 'partiel' : 'part du freelance') }})</span></dd></div>
      <div><dt>Environnement</dt><dd>@if($d['simulated'])<strong>TEST (sandbox)</strong> — aucun argent réel @else <strong>RÉEL (live)</strong> — argent réel @endif</dd></div>
      @if($op->kind === 'refund')<div><dt>Bénéficiaire</dt><dd>{{ $d['client'] }} (client, remboursement du paiement {{ $d['payment']->provider_reference ?? '' }} chez Genius Pay)</dd></div>
      @else<div><dt>Bénéficiaire</dt><dd>{{ $d['freelancer'] }} — {{ $d['beneficiary'] ? ($destLabels[$d['beneficiary']->method].' · '.$d['beneficiary']->holder_name.' · '.$d['beneficiary']->status) : '—' }}</dd></div>
      <div><dt>Décomposition</dt><dd>base {{ $m((int) $op->base_xof) }} − commission {{ $m((int) $op->commission_xof) }} ({{ $op->commission_bp / 100 }} %, taux figé dans l’accord, <em>proposition provisoire non approuvée</em>) = {{ $m((int) $op->amount_xof) }}</dd></div>@endif
      <div><dt>Commande</dt><dd>{{ $op->order_reference }} <span class="muted small">(client {{ $d['client'] }} · freelance {{ $d['freelancer'] }})</span></dd></div>
      <div><dt>Préparée par</dt><dd>{{ $d['requester'] }} — {{ $op->requested_reason }}</dd></div>
      <div><dt>Confirmation</dt><dd>{{ $d['confirmedBy'] ? 'Confirmée par '.$d['confirmedBy'] : 'Pas encore confirmée' }}</dd></div>
      <div><dt>Exécution</dt><dd>@if($op->execution_mode === 'api')Par API Genius Pay (réf. {{ $op->provider_reference }}@if($op->provider_refund_reference) · {{ $op->provider_refund_reference }}@endif)@if($op->reconciliation_reference) — rapprochement manuel : {{ $op->reconciliation_reference }} — {{ $op->reconciliation_proof }}@endif @elseif($op->execution_mode === 'manual')Manuelle par {{ $d['executor'] }} — référence externe <strong>{{ $op->external_reference }}</strong> — justificatif : {{ $op->proof_note }}@else Pas encore exécutée @endif</dd></div></dl></section>

  <section class="card" style="margin-top:16px"><h2 class="t-h2">Actions</h2>
    @if($d['canConfirm'])
      <form method="post" action="{{ route('admin.finance.act', [$op->reference, 'confirmer']) }}" class="stack-sm">@csrf
        <h3 class="t-h3">1. Confirmer le récapitulatif</h3>
        <label class="check"><input type="checkbox" name="confirm" value="1" required> <span>Je confirme : <strong>{{ $m((int) $op->amount_xof) }}</strong> · bénéficiaire <strong>{{ $op->kind === 'refund' ? $d['client'] : $d['freelancer'] }}</strong> · environnement <strong>{{ $d['simulated'] ? 'TEST (aucun argent réel)' : 'RÉEL (argent réel)' }}</strong>.</span></label>
        <button class="btn btn-primary" type="submit">Confirmer</button></form>
      <form method="post" action="{{ route('admin.finance.act', [$op->reference, 'refuser']) }}" class="stack-sm" style="margin-top:8px">@csrf<div class="field"><label for="f1">Motif du refus</label><input id="f1" name="note" minlength="10" maxlength="500" required></div><button class="btn btn-secondary" type="submit">Refuser (libère la réservation)</button></form>
    @endif
    @if($d['canExecuteApi'])
      <form method="post" action="{{ route('admin.finance.act', [$op->reference, 'executer-api']) }}" class="stack-sm">@csrf<h3 class="t-h3">2. Exécuter</h3><button class="btn btn-primary" type="submit">Exécuter par API Genius Pay (remboursement total)</button> <span class="muted small">Envoi unique : en cas de délai dépassé ou de réponse incohérente, rien n’est renvoyé automatiquement.</span></form>
    @elseif($op->state === 'approved' && $op->kind === 'refund' && $op->scope === 'partial')<p class="muted small">Remboursement partiel : non exécuté par API (règles des remboursements successifs et idempotence non établies par la documentation). Effectuez-le chez le prestataire, puis enregistrez-le ci-dessous.</p>@endif
    @if($op->state === 'approved' && $op->kind === 'payout')<p class="muted small">Exécution du reversement via Genius Pay : <strong>indisponible</strong> (aucune API de reversement documentée). Effectuez le transfert hors FreeCI puis enregistrez-le ci-dessous.</p>@endif
    @if($d['canRecord'])
      <form method="post" action="{{ route('admin.finance.act', [$op->reference, 'enregistrer']) }}" class="stack-sm" style="margin-top:12px">@csrf
        <h3 class="t-h3">2. Enregistrer une exécution manuelle déjà effectuée</h3>
        <div class="field"><label for="f2">Référence externe du transfert</label><input id="f2" name="external_reference" maxlength="80" required></div>
        <div class="field"><label for="f3">Justificatif (relevé, capture, numéro de transaction…)</label><textarea id="f3" name="proof_note" rows="2" minlength="10" maxlength="500" required></textarea></div>
        <div class="field"><label for="f4">Retapez le montant ({{ (int) $op->amount_xof }})</label><input id="f4" type="number" name="amount_confirm" inputmode="numeric" required></div>
        <label class="check"><input type="checkbox" name="confirm" value="1" required> <span>Je confirme que ce transfert a réellement été effectué{{ $d['simulated'] ? ' (opération de test : aucun argent réel)' : '' }}.</span></label>
        <button class="btn btn-primary" type="submit">Enregistrer comme effectué</button></form>
    @endif
    @if($d['canReconcile'])
      <form method="post" action="{{ route('admin.finance.act', [$op->reference, 'rapprocher']) }}" class="stack-sm" style="margin-top:12px">@csrf<input type="hidden" name="outcome" value="refunded">
        <h3 class="t-h3">Rapprochement manuel : j’ai constaté que le remboursement a eu lieu</h3>
        <div class="field"><label for="f5">Référence du remboursement (ou du contrôle dans le tableau de bord)</label><input id="f5" name="reference" maxlength="80" required></div>
        <div class="field"><label for="f6">Justificatif : ce que vous avez constaté, où, quand</label><textarea id="f6" name="proof" rows="2" minlength="20" maxlength="500" required></textarea></div>
        <div class="field"><label for="f7">Retapez le montant effectivement remboursé ({{ (int) $op->amount_xof }})</label><input id="f7" type="number" name="amount_confirm" inputmode="numeric" required></div>
        <label class="check"><input type="checkbox" name="confirm" value="1" required> <span>Je confirme ce constat.</span></label>
        <button class="btn btn-primary" type="submit">Confirmer le remboursement constaté</button></form>
      <form method="post" action="{{ route('admin.finance.act', [$op->reference, 'rapprocher']) }}" class="stack-sm" style="margin-top:12px">@csrf<input type="hidden" name="outcome" value="not_refunded">
        <h3 class="t-h3">Rapprochement manuel : j’ai constaté qu’aucun remboursement n’a eu lieu</h3>
        <div class="field"><label for="f8">Référence du contrôle effectué</label><input id="f8" name="reference" maxlength="80" required></div>
        <div class="field"><label for="f9">Justificatif : ce que vous avez constaté, où, quand</label><textarea id="f9" name="proof" rows="2" minlength="20" maxlength="500" required></textarea></div>
        <label class="check"><input type="checkbox" name="confirm" value="1" required> <span>Je confirme ce constat : la réservation sera libérée par une écriture correctrice.</span></label>
        <button class="btn btn-secondary" type="submit">Constater l’absence de remboursement</button></form>
    @endif
    @if($d['canCancel'])
      <form method="post" action="{{ route('admin.finance.act', [$op->reference, 'annuler']) }}" class="stack-sm" style="margin-top:12px">@csrf<div class="field"><label for="f10">Motif d’annulation</label><input id="f10" name="note" minlength="10" maxlength="500" required></div><button class="btn btn-secondary" type="submit">Annuler l’opération (libère la réservation)</button></form>
    @endif</section>

  <section class="card" style="margin-top:16px"><h2 class="t-h2">Registre</h2>
    @forelse($d['batches'] as $b)<p><strong>{{ $b['kind'] }}</strong> @if($b['corrects'])<span class="badge tone-neutral">écriture correctrice</span>@endif <span class="muted small">{{ $b['when'] }}{{ $b['memo'] ? ' — '.$b['memo'] : '' }}</span></p>
      <ul class="small">@foreach($b['lines'] as $l)<li>{{ $l['account'] }} : {{ $l['amount'] > 0 ? '+' : '' }}{{ \App\Shared\Money::xof($l['amount'])->formatted() }}</li>@endforeach</ul>
    @empty<p class="muted">Aucune écriture.</p>@endforelse</section>

  <section class="card" style="margin-top:16px"><h2 class="t-h2">Historique</h2>
    @foreach($d['approvals'] as $a)<p class="small">{{ $a['when'] }} — {{ $a['who'] }} : <strong>{{ $a['decision'] === 'approved' ? 'confirmé' : 'refusé' }}</strong>@if($a['note']) — {{ $a['note'] }}@endif</p>@endforeach
    <ul class="small">@foreach($d['events'] as $e)<li>{{ $e['when'] }} — {{ $e['who'] }} : {{ $e['note'] ?? $e['type'] }}</li>@endforeach</ul></section>
</x-layouts.admin>

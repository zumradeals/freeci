@php($v = $working ?? $live)
<x-layouts.account :title="$v?->title ?: 'Mission'" space="client">
  <div class="page-body">
    <nav class="crumbs" aria-label="Fil d’Ariane"><a class="back-m" href="{{ route('client.missions') }}"><x-fc.icon name="arrow-right" :size="16" class="flip" />Mes missions</a><a class="hide-m" href="{{ route('client.missions') }}">Mes missions</a><span class="sep hide-m" aria-hidden="true">›</span><span class="hide-m" aria-current="page">{{ $v?->title }}</span></nav>
    <section class="card order-head" aria-labelledby="h-title"><div class="top"><span class="badge tone-{{ $tone }}"><x-fc.icon :name="$icon" :size="16" />{{ $status }}</span></div>
      <h1 class="t-h1" id="h-title">{{ $v?->title ?: 'Sans titre' }}</h1>
      <dl class="meta"><div><dt>Budget</dt><dd>@if($v?->budget_xof)<x-fc.money :amount="\App\Shared\Money::xof($v->budget_xof)" />@else —@endif</dd></div>
        @if($live)<div><dt>Date limite de candidature</dt><dd>{{ \App\Shared\Dates::format($live->application_deadline) }}<small>Sélection possible jusqu’au {{ $selectionEnd }}</small></dd></div>@endif
        <div><dt>Propositions</dt><dd>{{ $proposals }}</dd></div></dl></section>
    @if($note)<div class="notice tone-{{ $tone === 'warning' ? 'warning' : 'info' }}" role="note"><x-fc.icon :name="$icon" /><p>@if($working?->state === 'changes_requested')<strong>Motif de la modération :</strong> « {{ $note }} »@else{{ $note }}@endif</p></div>@endif
    @if($mission->status === 'selection_ended')
      <section class="action-card" aria-labelledby="h-act"><p class="eyebrow"><x-fc.icon name="arrow-right" :size="16" />Action attendue</p><h2 class="t-h2" id="h-act" style="margin-top:6px">La sélection est terminée : que souhaitez-vous faire ?</h2>
        <p style="margin-top:6px">La commande issue de votre choix a été annulée ou a expiré avant paiement. <strong>La mission n’a pas été rouverte automatiquement.</strong> La période de sélection court jusqu’au {{ $selectionEnd }}.</p>
        <div class="row" style="margin-top:16px"><a class="btn btn-primary btn-lg" href="{{ route('client.missions.confirm', [$mission->getKey(), 'rouvrir']) }}">Rouvrir la mission</a><a class="btn btn-secondary btn-lg" href="{{ route('client.missions.confirm', [$mission->getKey(), 'fermer']) }}">Fermer la mission</a></div></section>
    @elseif($mission->status === 'reserved' || $mission->status === 'awarded')
      <section class="card" aria-labelledby="h-act"><p class="eyebrow"><x-fc.icon name="clock" :size="16" />{{ $mission->status === 'reserved' ? 'Réservée' : 'Attribuée' }}</p><h2 class="t-h2" id="h-act" style="margin-top:6px">{{ $mission->status === 'reserved' ? 'Une proposition est retenue : la commande attend le paiement' : 'Paiement confirmé : la commande suit son cours' }}</h2>
        @if($orderReference)<div style="margin-top:12px"><a class="btn btn-primary" href="{{ route('orders.show', $orderReference) }}">Ouvrir la commande {{ $orderReference }}</a></div>@endif</section>
    @elseif($mission->status === 'open' && $proposals)
      <section class="action-card" aria-labelledby="h-act"><p class="eyebrow"><x-fc.icon name="arrow-right" :size="16" />Action attendue</p><h2 class="t-h2" id="h-act" style="margin-top:6px">{{ $proposals }} proposition{{ $proposals > 1 ? 's' : '' }} à examiner</h2><div style="margin-top:12px"><a class="btn btn-primary btn-lg" href="{{ route('client.missions.proposals', $mission->getKey()) }}">Comparer les propositions</a></div></section>
    @endif
    <div class="row" style="gap:8px">
      @if($canEdit)<a class="btn btn-secondary" href="{{ route('client.missions.edit', $mission->getKey()) }}">{{ $working->state === 'changes_requested' ? 'Corriger' : 'Modifier' }}</a>@endif
      @if($canPreview)<a class="btn btn-secondary" href="{{ route('client.missions.preview', $mission->getKey()) }}">Aperçu</a>@endif
      @if($canUnsubmit)<a class="btn btn-secondary" href="{{ route('client.missions.confirm', [$mission->getKey(), 'retirer-soumission']) }}">Retirer la soumission</a>@endif
      @if($canRevise)<a class="btn btn-secondary" href="{{ route('client.missions.confirm', [$mission->getKey(), 'nouvelle-version']) }}">Modifier la mission</a>@endif
      @if($live && $mission->status === 'open')<a class="btn btn-link" href="{{ route('missions.show', $mission->slug) }}">Voir la page publique</a>@endif
      @if($hasPlan ?? false)<a class="btn btn-secondary" href="{{ route('milestones.show', $mission->getKey()) }}">Plan de jalons</a>@endif
      @if($mission->status === 'open')<a class="btn btn-secondary" href="{{ route('client.missions.invitations', $mission->getKey()) }}">Invitations</a>@endif
      @if($mission->status === 'open' && $proposals)<a class="btn btn-secondary" href="{{ route('client.missions.proposals', $mission->getKey()) }}">Propositions ({{ $proposals }})</a>@endif
      @if($canClose && $mission->status === 'open')<a class="btn btn-link" href="{{ route('client.missions.confirm', [$mission->getKey(), 'fermer']) }}">Fermer la mission</a>@endif
      @if($canCancel)<a class="btn btn-link" href="{{ route('client.missions.confirm', [$mission->getKey(), 'annuler']) }}">Annuler la mission</a>@endif
    </div>
    @if($v)<section class="card" aria-labelledby="h-desc" style="margin-top:16px"><h2 class="t-h2 card-title" id="h-desc">Description {{ $working ? '(version de travail)' : '(publiée)' }}</h2><p>{!! nl2br(e($v->description)) !!}</p>
      @if(count($v->client_inputs))<h3 class="t-h3" style="margin-top:12px">Éléments du brief</h3><ul class="checklist need">@foreach($v->client_inputs as $i)<li><x-fc.icon name="clipboard" /><span>{{ $i }}</span></li>@endforeach</ul>@endif</section>@endif
    @if(count($history))<section class="card" style="margin-top:16px" aria-labelledby="h-hist"><h2 class="t-h2 card-title" id="h-hist">Historique</h2>
      <ol class="timeline">@foreach($history as $h)<li><span class="pt" aria-hidden="true"></span><div><p class="tt">{{ ['created' => 'Brouillon créé', 'submitted' => 'Soumise à modération', 'submission_withdrawn' => 'Soumission retirée', 'approved' => 'Approuvée et publiée', 'changes_requested' => 'Correction demandée par la modération', 'revision_started' => 'Nouvelle version démarrée', 'closed' => 'Mission fermée', 'cancelled' => 'Mission annulée', 'reopened' => 'Mission rouverte', 'proposal_selected' => 'Proposition retenue', 'reservation_ended' => 'Réservation terminée (commande non payée)', 'awarded' => 'Mission attribuée', 'expired' => 'Mission expirée'][$h['type']] ?? $h['type'] }}@if(($h['meta']['proposals_to_reconfirm'] ?? 0) > 0) · {{ $h['meta']['proposals_to_reconfirm'] }} proposition(s) à reconfirmer @endif</p><p class="when">{{ $h['when'] }}@if($h['by']) · {{ $h['by'] }}@endif</p>@if($h['note'])<p class="why">« {{ $h['note'] }} »</p>@endif</div></li>@endforeach</ol></section>@endif
  </div>
</x-layouts.account>

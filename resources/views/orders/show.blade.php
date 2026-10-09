@php
  $isF = $d->perspective === 'freelancer';
  $steps = ['Accord', 'Paiement', 'Brief', 'Réalisation', 'Livraison', 'Validation', 'Clôture'];
  $back = $isF ? route('freelance.dashboard') : route('account.dashboard');
  $act = collect($d->actions);
  $dl = $d->delivery;
  $closedOk = $d->stateValue === 'closed';
  $showDeliveries = in_array($d->stateValue, ['in_progress', 'revision_requested', 'delivered', 'validated', 'closed'], true) || count($dl['deliveries'] ?? []) > 0;
  $first = $showDeliveries ? 'livraisons' : 'accord';
@endphp
<x-layouts.account :title="'Commande '.$d->reference" :space="$space">
  <div class="page-body">
    <nav class="crumbs" aria-label="Fil d’Ariane"><a class="back-m" href="{{ $back }}"><x-fc.icon name="arrow-right" :size="16" class="flip" />Vue d’ensemble</a><a class="hide-m" href="{{ $back }}">Vue d’ensemble</a><span class="sep hide-m" aria-hidden="true">›</span><span class="hide-m" aria-current="page">Commande {{ $d->reference }}</span></nav>

    @if(! $d->startedAt && in_array($d->stateValue, ['awaiting_acceptance', 'awaiting_payment', 'awaiting_brief'], true))
      @if(! $isF)
      <section class="card" aria-label="Fichiers du brief">
        <h2 class="t-h2">{{ $d->briefRequiresFiles ? 'Joignez les fichiers nécessaires à votre prestation' : 'Photos et documents pour le freelance' }}</h2>
        <p class="mt-8">Vous pouvez les transmettre dès maintenant depuis l’onglet Brief, sans attendre le paiement.</p>
        @unless($d->uploadsEnabled)<p class="field-error mt-8" role="alert">Le dépôt est indisponible : l’administration doit rétablir le contrôle de sécurité.@if($d->briefRequiresFiles) Un texte ne remplace pas le fichier obligatoire. @unless($d->payment && $d->payment['state'] === 'confirmed')Attendez son rétablissement avant de payer.@endunless @endif</p>@endunless
        <a class="btn btn-secondary mt-12" href="#brief" data-goto-tab="brief" data-goto="h-files">{{ $d->uploadsEnabled ? 'Joindre mes fichiers' : 'Voir les pièces jointes' }}</a>
      </section>
      @elseif($d->briefRequiresFiles && ! $d->uploadsEnabled && ! $d->briefComplete)
      <div class="notice tone-warning" role="note"><p>Le client ne peut actuellement pas joindre le fichier obligatoire : le contrôle de sécurité est indisponible. L’administration doit le rétablir pour permettre de compléter le brief.</p></div>
      @endif
    @endif

    <section class="card order-head" aria-labelledby="h-title">
      <div class="top"><span class="badge tone-{{ $d->tone }}"><x-fc.icon :name="$d->icon" :size="16" />{{ $d->stateLabel }}</span><span class="muted">Réf. <span class="num">{{ $d->reference }}</span></span><a class="btn btn-secondary" href="{{ route('messages.order', $d->reference) }}"><x-fc.icon name="message" :size="18" />Messages @if($d->messageUnread > 0)<span class="count-badge" aria-label="{{ $d->messageUnread }} non lu{{ $d->messageUnread > 1 ? 's' : '' }}">{{ $d->messageUnread }}</span>@endif</a>@if($d->environment === 'test')<span class="tag-demo">Commande de test — aucun argent réel</span>@elseif($d->environment === 'legacy')<span class="tag-demo">Ancienne commande</span>@endif</div>
      <h1 class="t-h1" id="h-title">{{ $d->title }}</h1>@if($d->origin === 'offer')<p><span class="badge tone-info">Offre personnalisée</span></p>@endif
      <dl class="meta">
        <div><dt>{{ $d->otherPartyLabel }}</dt><dd>{{ $d->otherPartyName }}</dd></div>
        <div><dt>Montant convenu</dt><dd><x-fc.money :amount="$d->price" /></dd></div>
        <div class="span2"><dt>Échéance de réalisation</dt><dd>@if($d->dueAt){{ \App\Shared\Dates::format($d->dueAt) }}@if($dl['late'])<span class="badge tone-warning" style="margin-left:8px"><x-fc.icon name="warn" :size="16" />Échéance dépassée</span>@endif<small>@if($dl['initialDue'])Report accepté (initialement le {{ $dl['initialDue'] }}). @endif Départ le {{ \App\Shared\Dates::format($d->startedAt) }}.</small>@else Non démarrée<small>Elle est enregistrée une seule fois, après paiement confirmé et brief complet.</small>@endif</dd></div>
      </dl>
      @if(! $d->isFinal || $closedOk)
      <div><ol class="steps-d" aria-label="Étapes de la commande">
        @foreach($steps as $i => $s)
          @php($cls = $i < $d->stepIndex ? 'done' : ($i === $d->stepIndex ? 'cur' : ''))
          <li class="{{ $cls }}" @if($cls === 'cur') aria-current="step" @endif><span class="mk" aria-hidden="true">@if($cls === 'done')<x-fc.icon name="check" :size="16" />@elseif($cls !== 'cur'){{ $i + 1 }}@endif</span><span>{{ $s }}<span class="sr-only"> ({{ $cls === 'done' ? 'terminée' : ($cls === 'cur' ? 'étape en cours' : 'à venir') }})</span></span></li>
        @endforeach
      </ol>
      <details class="steps-m"><summary><span>@if($closedOk)<strong>Commande clôturée</strong> · 7 étapes terminées @else Étape {{ $d->stepIndex + 1 }} sur 7 · <strong>{{ $steps[$d->stepIndex] }}</strong>@endif</span><x-fc.icon name="chev-down" :size="20" /></summary>
        <div class="bar" aria-hidden="true">@foreach($steps as $i => $s)<i class="{{ $i < $d->stepIndex ? 'done' : ($i === $d->stepIndex ? 'cur' : '') }}"></i>@endforeach</div>
        <ol aria-label="Étapes de la commande">@foreach($steps as $i => $s)<li class="{{ $i < $d->stepIndex ? 'done' : ($i === $d->stepIndex ? 'cur' : '') }}"><x-fc.icon :name="$i < $d->stepIndex ? 'check-circle' : ($i === $d->stepIndex ? 'clock' : 'minus-circle')" :size="20" /><span>{{ $s }}</span></li>@endforeach</ol></details></div>
      @endif
    </section>

    <div class="od-grid"><div class="od-main">
    {{-- Action attendue — calculée côté serveur, revérifiée à l'exécution --}}
    @if($closedOk)
      <section class="card card-accent-success" aria-labelledby="h-closed"><p class="eyebrow"><x-fc.icon name="check-circle" :size="16" />Clôturée</p><h2 class="t-h2 mt-6" id="h-closed">Livraison v{{ $dl['latestVersion'] }} validée : commande clôturée</h2>
        <p class="mt-6">Validée le {{ $dl['validatedAt'] }}. Cette clôture est <strong>commerciale</strong> : elle ne confirme ni ne déclenche aucun reversement.</p>
        <p class="muted mt-8">Toutes les versions livrées restent consultables dans l’onglet « Livraisons ».</p>
        <p class="mt-12"><a class="btn btn-secondary" href="{{ route('orders.review', $d->reference) }}">{{ $isF ? 'Voir l’avis du client' : 'Laisser un avis ou voir mon avis' }}</a>@if($d->environment === 'test') <span class="tag-demo">Commande de test : aperçu non public</span>@endif</p></section>
    @elseif($d->isFinal)
      <section class="card" aria-labelledby="h-closed"><p class="eyebrow">{{ $d->stateValue === 'expired' ? 'Expirée' : 'Annulée' }}</p><h2 class="t-h2 mt-6" id="h-closed">{{ $d->closureReason }}</h2>
        @if($d->closureNote && $d->stateValue === 'cancelled' && $d->closureReason === 'Demande refusée par le freelance')<p class="mt-8"><strong>Motif :</strong> « {{ $d->closureNote }} »</p>@endif
        <p class="muted mt-8">Aucun montant n’a été encaissé. Aucune livraison n’est attendue.</p>
        @unless($isF)<div class="mt-16"><a class="btn btn-secondary" href="{{ route('services.index') }}">Parcourir les services</a></div>@endunless</section>
    @elseif($d->stateValue === 'disputed')
      <section class="card card-accent-error" aria-labelledby="h-disp"><p class="eyebrow"><x-fc.icon name="error" :size="16" />En litige</p><h2 class="t-h2 mt-6" id="h-disp">Cette commande fait l’objet d’un dossier d’assistance</h2>
        <p class="mt-6"><strong>Actions suspendues :</strong> livrer, demander une correction, valider la livraison et proposer ou accepter un report. <strong>Rien n’est validé automatiquement.</strong> Les messages de la commande restent possibles.</p>
        <p class="mt-6">Le reversement non exécuté est bloqué en interne. La décision est prise par l’équipe d’assistance, après examen des deux parties.@if($d->supportCase) <a href="{{ route('support.show', $d->supportCase) }}">Suivre le dossier {{ $d->supportCase }}</a>@endif</p></section>
    @elseif($d->stateValue === 'awaiting_acceptance' && $isF)
      <section class="action-card" aria-labelledby="h-action"><p class="eyebrow"><x-fc.icon name="arrow-right" :size="16" />Action attendue</p>
        <h2 class="t-h2 mt-6" id="h-action">Répondre à la demande</h2>
        <p class="mt-6">{{ $d->clientName }} souhaite commander cette prestation. Lisez son besoin dans l’onglet « Brief ».</p>
        <p class="due due-block"><x-fc.icon name="clock" :size="20" /><span>Répondre avant le <strong>{{ \App\Shared\Dates::format($d->responseDeadline) }}</strong> <span class="{{ \App\Shared\Dates::isUrgent($d->responseDeadline) ? 'urgent' : 'rel' }}">({{ \App\Shared\Dates::until($d->responseDeadline) }})</span></span></p>
        <div class="row mt-16"><a class="btn btn-primary btn-lg" href="{{ route('orders.confirm', [$d->reference, 'accept']) }}">Accepter la demande</a><a class="btn btn-secondary btn-lg" href="{{ route('orders.confirm', [$d->reference, 'decline']) }}">Refuser la demande</a></div>
        <p class="effect mt-12">Si vous acceptez, la commande attend le paiement du client : <strong>le travail ne commence qu’après paiement confirmé et brief complet</strong>.</p></section>
    @elseif($d->stateValue === 'awaiting_acceptance')
      <section class="card" aria-labelledby="h-action"><p class="eyebrow"><x-fc.icon name="clock" :size="16" />En attente de réponse</p>
        <h2 class="t-h2 mt-6" id="h-action">Votre demande a été envoyée à {{ $d->freelancerName }}</h2>
        <p class="due due-block"><x-fc.icon name="clock" :size="20" /><span>Réponse attendue avant le <strong>{{ \App\Shared\Dates::format($d->responseDeadline) }}</strong> <span class="rel">({{ \App\Shared\Dates::until($d->responseDeadline) }})</span></span></p>
        <p class="effect mt-12">Vous ne payez rien à cette étape. Si la demande n’a pas de réponse à temps, elle expire sans frais.</p>
        <div class="mt-16"><a class="btn btn-secondary" href="{{ route('orders.confirm', [$d->reference, 'withdraw']) }}">Retirer la demande</a></div></section>
    @elseif($d->stateValue === 'awaiting_payment')
      <section class="{{ $d->canPay ? 'action-card' : 'card' }}" aria-labelledby="h-action"><p class="eyebrow"><x-fc.icon name="{{ $d->canPay ? 'arrow-right' : 'warn' }}" :size="16" />{{ $isF ? 'En attente du client' : ($d->canPay ? 'Action attendue' : 'En attente de paiement') }}</p>
        @if($d->paymentOpen)
          <h2 class="t-h2 mt-6" id="h-action">{{ $isF ? 'Le client règle la commande' : 'Vérification du paiement en cours' }}</h2>
          <p class="mt-6">Un paiement est en cours de vérification. @unless($isF)<strong>Ne payez pas une seconde fois.</strong>@endunless</p>
          @unless($isF)<div class="mt-16"><a class="btn btn-primary" href="{{ route('orders.payment', $d->reference) }}">Voir l’état du paiement</a></div>@endunless
        @elseif($d->canPay)
          <h2 class="t-h2 mt-6" id="h-action">Payer {{ $d->price->formatted() }} FCFA</h2>
          <p class="mt-6">{{ $d->origin === 'mission' ? 'Proposition retenue' : ($d->origin === 'offer' ? 'Offre acceptée' : 'Demande acceptée') }} le {{ \App\Shared\Dates::format($d->acceptedAt) }} @if($d->origin !== 'mission')par {{ $d->freelancerName }}@else({{ $d->freelancerName }})@endif.</p>
          @if($d->paymentDeadline)<p class="due due-block"><x-fc.icon name="clock" :size="20" /><span>Payer avant le <strong>{{ \App\Shared\Dates::format($d->paymentDeadline) }}</strong> <span class="{{ \App\Shared\Dates::isUrgent($d->paymentDeadline) ? 'urgent' : 'rel' }}">({{ \App\Shared\Dates::until($d->paymentDeadline) }})</span></span></p>@endif
          <div class="row mt-16"><a class="btn btn-primary btn-lg" href="{{ route('orders.payment', $d->reference) }}">Aller au paiement</a><a class="btn btn-secondary btn-lg" href="{{ route('orders.confirm', [$d->reference, 'cancel']) }}">Annuler la commande</a></div>
          <p class="effect mt-12">@if($d->environment === 'test')<strong>Mode test — aucun argent réel n’est débité.</strong> @endif Le travail commence après paiement confirmé côté serveur et brief complet.</p>
        @else
          <h2 class="t-h2 mt-6" id="h-action">{{ $isF ? 'Vous avez accepté : la commande attend le paiement' : 'Demande acceptée : la commande attend le paiement' }}</h2>
          <p class="mt-6">{{ $d->origin === 'mission' ? 'Proposition retenue' : ($d->origin === 'offer' ? 'Offre acceptée' : 'Acceptée') }} le {{ \App\Shared\Dates::format($d->acceptedAt) }}@if($d->origin !== 'mission') par {{ $d->freelancerName }}@endif.</p>
          <p class="note-line mt-12"><x-fc.icon name="lock" :size="16" /><span><strong>Le paiement n’est pas ouvert pour cette commande.</strong> Aucun paiement ne peut être effectué ici : la commande reste « en attente de paiement », sans échéance de paiement. <strong>Le travail ne commence — et aucune échéance de réalisation ne court — qu’après paiement confirmé et brief complet.</strong></span></p>
          @unless($isF)<div class="mt-16"><a class="btn btn-secondary" href="{{ route('orders.confirm', [$d->reference, 'cancel']) }}">Annuler la commande</a></div>@endunless
        @endif</section>
    @elseif($d->stateValue === 'awaiting_brief')
      <section class="{{ $isF ? 'card' : 'action-card' }}" aria-labelledby="h-action"><p class="eyebrow"><x-fc.icon name="arrow-right" :size="16" />{{ $isF ? 'En attente du client' : 'Action attendue' }}</p>
        <h2 class="t-h2 mt-6" id="h-action">{{ $isF ? 'Paiement confirmé : le brief doit être complété' : 'Complétez le brief pour lancer le travail' }}</h2>
        <p class="mt-6">Le paiement est confirmé. Le travail démarre dès que le brief est complet ({{ $d->briefMissing }} {{ $d->briefMissing > 1 ? 'éléments manquants' : 'élément manquant' }}).</p>
        <div class="mt-16"><a class="btn btn-primary btn-lg" href="#brief" data-goto-tab="brief" data-goto="panel-brief">Voir le brief</a></div></section>
    @elseif($d->stateValue === 'in_progress')
      <section class="{{ $isF ? 'action-card' : 'card' }}" aria-labelledby="h-action"><p class="eyebrow"><x-fc.icon name="{{ $isF ? 'arrow-right' : 'check-circle' }}" :size="16" />{{ $isF ? 'Action attendue' : 'En cours' }}</p>
        <h2 class="t-h2 mt-6" id="h-action">{{ $isF ? 'Déposer la livraison' : $d->freelancerName.' réalise votre commande' }}</h2>
        <p class="due due-block"><x-fc.icon name="clock" :size="20" /><span>Échéance de livraison : <strong>{{ \App\Shared\Dates::format($d->dueAt) }}</strong> <span class="{{ $dl['late'] || \App\Shared\Dates::isUrgent($d->dueAt) ? 'urgent' : 'rel' }}">({{ \App\Shared\Dates::until($d->dueAt) }})</span></span></p>
        @if($isF)<div class="row mt-16"><a class="btn btn-primary btn-lg" href="{{ route('orders.delivery', $d->reference) }}">Préparer la livraison</a>@if($dl['canRequestExtension'])<a class="btn btn-secondary btn-lg" href="{{ route('orders.extension', $d->reference) }}">Proposer un report</a>@endif</div>
        <p class="effect mt-12">Le client ne voit rien avant que vous ne <strong>soumettiez</strong> la livraison.</p>
        @else<p class="muted mt-8">Départ le {{ \App\Shared\Dates::format($d->startedAt) }}. Vous serez invité à examiner la livraison dès qu’elle est soumise.</p>@endif</section>
    @elseif($d->stateValue === 'revision_requested')
      @php($last = collect($dl['deliveries'])->firstWhere('isLatest', true))
      <section class="{{ $isF ? 'action-card' : 'card' }}" aria-labelledby="h-action"><p class="eyebrow"><x-fc.icon name="{{ $isF ? 'arrow-right' : 'clock' }}" :size="16" />{{ $isF ? 'Action attendue' : 'Correction demandée' }}</p>
        <h2 class="t-h2 mt-6" id="h-action">{{ $isF ? 'Répondre à la correction n° '.($last['correction']['number'] ?? '') : $d->freelancerName.' prépare une nouvelle version' }}</h2>
        @if($last && $last['correction'])<div class="quote" style="margin-top:10px"><strong>Correction {{ $last['correction']['number'] }} sur {{ $dl['corrections']['included'] }} demandée le {{ $last['correction']['when'] }}</strong><br>« {{ $last['correction']['reason'] }} »</div>@endif
        <p class="due due-block"><x-fc.icon name="clock" :size="20" /><span>Échéance de livraison : <strong>{{ \App\Shared\Dates::format($d->dueAt) }}</strong> <span class="{{ $dl['late'] ? 'urgent' : 'rel' }}">({{ \App\Shared\Dates::until($d->dueAt) }})</span></span></p>
        @if($isF)<div class="row mt-16"><a class="btn btn-primary btn-lg" href="{{ route('orders.delivery', $d->reference) }}">Préparer la livraison v{{ $dl['latestVersion'] + 1 }}</a>@if($dl['canRequestExtension'])<a class="btn btn-secondary btn-lg" href="{{ route('orders.extension', $d->reference) }}">Proposer un report</a>@endif</div>
        <p class="effect mt-12">La nouvelle version <strong>ne remplace pas</strong> la précédente : toutes restent consultables.</p>@endif</section>
    @elseif($d->stateValue === 'delivered')
      @php($rv = $dl['review'])
      <section class="{{ $isF ? 'card' : 'action-card' }}" aria-labelledby="h-action"><p class="eyebrow"><x-fc.icon name="{{ $isF ? 'clock' : 'arrow-right' }}" :size="16" />{{ $isF ? 'En attente du client' : 'Action attendue' }}</p>
        <h2 class="t-h2 mt-6" id="h-action">{{ $isF ? 'Livraison v'.$dl['latestVersion'].' soumise : en attente d’examen' : 'Examiner la livraison v'.$dl['latestVersion'] }}</h2>
        @php($lv = collect($dl['deliveries'])->firstWhere('isLatest', true))
        <p class="mt-6">{{ count($lv['files'] ?? []) }} fichier{{ count($lv['files'] ?? []) > 1 ? 's' : '' }} déposé{{ count($lv['files'] ?? []) > 1 ? 's' : '' }} le {{ $lv['when'] }} par {{ $lv['author'] }}.</p>
        @if($rv)<p class="due due-block"><x-fc.icon name="clock" :size="20" /><span>{{ $isF ? 'Délai d’examen du client' : 'À décider avant le' }} <strong>{{ \App\Shared\Dates::format($rv['deadline']) }}</strong> <span class="{{ $rv['overdue'] ? 'urgent' : 'rel' }}">({{ \App\Shared\Dates::until($rv['deadline']) }})</span></span></p>
          @if($rv['overdue'])<p class="note-line mt-8"><x-fc.icon name="info" :size="16" /><span><strong>Le délai d’examen est dépassé.</strong> La commande reste ouverte : rien n’est validé, clôturé ni libéré automatiquement.@if($rv['followUp']) Un besoin de suivi est enregistré ; <strong>aucun support n’a été contacté automatiquement</strong>.@endif</span></p>@endif @endif
        @if($isF && $dl['disagreement'])<p class="note-line mt-8"><x-fc.icon name="info" :size="16" /><span><strong>Le client a signalé un désaccord le {{ $dl['disagreement']['when'] }}</strong> : « {{ $dl['disagreement']['note'] }} ». Un besoin de suivi est enregistré ; la commande reste ouverte et <strong>aucun support n’a été contacté automatiquement</strong>.</span></p>@endif
        @unless($isF)<div class="mt-16"><a class="btn btn-primary btn-lg" href="#livraison-v{{ $dl['latestVersion'] }}" data-goto-tab="livraisons" data-goto="livraison-v{{ $dl['latestVersion'] }}">Consulter les fichiers de la livraison v{{ $dl['latestVersion'] }} <x-fc.icon name="arrow-down" :size="18" /></a></div>
        <p class="effect mt-12">Ensuite, vous pourrez <strong>valider la livraison</strong> ou <strong>demander une correction</strong> ({{ $dl['corrections']['remaining'] }} sur {{ $dl['corrections']['included'] }} restante{{ $dl['corrections']['remaining'] > 1 ? 's' : '' }}).</p>@endunless</section>
    @endif

    @if($dl['extension'])
      @php($x = $dl['extension'])
      <section class="{{ $dl['canAnswerExtension'] ? 'action-card' : 'card' }}" aria-labelledby="h-ext"><p class="eyebrow"><x-fc.icon name="clock" :size="16" />Report d’échéance proposé</p>
        <h2 class="t-h2 mt-6" id="h-ext">{{ $dl['canAnswerExtension'] ? $d->freelancerName.' propose une nouvelle échéance' : 'Votre proposition attend la réponse du client' }}</h2>
        <dl class="defs mt-8"><div><dt>Échéance actuelle</dt><dd>{{ $x['previous'] }}</dd></div><div><dt>Échéance proposée</dt><dd>{{ $x['proposed'] }}</dd></div><div><dt>Motif</dt><dd>« {{ $x['reason'] }} »</dd></div></dl>
        @if($dl['canAnswerExtension'])<div class="row mt-16"><a class="btn btn-primary" href="{{ route('orders.extension.answer', [$d->reference, 'accepter']) }}">Accepter le report</a><a class="btn btn-secondary" href="{{ route('orders.extension.answer', [$d->reference, 'refuser']) }}">Refuser le report</a></div>
        <p class="effect mt-12">L’échéance ne change que si vous acceptez ; sans réponse, elle reste inchangée.</p>
        @elseif($dl['canWithdrawExtension'])<form method="post" action="{{ route('orders.extension.withdraw', $d->reference) }}" data-once style="margin-top:12px">@csrf<input type="hidden" name="extension_id" value="{{ $x['id'] }}"><input type="hidden" name="expected_version" value="{{ $d->version }}"><input type="hidden" name="operation_key" value="{{ \Illuminate\Support\Str::uuid() }}"><button class="btn btn-secondary" type="submit" data-once-label="Retrait…">Retirer la proposition</button></form>@endif</section>
    @endif

    <div class="cols">
      <div>
        <div class="tabs" role="tablist" aria-label="Sections de la commande">
          @if($showDeliveries)<button class="tab" role="tab" type="button" id="tab-livraisons" aria-controls="panel-livraisons" aria-selected="true" tabindex="0">Livraisons @if(count($dl['deliveries']))<span class="n">{{ count($dl['deliveries']) }}</span>@endif</button>@endif
          <button class="tab" role="tab" type="button" id="tab-accord" aria-controls="panel-accord" aria-selected="{{ $first === 'accord' ? 'true' : 'false' }}" tabindex="{{ $first === 'accord' ? '0' : '-1' }}">Accord</button>
          <button class="tab" role="tab" type="button" id="tab-brief" aria-controls="panel-brief" aria-selected="false" tabindex="-1">Brief</button>
          <button class="tab" role="tab" type="button" id="tab-finances" aria-controls="panel-finances" aria-selected="false" tabindex="-1">Finances</button>
          <button class="tab" role="tab" type="button" id="tab-historique" aria-controls="panel-historique" aria-selected="false" tabindex="-1">Historique</button>
        </div>

        @if($showDeliveries)
        <div class="panel dl-panel" role="tabpanel" id="panel-livraisons" aria-labelledby="tab-livraisons" tabindex="0">
          @include('orders._deliveries')
        </div>
        @endif

        <div class="panel" role="tabpanel" id="panel-accord" aria-labelledby="tab-accord" tabindex="0" @if($first !== 'accord') hidden @endif>
          <section class="card" aria-labelledby="h-accord"><h2 class="t-h2 card-title" id="h-accord">Accord de commande</h2>
            <dl class="defs">
              <div><dt>Parties</dt><dd>Client : {{ $d->clientName }}<small>Freelance : {{ $d->freelancerName }}</small></dd></div>
              <div><dt>{{ $d->origin === 'mission' ? 'Mission' : ($d->origin === 'offer' ? 'Offre personnalisée' : 'Prestation') }}</dt><dd>{{ $d->title }}<small>{{ $d->categoryName }} · @if($d->origin === 'mission')proposition v{{ $d->proposalNumber }} retenue : conditions de la proposition figées à la sélection @elseif($d->origin === 'offer')offre envoyée dans la conversation et acceptée par le client : conditions de l’offre figées à l’acceptation @else version {{ $d->serviceVersion }} du service, au moment de la demande @endif</small></dd></div>
              <div><dt>Périmètre</dt><dd>{{ $d->scope }}</dd></div>
              <div><dt>Livrables</dt><dd>@foreach($d->deliverables as $x){{ $x }}@if(! $loop->last)<br>@endif @endforeach</dd></div>
              <div><dt>Non inclus</dt><dd>@foreach($d->exclusions as $x){{ $x }}@if(! $loop->last)<br>@endif @endforeach</dd></div>
              @if($d->tierName)<div><dt>Formule</dt><dd>{{ $d->tierName }}<small>Choisie parmi les formules du service, au moment de la demande</small></dd></div>@endif
              @if(count($d->selectedOptions))<div><dt>Options retenues</dt><dd>@foreach($d->selectedOptions as $o){{ $o['label'] }} · + <x-fc.money :amount="\App\Shared\Money::xof((int) $o['price_xof'])" />@if(($o['delivery_days'] ?? 0) != 0) · {{ $o['delivery_days'] > 0 ? '+ ' : '− ' }}{{ abs($o['delivery_days']) }} {{ abs($o['delivery_days']) > 1 ? 'jours' : 'jour' }}@endif @if(! $loop->last)<br>@endif @endforeach</dd></div>@endif
              <div><dt>Prix convenu</dt><dd><x-fc.money :amount="$d->price" /><small>Figé dans l’accord @if($d->basePrice !== null && count($d->selectedOptions)) · formule <x-fc.money :amount="\App\Shared\Money::xof($d->basePrice)" /> + options <x-fc.money :amount="\App\Shared\Money::xof($d->price->xof - $d->basePrice)" />@endif</small></dd></div>
              <div><dt>Délai</dt><dd>{{ $d->deliveryDays }} {{ $d->deliveryDays > 1 ? 'jours' : 'jour' }} à partir du départ</dd></div>
              <div><dt>Départ</dt><dd>@if($d->startedAt){{ \App\Shared\Dates::format($d->startedAt) }}<small>Enregistré une seule fois, après paiement confirmé côté serveur et brief complet.</small>@else Non enregistré<small>Enregistré une seule fois, après paiement confirmé et brief complet. Aucune échéance de réalisation ne court avant.</small>@endif</dd></div>
              @if($d->dueAt)<div><dt>Échéance de réalisation</dt><dd>{{ \App\Shared\Dates::format($d->dueAt) }}<small>Fixée au départ ; seul un report accepté par le client la modifie (l’ancienne valeur est conservée).</small></dd></div>@endif
              <div><dt>Corrections incluses</dt><dd>{{ $d->revisionsIncluded }}</dd></div>
              <div><dt>Fichiers livrables</dt><dd>@switch($dl['deliveryMode'] ?? 'unspecified')@case('files')Exigés : au moins un fichier contrôlé par livraison @break @case('message')Livraison par message : aucun fichier exigé (précisé dans l’accord) @break @default Non précisé<small>Accord antérieur à cette règle. Les conditions figées sont conservées telles quelles et l’obligation de joindre un fichier n’est <strong>pas contrôlée automatiquement</strong> : le client examine ce qui est livré, peut demander une correction ou laisser la livraison non validée.</small>@endswitch</dd></div>
              @if($d->origin === 'offer')<div><dt>Acceptation</dt><dd>Offre acceptée le {{ \App\Shared\Dates::format($d->acceptedAt) }}<small>L’offre vaut acceptation du freelance : aucun délai de réponse.</small></dd></div>@elseif($d->origin === 'mission')<div><dt>Sélection</dt><dd>Proposition retenue le {{ \App\Shared\Dates::format($d->acceptedAt) }}<small>La proposition vaut acceptation du freelance : aucun délai de réponse.</small></dd></div>@else<div><dt>Délais de la demande</dt><dd>Réponse du freelance avant le {{ \App\Shared\Dates::format($d->responseDeadline) }}</dd></div>@endif
              <div><dt>Conditions</dt><dd>Conditions de la demande v{{ $d->conditionsVersion }}<small>Acceptées le {{ \App\Shared\Dates::format($d->conditionsAcceptedAt) }}</small></dd></div>
            </dl></section>
          <p class="note-line"><x-fc.icon name="lock" :size="16" /><span>Accord figé : il ne change pas si le service ou ses tarifs évoluent ensuite.</span></p>
        </div>

        <div class="panel" role="tabpanel" id="panel-brief" aria-labelledby="tab-brief" tabindex="0" hidden>
          <section class="card" aria-labelledby="h-brief"><div class="row" style="justify-content:space-between;margin-bottom:12px"><h2 class="t-h2" id="h-brief">Brief</h2>
            @if($d->briefComplete)<span class="badge tone-success"><x-fc.icon name="check-circle" :size="16" />Brief complet</span>@else<span class="badge tone-warning"><x-fc.icon name="warn" :size="16" />{{ $d->briefMissing }} {{ $d->briefMissing > 1 ? 'éléments manquants' : 'élément manquant' }}</span>@endif</div>
            <ul class="checklist ok">@foreach($d->briefItems as $it)<li><x-fc.icon :name="trim($it['answer']) !== '' ? 'check-circle' : 'minus-circle'" /><span><b>{{ $it['label'] }}</b> — {!! nl2br(e($it['answer'])) !!}</span></li>@endforeach</ul>
            @if($d->briefNotes)<h3 class="t-h3 mt-16">Précisions</h3><p>{!! nl2br(e($d->briefNotes)) !!}</p>@endif
            <p class="muted mt-16">Le travail démarre après paiement confirmé <em>et</em> brief complet. Compléter le brief ne modifie <strong>jamais</strong> le prix, le périmètre ni le délai de l’accord : un changement de périmètre passe par une nouvelle demande.</p></section>
          <section class="card mt-16" aria-labelledby="h-files"><div class="row" style="justify-content:space-between;margin-bottom:8px"><h2 class="t-h2" id="h-files" tabindex="-1">Pièces jointes</h2>@if($d->briefRequiresFiles)<span class="badge tone-warning"><x-fc.icon name="warn" :size="16" />Au moins un fichier contrôlé requis</span>@endif</div>
            <p class="note-line"><x-fc.icon name="shield" :size="16" /><span><strong>Fichiers privés.</strong> Seules les deux parties y accèdent, et seulement après un contrôle de sécurité réussi. Ce contrôle vérifie le format et l’absence de contenu dangereux ; il ne dit rien de la qualité du contenu.</span></p>
            @if(count($d->files))
              <div class="files mt-12">@foreach($d->files as $f)
                <div class="file-line"><x-fc.icon name="file" :size="22" class="fi" /><span class="fn">{{ $f['name'] }}<span class="meta-f">{{ $f['size'] }}</span><span class="sec"><x-fc.icon :name="$f['icon']" :size="16" />{{ $f['label'] }}</span>@if($f['note'])<small class="muted">{{ $f['note'] }}</small>@endif</span>
                  <span class="acts">@if($f['url'])<a class="btn btn-secondary" href="{{ $f['url'] }}"><x-fc.icon name="download" :size="18" />Télécharger<span class="sr-only"> {{ $f['name'] }}</span></a>@endif
                    @if($f['canRemove'])<form method="post" action="{{ route('orders.files.destroy', [$d->reference, $f['id']]) }}">@csrf<button class="btn btn-link" type="submit">Retirer<span class="sr-only"> {{ $f['name'] }}</span></button></form>@endif</span></div>
              @endforeach</div>
            @else<p class="muted mt-12">Aucun fichier pour l’instant.</p>@endif
            @if($d->canUpload)
              <form method="post" action="{{ route('orders.files.store', $d->reference) }}" enctype="multipart/form-data" data-once style="display:grid;gap:12px;margin-top:16px">@csrf
                <div class="field"><label for="brief-file">Ajouter un fichier</label><p class="hint" id="brief-file-h">{{ $d->uploadLimits }}</p><input class="input" id="brief-file" type="file" name="file" required aria-describedby="brief-file-h"></div>
                @error('file')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                <div><button class="btn btn-secondary" type="submit" data-once-label="Envoi…">Envoyer le fichier</button></div></form>
            @elseif(! $isF && ! $d->uploadsEnabled && ! $d->startedAt && in_array($d->stateValue, ['awaiting_acceptance', 'awaiting_payment', 'awaiting_brief'], true))
              <p class="note-line mt-12"><x-fc.icon name="warn" :size="16" /><span>Le dépôt de fichiers est <strong>temporairement indisponible</strong> : le contrôle de sécurité doit être rétabli par l’administration. @if($d->briefRequiresFiles)<strong>Un texte ne remplace pas le fichier obligatoire. Le travail reste en attente.</strong>@endif Contactez l’assistance en indiquant la référence {{ $d->reference }}.</span></p>
            @endif
          </section>
        </div>

        <div class="panel" role="tabpanel" id="panel-finances" aria-labelledby="tab-finances" tabindex="0" hidden>
          <p class="note-line"><x-fc.icon name="info" :size="16" /><span>Paiement, remboursement et reversement sont suivis séparément de l’avancement de la commande.</span></p>
          <div class="fin-grid">
            <section class="card fin" aria-labelledby="f-pay"><div class="fh"><h3 id="f-pay">Paiement</h3>@if($d->payment)<span class="badge tone-{{ $d->payment['tone'] }}"><x-fc.icon :name="$d->payment['icon']" :size="16" />{{ $d->payment['label'] }}</span>@else<span class="badge tone-neutral"><x-fc.icon name="minus-circle" :size="16" />Non démarré</span>@endif</div>
              <dl><div><dt>Montant convenu</dt><dd><x-fc.money :amount="$d->price" /></dd></div>
                @if($d->payment)<div><dt>Référence</dt><dd class="num">{{ $d->payment['reference'] }}</dd></div><div><dt>Dernière mise à jour</dt><dd>{{ \App\Shared\Dates::format($d->payment['at']) }}</dd></div>@endif</dl>
              <p class="what">@if($d->payment && $d->payment['state'] === 'confirmed')Confirmé côté serveur @if($d->environment === 'test') (mode test) : aucun argent réel n’a été débité@endif.@elseif($d->payment && $d->payment['state'] === 'failed')Aucun montant n’a été confirmé.@elseif($d->payment)Vérification en cours auprès de Genius Pay.@else Aucune tentative de paiement. @if($d->canPay)Le paiement est ouvert pour cette commande.@elseif($d->environment === 'test')Commande de test : elle ne peut jamais être payée en argent réel.@elseif($d->environment === 'legacy')Commande antérieure à l’ouverture des paiements : elle n’est pas payable.@else Le paiement n’est pas ouvert pour cette commande pour le moment.@endif @endif</p></section>
            <section class="card fin" aria-labelledby="f-fin"><div class="fh"><h3 id="f-fin">Situation financière</h3><span class="badge tone-{{ $d->confirmedXof > 0 ? 'success' : 'neutral' }}"><x-fc.icon :name="$d->confirmedXof > 0 ? 'check-circle' : 'minus-circle'" :size="16" />{{ $d->confirmedXof > 0 ? ($d->environment === 'live' ? 'Encaissement' : 'Encaissement de test') : 'Aucun encaissement' }}</span></div>
              <dl><div><dt>{{ $d->environment === 'live' ? 'Encaissé' : 'Encaissé (test, non réel)' }}</dt><dd>{{ \App\Shared\Money::xof($d->confirmedXof)->formatted() }} FCFA</dd></div></dl>
              <p class="what">État financier distinct de la tentative de paiement et de la commande ; enregistré une seule fois au paiement confirmé.</p></section>
            <section class="card fin" aria-labelledby="f-ref"><div class="fh"><h3 id="f-ref">Remboursement</h3>@if(count($d->finance['refunds']))<span class="badge tone-{{ $d->finance['refunds'][count($d->finance['refunds']) - 1]['tone'] }}">{{ $d->finance['refunds'][count($d->finance['refunds']) - 1]['label'] }}</span>@else<span class="badge tone-neutral"><x-fc.icon name="minus-circle" :size="16" />Aucun</span>@endif</div>
              @forelse($d->finance['refunds'] as $x)<p class="what">{{ $x['label'] }} : {{ \App\Shared\Money::xof($x['amount'])->formatted() }} FCFA ({{ $x['reference'] }}){{ $x['simulated'] ? ' — mode test, aucun argent réel' : '' }}</p>@empty<p class="what">{{ $d->confirmedXof > 0 ? 'Aucun remboursement demandé. Une décision du support n’est jamais un remboursement effectué : seule une confirmation établie l’affiche « confirmé ».' : 'Aucun montant encaissé, donc rien à rembourser.' }}</p>@endforelse</section>
            <section class="card fin" aria-labelledby="f-pout"><div class="fh"><h3 id="f-pout">Reversement</h3>@if($isF && $d->finance['payout'])<span class="badge tone-{{ ['confirmed' => 'success', 'failed' => 'error'][$d->finance['payout']['state']] ?? 'warning' }}">{{ $d->finance['payout']['label'] }}</span>@else<span class="badge tone-neutral"><x-fc.icon name="minus-circle" :size="16" />{{ $closedOk ? 'Non déclenché' : 'Sans objet' }}</span>@endif</div>
              <p class="what">@if($isF && $d->finance['payout'])Part du freelance : {{ \App\Shared\Money::xof($d->finance['payout']['amount'])->formatted() }} FCFA ({{ $d->finance['payout']['reference'] }}). Voir <a href="{{ route('freelance.earnings') }}">Revenus</a>.@elseif($closedOk)La clôture commerciale ne confirme ni ne déclenche aucun reversement : il est demandé, approuvé puis enregistré par l’équipe, séparément de la commande.@else Le reversement au freelance n’existe qu’après une prestation validée, et il est suivi séparément de la commande.@endif</p></section>
          </div>
        </div>

        <div class="panel" role="tabpanel" id="panel-historique" aria-labelledby="tab-historique" tabindex="0" hidden>
          <section class="card" aria-labelledby="h-hist"><h2 class="t-h2 card-title" id="h-hist">Historique</h2>
            <ol class="timeline">@foreach($d->events as $e)
              <li class="{{ $e['now'] ? 'now' : '' }}"><span class="pt" aria-hidden="true"></span><div><p class="tt">{{ $e['title'] }}</p><p class="when">{{ $e['when'] }}</p>
                @if($e['to'])<div class="trans">@if($e['from'])<span class="badge tone-neutral">{{ $e['from'] }}</span><span aria-label="devient"><x-fc.icon name="arrow-right" :size="16" /></span>@endif<span class="badge tone-{{ $e['toTone'] }}">{{ $e['to'] }}</span></div>@endif
                @if($e['note'])<p class="why">« {{ $e['note'] }} »</p>@endif</div></li>
            @endforeach</ol></section>
        </div>
      </div>
    </div>
    </div>
    <aside class="card od-sum" aria-label="Résumé de la commande">
      <div><p class="muted small">Montant convenu</p><p class="od-price"><x-fc.money :amount="$d->price" /></p></div>
      <dl>
        @if($d->dueAt)<div><dt>Échéance</dt><dd>{{ \App\Shared\Dates::short($d->dueAt) }}</dd></div>@endif
        @if(isset($dl['corrections']['included']))<div><dt>Corrections</dt><dd>{{ $dl['corrections']['remaining'] }} sur {{ $dl['corrections']['included'] }} restante{{ $dl['corrections']['remaining'] > 1 ? 's' : '' }}</dd></div>@endif
        @if($d->payment)<div><dt>Paiement</dt><dd>{{ $d->payment['state'] === 'confirmed' ? 'Confirmé' : 'En attente' }}</dd></div>@endif
      </dl>
      <div class="od-party"><x-fc.avatar :name="$d->otherPartyName" :user="$d->otherPartyId" size="lg" /><div><b>{{ $d->otherPartyName }}</b><p class="muted small">{{ $d->otherPartyLabel }}</p></div></div>
      <a class="btn btn-secondary" href="{{ route('messages.order', $d->reference) }}"><x-fc.icon name="message" :size="18" />Écrire {{ $isF ? 'au client' : 'au freelance' }}</a>
      <div class="od-help" aria-labelledby="h-sup"><b id="h-sup">Besoin d’aide ?</b>
        <a href="{{ route('support.new', ['commande' => $d->reference]) }}"><x-fc.icon name="info" :size="18" />Contacter le support</a>
        @if($d->supportCase)<a href="{{ route('support.show', $d->supportCase) }}"><x-fc.icon name="message" :size="18" />Voir le dossier {{ $d->supportCase }}</a>@endif
        @if(count($d->disputeKinds))<a href="{{ route('orders.dispute', $d->reference) }}"><x-fc.icon name="flag" :size="18" />{{ in_array('claim', $d->disputeKinds, true) ? 'Déposer une réclamation' : 'Litige ou annulation' }}</a>@endif
        <p class="muted small">Un litige suspend les actions de la commande ; rien n’est validé automatiquement.</p></div>
    </aside>
    </div>
  </div>
</x-layouts.account>

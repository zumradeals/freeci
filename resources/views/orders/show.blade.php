@php
  $isF = $d->perspective === 'freelancer';
  $steps = ['Accord', 'Paiement', 'Brief', 'Réalisation', 'Livraison', 'Validation', 'Clôture'];
  $back = $isF ? route('freelance.dashboard') : route('account.dashboard');
  $act = collect($d->actions);
@endphp
<x-layouts.account :title="'Commande '.$d->reference" :space="$space">
  <nav class="crumbs" aria-label="Fil d’Ariane" style="margin-bottom:-8px"><a class="back-m" href="{{ $back }}"><x-fc.icon name="arrow-right" :size="16" class="flip" />Vue d’ensemble</a><a class="hide-m" href="{{ $back }}">Vue d’ensemble</a><span class="sep hide-m" aria-hidden="true">›</span><span class="hide-m" aria-current="page">Commande {{ $d->reference }}</span></nav>

  <section class="card order-head" aria-labelledby="h-title">
    <div class="top"><span class="badge tone-{{ $d->tone }}"><x-fc.icon :name="$d->icon" :size="16" />{{ $d->stateLabel }}</span><span class="muted">Réf. <span class="num">{{ $d->reference }}</span></span>@if($d->isDemo)<span class="tag-demo">Démonstration</span>@endif</div>
    <h1 class="t-h1" id="h-title">{{ $d->title }}</h1>
    <dl class="meta">
      <div><dt>{{ $d->otherPartyLabel }}</dt><dd>{{ $d->otherPartyName }}</dd></div>
      <div><dt>Montant convenu</dt><dd><x-fc.money :amount="$d->price" /></dd></div>
      <div class="span2"><dt>Échéance de réalisation</dt><dd>Non démarrée<small>Elle est enregistrée une seule fois, après paiement confirmé et brief complet.</small></dd></div>
    </dl>
    @unless($d->isFinal)
    <div><ol class="steps-d" aria-label="Étapes de la commande">
      @foreach($steps as $i => $s)
        @php($cls = $i < $d->stepIndex ? 'done' : ($i === $d->stepIndex ? 'cur' : ''))
        <li class="{{ $cls }}" @if($cls === 'cur') aria-current="step" @endif><span class="mk" aria-hidden="true">@if($cls === 'done')<x-fc.icon name="check" :size="16" />@elseif($cls !== 'cur'){{ $i + 1 }}@endif</span><span>{{ $s }}<span class="sr-only"> ({{ $cls === 'done' ? 'terminée' : ($cls === 'cur' ? 'étape en cours' : 'à venir') }})</span></span></li>
      @endforeach
    </ol>
    <details class="steps-m"><summary><span>Étape {{ $d->stepIndex + 1 }} sur 7 · <strong>{{ $steps[$d->stepIndex] }}</strong></span><x-fc.icon name="chev-down" :size="20" /></summary>
      <div class="bar" aria-hidden="true">@foreach($steps as $i => $s)<i class="{{ $i < $d->stepIndex ? 'done' : ($i === $d->stepIndex ? 'cur' : '') }}"></i>@endforeach</div>
      <ol aria-label="Étapes de la commande">@foreach($steps as $i => $s)<li class="{{ $i < $d->stepIndex ? 'done' : ($i === $d->stepIndex ? 'cur' : '') }}"><x-fc.icon :name="$i < $d->stepIndex ? 'check-circle' : ($i === $d->stepIndex ? 'clock' : 'minus-circle')" :size="20" /><span>{{ $s }}</span></li>@endforeach</ol></details></div>
    @endunless
  </section>

  {{-- Action attendue — calculée côté serveur, revérifiée à l'exécution --}}
  @if($d->isFinal)
    <section class="card" aria-labelledby="h-closed"><p class="eyebrow">{{ $d->stateValue === 'expired' ? 'Expirée' : 'Annulée' }}</p><h2 class="t-h2" id="h-closed" style="margin-top:6px">{{ $d->closureReason }}</h2>
      @if($d->closureNote && $d->stateValue === 'cancelled' && $d->closureReason === 'Demande refusée par le freelance')<p style="margin-top:8px"><strong>Motif :</strong> « {{ $d->closureNote }} »</p>@endif
      <p class="muted" style="margin-top:8px">Aucun montant n’a été encaissé. Aucune livraison n’est attendue.</p>
      @unless($isF)<div style="margin-top:16px"><a class="btn btn-secondary" href="{{ route('services.index') }}">Parcourir les services</a></div>@endunless</section>
  @elseif($d->stateValue === 'awaiting_acceptance' && $isF)
    <section class="action-card" aria-labelledby="h-action"><p class="eyebrow"><x-fc.icon name="arrow-right" :size="16" />Action attendue</p>
      <h2 class="t-h2" id="h-action" style="margin-top:6px">Répondre à la demande</h2>
      <p style="margin-top:6px">{{ $d->clientName }} souhaite commander cette prestation. Lisez son besoin dans l’onglet « Brief ».</p>
      <p class="due due-block"><x-fc.icon name="clock" :size="20" /><span>Répondre avant le <strong>{{ \App\Shared\Dates::format($d->responseDeadline) }}</strong> <span class="{{ \App\Shared\Dates::isUrgent($d->responseDeadline) ? 'urgent' : 'rel' }}">({{ \App\Shared\Dates::until($d->responseDeadline) }})</span></span></p>
      <div class="row" style="margin-top:16px"><a class="btn btn-primary btn-lg" href="{{ route('orders.confirm', [$d->reference, 'accept']) }}">Accepter la demande</a><a class="btn btn-secondary btn-lg" href="{{ route('orders.confirm', [$d->reference, 'decline']) }}">Refuser la demande</a></div>
      <p class="effect" style="margin-top:12px">Si vous acceptez, la commande attend le paiement du client : <strong>le travail ne commence qu’après paiement confirmé et brief complet</strong>.</p></section>
  @elseif($d->stateValue === 'awaiting_acceptance')
    <section class="card" aria-labelledby="h-action"><p class="eyebrow"><x-fc.icon name="clock" :size="16" />En attente de réponse</p>
      <h2 class="t-h2" id="h-action" style="margin-top:6px">Votre demande a été envoyée à {{ $d->freelancerName }}</h2>
      <p class="due due-block"><x-fc.icon name="clock" :size="20" /><span>Réponse attendue avant le <strong>{{ \App\Shared\Dates::format($d->responseDeadline) }}</strong> <span class="rel">({{ \App\Shared\Dates::until($d->responseDeadline) }})</span></span></p>
      <p class="effect" style="margin-top:12px">Vous ne payez rien à cette étape. Si la demande n’a pas de réponse à temps, elle expire sans frais.</p>
      <div style="margin-top:16px"><a class="btn btn-secondary" href="{{ route('orders.confirm', [$d->reference, 'withdraw']) }}">Retirer la demande</a></div></section>
  @elseif($d->stateValue === 'awaiting_payment')
    <section class="card" aria-labelledby="h-action"><p class="eyebrow"><x-fc.icon name="warn" :size="16" />{{ $isF ? 'En attente du client' : 'En attente de paiement' }}</p>
      <h2 class="t-h2" id="h-action" style="margin-top:6px">{{ $isF ? 'Vous avez accepté : la commande attend le paiement' : 'Demande acceptée : la commande attend le paiement' }}</h2>
      <p style="margin-top:6px">Acceptée le {{ \App\Shared\Dates::format($d->acceptedAt) }} par {{ $d->freelancerName }}.</p>
      <p class="note-line" style="margin-top:12px"><x-fc.icon name="lock" :size="16" /><span><strong>Le paiement n’est pas encore ouvert dans cette version.</strong> Aucun paiement ne peut être effectué ni simulé ici : la commande reste « en attente de paiement », sans échéance de paiement. <strong>Le travail ne commence — et aucune échéance de réalisation ne court — qu’après paiement confirmé et brief complet.</strong></span></p>
      @unless($isF)<div style="margin-top:16px"><a class="btn btn-secondary" href="{{ route('orders.confirm', [$d->reference, 'cancel']) }}">Annuler la commande</a></div>@endunless</section>
  @endif

  <div class="cols">
    <div>
      <div class="tabs" role="tablist" aria-label="Sections de la commande">
        <button class="tab" role="tab" type="button" id="tab-accord" aria-controls="panel-accord" aria-selected="true" tabindex="0">Accord</button>
        <button class="tab" role="tab" type="button" id="tab-brief" aria-controls="panel-brief" aria-selected="false" tabindex="-1">Brief</button>
        <button class="tab" role="tab" type="button" id="tab-finances" aria-controls="panel-finances" aria-selected="false" tabindex="-1">Finances</button>
        <button class="tab" role="tab" type="button" id="tab-historique" aria-controls="panel-historique" aria-selected="false" tabindex="-1">Historique</button>
      </div>

      <div class="panel" role="tabpanel" id="panel-accord" aria-labelledby="tab-accord" tabindex="0">
        <section class="card" aria-labelledby="h-accord"><h2 class="t-h2 card-title" id="h-accord">Accord de commande</h2>
          <dl class="defs">
            <div><dt>Parties</dt><dd>Client : {{ $d->clientName }}<small>Freelance : {{ $d->freelancerName }}</small></dd></div>
            <div><dt>Prestation</dt><dd>{{ $d->title }}<small>{{ $d->categoryName }} · version {{ $d->serviceVersion }} du service, au moment de la demande</small></dd></div>
            <div><dt>Périmètre</dt><dd>{{ $d->scope }}</dd></div>
            <div><dt>Livrables</dt><dd>@foreach($d->deliverables as $x){{ $x }}@if(! $loop->last)<br>@endif @endforeach</dd></div>
            <div><dt>Non inclus</dt><dd>@foreach($d->exclusions as $x){{ $x }}@if(! $loop->last)<br>@endif @endforeach</dd></div>
            <div><dt>Prix convenu</dt><dd><x-fc.money :amount="$d->price" /><small>Figé dans l’accord</small></dd></div>
            <div><dt>Délai</dt><dd>{{ $d->deliveryDays }} {{ $d->deliveryDays > 1 ? 'jours' : 'jour' }} à partir du départ</dd></div>
            <div><dt>Départ</dt><dd>Non enregistré<small>Enregistré une seule fois, après paiement confirmé et brief complet. Aucune échéance de réalisation ne court avant.</small></dd></div>
            <div><dt>Corrections incluses</dt><dd>{{ $d->revisionsIncluded }}</dd></div>
            <div><dt>Délais de la demande</dt><dd>Réponse du freelance avant le {{ \App\Shared\Dates::format($d->responseDeadline) }}</dd></div>
            <div><dt>Conditions</dt><dd>Conditions de la demande v{{ $d->conditionsVersion }}<small>Acceptées le {{ \App\Shared\Dates::format($d->conditionsAcceptedAt) }}</small></dd></div>
          </dl></section>
        <p class="note-line"><x-fc.icon name="lock" :size="16" /><span>Accord figé : il ne change pas si le service ou ses tarifs évoluent ensuite.</span></p>
      </div>

      <div class="panel" role="tabpanel" id="panel-brief" aria-labelledby="tab-brief" tabindex="0" hidden>
        <section class="card" aria-labelledby="h-brief"><div class="row" style="justify-content:space-between;margin-bottom:12px"><h2 class="t-h2" id="h-brief">Brief</h2>
          @if($d->briefComplete)<span class="badge tone-success"><x-fc.icon name="check-circle" :size="16" />Brief complet</span>@else<span class="badge tone-warning"><x-fc.icon name="warn" :size="16" />{{ $d->briefMissing }} {{ $d->briefMissing > 1 ? 'éléments manquants' : 'élément manquant' }}</span>@endif</div>
          <ul class="checklist ok">@foreach($d->briefItems as $it)<li><x-fc.icon :name="trim($it['answer']) !== '' ? 'check-circle' : 'minus-circle'" /><span><b>{{ $it['label'] }}</b> — {!! nl2br(e($it['answer'])) !!}</span></li>@endforeach</ul>
          @if($d->briefNotes)<h3 class="t-h3" style="margin-top:16px">Précisions</h3><p>{!! nl2br(e($d->briefNotes)) !!}</p>@endif
          <p class="muted" style="margin-top:16px">Brief textuel enregistré avec la demande. Il ne déclenche rien seul : le travail démarre après paiement confirmé <em>et</em> brief complet. Les fichiers joints et le complément de brief arrivent dans un prochain lot.</p></section>
      </div>

      <div class="panel" role="tabpanel" id="panel-finances" aria-labelledby="tab-finances" tabindex="0" hidden>
        <p class="note-line"><x-fc.icon name="info" :size="16" /><span>Paiement, remboursement et reversement sont suivis séparément de l’avancement de la commande.</span></p>
        <div class="fin-grid">
          <section class="card fin" aria-labelledby="f-pay"><div class="fh"><h3 id="f-pay">Paiement</h3><span class="badge tone-neutral"><x-fc.icon name="minus-circle" :size="16" />Non ouvert</span></div>
            <dl><div><dt>Montant convenu</dt><dd><x-fc.money :amount="$d->price" /></dd></div><div><dt>Paiement reçu</dt><dd>Aucun</dd></div></dl>
            <p class="what">Le paiement n’est pas encore ouvert dans cette version. Rien n’a été encaissé.</p></section>
          <section class="card fin" aria-labelledby="f-ref"><div class="fh"><h3 id="f-ref">Remboursement</h3><span class="badge tone-neutral"><x-fc.icon name="minus-circle" :size="16" />Sans objet</span></div><p class="what">Aucun montant encaissé, donc rien à rembourser.</p></section>
          <section class="card fin" aria-labelledby="f-pout"><div class="fh"><h3 id="f-pout">Reversement</h3><span class="badge tone-neutral"><x-fc.icon name="minus-circle" :size="16" />Sans objet</span></div><p class="what">Le reversement au freelance n’existe qu’après une prestation validée.</p></section>
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
</x-layouts.account>

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
      <div class="span2"><dt>Échéance de réalisation</dt><dd>@if($d->dueAt){{ \App\Shared\Dates::format($d->dueAt) }}<small>Départ le {{ \App\Shared\Dates::format($d->startedAt) }} : enregistrés une seule fois.</small>@else Non démarrée<small>Elle est enregistrée une seule fois, après paiement confirmé et brief complet.</small>@endif</dd></div>
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
    <section class="{{ $d->canPay ? 'action-card' : 'card' }}" aria-labelledby="h-action"><p class="eyebrow"><x-fc.icon name="{{ $d->canPay ? 'arrow-right' : 'warn' }}" :size="16" />{{ $isF ? 'En attente du client' : ($d->canPay ? 'Action attendue' : 'En attente de paiement') }}</p>
      @if($d->paymentOpen)
        <h2 class="t-h2" id="h-action" style="margin-top:6px">{{ $isF ? 'Le client règle la commande' : 'Vérification du paiement en cours' }}</h2>
        <p style="margin-top:6px">Un paiement simulé est en cours de vérification. @unless($isF)<strong>Ne payez pas une seconde fois.</strong>@endunless</p>
        @unless($isF)<div style="margin-top:16px"><a class="btn btn-primary" href="{{ route('orders.payment', $d->reference) }}">Voir l’état du paiement</a></div>@endunless
      @elseif($d->canPay)
        <h2 class="t-h2" id="h-action" style="margin-top:6px">Payer {{ $d->price->formatted() }} FCFA (simulation)</h2>
        <p style="margin-top:6px">Demande acceptée le {{ \App\Shared\Dates::format($d->acceptedAt) }} par {{ $d->freelancerName }}.</p>
        @if($d->paymentDeadline)<p class="due due-block"><x-fc.icon name="clock" :size="20" /><span>Payer avant le <strong>{{ \App\Shared\Dates::format($d->paymentDeadline) }}</strong> <span class="{{ \App\Shared\Dates::isUrgent($d->paymentDeadline) ? 'urgent' : 'rel' }}">({{ \App\Shared\Dates::until($d->paymentDeadline) }})</span></span></p>@endif
        <div style="margin-top:16px" class="row"><a class="btn btn-primary btn-lg" href="{{ route('orders.payment', $d->reference) }}">Aller au paiement simulé</a><a class="btn btn-secondary btn-lg" href="{{ route('orders.confirm', [$d->reference, 'cancel']) }}">Annuler la commande</a></div>
        <p class="effect" style="margin-top:12px"><strong>Paiement simulé — aucun argent n’est débité.</strong> Le travail commence après paiement confirmé côté serveur et brief complet.</p>
      @else
        <h2 class="t-h2" id="h-action" style="margin-top:6px">{{ $isF ? 'Vous avez accepté : la commande attend le paiement' : 'Demande acceptée : la commande attend le paiement' }}</h2>
        <p style="margin-top:6px">Acceptée le {{ \App\Shared\Dates::format($d->acceptedAt) }} par {{ $d->freelancerName }}.</p>
        <p class="note-line" style="margin-top:12px"><x-fc.icon name="lock" :size="16" /><span><strong>Le paiement n’est pas ouvert pour cette commande.</strong> Aucun paiement ne peut être effectué ici : la commande reste « en attente de paiement », sans échéance de paiement. <strong>Le travail ne commence — et aucune échéance de réalisation ne court — qu’après paiement confirmé et brief complet.</strong></span></p>
        @unless($isF)<div style="margin-top:16px"><a class="btn btn-secondary" href="{{ route('orders.confirm', [$d->reference, 'cancel']) }}">Annuler la commande</a></div>@endunless
      @endif</section>
  @elseif($d->stateValue === 'awaiting_brief')
    <section class="{{ $isF ? 'card' : 'action-card' }}" aria-labelledby="h-action"><p class="eyebrow"><x-fc.icon name="arrow-right" :size="16" />{{ $isF ? 'En attente du client' : 'Action attendue' }}</p>
      <h2 class="t-h2" id="h-action" style="margin-top:6px">{{ $isF ? 'Paiement confirmé : le brief doit être complété' : 'Complétez le brief pour lancer le travail' }}</h2>
      <p style="margin-top:6px">Le paiement est confirmé. Le travail démarre dès que le brief est complet ({{ $d->briefMissing }} {{ $d->briefMissing > 1 ? 'éléments manquants' : 'élément manquant' }}).</p>
      <div style="margin-top:16px"><a class="btn btn-primary btn-lg" href="#brief" data-goto-tab="brief" data-goto="panel-brief">Voir le brief</a></div></section>
  @elseif($d->stateValue === 'in_progress')
    <section class="card" aria-labelledby="h-action"><p class="eyebrow"><x-fc.icon name="check-circle" :size="16" />En cours</p>
      <h2 class="t-h2" id="h-action" style="margin-top:6px">Le travail a démarré</h2>
      <p style="margin-top:6px">Départ le {{ \App\Shared\Dates::format($d->startedAt) }} · échéance de livraison le <strong>{{ \App\Shared\Dates::format($d->dueAt) }}</strong> <span class="rel">({{ \App\Shared\Dates::until($d->dueAt) }})</span>.</p>
      <p class="muted" style="margin-top:8px">La livraison arrive dans un prochain lot : aucune livraison n’est possible dans cette version.</p></section>
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
            <div><dt>Départ</dt><dd>@if($d->startedAt){{ \App\Shared\Dates::format($d->startedAt) }}<small>Enregistré une seule fois, après paiement confirmé côté serveur et brief complet.</small>@else Non enregistré<small>Enregistré une seule fois, après paiement confirmé et brief complet. Aucune échéance de réalisation ne court avant.</small>@endif</dd></div>
            @if($d->dueAt)<div><dt>Échéance de réalisation</dt><dd>{{ \App\Shared\Dates::format($d->dueAt) }}<small>Fixée au départ ; elle ne change pas.</small></dd></div>@endif
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
          <p class="muted" style="margin-top:16px">Le travail démarre après paiement confirmé <em>et</em> brief complet. Compléter le brief ne modifie <strong>jamais</strong> le prix, le périmètre ni le délai de l’accord : un changement de périmètre passe par une nouvelle demande.</p></section>
        <section class="card" aria-labelledby="h-files" style="margin-top:16px"><div class="row" style="justify-content:space-between;margin-bottom:8px"><h2 class="t-h2" id="h-files">Pièces jointes</h2>@if($d->briefRequiresFiles)<span class="badge tone-warning"><x-fc.icon name="warn" :size="16" />Au moins un fichier contrôlé requis</span>@endif</div>
          <p class="note-line"><x-fc.icon name="shield" :size="16" /><span><strong>Fichiers privés.</strong> Seules les deux parties y accèdent, et seulement après un contrôle de sécurité réussi. Ce contrôle vérifie le format et l’absence de contenu dangereux ; il ne dit rien de la qualité du contenu.</span></p>
          @if(count($d->files))
            <div class="files" style="margin-top:12px">@foreach($d->files as $f)
              <div class="file-line"><x-fc.icon name="file" :size="22" class="fi" /><span class="fn">{{ $f['name'] }}<span class="meta-f">{{ $f['size'] }}</span><span class="sec"><x-fc.icon :name="$f['icon']" :size="16" />{{ $f['label'] }}</span>@if($f['note'])<small class="muted">{{ $f['note'] }}</small>@endif</span>
                <span class="acts">@if($f['url'])<a class="btn btn-secondary" href="{{ $f['url'] }}"><x-fc.icon name="download" :size="18" />Télécharger<span class="sr-only"> {{ $f['name'] }}</span></a>@endif
                  @if($f['canRemove'])<form method="post" action="{{ route('orders.files.destroy', [$d->reference, $f['id']]) }}">@csrf<button class="btn btn-link" type="submit">Retirer<span class="sr-only"> {{ $f['name'] }}</span></button></form>@endif</span></div>
            @endforeach</div>
          @else<p class="muted" style="margin-top:12px">Aucun fichier pour l’instant.</p>@endif
          @if($d->canUpload)
            <form method="post" action="{{ route('orders.files.store', $d->reference) }}" enctype="multipart/form-data" data-once style="display:grid;gap:12px;margin-top:16px">@csrf
              <div class="field"><label for="brief-file">Ajouter un fichier</label><p class="hint" id="brief-file-h">{{ $d->uploadLimits }}</p><input class="input" id="brief-file" type="file" name="file" required aria-describedby="brief-file-h"></div>
              <div><button class="btn btn-secondary" type="submit" data-once-label="Envoi…">Envoyer le fichier</button></div></form>
          @elseif(! $isF && ! $d->uploadsEnabled && ! $d->startedAt && in_array($d->stateValue, ['awaiting_acceptance', 'awaiting_payment', 'awaiting_brief'], true))
            <p class="note-line" style="margin-top:12px"><x-fc.icon name="info" :size="16" /><span>Le dépôt de fichiers est <strong>désactivé</strong> sur cette installation : aucun service de contrôle de sécurité n’est disponible. Décrivez votre besoin dans le texte du brief.</span></p>
          @endif
        </section>
      </div>

      <div class="panel" role="tabpanel" id="panel-finances" aria-labelledby="tab-finances" tabindex="0" hidden>
        <p class="note-line"><x-fc.icon name="info" :size="16" /><span>Paiement, remboursement et reversement sont suivis séparément de l’avancement de la commande.</span></p>
        <div class="fin-grid">
          <section class="card fin" aria-labelledby="f-pay"><div class="fh"><h3 id="f-pay">Paiement</h3>@if($d->payment)<span class="badge tone-{{ $d->payment['tone'] }}"><x-fc.icon :name="$d->payment['icon']" :size="16" />{{ $d->payment['label'] }}</span>@else<span class="badge tone-neutral"><x-fc.icon name="minus-circle" :size="16" />Non démarré</span>@endif</div>
            <dl><div><dt>Montant convenu</dt><dd><x-fc.money :amount="$d->price" /></dd></div>
              @if($d->payment)<div><dt>Référence</dt><dd class="num">{{ $d->payment['reference'] }}</dd></div><div><dt>Dernière mise à jour</dt><dd>{{ \App\Shared\Dates::format($d->payment['at']) }}</dd></div>@endif</dl>
            <p class="what">@if($d->payment && $d->payment['state'] === 'confirmed')Confirmé côté serveur (simulation) : aucun argent réel n’a été débité.@elseif($d->payment && $d->payment['state'] === 'failed')Aucun montant n’a été confirmé.@elseif($d->payment)Vérification en cours auprès du prestataire simulé.@else Aucune tentative de paiement. @if($d->canPay)Le paiement simulé est ouvert pour cette commande de démonstration.@else Le paiement n’est pas ouvert pour cette commande.@endif @endif</p></section>
          <section class="card fin" aria-labelledby="f-fin"><div class="fh"><h3 id="f-fin">Situation financière</h3><span class="badge tone-{{ $d->confirmedXof > 0 ? 'success' : 'neutral' }}"><x-fc.icon :name="$d->confirmedXof > 0 ? 'check-circle' : 'minus-circle'" :size="16" />{{ $d->confirmedXof > 0 ? 'Encaissement simulé' : 'Aucun encaissement' }}</span></div>
            <dl><div><dt>Encaissé (simulé)</dt><dd>{{ \App\Shared\Money::xof($d->confirmedXof)->formatted() }} FCFA</dd></div></dl>
            <p class="what">État financier distinct de la tentative de paiement et de la commande ; enregistré une seule fois au paiement confirmé.</p></section>
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

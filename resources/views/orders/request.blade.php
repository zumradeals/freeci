@php($cover = \App\Modules\Catalog\Support\ImageUrls::present($service->images)[0]['card'] ?? null)
<x-layouts.public title="Décrire votre besoin" robots="noindex" main-class="svc-page">
<div class="container rq-wrap request-page">
  <nav class="rq-crumbs" aria-label="Fil d’Ariane"><a class="rq-back" href="{{ route('services.show', $service->slug) }}"><x-fc.icon name="arrow-right" :size="16" class="flip" />Retour au service</a><a class="rq-hide" href="{{ route('services.index') }}">Services</a><span class="rq-hide" aria-hidden="true">›</span><a class="rq-hide" href="{{ route('services.show', $service->slug) }}">{{ $service->title }}</a><span class="rq-hide" aria-hidden="true">›</span><span class="rq-hide" aria-current="page">Demande</span></nav>
  <h1>Décrire votre besoin</h1>
  <p class="rq-lead">Quelques précisions pour que {{ explode(' ', $service->sellerName)[0] }} puisse accepter votre demande. Vous ne payez rien à cette étape.</p>
  <ol class="rq-track" aria-label="Étapes">@foreach(['Votre demande', 'Réponse du freelance', 'Paiement', 'Travail'] as $i => $label)<li class="{{ $i === 0 ? 'cur' : '' }}" @if($i === 0) aria-current="step" @endif><span class="mk" aria-hidden="true">{{ $i + 1 }}</span><span class="lb">{{ $label }}</span></li>@endforeach</ol>
  @if($changed)
    <div class="notice tone-warning" role="alert"><x-fc.icon name="warn" /><p><strong>Ce service a été modifié</strong> (prix, délai ou périmètre) depuis votre consultation. Relisez les conditions mises à jour ci-dessous avant d’envoyer : rien n’a été envoyé, votre texte est conservé.</p></div>
  @endif
  @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>Vérifiez les champs signalés ci-dessous. Rien n’a été envoyé.</p></div>@endif
  <div class="rq-grid request-layout">
    <form method="post" action="{{ route('services.request.store', $service->slug) }}" class="rq-main" data-once novalidate>
      @csrf
      <input type="hidden" name="service_version" value="{{ $service->version }}">
      <input type="hidden" name="operation_key" value="{{ $operationKey }}">
      @if(! empty($selection))
        @if($selection['tier_no'])<input type="hidden" name="tier" value="{{ $selection['tier_no'] }}">@endif
        @foreach($selection['options'] as $o)<input type="hidden" name="options[]" value="{{ $o['no'] }}">@endforeach
        <section class="card rq-card tr-recap" aria-labelledby="r-sel"><div class="row row-between"><h2 class="t-h2 card-title" id="r-sel">Votre sélection</h2><a href="{{ route('services.show', $service->slug) }}#formules">Modifier</a></div>
          <div class="tr-sum" style="border:0;padding:0">
            @if($selection['tier'])<div class="r"><span>Formule {{ $selection['tier']['name'] }} · {{ $selection['base_days'] }} {{ $selection['base_days'] > 1 ? 'jours' : 'jour' }} · {{ $selection['revisions'] }} {{ $selection['revisions'] > 1 ? 'corrections' : 'correction' }}</span><span><x-fc.money :amount="\App\Shared\Money::xof($selection['base_price'])" /></span></div>@endif
            @foreach($selection['options'] as $o)<div class="r"><span>{{ $o['label'] }}@if($o['delivery_days'] != 0) · {{ $o['delivery_days'] > 0 ? '+ ' : '− ' }}{{ abs($o['delivery_days']) }} {{ abs($o['delivery_days']) > 1 ? 'jours' : 'jour' }}@endif</span><span>+ <x-fc.money :amount="\App\Shared\Money::xof((int) $o['price_xof'])" /></span></div>@endforeach
            <div class="r tot"><span>Total</span><span><x-fc.money :amount="\App\Shared\Money::xof($selection['price'])" /></span></div>
            <div class="r"><span>Délai total · corrections</span><span>{{ $selection['days'] }} {{ $selection['days'] > 1 ? 'jours' : 'jour' }} · {{ $selection['revisions'] }}</span></div>
          </div>
          @error('tier')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror @error('options')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror
        </section>
      @endif

      <section class="card rq-card" aria-labelledby="r-need"><h2 class="t-h2 card-title" id="r-need">Votre besoin</h2>
        <div class="stack" style="display:grid;gap:16px">
          @foreach($service->clientInputs as $i => $label)
            @php($err = $errors->first('answers.'.$i))
            <div class="field">
              <label for="a-{{ $i }}">{{ $label }} <span class="req">(obligatoire)</span></label>
              <textarea class="textarea" id="a-{{ $i }}" name="answers[{{ $i }}]" rows="2" maxlength="1000" required @if($err) aria-invalid="true" aria-describedby="ae-{{ $i }}" @endif>{{ old('answers.'.$i, request()->input('answers.'.$i)) }}</textarea>
              @if($err)<p class="field-error" id="ae-{{ $i }}"><x-fc.icon name="error" :size="16" />{{ $err }}</p>@endif
            </div>
          @endforeach
          <div class="field">
            <label for="notes">Précisions <span class="req">(facultatif)</span></label>
            <textarea class="textarea" id="notes" name="notes" rows="4" maxlength="3000">{{ old('notes', request()->input('notes')) }}</textarea>
            @error('notes')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror
          </div>
          <p class="note-line"><x-fc.icon name="info" :size="16" /><span>Après l’envoi de cette demande, ouvrez <strong>« Joindre mes fichiers »</strong> dans votre dossier. Vous pouvez ajouter vos photos ou documents avant la réponse du freelance et avant le paiement. Ne collez pas de coordonnées privées (téléphone, adresse e-mail).</span></p>
          @unless($uploadsEnabled)<p class="field-error" role="alert">Le dépôt de fichiers est actuellement indisponible. Si votre prestation nécessite des fichiers, contactez l’assistance avant de payer : un texte ne remplace pas un fichier obligatoire.</p>@endunless
        </div>
      </section>

      <section class="card rq-card" aria-labelledby="r-sum"><h2 class="t-h2 card-title" id="r-sum">Envoyer la demande</h2>
        <div class="field">
          <label class="check" for="conditions"><input type="checkbox" id="conditions" name="conditions" value="1" @checked(old('conditions')) required> <span>J’ai lu et j’accepte les conditions de cette demande (version {{ config('freeci.orders.conditions_version') }}).</span></label>
          @error('conditions')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror
          <p class="muted small">Les conditions juridiques complètes restent à rédiger ; la version et la date de votre acceptation sont enregistrées.</p>
        </div>
        <div class="row"><button class="btn btn-primary btn-lg" type="submit" data-once-label="Envoi en cours…">Envoyer la demande</button><a class="btn btn-secondary btn-lg" href="{{ route('services.show', $service->slug) }}">Annuler</a></div>
      </section>
    </form>

    <aside class="rq-side" aria-label="Le service demandé et la suite">
      <section class="rq-sum" aria-labelledby="r-reminder">
        @if($cover)<div class="im"><img src="{{ $cover }}" alt="" width="640" height="320"></div>@endif
        <div class="b">
          <div><p class="muted small">Le service demandé</p><h2 id="r-reminder">{{ $service->title }}</h2><p class="muted small">{{ $service->sellerName }} · version {{ $service->version }} du service, consultée maintenant</p></div>
          <p class="rq-price">@if(! empty($selection))<x-fc.money :amount="\App\Shared\Money::xof($selection['price'])" size="lg" />@else<x-fc.money :amount="$service->price" size="lg" />@endif</p>
          <ul class="rq-facts"><li><x-fc.icon name="clock" :size="20" /><span><b>{{ ! empty($selection) ? $selection['days'] : $service->deliveryDays }} {{ (! empty($selection) ? $selection['days'] : $service->deliveryDays) > 1 ? 'jours' : 'jour' }}</b> après le départ</span></li><li><x-fc.icon name="pencil" :size="20" /><span><b>{{ ! empty($selection) ? $selection['revisions'] : $service->revisionsIncluded }} {{ (! empty($selection) ? $selection['revisions'] : $service->revisionsIncluded) > 1 ? 'corrections' : 'correction' }}</b> {{ (! empty($selection) ? $selection['revisions'] : $service->revisionsIncluded) > 1 ? 'incluses' : 'incluse' }}</span></li></ul>
          <p class="rq-lock"><x-fc.icon name="lock" :size="18" /><span>Ces conditions sont <strong>figées</strong> dans votre commande au moment de l’envoi : elles ne changent pas si le service est modifié ensuite.</span></p>
        </div>
      </section>
      <section class="rq-ok" aria-labelledby="r-next"><b id="r-next">Ce qui se passera</b>
        <ol>
          <li><span class="d">1</span><span><b>{{ $service->sellerName }} reçoit votre demande</b><br>Elle apparaît dans son espace freelance.</span></li>
          <li><span class="d">2</span><span><b>Il accepte ou refuse sous {{ config('freeci.orders.response_hours') }} h</b><br>En cas de refus, le motif vous est communiqué. Sans réponse, la demande expire.</span></li>
          <li><span class="d">3</span><span><b>Après acceptation, vous pouvez accéder au paiement lorsqu’il est disponible</b><br>Joignez auparavant les fichiers nécessaires depuis votre dossier. Le mode test ou réel est indiqué sur l’écran de paiement.</span></li>
          <li><span class="d">4</span><span><b>Le travail commence après paiement confirmé et brief complet</b><br>Aucune échéance de réalisation ne court avant.</span></li>
        </ol>
      </section>
    </aside>
  </div>
</div>
</x-layouts.public>

<x-layouts.public title="Décrire votre besoin" robots="noindex" main-class="svc-page">
<div class="container request-page">
  <nav class="crumbs" aria-label="Fil d’Ariane"><a class="back-m" href="{{ route('services.show', $service->slug) }}"><x-fc.icon name="arrow-right" :size="16" class="flip" />Retour au service</a><a class="hide-m" href="{{ route('services.index') }}">Services</a><span class="sep hide-m" aria-hidden="true">›</span><a class="hide-m" href="{{ route('services.show', $service->slug) }}">{{ $service->title }}</a><span class="sep hide-m" aria-hidden="true">›</span><span class="hide-m" aria-current="page">Demande</span></nav>
  <h1 class="t-h1">Décrire votre besoin</h1>
  @if($changed)
    <div class="notice tone-warning" role="alert"><x-fc.icon name="warn" /><p><strong>Ce service a été modifié</strong> (prix, délai ou périmètre) depuis votre consultation. Relisez les conditions mises à jour ci-dessous avant d’envoyer : rien n’a été envoyé, votre texte est conservé.</p></div>
  @endif
  @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>Vérifiez les champs signalés ci-dessous. Rien n’a été envoyé.</p></div>@endif
  <div class="request-layout">
    <form method="post" action="{{ route('services.request.store', $service->slug) }}" class="stack-lg" data-once novalidate>
      @csrf
      <input type="hidden" name="service_version" value="{{ $service->version }}">
      <input type="hidden" name="operation_key" value="{{ $operationKey }}">

      <section class="card" aria-labelledby="r-reminder"><h2 class="t-h2 card-title" id="r-reminder">1. Le service demandé</h2>
        <p style="font-weight:650">{{ $service->title }}</p>
        <p class="muted small">{{ $service->sellerName }} · version {{ $service->version }} du service, consultée maintenant</p>
        <p class="row" style="margin-top:8px;gap:4px 16px"><x-fc.money :amount="$service->price" /> <span>{{ $service->deliveryDays }} {{ $service->deliveryDays > 1 ? 'jours' : 'jour' }} après le départ</span> <span>{{ $service->revisionsIncluded }} {{ $service->revisionsIncluded > 1 ? 'corrections incluses' : 'correction incluse' }}</span></p>
        <p class="muted small" style="margin-top:8px">Ces conditions sont <strong>figées</strong> dans votre commande au moment de l’envoi : elles ne changent pas si le service est modifié ensuite.</p>
      </section>

      <section class="card" aria-labelledby="r-need"><h2 class="t-h2 card-title" id="r-need">2. Votre besoin</h2>
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
          <p class="note-line"><x-fc.icon name="info" :size="16" /><span>Dans cette version, le brief est <strong>textuel</strong> : l’envoi de fichiers n’est pas encore disponible. Ne collez pas de coordonnées privées (téléphone, adresse e-mail).</span></p>
        </div>
      </section>

      <section class="card" aria-labelledby="r-sum"><h2 class="t-h2 card-title" id="r-sum">3. Ce qui se passera</h2>
        <ol class="empty-steps">
          <li><div><b>{{ $service->sellerName }} reçoit votre demande</b><span>Elle apparaît dans son espace freelance.</span></div></li>
          <li><div><b>Il accepte ou refuse sous {{ config('freeci.orders.response_hours') }} h</b><span>En cas de refus, le motif vous est communiqué. Sans réponse, la demande expire.</span></div></li>
          <li><div><b>Après acceptation, la commande attend le paiement</b><span>Le paiement n’est pas encore ouvert dans cette version : <strong>aucun paiement n’est demandé ni possible</strong>.</span></div></li>
          <li><div><b>Le travail commence après paiement confirmé et brief complet</b><span>Aucune échéance de réalisation ne court avant.</span></div></li>
        </ol>
        <div class="field" style="margin-top:16px">
          <label class="check" for="conditions"><input type="checkbox" id="conditions" name="conditions" value="1" @checked(old('conditions')) required> <span>J’ai lu et j’accepte les conditions de cette demande (version {{ config('freeci.orders.conditions_version') }}).</span></label>
          @error('conditions')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror
          <p class="muted small">Les conditions juridiques complètes restent à rédiger ; la version et la date de votre acceptation sont enregistrées.</p>
        </div>
        <div class="row" style="margin-top:16px"><button class="btn btn-primary btn-lg" type="submit" data-once-label="Envoi en cours…">Envoyer la demande</button><a class="btn btn-secondary btn-lg" href="{{ route('services.show', $service->slug) }}">Annuler</a></div>
      </section>
    </form>
  </div>
</div>
</x-layouts.public>

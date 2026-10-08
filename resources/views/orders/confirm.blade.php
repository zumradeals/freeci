<x-layouts.account :title="$title" :space="$space">
  <nav class="crumbs" aria-label="Fil d’Ariane"><a class="back-m" href="{{ route('orders.show', $d->reference) }}"><x-fc.icon name="arrow-right" :size="16" class="flip" />Retour à la commande</a><a class="hide-m" href="{{ route('orders.show', $d->reference) }}">Commande {{ $d->reference }}</a><span class="sep hide-m" aria-hidden="true">›</span><span class="hide-m" aria-current="page">{{ $title }}</span></nav>
  <div class="sx-head"><div><p class="sx-kicker">Commande {{ $d->reference }}</p><h1 id="c-title">{{ $title }}</h1><p class="muted">{{ $d->title }} · {{ $d->otherPartyLabel }} : {{ $d->otherPartyName }}</p></div></div>
  @if($errors->any())<div class="rq-info" role="alert"><x-fc.icon name="error" :size="18" /><span>Vérifiez le champ signalé ci-dessous.</span></div>@endif
  <div class="ac-grid" style="margin-top:16px">
    <form method="post" action="{{ route('orders.act', [$d->reference, $action]) }}" data-once novalidate>
      @csrf
      <input type="hidden" name="expected_version" value="{{ $d->version }}">
      <input type="hidden" name="operation_key" value="{{ $operationKey }}">
      <section class="ed-card" aria-labelledby="h-act"><h2 id="h-act">Votre décision</h2>
        @if($action === 'decline')
          <div class="field"><label for="reason">Motif du refus <span class="req">(obligatoire, visible du client)</span></label>
            <textarea class="textarea" id="reason" name="reason" rows="4" maxlength="1000" required @error('reason') aria-invalid="true" @enderror>{{ old('reason') }}</textarea>
            @error('reason')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
        @endif
        <div class="ac-acts"><button class="btn {{ $action === 'accept' ? 'btn-primary' : 'btn-secondary' }} btn-lg" type="submit" data-once-label="Enregistrement…">{{ $button }}</button><a class="btn btn-link" href="{{ route('orders.show', $d->reference) }}">Revenir</a></div></section>
    </form>
    <aside class="ac-side"><section class="ed-ck" aria-labelledby="h-wh"><h3 id="h-wh">Ce qui va se passer</h3><p>{{ $consequences }}</p></section></aside>
  </div>
</x-layouts.account>

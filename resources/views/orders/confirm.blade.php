<x-layouts.account :title="$title" :space="$space">
  <nav class="crumbs" aria-label="Fil d’Ariane"><a class="back-m" href="{{ route('orders.show', $d->reference) }}"><x-fc.icon name="arrow-right" :size="16" class="flip" />Retour à la commande</a><a class="hide-m" href="{{ route('orders.show', $d->reference) }}">Commande {{ $d->reference }}</a><span class="sep hide-m" aria-hidden="true">›</span><span class="hide-m" aria-current="page">{{ $title }}</span></nav>
  <section class="card" style="max-width:640px" aria-labelledby="c-title">
    <h1 class="t-h1" id="c-title">{{ $title }}</h1>
    <p style="margin-top:8px"><strong>{{ $d->title }}</strong><br><span class="muted">{{ $d->reference }} · {{ $d->otherPartyLabel }} : {{ $d->otherPartyName }}</span></p>
    <div class="notice tone-info" style="margin-top:16px"><x-fc.icon name="info" /><p><strong>Ce qui va se passer.</strong> {{ $consequences }}</p></div>
    @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>Vérifiez le champ signalé ci-dessous.</p></div>@endif
    <form method="post" action="{{ route('orders.act', [$d->reference, $action]) }}" data-once style="display:grid;gap:16px;margin-top:16px" novalidate>
      @csrf
      <input type="hidden" name="expected_version" value="{{ $d->version }}">
      <input type="hidden" name="operation_key" value="{{ $operationKey }}">
      @if($action === 'decline')
        <div class="field"><label for="reason">Motif du refus <span class="req">(obligatoire, visible du client)</span></label>
          <textarea class="textarea" id="reason" name="reason" rows="4" maxlength="1000" required @error('reason') aria-invalid="true" @enderror>{{ old('reason') }}</textarea>
          @error('reason')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
      @endif
      <div class="row"><button class="btn {{ $action === 'accept' ? 'btn-primary' : 'btn-secondary' }} btn-lg" type="submit" data-once-label="Enregistrement…">{{ $button }}</button><a class="btn btn-link" href="{{ route('orders.show', $d->reference) }}">Revenir</a></div>
    </form>
  </section>
</x-layouts.account>

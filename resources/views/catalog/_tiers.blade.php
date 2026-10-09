@props(['service'])
@php($own = auth()->check() && auth()->id() === $service->sellerUserId)
@php($sellerOk = app(\App\Modules\Catalog\Actions\SellerSignals::class)->for($service->sellerUserId)['available'])
@php($canOrder = $service->acceptsRequests && ! $own && $sellerOk && $service->id !== '' && ! isset($preview))
@php($tiers = $service->tiers)
@php($options = $service->options)
<section class="card tr-block" id="formules" aria-labelledby="h-formules">
  <h2 class="t-h2" id="h-formules">{{ count($tiers) >= 2 ? 'Choisissez une formule' : 'Options payantes' }}</h2>
  <form method="get" action="{{ route('services.request', $service->slug) }}" class="tr-form" data-tier-form data-base-price="{{ (int) $service->price->xof }}" data-base-days="{{ $service->deliveryDays }}" data-base-rev="{{ $service->revisionsIncluded }}">
    @if(count($tiers) >= 2)
      <div class="tr-grid" role="radiogroup" aria-labelledby="h-formules">
        @foreach($tiers as $i => $t)
          <label class="tr-card" for="formule-{{ $i + 1 }}">
            <input class="tr-radio" type="radio" id="formule-{{ $i + 1 }}" name="formule" value="{{ $i + 1 }}" required data-name="{{ $t['name'] }}" data-price="{{ (int) $t['price_xof'] }}" data-days="{{ (int) $t['delivery_days'] }}" data-rev="{{ (int) $t['revisions_included'] }}">
            <header><b>{{ $t['name'] }}</b><span class="pr"><x-fc.money :amount="\App\Shared\Money::xof((int) $t['price_xof'])" /></span></header>
            <div class="tr-body"><div class="tr-kv"><span><x-fc.icon name="clock" :size="18" /> {{ $t['delivery_days'] }} {{ $t['delivery_days'] > 1 ? 'jours' : 'jour' }}</span><span><x-fc.icon name="pencil" :size="18" /> {{ $t['revisions_included'] }} {{ $t['revisions_included'] > 1 ? 'corrections' : 'correction' }}</span></div>
              <ul>@foreach($t['includes'] as $x)<li><x-fc.icon name="check" :size="18" /><span>{{ $x }}</span></li>@endforeach</ul></div>
            <footer><span class="btn btn-secondary tr-pick" aria-hidden="true">Choisir cette formule</span></footer>
          </label>
        @endforeach
      </div>
    @endif
    @if(count($options))
      <h3 class="tr-h3">Options payantes <span class="muted small">(facultatives)</span></h3>
      <div class="tr-opts">
        @foreach($options as $i => $o)
          <label class="tr-opt" for="option-{{ $i + 1 }}"><input type="checkbox" id="option-{{ $i + 1 }}" name="options[]" value="{{ $i + 1 }}" data-label="{{ $o['label'] }}" data-price="{{ (int) $o['price_xof'] }}" data-days="{{ (int) $o['delivery_days'] }}"><span><b>{{ $o['label'] }}</b>@if($o['delivery_days'] != 0)<small>{{ $o['delivery_days'] > 0 ? '+ '.$o['delivery_days'].' '.($o['delivery_days'] > 1 ? 'jours' : 'jour').' de délai' : '− '.abs($o['delivery_days']).' '.(abs($o['delivery_days']) > 1 ? 'jours' : 'jour').' de délai' }}</small>@endif</span><b>+ <x-fc.money :amount="\App\Shared\Money::xof((int) $o['price_xof'])" /></b></label>
        @endforeach
      </div>
    @endif
    <div class="tr-sum" aria-live="polite">
      <b>Votre sélection</b>
      <div data-tier-rows><p class="muted small" style="margin:0">{{ count($tiers) >= 2 ? 'Choisissez une formule : le total s’affiche ici. Sans JavaScript, il est calculé à l’étape suivante.' : 'Cochez les options voulues : le total s’affiche ici.' }}</p></div>
    </div>
    @if($canOrder)<div><button class="btn btn-primary btn-lg" type="submit">Demander cette prestation</button><p class="muted small" style="margin:6px 0 0">Aucun paiement à cette étape. Prix et délai sont figés à l’accord du freelance. Le total est recalculé par le serveur.</p></div>
    @elseif($own)<p class="muted small">C’est votre service : vous ne pouvez pas le commander.</p>
    @elseif(! $sellerOk)<span class="btn btn-lg is-off" aria-disabled="true">Demande impossible pour le moment</span>
    @endif
  </form>
</section>

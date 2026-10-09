@props(['o', 'space'])
@if($o['state'] === 'pending')
  <article class="of-card {{ $o['mine'] ? 'mine' : 'theirs' }}" id="o-{{ $o['id'] }}" aria-label="Offre personnalisée">
    <header><b><x-fc.icon name="briefcase" :size="18" /> Offre personnalisée</b><span class="of-st wait">{{ $o['stateLabel'] }}</span></header>
    <div class="of-body"><h3>{{ $o['title'] }}</h3><p class="muted small" style="margin:0">Proposée par {{ $o['mine'] ? 'vous' : $o['freelancerName'] }} · valable jusqu’au {{ $o['validUntilShort'] }}</p>
      <div class="of-kv"><div><small>Prix</small><b><x-fc.money :amount="\App\Shared\Money::xof($o['price'])" /></b></div><div><small>Délai</small><b>{{ $o['days'] }} {{ $o['days'] > 1 ? 'jours' : 'jour' }}</b></div><div><small>Corrections</small><b>{{ $o['revisions'] }} {{ $o['revisions'] > 1 ? 'incluses' : 'incluse' }}</b></div></div>
      <p style="margin:0">{{ \Illuminate\Support\Str::limit($o['scope'], 160) }}</p></div>
    <div class="of-foot">
      @if($o['mine'])<form method="post" action="{{ route('offers.withdraw', $o['id']) }}" data-once>@csrf<button class="btn btn-link" type="submit">Retirer l’offre</button></form><a class="btn btn-link" href="{{ route('offers.show', $o['id']) }}">Voir le détail</a>
      @else<a class="btn btn-primary" href="{{ route('offers.show', $o['id']) }}">Examiner l’offre</a><span class="muted small">Valable jusqu’au {{ $o['validUntilShort'] }}</span>@endif
    </div>
  </article>
@else
  <div class="of-compact" id="o-{{ $o['id'] }}"><span class="of-st {{ $o['tone'] }}">{{ $o['stateLabel'] }}</span>
    <span><b>{{ $o['title'] }}</b> · <x-fc.money :amount="\App\Shared\Money::xof($o['price'])" />@if($o['state'] === 'accepted' && $o['respondedAt']) · le {{ $o['respondedAt'] }}@elseif($o['state'] === 'withdrawn' && $o['replaced']) · remplacée par une nouvelle offre@elseif($o['state'] === 'expired') · validité dépassée le {{ $o['validUntilShort'] }}@endif</span>
    @if($o['state'] === 'accepted' && $o['orderReference'])<a class="btn btn-secondary" href="{{ route('orders.show', $o['orderReference']) }}">Ouvrir la commande {{ $o['orderReference'] }}</a>
    @elseif($o['state'] === 'declined' && $o['declineNote'])<span class="muted small">Motif indiqué : « {{ $o['declineNote'] }} »</span>@endif
    @if($o['state'] !== 'accepted')<a class="btn btn-link" href="{{ route('offers.show', $o['id']) }}">Détail</a>@endif</div>
@endif

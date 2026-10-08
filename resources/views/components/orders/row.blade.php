@props(['c'])
<a class="od-row" href="{{ route('orders.show', $c->reference) }}">
  <span class="od-t"><b>{{ $c->title }}</b><small>Réf. <span class="num">{{ $c->reference }}</span>@if($c->environment === 'test') · <span class="tag-demo">Commande de test</span>@elseif($c->environment === 'legacy') · <span class="tag-demo">Ancienne commande</span>@endif</small></span>
  <span class="od-who"><x-fc.avatar :name="$c->otherParty" :user="$c->otherPartyId" /><span>{{ $c->otherParty }}</span></span>
  <span class="od-st"><span class="badge tone-{{ $c->tone }}"><x-fc.icon :name="$c->icon" :size="16" />{{ $c->stateLabel }}</span></span>
  <span class="od-amt"><x-fc.money :amount="$c->amount" /></span>
  <span class="od-due {{ $c->group === 'todo' ? 'u' : '' }}">{{ $c->info }}</span>
  <span class="od-ch" aria-hidden="true"><x-fc.icon name="arrow-right" :size="18" /></span>
</a>

@props(['c'])
<article class="order-card">
  <h3 class="ttl"><a class="stretch" href="{{ route('orders.show', $c->reference) }}">{{ $c->title }}</a></h3>
  <p class="am"><x-fc.money :amount="$c->amount" /></p>
  <p class="mt"><span class="num">{{ $c->reference }}</span> · {{ $c->otherParty }}@if($c->environment === 'test') · <span class="tag-demo">Commande de test</span>@elseif($c->environment === 'legacy') · <span class="tag-demo">Ancienne commande</span>@endif@if($c->isDemo) · <span class="tag-demo">Démonstration</span>@endif</p>
  <div class="st"><span class="badge tone-{{ $c->tone }}"><x-fc.icon :name="$c->icon" :size="16" />{{ $c->stateLabel }}</span>@if($c->info)<span class="due-t">{{ $c->info }}</span>@endif</div>
  <span class="chev" aria-hidden="true"><x-fc.icon name="arrow-right" :size="20" /></span>
</article>

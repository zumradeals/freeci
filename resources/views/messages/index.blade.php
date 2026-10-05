<x-layouts.account title="Messages" :space="$space">
  <header class="page-head"><div class="row-top"><div><p class="eyebrow">{{ $space === 'freelancer' ? 'Espace freelance' : 'Espace client' }}</p><h1 class="t-h1">Messages</h1></div></div></header>
  <p class="note-line"><x-fc.icon name="lock" :size="16" /><span>Conversations privées entre deux personnes. <strong>Un message ou une pièce jointe n’est jamais une livraison, une modification de l’accord, l’acceptation d’un report ni une validation</strong> : ces actions se font depuis la commande.</span></p>
  @if(count($conversations))
    <div class="order-list">@foreach($conversations as $c)
      <article class="order-card"><h2 class="ttl"><a class="stretch" href="{{ route('messages.show', array_filter(['conversation' => $c['id'], 'espace' => $space === 'freelancer' ? 'freelance' : null])) }}">{{ $c['with'] }}</a></h2>
        <p class="am">@if($c['unread'] > 0)<span class="count-badge" aria-label="{{ $c['unread'] }} non lu{{ $c['unread'] > 1 ? 's' : '' }}">{{ $c['unread'] }}</span>@endif</p>
        <p class="mt">{{ $c['kind'] }} · {{ $c['context'] }}@if($c['linkedOrder']) · rattachée à la commande @endif</p>
        <div class="st"><span class="due-t">{{ $c['snippet'] }}</span><span class="muted small">{{ $c['when'] }}</span></div><span class="chev" aria-hidden="true"><x-fc.icon name="arrow-right" :size="20" /></span></article>
    @endforeach</div>
  @else
    <div class="card empty"><span class="ico-lg"><x-fc.icon name="message" :size="26" /></span><p style="font-weight:600">Aucune conversation pour l’instant.</p><p class="muted" style="max-width:36em">Posez une question depuis la fiche d’un service, une proposition ou une commande : la conversation apparaît ici.</p></div>
  @endif
  @if(count($blocked))<section class="card" style="margin-top:16px" aria-labelledby="h-bl"><h2 class="t-h3" id="h-bl">Contacts bloqués</h2><ul class="stack-sm">@foreach($blocked as $b)<li>{{ $b['name'] }} <span class="muted small">depuis le {{ $b['since'] }}</span></li>@endforeach</ul><p class="muted small">Pour débloquer, ouvrez la conversation concernée.</p></section>@endif
</x-layouts.account>

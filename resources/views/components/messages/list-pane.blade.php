@props(['conversations', 'blocked', 'space', 'current' => null])
@php
  $unreadTotal = collect($conversations)->sum('unread');
  $onlyUnread = request('filtre') === 'non-lues';
  $shown = $onlyUnread ? collect($conversations)->where('unread', '>', 0) : collect($conversations);
  $q = fn (array $extra = []) => array_filter(array_merge(['espace' => $space === 'freelancer' ? 'freelance' : null], $extra));
@endphp
<section class="ms-list" aria-label="Conversations">
  <div class="ms-lh"><h1>Messages</h1></div>
  <nav class="ms-tabs" aria-label="Filtrer les conversations">
    <a class="ms-tab {{ $onlyUnread ? '' : 'on' }}" href="{{ route('messages.index', $q()) }}" @unless($onlyUnread) aria-current="page" @endunless>Toutes</a>
    <a class="ms-tab {{ $onlyUnread ? 'on' : '' }}" href="{{ route('messages.index', $q(['filtre' => 'non-lues'])) }}" @if($onlyUnread) aria-current="page" @endif>Non lues{{ $unreadTotal > 0 ? ' · '.$unreadTotal : '' }}</a>
  </nav>
  <div class="ms-items">
    @forelse($shown as $c)
      <a class="ms-item {{ $current === $c['id'] ? 'on' : '' }} {{ $c['unread'] > 0 ? 'unread' : '' }}" href="{{ route('messages.show', $q(['conversation' => $c['id']])) }}" @if($current === $c['id']) aria-current="page" @endif>
        <span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($c['with'], 0, 1)) }}</span>
        <span class="ms-name">{{ $c['with'] }}</span><span class="ms-when">{{ $c['when'] }}</span>
        <span class="ms-snip">{{ $c['snippet'] }}</span><span>@if($c['unread'] > 0)<span class="ms-n" aria-label="{{ $c['unread'] }} non lu{{ $c['unread'] > 1 ? 's' : '' }}">{{ $c['unread'] }}</span>@endif</span>
        <span class="ms-ctx">{{ $c['kind'] }} · {{ $c['context'] }}@if($c['linkedOrder']) · rattachée à la commande @endif</span>
      </a>
    @empty
      <div class="ms-empty"><x-fc.icon name="message" :size="26" /><p style="font-weight:600">{{ $onlyUnread ? 'Aucune conversation non lue.' : 'Aucune conversation pour l’instant.' }}</p>@unless($onlyUnread)<p class="muted small">Posez une question depuis la fiche d’un service, une proposition ou une commande : la conversation apparaît ici.</p>@endunless</div>
    @endforelse
    @if(count($blocked))<div class="ms-blocked"><h2>Contacts bloqués</h2><ul>@foreach($blocked as $b)<li>{{ $b['name'] }} <span class="muted small">depuis le {{ $b['since'] }}</span></li>@endforeach</ul><p class="muted small">Pour débloquer, ouvrez la conversation concernée.</p></div>@endif
  </div>
</section>

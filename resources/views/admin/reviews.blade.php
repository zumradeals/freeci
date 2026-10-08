<x-layouts.admin title="Avis">
  <div class="page-body">
    <div class="sx-head"><div><p class="sx-kicker">Administration</p><h1>Modération des avis</h1><p class="muted">Masquer ou rétablir un avis ou une réponse avec un motif ; jamais de réécriture.</p></div></div>
    <div class="rq-info"><x-fc.icon name="info" :size="18" /><span><strong>Vous modérez, vous ne réécrivez pas.</strong> Masquer ou rétablir un avis ou une réponse exige une catégorie (pour un masquage) et un motif ; la note et le commentaire ne sont jamais modifiés, l’historique est conservé et les moyennes se recalculent seules. <strong>Une note négative, seule, n’est pas un motif de retrait.</strong> Aucun avis ne peut être créé par l’administration.</span></div>
    <nav class="sv-tabs" aria-label="Filtre" style="margin-top:16px">
      <a class="sv-tab {{ $filter === 'reported' ? 'on' : '' }}" href="{{ route('admin.reviews', ['filtre' => 'reported']) }}" @if($filter === 'reported') aria-current="page" @endif>Signalés <span class="n">{{ $counts['reported'] }}</span></a>
      <a class="sv-tab {{ $filter === 'hidden' ? 'on' : '' }}" href="{{ route('admin.reviews', ['filtre' => 'hidden']) }}" @if($filter === 'hidden') aria-current="page" @endif>Masqués <span class="n">{{ $counts['hidden'] }}</span></a>
      <a class="sv-tab {{ $filter === 'all' ? 'on' : '' }}" href="{{ route('admin.reviews', ['filtre' => 'all']) }}" @if($filter === 'all') aria-current="page" @endif>Tous (commandes réelles)</a>
      <a class="sv-tab {{ $filter === 'test' ? 'on' : '' }}" href="{{ route('admin.reviews', ['filtre' => 'test']) }}" @if($filter === 'test') aria-current="page" @endif>Aperçus de test</a></nav>
    <div style="display:grid;gap:14px">
    @forelse($page as $r)
      <article class="rv-c {{ $r['reports'] > 0 ? 'rep' : '' }}"><div class="rv-top"><span class="rv-meta">Commande {{ $r['order'] }} · {{ $r['origin'] }} · client {{ $r['author'] }} → freelance {{ $r['subject'] }}</span>
        <span style="display:flex;gap:8px;flex-wrap:wrap">@if(! $r['public'])<span class="tag-demo">Test : jamais public</span>@elseif($r['hidden'])<span class="badge tone-warning">Masqué</span>@elseif($r['waiting'])<span class="badge tone-neutral">Publication le {{ $r['visibleAt'] }}</span>@else<span class="badge tone-success">Publié</span>@endif
        @if($r['reports'] > 0)<span class="badge tone-error">{{ $r['reports'] }} signalement{{ $r['reports'] > 1 ? 's' : '' }} en cours</span>@endif</span></div>
        <div><span class="rv-st" aria-hidden="true">{{ str_repeat('★', (int) $r['rating']) }}{{ str_repeat('☆', 5 - (int) $r['rating']) }}</span> <strong>{{ $r['rating'] }}/5</strong></div>
        <p style="margin:0">{!! nl2br(e($r['comment'])) !!}</p>
        @if($r['reply'])<div class="rv-reply"><b>Réponse du freelance{{ $r['reply']['hidden'] ? ' (masquée)' : '' }}</b><span>{!! nl2br(e($r['reply']['body'])) !!}</span></div>@endif
        @if($r['public'])
          @foreach(array_filter([['review', $r['hidden'], 'l’avis'], $r['reply'] ? ['reply', $r['reply']['hidden'], 'la réponse'] : null]) as [$target, $isHidden, $label])
            <details class="rv-act"><summary><span>{{ $isHidden ? 'Rétablir' : 'Masquer' }} {{ $label }}</span><x-fc.icon name="chev-down" :size="16" /></summary>
              <form method="post" action="{{ route('admin.reviews.act', [$r['id'], $isHidden ? 'retablir' : 'masquer']) }}" class="in">@csrf<input type="hidden" name="target" value="{{ $target }}">
                <h3 class="sr-only">{{ $isHidden ? 'Rétablir' : 'Masquer' }} {{ $label }}</h3>
                @unless($isHidden)<div class="field"><label for="c-{{ $r['id'] }}-{{ $target }}">Catégorie</label><select class="select" id="c-{{ $r['id'] }}-{{ $target }}" name="category" required><option value="">Choisir…</option>@foreach($categories as $k => $label2)<option value="{{ $k }}">{{ $label2 }}</option>@endforeach</select></div>@endunless
                <div class="field"><label for="m-{{ $r['id'] }}-{{ $target }}">Motif (20 caractères minimum)</label><textarea class="textarea" id="m-{{ $r['id'] }}-{{ $target }}" name="reason" rows="3" minlength="20" maxlength="1000" required></textarea></div>
                <div><button class="btn {{ $isHidden ? 'btn-secondary' : 'btn-primary' }}" type="submit">{{ $isHidden ? 'Rétablir' : 'Masquer' }}</button></div></form></details>
          @endforeach
        @endif
        @if(count($r['history']))<details class="rv-hist"><summary>Historique de modération ({{ count($r['history']) }})</summary><ul class="small" style="margin:6px 0 0">@foreach($r['history'] as $h)<li>{{ $h['when'] }} — {{ $h['who'] }} : {{ $h['target'] }} {{ $h['action'] }}@if($h['category']) ({{ $h['category'] }})@endif — {{ $h['reason'] }}</li>@endforeach</ul></details>@endif
      </article>
    @empty<div class="ed-card empty" style="justify-items:center;text-align:center"><span class="ico-lg"><x-fc.icon name="check-circle" :size="26" /></span><p style="font-weight:600">Aucun avis dans cette liste.</p></div>@endforelse
    </div>
    <x-admin.pager :p="$page" />
  </div>
</x-layouts.admin>

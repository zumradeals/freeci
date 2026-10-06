<x-layouts.admin title="Avis">
  <header class="page-head"><div class="row-top"><div><p class="eyebrow">Administration</p><h1 class="t-h1">Modération des avis</h1></div></div></header>
  <div class="notice tone-info"><x-fc.icon name="info" /><p><strong>Vous modérez, vous ne réécrivez pas.</strong> Masquer ou rétablir un avis ou une réponse exige une catégorie (pour un masquage) et un motif ; la note et le commentaire ne sont jamais modifiés, l’historique est conservé et les moyennes se recalculent seules. <strong>Une note négative, seule, n’est pas un motif de retrait.</strong> Aucun avis ne peut être créé par l’administration.</p></div>
  <nav class="tabs" aria-label="Filtre" style="margin:16px 0">
    <a href="{{ route('admin.reviews', ['filtre' => 'reported']) }}" @if($filter === 'reported') aria-current="page" @endif>Signalés ({{ $counts['reported'] }})</a>
    <a href="{{ route('admin.reviews', ['filtre' => 'hidden']) }}" @if($filter === 'hidden') aria-current="page" @endif>Masqués ({{ $counts['hidden'] }})</a>
    <a href="{{ route('admin.reviews', ['filtre' => 'all']) }}" @if($filter === 'all') aria-current="page" @endif>Tous (commandes réelles)</a>
    <a href="{{ route('admin.reviews', ['filtre' => 'test']) }}" @if($filter === 'test') aria-current="page" @endif>Aperçus de test</a></nav>
  @forelse($page as $r)
    <section class="card" style="margin-top:12px"><p class="muted small">Commande {{ $r['order'] }} · {{ $r['origin'] }} · client {{ $r['author'] }} → freelance {{ $r['subject'] }}
      @if(! $r['public'])<span class="tag-demo">Test : jamais public</span>@elseif($r['hidden'])<span class="badge tone-warning">Masqué</span>@elseif($r['waiting'])<span class="badge tone-neutral">Publication le {{ $r['visibleAt'] }}</span>@else<span class="badge tone-success">Publié</span>@endif
      @if($r['reports'] > 0)<span class="badge tone-error">{{ $r['reports'] }} signalement{{ $r['reports'] > 1 ? 's' : '' }} en cours</span>@endif</p>
      <p style="margin-top:6px"><strong>{{ $r['rating'] }}/5</strong></p><blockquote class="quote" style="margin:6px 0">{!! nl2br(e($r['comment'])) !!}</blockquote>
      @if($r['reply'])<blockquote class="quote" style="margin:6px 0"><strong>Réponse{{ $r['reply']['hidden'] ? ' (masquée)' : '' }} :</strong> {!! nl2br(e($r['reply']['body'])) !!}</blockquote>@endif
      @if($r['public'])
        @foreach(array_filter([['review', $r['hidden'], 'l’avis'], $r['reply'] ? ['reply', $r['reply']['hidden'], 'la réponse'] : null]) as [$target, $isHidden, $label])
          <form method="post" action="{{ route('admin.reviews.act', [$r['id'], $isHidden ? 'retablir' : 'masquer']) }}" class="stack-sm" style="margin-top:10px">@csrf<input type="hidden" name="target" value="{{ $target }}">
            <h3 class="t-h3">{{ $isHidden ? 'Rétablir' : 'Masquer' }} {{ $label }}</h3>
            @unless($isHidden)<div class="field"><label for="c-{{ $r['id'] }}-{{ $target }}">Catégorie</label><select class="select" id="c-{{ $r['id'] }}-{{ $target }}" name="category" required><option value="">Choisir…</option>@foreach($categories as $k => $label2)<option value="{{ $k }}">{{ $label2 }}</option>@endforeach</select></div>@endunless
            <div class="field"><label for="m-{{ $r['id'] }}-{{ $target }}">Motif (20 caractères minimum)</label><textarea class="input" id="m-{{ $r['id'] }}-{{ $target }}" name="reason" rows="2" minlength="20" maxlength="1000" required></textarea></div>
            <button class="btn {{ $isHidden ? 'btn-secondary' : 'btn-primary' }}" type="submit">{{ $isHidden ? 'Rétablir' : 'Masquer' }}</button></form>
        @endforeach
      @endif
      @if(count($r['history']))<details style="margin-top:10px"><summary>Historique de modération ({{ count($r['history']) }})</summary><ul class="small">@foreach($r['history'] as $h)<li>{{ $h['when'] }} — {{ $h['who'] }} : {{ $h['target'] }} {{ $h['action'] }}@if($h['category']) ({{ $h['category'] }})@endif — {{ $h['reason'] }}</li>@endforeach</ul></details>@endif
    </section>
  @empty<div class="card empty" style="margin-top:12px"><span class="ico-lg"><x-fc.icon name="check-circle" :size="26" /></span><p style="font-weight:600">Aucun avis dans cette liste.</p></div>@endforelse
  <x-admin.pager :p="$page" />
</x-layouts.admin>

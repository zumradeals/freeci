<x-layouts.account title="Mes services" space="freelancer">
  <header class="page-head"><div class="row-top"><div><p class="eyebrow">Espace freelance</p><h1 class="t-h1">Mes services</h1></div></div></header>
  @if(count($services))
    <div class="card"><ul class="stack-sm">@foreach($services as $s)<li class="row" style="justify-content:space-between;gap:8px 16px"><span style="min-width:0">@if($s['published'])<a href="{{ route('services.show', $s['slug']) }}">{{ $s['title'] }}</a>@else{{ $s['title'] }}@endif<br><small class="muted">{{ $s['status'] }}</small></span><x-fc.money :amount="$s['price']" /></li>@endforeach</ul></div>
    <p class="note-line"><x-fc.icon name="info" :size="16" /><span>Lecture seule : la modification des services arrive dans un prochain lot.</span></p>
  @else
    <div class="card empty"><span class="ico-lg"><x-fc.icon name="briefcase" :size="26" /></span><p style="font-weight:600">Aucun service à votre nom.</p><p class="muted" style="max-width:36em">La création et la publication de services arrivent dans un prochain lot.</p></div>
  @endif
</x-layouts.account>

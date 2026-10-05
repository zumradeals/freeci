<x-layouts.account title="Mes missions" space="client">
  <header class="page-head"><div class="row-top"><div><p class="eyebrow">Espace client</p><h1 class="t-h1">Mes missions</h1></div><a class="btn btn-primary" href="{{ route('client.missions.new') }}">Publier une mission</a></div></header>
  @if(count($missions))
    <div class="stack-lg">@foreach($missions as $m)
      <article class="card" aria-labelledby="m-{{ $m['id'] }}">
        <div class="row" style="justify-content:space-between;gap:8px 16px;align-items:flex-start"><h2 class="t-h3" id="m-{{ $m['id'] }}" style="min-width:0"><a href="{{ route('client.missions.show', $m['id']) }}">{{ $m['title'] }}</a></h2>@if($m['budget'])<x-fc.money :amount="$m['budget']" />@endif</div>
        <p style="margin-top:8px"><span class="badge tone-{{ $m['tone'] }}"><x-fc.icon :name="$m['icon']" :size="16" />{{ $m['status'] }}</span>@if($m['proposals'])<span class="muted small"> · {{ $m['proposals'] }} proposition{{ $m['proposals'] > 1 ? 's' : '' }}</span>@endif</p>
        @if($m['deadline'])<p class="muted small" style="margin-top:6px">Date limite de candidature : {{ $m['deadline'] }}</p>@endif
        @if($m['needsAction'])<p class="note-line" style="margin-top:8px"><x-fc.icon name="warn" :size="16" /><span><strong>Action attendue de votre part.</strong> {{ $m['note'] }}</span></p>@endif
        <div class="row" style="margin-top:12px"><a class="btn btn-secondary" href="{{ route('client.missions.show', $m['id']) }}">Ouvrir<span class="sr-only"> {{ $m['title'] }}</span></a>@if($m['proposals'])<a class="btn btn-secondary" href="{{ route('client.missions.proposals', $m['id']) }}">Voir les propositions</a>@endif</div>
      </article>
    @endforeach</div>
  @else
    <div class="card empty"><span class="ico-lg"><x-fc.icon name="briefcase" :size="26" /></span><p style="font-weight:600">Aucune mission pour l’instant.</p><p class="muted" style="max-width:36em">Décrivez votre besoin : des freelances vous envoient des propositions à prix ferme, vous en comparez puis en retenez une. Votre mission reste invisible tant que la modération ne l’a pas approuvée.</p><a class="btn btn-primary" href="{{ route('client.missions.new') }}">Publier une mission</a></div>
  @endif
</x-layouts.account>

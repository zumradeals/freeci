<x-layouts.public :title="$m['title']" :description="mb_substr($m['description'], 0, 150)">
<div class="container" style="padding-block:24px;max-width:860px">
  @isset($preview)<div class="notice tone-warning" role="note"><x-fc.icon name="flag" /><p><strong>Aperçu — non publié.</strong> Rendu public de votre version de travail ; personne d’autre ne le voit. <a href="{{ $preview }}">Revenir à l’édition</a></p></div>@endisset
  <nav class="crumbs" aria-label="Fil d’Ariane"><a class="back-m" href="{{ route('missions.index') }}"><x-fc.icon name="arrow-right" :size="16" class="flip" />Missions</a><a class="hide-m" href="{{ route('missions.index') }}">Missions</a><span class="sep hide-m" aria-hidden="true">›</span><span class="hide-m" aria-current="page">{{ $m['category'] }}</span></nav>
  <p class="eyebrow">{{ $m['category'] }}@if($m['isDemo']) · <span class="tag-demo">Exemple fictif</span>@endif</p>
  <h1 class="t-h1">{{ $m['title'] }}</h1>
  <section class="card" style="margin-top:12px"><dl class="meta"><div><dt>Budget du client</dt><dd><x-fc.money :amount="$m['budget']" /></dd></div><div><dt>Candidatures jusqu’au</dt><dd>{{ $m['deadline'] }}</dd></div></dl>
    @if($m['status'] !== 'preview' && ! $m['accepting'])<p class="note-line" style="margin-top:8px"><x-fc.icon name="lock" :size="16" /><span><strong>Cette mission n’accepte plus de propositions.</strong></span></p>@endif</section>
  <section style="margin-top:16px" aria-labelledby="h-d"><h2 class="t-h2" id="h-d">Le besoin</h2><p>{!! nl2br(e($m['description'])) !!}</p></section>
  @if(count($m['inputs']))<section style="margin-top:16px" aria-labelledby="h-i"><h2 class="t-h2" id="h-i">Ce que le client fournira</h2><ul class="checklist need">@foreach($m['inputs'] as $i)<li><x-fc.icon name="clipboard" /><span>{{ $i }}</span></li>@endforeach</ul>
    <p class="muted small">Seuls les intitulés sont publics ; les réponses ne sont communiquées qu’au freelance retenu.</p></section>@endif
  <p class="note-line" style="margin-top:16px"><x-fc.icon name="shield" :size="16" /><span>Aucune pièce jointe ni coordonnée du client n’est publiée. Les échanges passent par FreeCI.</span></p>
  @unless(isset($preview))
  <section class="card" style="margin-top:16px" aria-labelledby="h-p"><h2 class="t-h2" id="h-p">Votre proposition</h2>
    @if($m['own'])<p>C’est votre mission. <a href="{{ route('client.missions') }}">Gérer mes missions</a></p>
    @elseif($m['mine'])
      <p><span class="badge tone-{{ $m['mine']['state'] === 'selected' ? 'success' : ($m['mine']['stale'] || $m['mine']['expired'] ? 'warning' : 'info') }}">{{ ['active' => 'Proposition envoyée', 'selected' => 'Retenue', 'withdrawn' => 'Retirée', 'released' => 'Libérée', 'closed' => 'Mission terminée'][$m['mine']['state']] }}</span> version {{ $m['mine']['number'] }} · <x-fc.money :amount="$m['mine']['price']" /> · valable jusqu’au {{ $m['mine']['validUntil'] }}</p>
      @if($m['mine']['stale'] && $m['mine']['state'] === 'active')<p class="note-line"><x-fc.icon name="warn" :size="16" /><span><strong>Besoin modifié depuis votre proposition.</strong> Reconfirmez-la pour qu’elle puisse être retenue.</span></p>@endif
      @if($m['accepting'] && $m['mine']['state'] !== 'selected')<div class="row" style="margin-top:12px"><a class="btn btn-primary" href="{{ route('missions.proposal', $m['slug']) }}">{{ $m['mine']['stale'] ? 'Reconfirmer ma proposition' : ($m['mine']['state'] === 'active' ? 'Réviser ma proposition' : 'Proposer à nouveau') }}</a>@if($m['mine']['state'] === 'active')<a class="btn btn-link" href="{{ route('freelance.proposals.withdraw', $m['mine']['id']) }}">Retirer ma proposition</a>@endif</div>@endif
    @elseif($m['accepting'])
      @guest<p>Connectez-vous pour proposer vos services.</p><a class="btn btn-primary" href="{{ route('login') }}">Se connecter</a>
      @else<p>Vous avez les compétences pour ce besoin ? Envoyez une proposition à prix ferme ; seul le client la verra.</p><a class="btn btn-primary btn-lg" href="{{ route('missions.proposal', $m['slug']) }}">Faire une proposition</a>@endguest
    @else<p class="muted">Cette mission n’accepte plus de propositions.</p>@endif
  </section>
  @endunless
</div>
</x-layouts.public>

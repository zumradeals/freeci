@php($c = $dl['corrections'])
<div class="dl-counter"><span class="dl-dots" aria-hidden="true">@for($i = 0; $i < $c['included']; $i++)<i class="{{ $i < $c['used'] ? 'u' : '' }}"></i>@endfor</span><p class="muted" style="margin:0">Corrections : <strong style="color:var(--ink-900)">{{ $c['used'] }} utilisée{{ $c['used'] > 1 ? 's' : '' }} sur {{ $c['included'] }}</strong> ({{ $c['remaining'] }} restante{{ $c['remaining'] > 1 ? 's' : '' }}). Chaque livraison est une version conservée.</p></div>

@forelse($dl['deliveries'] as $v)
  @if($v['isLatest'])
    <section class="ed-card dl-ver" aria-labelledby="livraison-v{{ $v['version'] }}"><div class="dl-vh"><h3 id="livraison-v{{ $v['version'] }}" tabindex="-1">Livraison v{{ $v['version'] }}</h3><span class="badge tone-{{ $v['tone'] }}"><x-fc.icon :name="$v['icon']" :size="16" />{{ $v['label'] }}</span></div>
      <p class="muted small">Déposée le {{ $v['when'] }} par {{ $v['author'] }}@if($v['answers']) · {{ $v['answers'] }}@endif</p>
      @if($v['message'])<p style="margin-top:10px">« {!! nl2br(e($v['message'])) !!} »</p>@endif
      <p class="dl-notice"><x-fc.icon name="shield" :size="18" /><span><strong>Contrôle de sécurité ≠ qualité du travail.</strong> Il vérifie le format des fichiers et l’absence de contenu dangereux ; il ne dit rien de la qualité de la livraison, que {{ $isF ? 'le client' : 'vous' }} seul examine.</span></p>
      @if(count($v['files']))@include('orders._files', ['files' => $v['files']])@else<p class="muted">Livraison sans fichier : le message ci-dessus fait foi.</p>@endif
      @if($v['correction'])<div class="dl-q"><strong>Correction {{ $v['correction']['number'] }} sur {{ $c['included'] }} demandée le {{ $v['correction']['when'] }}</strong><span>« {{ $v['correction']['reason'] }} »</span></div>@endif
      @if($dl['canDecide'])
        <div class="dl-dec"><h4 class="dl-dh" id="h-decision">Votre décision sur la livraison v{{ $v['version'] }}</h4>
          <div class="dl-choices">
            <div class="dl-choice"><h4>Demander une correction</h4>
              <ul><li>Dans le périmètre convenu.</li><li>@if($c['remaining'] > 0)Il vous reste <strong>{{ $c['remaining'] }} correction{{ $c['remaining'] > 1 ? 's' : '' }} sur {{ $c['included'] }}</strong>@if($c['remaining'] === 1) : ce sera la dernière @endif.@else<strong>Aucune correction incluse restante</strong> ({{ $c['used'] }} sur {{ $c['included'] }}).@endif</li><li>{{ $d->freelancerName }} dépose une version v{{ $v['version'] + 1 }}.</li></ul>
              @if($c['remaining'] > 0)<a class="btn btn-secondary" href="{{ route('orders.correction', $d->reference) }}">Demander une correction</a>@else<button class="btn btn-secondary" type="button" disabled>Demander une correction</button>@endif</div>
            <div class="dl-choice go"><h4>Valider la livraison</h4>
              <ul><li>La commande est <strong>clôturée</strong>.</li><li>Plus de correction possible.</li><li>Aucun reversement n’est confirmé ni déclenché par cette étape.</li></ul>
              <a class="btn btn-primary" href="{{ route('orders.validation', $d->reference) }}">Valider la livraison</a></div>
          </div>
          @if($c['remaining'] === 0)
            <div class="dl-notice"><x-fc.icon name="info" :size="18" /><div><p><strong>Vous n’êtes pas obligé de valider.</strong> Vos corrections incluses sont épuisées : vous pouvez laisser cette livraison <strong>non validée</strong>. La commande reste ouverte et rien ne vous force à accepter.</p>
              @if($dl['disagreement'])<p style="margin-top:6px">Désaccord signalé le {{ $dl['disagreement']['when'] }} : besoin de suivi enregistré. <strong>Aucun support n’a été contacté automatiquement.</strong></p>
              @elseif($dl['canSignalDisagreement'])<p style="margin-top:6px"><a href="{{ route('orders.disagreement', $d->reference) }}">Signaler un désaccord</a> : un besoin de suivi est enregistré (ce n’est pas un litige et aucun support n’est contacté automatiquement).</p>@endif</div></div>
          @endif
          @if($dl['review'])<p class="dl-notice"><x-fc.icon name="shield" :size="18" /><span>Vous décidez à votre rythme (délai d’examen indicatif : {{ \App\Shared\Dates::format($dl['review']['deadline']) }}). <strong>Sans réponse, rien n’est validé ni clôturé à votre place</strong> : la commande reste ouverte.</span></p>@endif</div>
      @endif
    </section>
  @else
    <details class="dl-sec"><summary><span>Livraison v{{ $v['version'] }} <span class="badge tone-{{ $v['tone'] }}" style="margin-left:6px">{{ $v['label'] }}</span> </span><x-fc.icon name="chev-down" :size="20" /></summary><div class="dl-body">
      <p class="muted small">Déposée le {{ $v['when'] }} par {{ $v['author'] }}@if($v['answers']) · {{ $v['answers'] }}@endif</p>
      @if($v['message'])<p style="margin-top:8px">« {!! nl2br(e($v['message'])) !!} »</p>@endif
      @if($v['correction'])<div class="dl-q"><strong>Correction {{ $v['correction']['number'] }} sur {{ $c['included'] }} demandée le {{ $v['correction']['when'] }}</strong><span>« {{ $v['correction']['reason'] }} »</span></div>@endif
      @if(count($v['files']))@include('orders._files', ['files' => $v['files']])@endif</div></details>
  @endif
@empty
  <section class="ed-card empty" style="justify-items:center;text-align:center"><span class="ico-lg"><x-fc.icon name="file" :size="26" /></span><h3 class="t-h2">Aucune livraison pour l’instant</h3>
    <p class="muted" style="max-width:36em">{{ $isF ? 'Déposez votre livraison depuis l’action attendue ci-dessus : le client ne la voit qu’une fois soumise.' : $d->freelancerName.' n’a pas encore soumis de livraison. Vous serez invité à l’examiner dès qu’elle sera soumise.' }}</p></section>
@endforelse

@if($dl['extensionHistory'])
  <section class="ed-card" aria-labelledby="h-extlog"><h3 class="dl-dh" id="h-extlog">Reports d’échéance</h3>
    <ol class="timeline">@foreach($dl['extensionHistory'] as $e)
      <li><span class="pt" aria-hidden="true"></span><div><p class="tt">Report {{ mb_strtolower($e['label']) }} : {{ $e['previous'] }} → {{ $e['proposed'] }}</p><p class="when">Proposé le {{ $e['when'] }}@if($e['decidedAt']) · décision le {{ $e['decidedAt'] }}@endif</p><p class="why">« {{ $e['reason'] }} »@if($e['note']) — réponse : « {{ $e['note'] }} »@endif</p></div></li>
    @endforeach</ol></section>
@endif

@php($c = $dl['corrections'])
<p class="muted">Corrections : <strong style="color:var(--ink-900)">{{ $c['used'] }} utilisée{{ $c['used'] > 1 ? 's' : '' }} sur {{ $c['included'] }}</strong> ({{ $c['remaining'] }} restante{{ $c['remaining'] > 1 ? 's' : '' }}). Chaque livraison est une version conservée.</p>

@forelse($dl['deliveries'] as $v)
  @if($v['isLatest'])
    <section class="card delivery" aria-labelledby="livraison-v{{ $v['version'] }}"><div class="dhead"><h3 class="t-h3" id="livraison-v{{ $v['version'] }}" tabindex="-1">Livraison v{{ $v['version'] }}</h3><span class="badge tone-{{ $v['tone'] }}"><x-fc.icon :name="$v['icon']" :size="16" />{{ $v['label'] }}</span></div>
      <p class="muted small">Déposée le {{ $v['when'] }} par {{ $v['author'] }}@if($v['answers']) · {{ $v['answers'] }}@endif</p>
      @if($v['message'])<p style="margin-top:10px">« {!! nl2br(e($v['message'])) !!} »</p>@endif
      <p class="note-line" style="margin-top:12px"><x-fc.icon name="shield" :size="16" /><span><strong>Contrôle de sécurité ≠ qualité du travail.</strong> Il vérifie le format des fichiers et l’absence de contenu dangereux ; il ne dit rien de la qualité de la livraison, que {{ $isF ? 'le client' : 'vous' }} seul examine.</span></p>
      @if(count($v['files']))@include('orders._files', ['files' => $v['files']])@else<p class="muted" style="margin-top:12px">Livraison sans fichier : le message ci-dessus fait foi.</p>@endif
      @if($v['correction'])<div class="quote"><strong>Correction {{ $v['correction']['number'] }} sur {{ $c['included'] }} demandée le {{ $v['correction']['when'] }}</strong><br>« {{ $v['correction']['reason'] }} »</div>@endif
      @if($dl['canDecide'])
        <div class="decision-wrap"><h4 class="t-h3" id="h-decision">Votre décision sur la livraison v{{ $v['version'] }}</h4>
          <div class="decision">
            <div class="choice"><h5>Demander une correction</h5>
              <ul><li>Dans le périmètre convenu.</li><li>@if($c['remaining'] > 0)Il vous reste <strong>{{ $c['remaining'] }} correction{{ $c['remaining'] > 1 ? 's' : '' }} sur {{ $c['included'] }}</strong>@if($c['remaining'] === 1) : ce sera la dernière @endif.@else<strong>Aucune correction incluse restante</strong> ({{ $c['used'] }} sur {{ $c['included'] }}).@endif</li><li>{{ $d->freelancerName }} dépose une version v{{ $v['version'] + 1 }}.</li></ul>
              @if($c['remaining'] > 0)<a class="btn btn-secondary btn-block" href="{{ route('orders.correction', $d->reference) }}">Demander une correction</a>@else<button class="btn btn-secondary btn-block" type="button" disabled>Demander une correction</button>@endif</div>
            <div class="choice"><h5>Valider la livraison</h5>
              <ul><li>La commande est <strong>clôturée</strong>.</li><li>Plus de correction possible.</li><li>Aucun reversement n’est confirmé ni déclenché par cette étape.</li></ul>
              <a class="btn btn-secondary btn-block" href="{{ route('orders.validation', $d->reference) }}">Valider la livraison</a></div>
          </div>
          @if($dl['review'])<p class="note-line"><x-fc.icon name="shield" :size="16" /><span>Vous décidez à votre rythme (délai d’examen indicatif : {{ \App\Shared\Dates::format($dl['review']['deadline']) }}). <strong>Sans réponse, rien n’est validé ni clôturé à votre place</strong> : la commande reste ouverte.</span></p>@endif</div>
      @endif
    </section>
  @else
    <details class="fold prev"><summary><span>Livraison v{{ $v['version'] }} <span class="badge tone-{{ $v['tone'] }}" style="margin-left:6px">{{ $v['label'] }}</span> </span><x-fc.icon name="chev-down" :size="20" class="chev" /></summary><div class="fold-body">
      <p class="muted small">Déposée le {{ $v['when'] }} par {{ $v['author'] }}@if($v['answers']) · {{ $v['answers'] }}@endif</p>
      @if($v['message'])<p style="margin-top:8px">« {!! nl2br(e($v['message'])) !!} »</p>@endif
      @if($v['correction'])<div class="quote"><strong>Correction {{ $v['correction']['number'] }} sur {{ $c['included'] }} demandée le {{ $v['correction']['when'] }}</strong><br>« {{ $v['correction']['reason'] }} »</div>@endif
      @if(count($v['files']))@include('orders._files', ['files' => $v['files']])@endif</div></details>
  @endif
@empty
  <section class="card empty"><span class="ico-lg"><x-fc.icon name="file" :size="26" /></span><h3 class="t-h2">Aucune livraison pour l’instant</h3>
    <p class="muted" style="max-width:36em">{{ $isF ? 'Déposez votre livraison depuis l’action attendue ci-dessus : le client ne la voit qu’une fois soumise.' : $d->freelancerName.' n’a pas encore soumis de livraison. Vous serez invité à l’examiner dès qu’elle sera soumise.' }}</p></section>
@endforelse

@if($dl['extensionHistory'])
  <section class="card" aria-labelledby="h-extlog" style="margin-top:16px"><h3 class="t-h3" id="h-extlog">Reports d’échéance</h3>
    <ol class="timeline">@foreach($dl['extensionHistory'] as $e)
      <li><span class="pt" aria-hidden="true"></span><div><p class="tt">Report {{ mb_strtolower($e['label']) }} : {{ $e['previous'] }} → {{ $e['proposed'] }}</p><p class="when">Proposé le {{ $e['when'] }}@if($e['decidedAt']) · décision le {{ $e['decidedAt'] }}@endif</p><p class="why">« {{ $e['reason'] }} »@if($e['note']) — réponse : « {{ $e['note'] }} »@endif</p></div></li>
    @endforeach</ol></section>
@endif

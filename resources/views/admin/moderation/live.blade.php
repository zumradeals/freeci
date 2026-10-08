@php($isService = $kind === 'service')
@php($suspended = $d['status'] === 'suspended')
<x-layouts.admin title="Contenu en ligne">
  <div class="page-body">
    <nav class="crumbs" aria-label="Fil d’Ariane"><a class="back-m" href="{{ route('admin.moderation', ['onglet' => $suspended ? 'suspendus' : 'en-ligne', 'type' => $kind]) }}"><x-fc.icon name="arrow-right" :size="16" class="flip" />Modération</a><a class="hide-m" href="{{ route('admin.moderation', ['onglet' => $suspended ? 'suspendus' : 'en-ligne', 'type' => $kind]) }}">Modération</a><span class="sep hide-m" aria-hidden="true">›</span><span class="hide-m" aria-current="page">{{ $d['title'] }}</span></nav>
    <div class="sx-head"><div><p class="sx-kicker">{{ $isService ? 'Service' : 'Mission' }} en ligne</p><h1>{{ $d['title'] }}</h1>
      <p class="muted">{{ $isService ? 'Service' : 'Mission' }} de {{ $d['owner'] }} · état : {{ $suspended ? 'suspendu par la modération' : $d['status'] }}</p></div></div>
    <div class="ac-grid"><div class="ac-main">
    @if($d['own'])<div class="rq-info"><x-fc.icon name="warn" :size="18" /><span><strong>C’est votre propre contenu :</strong> vous ne pouvez pas le modérer.</span></div>@endif
    <div class="rq-info"><x-fc.icon name="info" :size="18" /><span>Suspendre retire le contenu du public et bloque les nouvelles demandes ou propositions. <strong>Les commandes en cours et leurs obligations ne sont pas affectées.</strong> Votre mot de passe sera redemandé s’il y a plus de {{ config('freeci.admin.reauth_minutes') }} minutes.</span></div>
    @if(! $d['own'])
      @if($suspended)
        <section class="ed-card"><h2>Remettre en ligne</h2>
          <form method="post" action="{{ route('admin.moderation.toggle', [$kind, $d['id'], 'remettre']) }}" onsubmit="return confirm('Remettre ce contenu en ligne ?')">@csrf<button class="btn btn-primary" type="submit" data-once>Remettre en ligne</button></form></section>
      @elseif(($isService && $d['status'] === 'published') || (! $isService && $d['status'] === 'open'))
        <section class="ed-card ac-danger"><h2>Suspendre</h2>
          <form method="post" action="{{ route('admin.moderation.toggle', [$kind, $d['id'], 'suspendre']) }}" style="display:grid;gap:12px" onsubmit="return confirm('Suspendre ce contenu ? Il disparaît du public.')">@csrf
            <div class="field"><label for="f-reason">Motif (visible de l’auteur, 10 à 1000 caractères)</label><textarea class="textarea" id="f-reason" name="reason" rows="5" required minlength="10" maxlength="1000" @error('reason') aria-invalid="true" @enderror>{{ old('reason') }}</textarea>@error('reason')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
            <div><button class="btn btn-danger" type="submit" data-once>Suspendre</button></div></form></section>
      @else
        <p class="muted">Dans cet état, aucune suspension n’est possible ici.</p>
      @endif
    @endif
    </div>
    <aside class="ac-side"><section class="ed-ck" aria-labelledby="h-his"><h3 id="h-his">Historique</h3>
      @if(count($d['history']))<ol class="ed-tl">@foreach($d['history'] as $h)<li><span class="d"><x-fc.icon name="check" :size="14" /></span><p><b>{{ $h['what'] }}</b><br><span class="muted small">{{ $h['when'] }}@if($h['by']) · {{ $h['by'] }}@endif</span>@if($h['note'])<br><span class="muted small">{{ $h['note'] }}</span>@endif</p></li>@endforeach</ol>@else<p class="muted">Aucun événement.</p>@endif</section></aside></div>
  </div>
</x-layouts.admin>

@php($isService = $kind === 'service')
<x-layouts.admin title="Contrôle d’une version">
  <div class="page-body">
    <nav class="crumbs" aria-label="Fil d’Ariane"><a class="back-m" href="{{ route('admin.moderation', ['onglet' => $isService ? 'services' : 'missions']) }}"><x-fc.icon name="arrow-right" :size="16" class="flip" />Modération</a><a class="hide-m" href="{{ route('admin.moderation', ['onglet' => $isService ? 'services' : 'missions']) }}">Modération</a><span class="sep hide-m" aria-hidden="true">›</span><span class="hide-m" aria-current="page">Contrôle d’une version</span></nav>
    <div class="sx-head"><div><p class="sx-kicker">{{ $isService ? 'Service' : 'Mission' }} · version {{ $d['number'] }}</p><h1>{{ $d['title'] ?: 'Sans titre' }}</h1>
      <p class="muted">{{ $isService ? 'Service' : 'Mission' }} de {{ $d['owner'] }} · version {{ $d['number'] }}{{ $d['liveNumber'] ? ' (version publique actuelle : v'.$d['liveNumber'].')' : ' — première soumission, rien n’est encore public' }}</p></div></div>
    <div class="ac-grid"><div class="ac-main">
    @if($d['own'])<div class="rq-info"><x-fc.icon name="warn" :size="18" /><span><strong>C’est votre propre contenu :</strong> vous ne pouvez pas le modérer. Un autre administrateur doit le contrôler.</span></div>@endif
    @unless($d['inReview'])<div class="rq-info"><x-fc.icon name="info" :size="18" /><span>Cette version n’est plus en contrôle (état : {{ $d['state'] }}). Aucune décision n’est possible.@if($d['decisionNote']) Dernier motif : {{ $d['decisionNote'] }}@endif</span></div>@endunless
    @if($isService && ! $d['profilePublished'])<div class="rq-info"><x-fc.icon name="warn" :size="18" /><span>Le profil du freelance n’est pas publié : l’approbation sera refusée tant qu’il ne l’est pas.</span></div>@endif
    <section class="md-diff" aria-labelledby="h-diff"><h2 id="h-diff" style="font-size:1.25rem;font-weight:750;margin:0">{{ $d['liveNumber'] ? 'Version soumise et version publique' : 'Contenu soumis' }}</h2>
      @if($d['liveNumber'])<p class="muted small" style="margin:0">Les lignes modifiées sont surlignées. Colonne de gauche : version soumise ; colonne de droite : version actuellement publique.</p>@endif
      @foreach($d['rows'] as $r)
        <div class="md-d {{ $r['changed'] ? 'ch' : '' }}"><h3 class="md-dh">{{ $r['label'] }}@if($r['changed']) <span class="badge tone-warning">modifié</span>@endif</h3>
          <div class="md-dc {{ $d['liveNumber'] ? '' : 'one' }}"><div class="md-v"><small>Version soumise v{{ $d['number'] }}</small>{{ $r['new'] }}</div>@if($d['liveNumber'])<div class="md-v old"><small>Version publique v{{ $d['liveNumber'] }}</small>{{ $r['old'] ?? '—' }}</div>@endif</div></div>
      @endforeach
    </section></div>
    <aside class="ac-side">
      @if($d['inReview'] && ! $d['own'])
      <section class="ed-ck md-dec" aria-labelledby="h-dec"><h3 id="h-dec">Décision</h3>
        <form method="post" action="{{ route('admin.moderation.decide', [$kind, $d['versionId'], 'approuver']) }}" onsubmit="return confirm('Publier cette version ? Elle devient visible du public.')">@csrf<button class="btn btn-primary btn-lg md-ok" type="submit" data-once>Approuver et publier</button></form>
        <form method="post" action="{{ route('admin.moderation.decide', [$kind, $d['versionId'], 'refuser']) }}" class="md-no">@csrf
          <div class="field"><label for="f-reason">Demander une correction (motif visible de l’auteur, 10 à 1000 caractères)</label><textarea class="textarea" id="f-reason" name="reason" rows="5" required minlength="10" maxlength="1000" @error('reason') aria-invalid="true" @enderror>{{ old('reason') }}</textarea>@error('reason')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
          <button class="btn btn-secondary" type="submit" data-once>Refuser avec ce motif</button>
        </form></section>
      @endif
      <section class="ed-ck" aria-labelledby="h-his"><h3 id="h-his">Historique</h3>
        @if(count($d['history']))<ol class="ed-tl">@foreach($d['history'] as $h)<li><span class="d"><x-fc.icon name="check" :size="14" /></span><p><b>{{ $h['what'] }}</b><br><span class="muted small">{{ $h['when'] }}@if($h['by']) · {{ $h['by'] }}@endif</span>@if($h['note'])<br><span class="muted small">{{ $h['note'] }}</span>@endif</p></li>@endforeach</ol>@else<p class="muted">Aucun événement.</p>@endif
      </section></aside></div>
  </div>
</x-layouts.admin>

@php($isService = $kind === 'service')
<x-layouts.admin title="Contrôle d’une version">
  <header class="page-head"><div class="row-top"><div><p class="eyebrow"><a href="{{ route('admin.moderation', ['onglet' => $isService ? 'services' : 'missions']) }}">← Modération</a></p><h1 class="t-h1">{{ $d['title'] ?: 'Sans titre' }}</h1>
    <p class="muted">{{ $isService ? 'Service' : 'Mission' }} de {{ $d['owner'] }} · version {{ $d['number'] }}{{ $d['liveNumber'] ? ' (version publique actuelle : v'.$d['liveNumber'].')' : ' — première soumission, rien n’est encore public' }}</p></div></div></header>
  @if($d['own'])<div class="notice tone-warning"><x-fc.icon name="warn" /><p><strong>C’est votre propre contenu :</strong> vous ne pouvez pas le modérer. Un autre administrateur doit le contrôler.</p></div>@endif
  @unless($d['inReview'])<div class="notice tone-info"><x-fc.icon name="info" /><p>Cette version n’est plus en contrôle (état : {{ $d['state'] }}). Aucune décision n’est possible.@if($d['decisionNote']) Dernier motif : {{ $d['decisionNote'] }}@endif</p></div>@endunless
  @if($isService && ! $d['profilePublished'])<div class="notice tone-warning"><x-fc.icon name="warn" /><p>Le profil du freelance n’est pas publié : l’approbation sera refusée tant qu’il ne l’est pas.</p></div>@endif
  <section aria-labelledby="h-diff" style="margin-top:16px"><h2 class="t-h2" id="h-diff">{{ $d['liveNumber'] ? 'Version soumise et version publique' : 'Contenu soumis' }}</h2>
    @if($d['liveNumber'])<p class="muted small" style="margin:4px 0 8px">Les lignes modifiées sont surlignées. Colonne de gauche : version soumise ; colonne de droite : version actuellement publique.</p>@endif
    <div class="diff" role="list">@foreach($d['rows'] as $r)
      <div class="diff-row {{ $r['changed'] ? 'changed' : '' }} {{ $d['liveNumber'] ? '' : 'single' }}" role="listitem"><h3>{{ $r['label'] }}@if($r['changed']) <span class="badge tone-warning">modifié</span>@endif</h3><div class="v">{{ $r['new'] }}</div>@if($d['liveNumber'])<div class="v old">{{ $r['old'] ?? '—' }}</div>@endif</div>
    @endforeach</div>
  </section>
  @if($d['inReview'] && ! $d['own'])
  <section class="card stack" style="margin-top:24px" aria-labelledby="h-dec"><h2 class="t-h2" id="h-dec">Décision</h2>
    <form method="post" action="{{ route('admin.moderation.decide', [$kind, $d['versionId'], 'approuver']) }}" onsubmit="return confirm('Publier cette version ? Elle devient visible du public.')">@csrf<button class="btn btn-primary btn-lg" type="submit" data-once>Approuver et publier</button></form>
    <form method="post" action="{{ route('admin.moderation.decide', [$kind, $d['versionId'], 'refuser']) }}" class="stack">@csrf
      <div class="field"><label for="f-reason">Demander une correction (motif visible de l’auteur, 10 à 1000 caractères)</label><textarea class="textarea" id="f-reason" name="reason" required minlength="10" maxlength="1000" @error('reason') aria-invalid="true" @enderror>{{ old('reason') }}</textarea>@error('reason')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
      <button class="btn btn-secondary" type="submit" data-once>Refuser avec ce motif</button>
    </form>
  </section>
  @endif
  <section style="margin-top:24px" aria-labelledby="h-his"><h2 class="t-h3" id="h-his">Historique</h2>
    @if(count($d['history']))<ul class="hist">@foreach($d['history'] as $h)<li><strong>{{ $h['what'] }}</strong> · {{ $h['when'] }}@if($h['by']) · {{ $h['by'] }}@endif @if($h['note'])<br><span class="muted">{{ $h['note'] }}</span>@endif</li>@endforeach</ul>@else<p class="muted">Aucun événement.</p>@endif
  </section>
</x-layouts.admin>

@php($isService = $kind === 'service')
@php($suspended = $d['status'] === 'suspended')
<x-layouts.admin title="Contenu en ligne">
  <div class="page-body">
    <header class="page-head"><div class="row-top"><div><p class="eyebrow"><a href="{{ route('admin.moderation', ['onglet' => $suspended ? 'suspendus' : 'en-ligne', 'type' => $kind]) }}">← Modération</a></p><h1 class="t-h1">{{ $d['title'] }}</h1>
      <p class="muted">{{ $isService ? 'Service' : 'Mission' }} de {{ $d['owner'] }} · état : {{ $suspended ? 'suspendu par la modération' : $d['status'] }}</p></div></div></header>
    @if($d['own'])<div class="notice tone-warning"><x-fc.icon name="warn" /><p><strong>C’est votre propre contenu :</strong> vous ne pouvez pas le modérer.</p></div>@endif
    <div class="notice tone-info"><x-fc.icon name="info" /><p>Suspendre retire le contenu du public et bloque les nouvelles demandes ou propositions. <strong>Les commandes en cours et leurs obligations ne sont pas affectées.</strong> Votre mot de passe sera redemandé s’il y a plus de {{ config('freeci.admin.reauth_minutes') }} minutes.</p></div>
    @if(! $d['own'])
      @if($suspended)
        <section class="card stack" style="margin-top:16px"><h2 class="t-h3">Remettre en ligne</h2>
          <form method="post" action="{{ route('admin.moderation.toggle', [$kind, $d['id'], 'remettre']) }}" onsubmit="return confirm('Remettre ce contenu en ligne ?')">@csrf<button class="btn btn-primary" type="submit" data-once>Remettre en ligne</button></form></section>
      @elseif(($isService && $d['status'] === 'published') || (! $isService && $d['status'] === 'open'))
        <section class="card stack" style="margin-top:16px"><h2 class="t-h3">Suspendre</h2>
          <form method="post" action="{{ route('admin.moderation.toggle', [$kind, $d['id'], 'suspendre']) }}" class="stack" onsubmit="return confirm('Suspendre ce contenu ? Il disparaît du public.')">@csrf
            <div class="field"><label for="f-reason">Motif (visible de l’auteur, 10 à 1000 caractères)</label><textarea class="textarea" id="f-reason" name="reason" required minlength="10" maxlength="1000" @error('reason') aria-invalid="true" @enderror>{{ old('reason') }}</textarea>@error('reason')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
            <button class="btn btn-danger" type="submit" data-once>Suspendre</button></form></section>
      @else
        <p class="muted" style="margin-top:16px">Dans cet état, aucune suspension n’est possible ici.</p>
      @endif
    @endif
    <section><h2 class="t-h3">Historique</h2>
      @if(count($d['history']))<ul class="hist">@foreach($d['history'] as $h)<li><strong>{{ $h['what'] }}</strong> · {{ $h['when'] }}@if($h['by']) · {{ $h['by'] }}@endif @if($h['note'])<br><span class="muted">{{ $h['note'] }}</span>@endif</li>@endforeach</ul>@else<p class="muted">Aucun événement.</p>@endif</section>
  </div>
</x-layouts.admin>

@php($envQ = $live ? 'reel' : 'test')
<x-layouts.admin title="Exports CSV">
  <div class="page-body">
    <nav class="sd-crumbs" aria-label="Fil d’Ariane"><a href="{{ route('admin.home') }}">Administration</a> › <a href="{{ route('admin.stats', $p->query() + ['env' => $envQ]) }}">Statistiques</a> › <span aria-current="page">Exports CSV</span></nav>
    <div class="sx-head"><div><p class="sx-kicker">Administration</p><h1>Exports CSV</h1><p class="muted">Données de gestion pour votre comptabilité ou vos analyses.</p></div></div>
    @if(session('error'))<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>{{ session('error') }}</p></div>@endif
    @if($p->notice)<div class="notice tone-warning" role="status"><x-fc.icon name="warn" /><p>{{ $p->notice }}</p></div>@endif
    <div class="ac-grid"><div class="ac-main">
      <form class="st-bar" method="get" action="{{ route('admin.exports') }}">
        <div class="field"><label for="ex-per">Période</label><select class="select" id="ex-per" name="periode">@foreach($presets as $k => [$l])<option value="{{ $k }}" @selected($p->key === $k)>{{ $l }}</option>@endforeach<option value="perso" @selected($p->key === 'perso')>Personnalisée</option></select></div>
        <div class="field"><label for="ex-du">Du</label><input class="input" id="ex-du" type="date" name="du" value="{{ $p->from->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}"></div>
        <div class="field"><label for="ex-au">Au</label><input class="input" id="ex-au" type="date" name="au" value="{{ $p->lastDay()->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}"></div>
        <div class="field"><label for="ex-env">Environnement</label><select class="select" id="ex-env" name="env"><option value="reel" @selected($live)>Réel</option><option value="test" @selected(! $live)>Test et anciennes</option></select></div>
        <div><button class="btn btn-secondary" type="submit">Appliquer</button></div>
      </form>
      <p class="muted small" style="margin:0">{{ ucfirst($p->label()) }} (heure d’Abidjan) · {{ $live ? 'réel' : 'test et anciennes' }}. Les exports portent sur cette période et cet environnement.</p>
      <div class="ex-g">@foreach($datasets as $k => $d)
        <form class="ex-r" method="post" action="{{ route('admin.exports.download', $k) }}">@csrf
          @foreach($p->query() as $n => $v)<input type="hidden" name="{{ $n }}" value="{{ $v }}">@endforeach<input type="hidden" name="env" value="{{ $envQ }}">
          <span class="ex-i"><x-fc.icon :name="$d['icon']" :size="22" /></span>
          <div><b>{{ $d['label'] }}</b><small>{{ $d['sub'] }}</small><span class="ex-cols">Colonnes : {{ implode(', ', $d['cols']) }}</span></div>
          <button class="btn btn-secondary" type="submit">Exporter en CSV<span class="sr-only"> : {{ $d['label'] }}</span></button></form>
      @endforeach</div>
    </div>
    <aside class="ac-side"><section class="ed-ck"><h3>Règles des exports</h3><ul class="av-tl">
      <li><x-fc.icon name="check" :size="18" /><span>Réservés aux <b>administrateurs</b> ; <b>confirmation récente d’identité</b> à chaque export.</span></li>
      <li><x-fc.icon name="check" :size="18" /><span><b>Chaque export est journalisé</b> : qui, quel jeu de données, quelle période, quel environnement, combien de lignes.</span></li>
      <li><x-fc.icon name="check" :size="18" /><span><b>Aucun contenu privé</b> : ni messages, ni briefs, ni fichiers, ni coordonnées bancaires, ni e-mail, ni téléphone, ni adresse IP.</span></li>
      <li><x-fc.icon name="check" :size="18" /><span><b>{{ number_format($max, 0, ',', ' ') }} lignes au plus</b> ; au-delà, réduisez la période.</span></li>
      <li><x-fc.icon name="check" :size="18" /><span>Format Excel français : séparateur <b>;</b>, accents conservés (UTF-8), montants en francs entiers, dates en heure d’Abidjan.</span></li>
      <li><x-fc.icon name="check" :size="18" /><span>Cellules protégées contre l’injection de formules (=, +, −, @).</span></li></ul></section></aside></div>
  </div>
</x-layouts.admin>

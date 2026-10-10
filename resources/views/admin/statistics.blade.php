@php
  $envQ = ['env' => $live ? 'reel' : 'test'];
  $q = fn (array $o = []) => array_merge($p->query(), $envQ, $o);
  $spark = function (array $ser, string $color) {
    $pts = $ser['points']; $n = count($pts); $w = 600; $h = 150; $max = $ser['max'];
    $xy = [];
    foreach ($pts as $i => $pt) { $xy[] = [round($n > 1 ? $i * $w / ($n - 1) : $w / 2, 1), round($h - 14 - ($pt['value'] / $max) * ($h - 34), 1)]; }
    $path = implode(' ', array_map(fn ($c, $i) => ($i === 0 ? 'M' : 'L').$c[0].','.$c[1], $xy, array_keys($xy)));
    return ['path' => $path, 'area' => $path.' L'.$w.','.($h - 14).' L0,'.($h - 14).' Z', 'w' => $w, 'h' => $h, 'color' => $color];
  };
@endphp
<x-layouts.admin title="Statistiques">
  <div class="page-body">
    <nav class="sd-crumbs" aria-label="Fil d’Ariane"><a href="{{ route('admin.home') }}">Administration</a> › <span aria-current="page">Statistiques</span></nav>
    <div class="sx-head"><div><p class="sx-kicker">Administration</p><h1>Statistiques</h1><p class="muted">Indicateurs de la plateforme, avec leur période et leur formule.</p></div><div class="sx-acts"><a class="btn btn-secondary" href="{{ route('admin.exports', $q()) }}">Exports CSV</a></div></div>
    @if($p->notice)<div class="notice tone-warning" role="status"><x-fc.icon name="warn" /><p>{{ $p->notice }}</p></div>@endif
    <form class="st-bar" method="get" action="{{ route('admin.stats') }}" data-autosubmit>
      <input type="hidden" name="env" value="{{ $envQ['env'] }}">
      <div class="field"><label for="st-per">Période</label><select class="select" id="st-per" name="periode">@foreach($presets as $k => [$l])<option value="{{ $k }}" @selected($p->key === $k)>{{ $l }}</option>@endforeach<option value="perso" @selected($p->key === 'perso')>Personnalisée</option></select></div>
      <div class="field"><label for="st-du">Du</label><input class="input" id="st-du" type="date" name="du" value="{{ $p->from->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}"></div>
      <div class="field"><label for="st-au">Au</label><input class="input" id="st-au" type="date" name="au" value="{{ $p->lastDay()->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}"></div>
      <div><button class="btn btn-secondary" type="submit">Appliquer</button></div>
      <div class="field"><span class="label">Environnement</span><span class="st-env" role="group" aria-label="Environnement"><a class="{{ $live ? 'real' : '' }}" href="{{ route('admin.stats', array_merge($p->query(), ['env' => 'reel'])) }}" @if($live) aria-current="true" @endif>Réel</a><a class="{{ $live ? '' : 'test' }}" href="{{ route('admin.stats', array_merge($p->query(), ['env' => 'test'])) }}" @if(! $live) aria-current="true" @endif>Test et anciennes</a></span></div>
    </form>
    <p class="muted small" style="margin:8px 0 18px">{{ ucfirst($s['period']) }} (heure d’Abidjan, {{ $s['days'] }} jour{{ $s['days'] > 1 ? 's' : '' }}) · environnement <b>{{ $live ? 'réel' : 'test et anciennes' }}</b> : {{ $live ? 'seuls les paiements confirmés en mode réel sont comptés ; les comptes de démonstration sont exclus.' : 'commandes de test et antérieures à l’environnement explicite, comptes de démonstration inclus.' }} Le test n’est jamais mélangé au réel.</p>
    <div style="display:grid;gap:26px">
      @foreach([['Activité', 'activity'], ['Finances ('.($live ? 'réel' : 'test').')', 'finance']] as [$title, $key])
      <section class="st-sec" aria-labelledby="h-{{ $key }}"><h2 id="h-{{ $key }}">{{ $title }}</h2>
        <div class="st-k">@foreach($s[$key] as $c)<div class="st-c"><small>{{ $c['label'] }}</small><b>{{ $c['value'] }}</b>@if($c['delta'])<span class="d">{{ $c['delta'] }}</span>@endif<span class="st-f">{{ $c['formula'] }}</span></div>@endforeach</div>
        @if($key === 'activity')
        <div class="st-2">
          @foreach([['Commandes créées par jour', $s['series']['orders'], '#1b3a63', ''], ['Encaissé par jour (FCFA)', $s['series']['collected'], '#1a7f4b', 'Paiements confirmés, par jour de confirmation.']] as [$ct, $ser, $col, $note])
            @php($g = $spark($ser, $col))
            <div class="st-ch"><b>{{ $ct }}</b><svg viewBox="0 0 {{ $g['w'] }} {{ $g['h'] }}" role="img" aria-label="{{ $ct }}, {{ $s['period'] }}, maximum {{ number_format($ser['max'], 0, ',', ' ') }}"><path d="{{ $g['area'] }}" fill="{{ $col }}" opacity=".10"/><path d="{{ $g['path'] }}" fill="none" stroke="{{ $col }}" stroke-width="2.5"/><line x1="0" x2="{{ $g['w'] }}" y1="{{ $g['h'] - 14 }}" y2="{{ $g['h'] - 14 }}" stroke="#dfe5ec"/></svg>
              @if($note)<span class="muted small">{{ $note }}</span>@endif
              <details><summary class="small">Tableau équivalent ({{ count($ser['points']) }} jours)</summary><table class="st-tb"><thead><tr><th>Jour</th><th>Valeur</th></tr></thead><tbody>@foreach($ser['points'] as $pt)<tr><td>{{ \Illuminate\Support\Carbon::parse($pt['day'])->translatedFormat('j M Y') }}</td><td>{{ number_format($pt['value'], 0, ',', ' ') }}</td></tr>@endforeach</tbody></table></details></div>
          @endforeach
        </div>
        @else
        <div class="st-ch"><b>Volume par origine de commande</b><table class="st-tb"><thead><tr><th>Origine</th><th>Commandes créées</th><th>Encaissé</th></tr></thead><tbody>@foreach($s['byOrigin'] as $r)<tr><td>{{ $r['label'] }}</td><td>{{ number_format($r['orders'], 0, ',', ' ') }}</td><td>{{ $r['collected'] }}</td></tr>@endforeach</tbody></table></div>
        @endif
      </section>
      @endforeach
      <section class="st-sec" aria-labelledby="h-quality"><h2 id="h-quality">Qualité et assistance</h2>
        <div class="st-k">@foreach($s['quality'] as $c)<div class="st-c"><small>{{ $c['label'] }}</small><b>{{ $c['value'] }}</b><span class="st-f">{{ $c['formula'] }}</span></div>@endforeach</div></section>
    </div>
  </div>
</x-layouts.admin>

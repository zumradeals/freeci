<x-layouts.account title="Disponibilité et réactivité" space="freelancer">
  @php($s = $a['stats'])
  <div class="page-body">
    <nav class="sd-crumbs" aria-label="Fil d’Ariane"><a href="{{ route('freelance.dashboard') }}">Espace freelance</a> › <span aria-current="page">Disponibilité</span></nav>
    <div class="sx-head"><div><p class="sx-kicker">Espace freelance</p><h1>Disponibilité et réactivité</h1><p class="muted">Indiquez quand vous pouvez accepter de nouvelles demandes, et voyez ce que FreeCI mesure.</p></div></div>
    @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>{{ $errors->first() }}</p></div>@endif
    @unless($a['has_profile'])
      <div class="notice tone-info"><x-fc.icon name="info" /><p>Créez d’abord votre <a href="{{ route('freelance.profile') }}">profil freelance</a> : la disponibilité s’y rattache.</p></div>
    @else
    <div class="ac-grid"><div class="ac-main">
      <section class="ed-card" aria-labelledby="h-av"><div class="av-state"><h2 id="h-av">Ma disponibilité</h2>@if($a['unavailable'])<span class="av-pill off">Actuellement : indisponible</span>@else<span class="av-pill"><x-fc.icon name="check" :size="14" /> Actuellement : disponible</span>@endif</div>
        <form method="post" action="{{ route('freelance.availability.save') }}" class="stack-sm">@csrf
          <div class="av-opt">
            <label class="o {{ $a['unavailable'] ? '' : 'on' }}"><input type="radio" name="available" value="yes" @checked(! $a['unavailable'])><span><b>Disponible</b><small>Les clients peuvent vous envoyer de nouvelles demandes.</small></span></label>
            <label class="o {{ $a['unavailable'] ? 'on' : '' }}"><input type="radio" name="available" value="no" @checked($a['unavailable'])><span><b>Indisponible pour le moment</b><small>Vos services restent visibles, mais personne ne peut en demander de nouveaux. Rien ne change pour vos commandes en cours.</small></span></label>
          </div>
          <div class="field"><label for="av-back">De retour le (facultatif)</label><input class="input" id="av-back" name="back_on" type="date" value="{{ old('back_on', $a['back_on']) }}" min="{{ now()->addDay()->format('Y-m-d') }}" max="{{ now()->addYear()->format('Y-m-d') }}" @error('back_on') aria-invalid="true" @enderror><p class="muted small" style="margin:0">Cette date est visible des clients. Elle ne sert que si vous êtes indisponible.</p></div>
          <label><input type="checkbox" name="auto_reopen" value="1" @checked(old('auto_reopen', $a['auto_reopen']))> Me rendre disponible automatiquement ce jour-là (vous serez prévu par une notification).</label>
          <div><button class="btn btn-primary" type="submit" data-once>Enregistrer</button></div>
        </form></section>
      <section class="ed-card" aria-labelledby="h-chg"><h2 id="h-chg">Ce que ça change</h2><ul class="av-tl">
        <li><x-fc.icon name="check" :size="18" /><span><b>Vos demandes déjà reçues</b> restent à traiter dans leur délai habituel de {{ config('freeci.orders.response_hours') }} h.</span></li>
        <li><x-fc.icon name="check" :size="18" /><span><b>Vos commandes en cours</b> ne sont pas touchées.</span></li>
        <li><x-fc.icon name="check" :size="18" /><span><b>Vos propositions aux missions</b> restent possibles : c’est vous qui décidez d’y répondre.</span></li>
        <li><x-fc.icon name="check" :size="18" /><span><b>Dans le catalogue</b>, vos services s’affichent avec « Indisponible » et passent après les services disponibles.</span></li></ul></section>
      <section class="ed-card" aria-labelledby="h-rea"><h2 id="h-rea">Votre réactivité</h2>
        @if($s['count'] >= \App\Modules\Orders\Queries\ResponseStats::MIN)
          <div class="av-kpi"><div><b>{{ $s['label'] ?? '—' }}</b><small>Temps de réponse habituel (médiane){{ $s['label'] ? '' : ' : trop de demandes sans réponse pour l’établir' }}</small></div><div><b>{{ $s['rate'] }} %</b><small>Demandes traitées dans le délai de {{ config('freeci.orders.response_hours') }} h</small><div class="av-bar"><i style="width:{{ $s['rate'] }}%"></i></div></div></div>
        @else
          <p class="muted"><x-fc.icon name="info" :size="18" /> Pas encore assez de demandes pour calculer votre réactivité ({{ $s['count'] }} sur {{ \App\Modules\Orders\Queries\ResponseStats::MIN }} nécessaires). Les clients voient « Nouveau sur FreeCI ».</p>
        @endif
        <p class="muted small" style="margin:0">Mesurée sur vos <b>{{ \App\Modules\Orders\Queries\ResponseStats::SAMPLE }} dernières demandes</b> reçues dans les {{ \App\Modules\Orders\Queries\ResponseStats::DAYS }} derniers jours. Le temps est celui qui sépare la demande de votre acceptation ou de votre refus ; une demande restée sans réponse compte comme la plus longue. Une demande retirée par le client avant votre réponse, ou encore dans son délai, n’est pas comptée. Il faut au moins {{ \App\Modules\Orders\Queries\ResponseStats::MIN }} demandes pour afficher ces chiffres.</p></section>
    </div>
    <aside class="ac-side">
      <section class="ed-ck"><h3>Ce que voient les clients</h3><div style="display:grid;gap:10px">
        @if($a['unavailable'])<div><span class="av-pill off">{{ $a['back_label'] ? 'Indisponible jusqu’au '.\Illuminate\Support\Carbon::parse($a['back_on'])->translatedFormat('j F') : 'Indisponible' }}</span></div>@endif
        <div>@if($s['count'] >= \App\Modules\Orders\Queries\ResponseStats::MIN)@if($s['label'])<span class="av-pill"><x-fc.icon name="clock" :size="14" /> Répond en {{ $s['label'] }}</span>@endif @else<span class="av-pill new">Nouveau sur FreeCI</span>@endif</div></div>
        <p class="muted small" style="margin:10px 0 0">Seule la tranche est affichée (moins d’1 h, 6 h, 24 h, 48 h), jamais les détails.</p></section>
      <section class="ed-ck"><h3>Bon à savoir</h3><ul class="av-tl"><li><x-fc.icon name="check" :size="18" /><span>La réactivité est <b>informative</b> : elle ne modifie pas votre classement.</span></li><li><x-fc.icon name="check" :size="18" /><span>Pendant une période d’indisponibilité, aucune demande ne vous arrive : rien n’est compté contre vous.</span></li></ul></section>
    </aside></div>
    @endunless
  </div>
</x-layouts.account>

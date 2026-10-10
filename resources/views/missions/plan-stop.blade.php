<x-layouts.account title="Arrêter le plan de jalons" space="client">
  <div class="page-body">
    <nav class="sd-crumbs" aria-label="Fil d’Ariane"><a href="{{ route('milestones.show', $p['missionId']) }}">Plan de jalons</a> › <span aria-current="page">Arrêter le plan</span></nav>
    <div class="sx-head"><div><p class="sx-kicker">Mission</p><h1>Arrêter le plan de jalons</h1><p class="muted">{{ $p['title'] }}</p></div></div>
    @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>{{ $errors->first() }}</p></div>@endif
    <section class="ed-card"><p style="margin:0">Vous avez validé <strong>{{ $p['validatedCount'] }} jalon{{ $p['validatedCount'] > 1 ? 's' : '' }} sur {{ $p['count'] }}</strong> ({{ $p['validatedSum'] }}). Si vous arrêtez le plan :</p>
      <ul class="av-tl"><li><x-fc.icon name="check" :size="18" /><span>les jalons validés <b>restent acquis</b> ;</span></li><li><x-fc.icon name="check" :size="18" /><span>les jalons non ouverts, et le jalon ouvert non payé, sont <b>annulés sans paiement</b> ;</span></li><li><x-fc.icon name="check" :size="18" /><span>le freelance est prévenu ; un avis unique reste possible sur le dernier jalon validé ;</span></li><li><x-fc.icon name="check" :size="18" /><span>l’action est <b>définitive</b>.</span></li></ul>
      <form method="post" action="{{ route('milestones.stop.store', $p['missionId']) }}" data-once class="stack-sm">@csrf
        <label class="check"><input type="checkbox" name="confirm" value="1" required> <span>Je confirme vouloir arrêter ce plan de jalons.</span></label>
        <div class="row" style="gap:10px"><button class="btn btn-primary" type="submit">Arrêter le plan</button><a class="btn btn-secondary" href="{{ route('milestones.show', $p['missionId']) }}">Annuler</a></div></form></section>
  </div>
</x-layouts.account>

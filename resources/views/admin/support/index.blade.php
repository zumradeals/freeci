<x-layouts.admin title="Assistance">
  <header class="page-head"><div class="row-top"><div><p class="eyebrow">Administration</p><h1 class="t-h1">Assistance</h1></div></div></header>
  <div class="kpis" style="margin-bottom:16px">
    <a class="kpi" href="{{ route('admin.support') }}"><span class="n">{{ $counts['open'] }}</span><span class="l">dossiers en cours</span></a>
    <a class="kpi" href="{{ route('admin.support', ['qui' => 'unassigned']) }}"><span class="n">{{ $counts['unassigned'] }}</span><span class="l">non affectés</span></a>
    <a class="kpi" href="{{ route('admin.support', ['qui' => 'mine']) }}"><span class="n">{{ $counts['mine'] }}</span><span class="l">qui me sont affectés</span></a>
    <a class="kpi" href="{{ route('admin.support', ['onglet' => 'suivis']) }}"><span class="n">{{ $counts['followUps'] }}</span><span class="l">besoins de suivi sans dossier</span></a>
    <a class="kpi" href="{{ route('admin.support', ['onglet' => 'financier']) }}"><span class="n">{{ $counts['toProcess'] }}</span><span class="l">à traiter financièrement</span></a>
  </div>
  <nav class="tabs" aria-label="Assistance" style="margin-bottom:16px">
    <a href="{{ route('admin.support') }}" @if($tab === 'dossiers') aria-current="page" @endif>Dossiers</a>
    <a href="{{ route('admin.support', ['onglet' => 'suivis']) }}" @if($tab === 'suivis') aria-current="page" @endif>Besoins de suivi</a>
    <a href="{{ route('admin.support', ['onglet' => 'financier']) }}" @if($tab === 'financier') aria-current="page" @endif>À traiter financièrement</a>
  </nav>

  @if($tab === 'dossiers')
    <form method="get" class="filters card" style="margin-bottom:16px" role="search">
      <div class="field"><label for="f-q">Référence ou objet</label><input class="input" id="f-q" name="q" value="{{ $f['q'] }}" maxlength="40"></div>
      <div class="field"><label for="f-type">Type</label><select class="select" id="f-type" name="type"><option value="">Tous</option>@foreach($kinds as $k => $l)<option value="{{ $k }}" @selected($f['kind'] === $k)>{{ $l }}</option>@endforeach</select></div>
      <div class="field"><label for="f-statut">État</label><select class="select" id="f-statut" name="statut"><option value="">En cours</option>@foreach($statuses as $k => $l)<option value="{{ $k }}" @selected($f['status'] === $k)>{{ $l }}</option>@endforeach</select></div>
      <div class="field"><label for="f-qui">Affectation</label><select class="select" id="f-qui" name="qui"><option value="">Toutes</option><option value="mine" @selected($f['who'] === 'mine')>À moi</option><option value="unassigned" @selected($f['who'] === 'unassigned')>Non affectés</option></select></div>
      <div class="row"><button class="btn btn-primary" type="submit">Filtrer</button><a class="btn btn-link" href="{{ route('admin.support') }}">Réinitialiser</a></div>
    </form>
    @if($page->count())
      <div class="table-wrap"><table class="list"><caption class="sr-only">Dossiers d’assistance</caption>
        <thead><tr><th scope="col">Dossier</th><th scope="col">Type</th><th scope="col">État</th><th scope="col">Demandeur</th><th scope="col">Affecté à</th><th scope="col">Ouvert</th></tr></thead><tbody>
        @foreach($page as $r)<tr><td class="c-title"><a class="ttl" href="{{ route('admin.support.show', $r['reference']) }}">{{ $r['subject'] }}</a><span class="ref">{{ $r['reference'] }}@if($r['priority'] === 'high') · <strong>prioritaire</strong>@endif</span></td>
          <td data-label="Type">{{ $r['kind'] }}@if($r['origin'] === 'follow_up') <span class="tag-demo">suivi</span>@endif</td><td data-label="État">{{ $r['status'] }}</td><td data-label="Demandeur">{{ $r['requester'] }}</td>
          <td data-label="Affecté à">@if($r['conflict'])<span class="badge tone-warning">Vous êtes partie prenante</span>@elseif($r['mine'])<strong>Moi</strong>@else{{ $r['assignee'] ?? 'Non affecté' }}@endif</td><td data-label="Ouvert">{{ $r['since'] }} ({{ $r['age'] }} j)</td></tr>@endforeach
      </tbody></table></div>
      <x-admin.pager :p="$page" />
    @else
      <div class="card empty"><span class="ico-lg"><x-fc.icon name="check-circle" :size="26" /></span><p style="font-weight:600">Aucun dossier ne correspond.</p><p class="muted">Les nouveaux dossiers apparaîtront ici.</p></div>
    @endif
  @elseif($tab === 'suivis')
    <div class="notice tone-info" style="margin-bottom:12px"><x-fc.icon name="info" /><p><strong>Origine conservée, rien n’est transformé en litige.</strong> Ces besoins de suivi ont été enregistrés par FreeCI (silence d’un client, désaccord signalé). Ouvrir un dossier est une action explicite, journalisée, qui ne modifie pas la commande. Seules les parties peuvent ouvrir un litige.</p></div>
    @if(count($followUps))
      <div class="table-wrap"><table class="list"><caption class="sr-only">Besoins de suivi enregistrés</caption><thead><tr><th scope="col">Commande</th><th scope="col">Nature</th><th scope="col">Enregistré</th><th scope="col">Dossier</th></tr></thead><tbody>
        @foreach($followUps as $r)<tr><td data-label="Commande">{{ $r['order'] }}</td><td data-label="Nature">{{ $r['kind'] }}</td><td data-label="Enregistré">{{ $r['when'] }}</td>
          <td data-label="Dossier">@if($r['case'])<a href="{{ route('admin.support.show', $r['case']) }}">{{ $r['case'] }}</a>@else<form method="post" action="{{ route('admin.support.followup', $r['id']) }}">@csrf<button class="btn btn-secondary" type="submit" data-once>Ouvrir un dossier de suivi</button></form>@endif</td></tr>@endforeach
      </tbody></table></div>
    @else
      <div class="card empty"><span class="ico-lg"><x-fc.icon name="check-circle" :size="26" /></span><p style="font-weight:600">Aucun besoin de suivi enregistré.</p></div>
    @endif
  @else
    <div class="notice tone-warning" style="margin-bottom:12px"><x-fc.icon name="clock" /><p><strong>À traiter financièrement :</strong> décisions dont la suite (remboursement, répartition, reversement) reste à exécuter. <strong>Une décision ne rembourse ni ne verse rien</strong> : seules les opérations financières, demandées puis approuvées par d’autres personnes (@if(auth()->user()->isAdministrator())<a href="{{ route('admin.finance') }}">Finances</a>@else Finances, réservées aux administrateurs @endif), le font. Le blocage des reversements est interne à FreeCI et ne signifie pas qu’un blocage a été réalisé chez un prestataire.</p></div>
    @if(count($toProcess))
      <div class="table-wrap"><table class="list"><caption class="sr-only">Décisions à traiter financièrement</caption><thead><tr><th scope="col">Dossier</th><th scope="col">Commande</th><th scope="col">Suite à traiter</th><th scope="col">Décidé le</th><th scope="col">Blocage interne</th></tr></thead><tbody>
        @foreach($toProcess as $r)<tr><td data-label="Dossier"><a href="{{ route('admin.support.show', $r['reference']) }}">{{ $r['reference'] }}</a></td><td data-label="Commande">{{ $r['order'] }}</td><td data-label="Suite à traiter">{{ $r['need'] }}@if($r['note'])<br><span class="muted small">{{ $r['note'] }}</span>@endif</td><td data-label="Décidé le">{{ $r['when'] }}</td><td data-label="Blocage interne">{{ $r['hold'] ? 'Actif' : 'Levé' }}</td></tr>@endforeach
      </tbody></table></div>
    @else
      <div class="card empty"><span class="ico-lg"><x-fc.icon name="check-circle" :size="26" /></span><p style="font-weight:600">Aucune suite financière en attente.</p></div>
    @endif
  @endif
</x-layouts.admin>

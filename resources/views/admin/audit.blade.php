<x-layouts.admin title="Journal d’audit">
  <header class="page-head"><div class="row-top"><div><p class="eyebrow">Administration</p><h1 class="t-h1">Journal d’audit</h1></div></div></header>
  <nav class="tabs" aria-label="Journaux" style="margin-bottom:16px"><a href="{{ route('admin.audit') }}" aria-current="page">Actions administratives</a><a href="{{ route('admin.audit.security') }}">Événements de sécurité</a></nav>
  <form method="get" class="filters card" style="margin-bottom:16px" role="search">
    <div class="field"><label for="f-actor">Auteur (nom ou e-mail)</label><input class="input" id="f-actor" name="actor" value="{{ $f['actor'] ?? '' }}" maxlength="80"></div>
    <div class="field"><label for="f-action">Action</label><select class="select" id="f-action" name="action"><option value="">Toutes</option>@foreach($actions as $k => $l)<option value="{{ $k }}" @selected(($f['action'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="field"><label for="f-result">Résultat</label><select class="select" id="f-result" name="result"><option value="">Tous</option><option value="done" @selected(($f['result'] ?? '') === 'done')>Effectuée</option><option value="refused" @selected(($f['result'] ?? '') === 'refused')>Refusée</option></select></div>
    <div class="field"><label for="f-q">Cible (titre ou identifiant)</label><input class="input" id="f-q" name="q" value="{{ $f['q'] ?? '' }}" maxlength="80"></div>
    <div class="field"><label for="f-from">Du</label><input class="input" id="f-from" type="date" name="from" value="{{ $f['from'] ?? '' }}"></div>
    <div class="field"><label for="f-to">Au</label><input class="input" id="f-to" type="date" name="to" value="{{ $f['to'] ?? '' }}"></div>
    <div class="row"><button class="btn btn-primary" type="submit">Filtrer</button><a class="btn btn-link" href="{{ route('admin.audit') }}">Réinitialiser</a></div>
  </form>
  @if($page->count())
    <div class="table-wrap"><table class="list"><caption class="sr-only">Actions administratives</caption>
      <thead><tr><th scope="col">Date</th><th scope="col">Auteur</th><th scope="col">Action</th><th scope="col">Cible</th><th scope="col">Motif</th><th scope="col">Résultat</th></tr></thead><tbody>
      @foreach($page as $a)<tr><td data-label="Date">{{ $a['when'] }}</td><td data-label="Auteur">{{ $a['actor'] }}</td><td data-label="Action">{{ $a['action'] }}</td><td data-label="Cible">{{ $a['target'] }}</td><td data-label="Motif">{{ $a['reason'] ?? '—' }}</td>
        <td data-label="Résultat">@if($a['result'] === 'done')<span class="badge tone-success">Effectuée</span>@else<span class="badge tone-error">Refusée</span><br><span class="muted small">{{ $a['detail'] }}</span>@endif</td></tr>@endforeach
    </tbody></table></div>
    <x-admin.pager :p="$page" />
  @else
    <div class="card empty"><span class="ico-lg"><x-fc.icon name="clipboard" :size="26" /></span><p style="font-weight:600">Aucune action ne correspond.</p><p class="muted">Le journal est en ajout seul : il ne peut être ni modifié ni effacé.</p></div>
  @endif
</x-layouts.admin>

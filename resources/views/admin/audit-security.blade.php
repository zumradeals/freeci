<x-layouts.admin title="Événements de sécurité">
  <header class="page-head"><div class="row-top"><div><p class="eyebrow">Administration</p><h1 class="t-h1">Journal d’audit</h1></div></div></header>
  <nav class="tabs" aria-label="Journaux" style="margin-bottom:16px"><a href="{{ route('admin.audit') }}">Actions administratives</a><a href="{{ route('admin.audit.security') }}" aria-current="page">Événements de sécurité</a></nav>
  <form method="get" class="filters card" style="margin-bottom:16px" role="search">
    <div class="field"><label for="f-type">Événement</label><select class="select" id="f-type" name="type"><option value="">Tous</option>@foreach($types as $k => $l)<option value="{{ $k }}" @selected(($f['type'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="field"><label for="f-from">Du</label><input class="input" id="f-from" type="date" name="from" value="{{ $f['from'] ?? '' }}"></div>
    <div class="field"><label for="f-to">Au</label><input class="input" id="f-to" type="date" name="to" value="{{ $f['to'] ?? '' }}"></div>
    <div class="row"><button class="btn btn-primary" type="submit">Filtrer</button><a class="btn btn-link" href="{{ route('admin.audit.security') }}">Réinitialiser</a></div>
  </form>
  <p class="muted small" style="margin-bottom:12px">Aucun mot de passe, code ou secret n’est enregistré. L’adresse IP est conservée pour l’investigation de sécurité.</p>
  @if($page->count())
    <div class="table-wrap"><table class="list"><caption class="sr-only">Événements de sécurité</caption>
      <thead><tr><th scope="col">Date</th><th scope="col">Événement</th><th scope="col">Compte</th><th scope="col">Adresse IP</th><th scope="col">Détail</th></tr></thead><tbody>
      @foreach($page as $e)<tr><td data-label="Date">{{ $e['when'] }}</td><td data-label="Événement">{{ $e['type'] }}</td><td data-label="Compte">{{ $e['who'] }}</td><td data-label="Adresse IP">{{ $e['ip'] }}</td><td data-label="Détail">{{ $e['detail'] ?? '—' }}</td></tr>@endforeach
    </tbody></table></div>
    <x-admin.pager :p="$page" />
  @else
    <div class="card empty"><span class="ico-lg"><x-fc.icon name="shield" :size="26" /></span><p style="font-weight:600">Aucun événement ne correspond.</p></div>
  @endif
</x-layouts.admin>

<x-layouts.admin title="Utilisateurs">
  <header class="page-head"><div class="row-top"><div><p class="eyebrow">Administration</p><h1 class="t-h1">Utilisateurs</h1></div></div></header>
  <form method="get" class="filters card" style="margin-bottom:16px" role="search">
    <div class="field"><label for="f-q">Nom ou adresse e-mail</label><input class="input" id="f-q" name="q" value="{{ $f['q'] }}" maxlength="80"></div>
    <div class="field"><label for="f-statut">État</label><select class="select" id="f-statut" name="statut"><option value="">Tous</option><option value="active" @selected($f['status'] === 'active')>Actifs</option><option value="suspended" @selected($f['status'] === 'suspended')>Suspendus</option><option value="unverified" @selected($f['status'] === 'unverified')>Adresse non vérifiée</option></select></div>
    <div class="field"><label for="f-role">Rôle</label><select class="select" id="f-role" name="role"><option value="">Tous</option><option value="client" @selected($f['role'] === 'client')>Client</option><option value="freelance" @selected($f['role'] === 'freelance')>Freelance</option><option value="admin" @selected($f['role'] === 'admin')>Administrateur</option></select></div>
    <div class="row"><button class="btn btn-primary" type="submit">Filtrer</button><a class="btn btn-link" href="{{ route('admin.users') }}">Réinitialiser</a></div>
  </form>
  @if($page->count())
    <div class="table-wrap"><table class="list"><caption class="sr-only">Utilisateurs</caption>
      <thead><tr><th scope="col">Nom</th><th scope="col">Adresse e-mail</th><th scope="col">État</th><th scope="col">Inscrit le</th></tr></thead><tbody>
      @foreach($page as $u)<tr><td class="c-title"><a class="ttl" href="{{ route('admin.users.show', $u['id']) }}">{{ $u['name'] }}</a></td><td data-label="Adresse e-mail">{{ $u['email'] }}</td>
        <td data-label="État">@if($u['suspended'])<span class="badge tone-error">Suspendu</span>@else<span class="badge tone-success">Actif</span>@endif @unless($u['verified'])<span class="badge tone-warning">Non vérifiée</span>@endunless @if($u['demo'])<span class="tag-demo">Démo</span>@endif</td><td data-label="Inscrit le">{{ $u['since'] }}</td></tr>@endforeach
    </tbody></table></div>
    <x-admin.pager :p="$page" />
  @else
    <div class="card empty"><span class="ico-lg"><x-fc.icon name="search" :size="26" /></span><p style="font-weight:600">Aucun utilisateur ne correspond.</p><p class="muted">Modifiez la recherche ou les filtres.</p></div>
  @endif
</x-layouts.admin>

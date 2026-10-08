@php
  $ini = fn (string $n) => collect(preg_split('/\s+/', trim($n)) ?: [])->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') ?: '?';
  $tabs = ['' => ['Tous', $counts['all']], 'active' => ['Actifs', $counts['active']], 'suspended' => ['Suspendus', $counts['suspended']], 'unverified' => ['Adresse non vérifiée', $counts['unverified']]];
@endphp
<x-layouts.admin title="Utilisateurs">
  <div class="page-body">
    <div class="sx-head"><div><p class="sx-kicker">Administration</p><h1>Utilisateurs</h1><p class="muted">Comptes inscrits, état de vérification et suspensions.</p></div></div>
    <nav class="sv-tabs" aria-label="État des comptes">@foreach($tabs as $k => [$label, $n])<a class="sv-tab {{ $f['status'] === (string) $k ? 'on' : '' }}" href="{{ route('admin.users', array_filter(['statut' => $k, 'q' => $f['q'], 'role' => $f['role']])) }}" @if($f['status'] === (string) $k) aria-current="page" @endif>{{ $label }} <span class="n">{{ $n }}</span></a>@endforeach</nav>
    <section class="ed-card">
      <form method="get" class="us-f" role="search"><input type="hidden" name="statut" value="{{ $f['status'] }}">
        <div class="field"><label for="f-q">Nom ou adresse e-mail</label><input class="input" id="f-q" name="q" value="{{ $f['q'] }}" maxlength="80" placeholder="Rechercher…"></div>
        <div class="field"><label for="f-role">Rôle</label><select class="select" id="f-role" name="role"><option value="">Tous</option><option value="client" @selected($f['role'] === 'client')>Client</option><option value="freelance" @selected($f['role'] === 'freelance')>Freelance</option><option value="admin" @selected($f['role'] === 'admin')>Administrateur</option></select></div>
        <div class="ac-acts"><button class="btn btn-primary" type="submit">Filtrer</button><a class="btn btn-link" href="{{ route('admin.users') }}">Réinitialiser</a></div>
      </form>
      @if($page->count())
        <div class="us-l"><div class="us-r h" aria-hidden="true"><span></span><span>Nom</span><span>État</span><span>Inscrit le</span><span></span></div>
          @foreach($page as $u)<a class="us-r" href="{{ route('admin.users.show', $u['id']) }}"><span class="us-av" aria-hidden="true">{{ $ini($u['name']) }}</span><div><b>{{ $u['name'] }}</b><small>{{ $u['email'] }}</small></div>
            <div class="us-b">@if($u['suspended'])<span class="badge tone-error">Suspendu</span>@else<span class="badge tone-success">Actif</span>@endif @unless($u['verified'])<span class="badge tone-warning">Non vérifiée</span>@endunless @if($u['demo'])<span class="tag-demo">Démo</span>@endif</div><div><b>{{ $u['since'] }}</b></div><x-fc.icon name="arrow-right" :size="16" /></a>@endforeach
        </div>
        <x-admin.pager :p="$page" />
      @else
        <div class="empty" style="display:grid;justify-items:center;text-align:center;gap:8px;padding:16px"><span class="ico-lg"><x-fc.icon name="search" :size="26" /></span><p style="font-weight:600">Aucun utilisateur ne correspond.</p><p class="muted">Modifiez la recherche ou les filtres.</p></div>
      @endif
    </section>
  </div>
</x-layouts.admin>

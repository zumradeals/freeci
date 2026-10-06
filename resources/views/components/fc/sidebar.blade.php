{{-- Navigation des espaces connectés (barre latérale ET tiroir) : un seul composant, groupée par usage. Le sélecteur d'espace reflète les droits réels du compte. --}}
@props(['space' => 'client', 'drawer' => false])
@php
  $u = auth()->user();
  $isFreelance = $u->hasRole('freelance');
  $isStaff = $u->isStaff();
  $isAdmin = $isStaff && $u->isAdministrator();
  $cur = fn (string ...$routes) => request()->routeIs(...$routes) ? 'page' : null;
  $q = $space === 'freelancer' ? ['espace' => 'freelance'] : [];      // pages partagées : l'espace actif est conservé
  $a = fn (string $route, string $label, string $icon, string|array $match, array $params = [], ?string $extra = null) => [$route, $label, $icon, (array) $match, $params, $extra];
  if ($space === 'admin') {
    $pending = $isAdmin ? app(\App\Modules\Admin\Queries\ModerationQueue::class)->counts() : ['services' => 0, 'missions' => 0];
    $sq = app(\App\Modules\Support\Queries\StaffQueue::class)->counts($u);
    $groups = [
      'Pilotage' => array_filter([
        $isAdmin ? $a('admin.home', 'Tableau de bord', 'grid', 'admin.home') : null,
        $isAdmin ? $a('admin.moderation', 'Modération', 'shield', 'admin.moderation*', [], $pending['services'] + $pending['missions'] > 0 ? (string) ($pending['services'] + $pending['missions']) : null) : null,
        $isAdmin ? $a('admin.reviews', 'Avis', 'flag', 'admin.reviews*') : null,
        $isAdmin ? $a('admin.users', 'Utilisateurs', 'user', 'admin.users*') : null,
      ]),
      'Assistance' => [$a('admin.support', 'Dossiers', 'message', 'admin.support*', [], $sq['unassigned'] > 0 ? (string) $sq['unassigned'] : null)],
      'Finances' => $isAdmin ? [$a('admin.finance', 'Opérations', 'card', 'admin.finance*'), $a('admin.payments', 'Rapprochements', 'clipboard', 'admin.payments*')] : [],
      'Exploitation' => array_filter([
        $isAdmin ? $a('admin.operations', 'État et préparation', 'grid', 'admin.operations') : null,
        $isAdmin ? $a('admin.audit', 'Journal d’audit', 'clipboard', 'admin.audit*') : null,
        $a('admin.security', 'Ma sécurité', 'lock', 'admin.security*'),
      ]),
    ];
  } elseif ($space === 'freelancer') {
    $groups = [
      'Activité' => [
        $a('freelance.dashboard', 'Vue d’ensemble', 'grid', 'freelance.dashboard'), $a('freelance.orders', 'Demandes et commandes', 'clipboard', 'freelance.orders'),
        $a('freelance.services', 'Mes services', 'briefcase', 'freelance.services*'), $a('freelance.proposals', 'Mes propositions', 'pencil', 'freelance.proposals*'),
        $a('missions.index', 'Missions ouvertes', 'search', 'missions.*'), $a('freelance.profile', 'Mon profil', 'user', 'freelance.profile'),
      ],
      'Échanges' => [
        $a('messages.index', 'Messages', 'message', 'messages.*', $q, 'messages'), $a('notifications.index', 'Notifications', 'inbox', 'notifications.*', $q, 'notifications'), $a('favorites.index', 'Favoris', 'heart', 'favorites.*', $q),
      ],
      'Finances' => [$a('freelance.earnings', 'Revenus', 'card', 'freelance.earnings*')],
    ];
  } else {
    $groups = [
      'Activité' => [
        $a('account.dashboard', 'Vue d’ensemble', 'grid', 'account.dashboard'), $a('orders.index', 'Commandes', 'clipboard', 'orders.index'),
        $a('client.missions', 'Mes missions', 'briefcase', 'client.missions*'),
      ],
      'Échanges' => [
        $a('messages.index', 'Messages', 'message', 'messages.*', [], 'messages'), $a('notifications.index', 'Notifications', 'inbox', 'notifications.*', [], 'notifications'), $a('favorites.index', 'Favoris', 'heart', 'favorites.*'),
      ],
      'Finances' => [$a('client.finances', 'Paiements', 'card', 'client.finances')],
    ];
  }
  if ($space !== 'admin') {
    $groups['Compte et aide'] = [
      $a('account.settings', 'Mon compte', 'user', 'account.settings', $q), $a('support.index', 'Assistance', 'message', 'support.*', $q), $a('info', 'Aide', 'info', 'info', ['page' => 'aide']),
    ];
  } else {
    $groups['Compte et aide'] = [$a('info', 'Aide', 'info', 'info', ['page' => 'aide'])];
  }
@endphp
<div class="side-switch">
  @if($space === 'admin')
    <a class="switch-link" href="{{ route('account.dashboard') }}"><x-fc.icon name="user" />Espace client</a>
  @elseif($isFreelance)
    <div class="segmented" role="group" aria-label="Espace actif" style="--n: {{ $isStaff ? 3 : 2 }}">
      <a href="{{ route('account.dashboard') }}" @if($space === 'client') aria-current="true" @endif>Client</a>
      <a href="{{ route('freelance.dashboard') }}" @if($space === 'freelancer') aria-current="true" @endif>Freelance</a>
      @if($isStaff)<a href="{{ route('admin.home') }}" aria-label="Administration">Admin</a>@endif
    </div>
  @else
    <a class="switch-link" href="{{ route('freelance.activate') }}"><x-fc.icon name="briefcase" />Activer l’espace freelance</a>
    @if($isStaff)<a class="switch-link" href="{{ route('admin.home') }}"><x-fc.icon name="shield" />Administration</a>@endif
  @endif
</div>
@foreach($groups as $title => $items)
  @continue(count($items) === 0)
  <div class="side-group"><span class="side-title" id="sg-{{ $drawer ? 'd-' : '' }}{{ \Illuminate\Support\Str::slug($title) }}">{{ $title }}</span>
    <nav class="side-nav" aria-labelledby="sg-{{ $drawer ? 'd-' : '' }}{{ \Illuminate\Support\Str::slug($title) }}">
      @foreach($items as [$route, $label, $icon, $match, $params, $extra])
        <a href="{{ route($route, $params) }}" @if(request()->routeIs(...$match) && ($route !== 'info' || request()->route('page') === 'aide')) aria-current="page" @endif><x-fc.icon :name="$icon" />{{ $label }}
          @if($extra === 'messages')<livewire:unread-badge kind="messages" />@elseif($extra === 'notifications')<livewire:unread-badge kind="notifications" />@elseif($extra !== null)<span class="count-badge" aria-label="{{ $extra }} en attente">{{ $extra }}</span>@endif</a>
      @endforeach
    </nav></div>
@endforeach
<div class="side-foot"><form method="post" action="{{ route('logout') }}">@csrf<button class="side-logout" type="submit"><x-fc.icon name="lock" />Se déconnecter</button></form></div>

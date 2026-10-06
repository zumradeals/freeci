{{-- Navigation de l'espace actif (client ou freelance). Lien actif = page courante. --}}
@props(['space' => 'client', 'drawer' => false])
@php
  $isFreelance = auth()->user()->hasRole('freelance');
  $cur = fn (string ...$routes) => request()->routeIs(...$routes) ? 'page' : null;
@endphp
@if($space === 'admin')
  @php($pending = app(\App\Modules\Admin\Queries\ModerationQueue::class)->counts())
  <a href="{{ route('admin.home') }}" @if($cur('admin.home')) aria-current="page" @endif><x-fc.icon name="grid" />Tableau de bord</a>
  <a href="{{ route('admin.moderation') }}" @if($cur('admin.moderation*')) aria-current="page" @endif><x-fc.icon name="shield" />Modération @if($pending['services'] + $pending['missions'] > 0)<span class="count-badge" aria-label="{{ $pending['services'] + $pending['missions'] }} en attente">{{ $pending['services'] + $pending['missions'] }}</span>@endif</a>
  <a href="{{ route('admin.users') }}" @if($cur('admin.users*')) aria-current="page" @endif><x-fc.icon name="user" />Utilisateurs</a>
  <a href="{{ route('admin.audit') }}" @if($cur('admin.audit*')) aria-current="page" @endif><x-fc.icon name="clipboard" />Journal d’audit</a>
  <a href="{{ route('admin.security') }}" @if($cur('admin.security*')) aria-current="page" @endif><x-fc.icon name="lock" />Ma sécurité</a>
@elseif($space === 'freelancer')
  <a href="{{ route('freelance.dashboard') }}" @if($cur('freelance.dashboard')) aria-current="page" @endif><x-fc.icon name="grid" />Vue d’ensemble</a>
  <a href="{{ route('freelance.orders') }}" @if($cur('freelance.orders')) aria-current="page" @endif><x-fc.icon name="clipboard" />Demandes et commandes</a>
  <a href="{{ route('freelance.services') }}" @if($cur('freelance.services*')) aria-current="page" @endif><x-fc.icon name="briefcase" />Mes services</a>
  <a href="{{ route('missions.index') }}" @if($cur('missions.*')) aria-current="page" @endif><x-fc.icon name="search" />Missions ouvertes</a>
  <a href="{{ route('freelance.proposals') }}" @if($cur('freelance.proposals*')) aria-current="page" @endif><x-fc.icon name="pencil" />Mes propositions</a>
  <a href="{{ route('freelance.profile') }}" @if($cur('freelance.profile')) aria-current="page" @endif><x-fc.icon name="user" />Profil</a>
  <a href="{{ route('messages.index', ['espace' => 'freelance']) }}" @if($cur('messages.*')) aria-current="page" @endif><x-fc.icon name="message" />Messages<livewire:unread-badge kind="messages" /></a>
  <a href="{{ route('notifications.index', ['espace' => 'freelance']) }}" @if($cur('notifications.*')) aria-current="page" @endif><x-fc.icon name="inbox" />Notifications<livewire:unread-badge kind="notifications" /></a>
@else
  <a href="{{ route('account.dashboard') }}" @if($cur('account.dashboard')) aria-current="page" @endif><x-fc.icon name="grid" />Vue d’ensemble</a>
  <a href="{{ route('orders.index') }}" @if($cur('orders.index')) aria-current="page" @endif><x-fc.icon name="clipboard" />Commandes</a>
  <a href="{{ route('client.missions') }}" @if($cur('client.missions*')) aria-current="page" @endif><x-fc.icon name="briefcase" />Mes missions</a>
  <a href="{{ route('messages.index') }}" @if($cur('messages.*')) aria-current="page" @endif><x-fc.icon name="message" />Messages<livewire:unread-badge kind="messages" /></a>
  <a href="{{ route('notifications.index') }}" @if($cur('notifications.*')) aria-current="page" @endif><x-fc.icon name="inbox" />Notifications<livewire:unread-badge kind="notifications" /></a>
@endif
@if($drawer && $space === 'admin')
  <a href="{{ route('account.dashboard') }}"><x-fc.icon name="user" />Espace client</a>
@elseif($drawer)
  @if($space === 'freelancer')
    <a href="{{ route('account.dashboard') }}"><x-fc.icon name="user" />Passer à l’espace client</a>
  @elseif($isFreelance)
    <a href="{{ route('freelance.dashboard') }}"><x-fc.icon name="briefcase" />Passer à l’espace freelance</a>
  @else
    <a href="{{ route('freelance.activate') }}"><x-fc.icon name="briefcase" />Activer l’espace freelance</a>
  @endif
@endif

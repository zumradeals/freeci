{{-- Navigation de l'espace actif (client ou freelance). Lien actif = page courante. --}}
@props(['space' => 'client', 'drawer' => false])
@php
  $isFreelance = auth()->user()->hasRole('freelance');
  $cur = fn (string ...$routes) => request()->routeIs(...$routes) ? 'page' : null;
@endphp
@if($space === 'freelancer')
  <a href="{{ route('freelance.dashboard') }}" @if($cur('freelance.dashboard')) aria-current="page" @endif><x-fc.icon name="grid" />Vue d’ensemble</a>
  <a href="{{ route('freelance.orders') }}" @if($cur('freelance.orders')) aria-current="page" @endif><x-fc.icon name="clipboard" />Demandes et commandes</a>
  <a href="{{ route('freelance.services') }}" @if($cur('freelance.services')) aria-current="page" @endif><x-fc.icon name="briefcase" />Mes services</a>
  <a href="{{ route('freelance.profile') }}" @if($cur('freelance.profile')) aria-current="page" @endif><x-fc.icon name="user" />Profil</a>
  <a href="{{ route('coming-soon', 'messages') }}"><x-fc.icon name="message" />Messages <x-fc.soon /></a>
@else
  <a href="{{ route('account.dashboard') }}" @if($cur('account.dashboard')) aria-current="page" @endif><x-fc.icon name="grid" />Vue d’ensemble</a>
  <a href="{{ route('orders.index') }}" @if($cur('orders.index')) aria-current="page" @endif><x-fc.icon name="clipboard" />Commandes</a>
  <a href="{{ route('coming-soon', 'missions') }}"><x-fc.icon name="briefcase" />Missions <x-fc.soon /></a>
  <a href="{{ route('coming-soon', 'messages') }}"><x-fc.icon name="message" />Messages <x-fc.soon /></a>
@endif
@if($drawer)
  @if($space === 'freelancer')
    <a href="{{ route('account.dashboard') }}"><x-fc.icon name="user" />Passer à l’espace client</a>
  @elseif($isFreelance)
    <a href="{{ route('freelance.dashboard') }}"><x-fc.icon name="briefcase" />Passer à l’espace freelance</a>
  @else
    <a href="{{ route('freelance.activate') }}"><x-fc.icon name="briefcase" />Activer l’espace freelance</a>
  @endif
@endif

@props(['title'=>null, 'space'=>'client'])
<!doctype html>
<html lang="fr">
<head>
@include('partials.head', ['title' => $title, 'robots' => 'noindex, nofollow'])
</head>
<body class="">
<a class="skip-link" href="#contenu">Aller au contenu</a>
@include('partials.sprite')
@include('partials.demo-bar')
<header class="site-header header-app">
  <div class="container bar">
    <a class="logo" href="{{ route('home') }}" aria-label="FreeCI, accueil"><svg width="32" height="32" aria-hidden="true" focusable="false"><use href="#logo-mark"/></svg><span class="wm">Free<b>CI</b></span></a>
    <a class="cat-link" href="{{ route('services.index') }}"><x-fc.icon name="search" />Catalogue</a>
    <a class="account-chip" href="{{ route('account.dashboard') }}" aria-current="page"><span class="avatar" aria-hidden="true">{{ auth()->user()->initials() }}</span><span>{{ auth()->user()->name }}<small>{{ $space === 'freelancer' ? 'Espace freelance' : 'Espace client' }}</small></span></a>
    <button class="menu-btn" type="button" data-open="drawer" aria-haspopup="dialog"><x-fc.icon name="menu" :size="22" /><span>Menu</span></button>
  </div>
</header>
<div class="app">
<aside class="sidebar" aria-label="{{ $space === 'freelancer' ? 'Espace freelance' : 'Espace client' }}"><div class="side-sticky">
  <nav class="side-nav" aria-label="{{ $space === 'freelancer' ? 'Espace freelance' : 'Espace client' }}"><x-fc.space-nav :space="$space" /></nav>
  <nav class="side-nav" aria-label="Changer d’espace">
    @if($space === 'freelancer')<a href="{{ route('account.dashboard') }}"><x-fc.icon name="user" />Espace client</a>
    @elseif(auth()->user()->hasRole('freelance'))<a href="{{ route('freelance.dashboard') }}"><x-fc.icon name="briefcase" />Espace freelance</a>
    @else<a href="{{ route('freelance.activate') }}"><x-fc.icon name="briefcase" />Activer l’espace freelance</a>@endif
  </nav>
  @if(auth()->user()->isAdministrator())
  <nav class="side-nav" aria-label="Administration"><a href="{{ route('admin.home') }}"><x-fc.icon name="shield" />Administration <x-fc.soon /></a></nav>
  @endif
  <nav class="side-nav side-bottom" aria-label="Compte et aide">
    <a href="{{ route('coming-soon', 'compte') }}"><x-fc.icon name="user" />Compte <x-fc.soon /></a>
    <a href="{{ route('coming-soon', 'aide') }}"><x-fc.icon name="info" />Aide <x-fc.soon /></a>
    <form method="post" action="{{ route('logout') }}">@csrf<button class="side-logout" type="submit"><x-fc.icon name="lock" />Se déconnecter</button></form>
  </nav>
</div></aside>
<main id="contenu" class="main"><div class="main-inner">
@if(session('status'))<div class="notice tone-success" role="status"><x-fc.icon name="check-circle" /><p>{{ session('status') }}</p></div>@endif
@if(session('error'))<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>{{ session('error') }}</p></div>@endif
{{ $slot }}
</div></main>
</div>
<footer class="site-footer slim"><div class="container">
  <p>FreeCI · démonstration (données fictives)</p>
  <ul><li><a href="{{ route('coming-soon', 'aide') }}">Aide</a></li><li><a href="{{ route('coming-soon', 'conditions') }}">Conditions</a></li><li><a href="{{ route('coming-soon', 'confidentialite') }}">Confidentialité</a></li></ul>
</div></footer>
@include('partials.drawer', ['space' => $space])
@livewireScripts
</body>
</html>

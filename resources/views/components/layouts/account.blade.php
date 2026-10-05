@props(['title'=>null])
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
    <a class="account-chip" href="{{ route('account.dashboard') }}" aria-current="page"><span class="avatar" aria-hidden="true">{{ auth()->user()->initials() }}</span><span>{{ auth()->user()->name }}<small>Espace client</small></span></a>
    <button class="menu-btn" type="button" data-open="drawer" aria-haspopup="dialog"><x-fc.icon name="menu" :size="22" /><span>Menu</span></button>
  </div>
</header>
<div class="app">
<aside class="sidebar" aria-label="Espace client"><div class="side-sticky">
  <nav class="side-nav" aria-label="Espace client">
    <a href="{{ route('account.dashboard') }}" aria-current="page"><x-fc.icon name="grid" />Vue d’ensemble</a>
    <a href="{{ route('coming-soon', 'commandes') }}"><x-fc.icon name="clipboard" />Commandes <x-fc.soon /></a>
    <a href="{{ route('coming-soon', 'missions') }}"><x-fc.icon name="briefcase" />Missions <x-fc.soon /></a>
    <a href="{{ route('coming-soon', 'messages') }}"><x-fc.icon name="message" />Messages <x-fc.soon /></a>
  </nav>
  <nav class="side-nav side-bottom" aria-label="Compte et aide">
    <a href="{{ route('coming-soon', 'compte') }}"><x-fc.icon name="user" />Compte <x-fc.soon /></a>
    <a href="{{ route('coming-soon', 'aide') }}"><x-fc.icon name="info" />Aide <x-fc.soon /></a>
    <form method="post" action="{{ route('logout') }}">@csrf<button class="side-logout" type="submit"><x-fc.icon name="lock" />Se déconnecter</button></form>
  </nav>
</div></aside>
<main id="contenu" class="main"><div class="main-inner">
{{ $slot }}
</div></main>
</div>
<footer class="site-footer slim"><div class="container">
  <p>FreeCI · démonstration (données fictives)</p>
  <ul><li><a href="{{ route('coming-soon', 'aide') }}">Aide</a></li><li><a href="{{ route('coming-soon', 'conditions') }}">Conditions</a></li><li><a href="{{ route('coming-soon', 'confidentialite') }}">Confidentialité</a></li></ul>
</div></footer>
@include('partials.drawer')
@livewireScripts
</body>
</html>

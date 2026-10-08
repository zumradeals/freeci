@props(['title'=>null, 'space'=>'client'])
<!doctype html>
<html lang="fr">
<head>
@include('partials.head', ['title' => $title, 'robots' => 'noindex, nofollow'])
</head>
<body class="">
<a class="skip-link" href="#contenu">Aller au contenu</a>
@include('partials.sprite')
<header class="site-header header-app">
  <div class="container bar">
    <a class="logo" href="{{ route('home') }}" aria-label="FreeCI, accueil"><svg width="32" height="32" aria-hidden="true" focusable="false"><use href="#logo-mark"/></svg><span class="wm">Free<b>CI</b></span></a>
    <a class="cat-link" href="{{ route('services.index') }}"><x-fc.icon name="search" />Catalogue</a>
    <a class="account-chip" href="{{ route('account.dashboard') }}" aria-current="page"><x-fc.avatar :name="auth()->user()->name" :user="auth()->id()" /><span>{{ auth()->user()->name }}<small>{{ $space === 'freelancer' ? 'Espace freelance' : 'Espace client' }}</small></span></a>
    <button class="menu-btn" type="button" data-open="drawer" aria-haspopup="dialog"><x-fc.icon name="menu" :size="22" /><span>Menu</span></button>
  </div>
</header>
<div class="app">
<aside class="sidebar" aria-label="{{ $space === 'freelancer' ? 'Espace freelance' : 'Espace client' }}"><div class="side-sticky"><x-fc.sidebar :space="$space" /></div></aside>
<main id="contenu" class="main"><div class="main-inner">
@if(session('status'))<div class="notice tone-success" role="status"><x-fc.icon name="check-circle" /><p>{{ session('status') }}</p></div>@endif
@if(auth()->user()->isSuspended())<div class="notice tone-warning" role="status"><x-fc.icon name="warn" /><p><strong>Votre compte est suspendu.</strong> Vous ne pouvez pas démarrer de nouvelle activité (demande, mission, proposition, nouvelle conversation). Vos commandes en cours se poursuivent normalement.</p></div>@endif
@if(session('error'))<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>{{ session('error') }}</p></div>@endif
{{ $slot }}
</div></main>
</div>
<footer class="site-footer slim"><div class="container">
  <p>FreeCI</p>
  <ul><li><a href="{{ route('info', 'aide') }}">Aide</a></li><li><a href="{{ route('info', 'contact') }}">Contact</a></li><li><a href="{{ route('info', 'conditions') }}">Conditions</a></li><li><a href="{{ route('info', 'confidentialite') }}">Confidentialité</a></li><li><a href="{{ route('info', 'mentions-legales') }}">Mentions légales</a></li></ul>
</div></footer>
@include('partials.drawer', ['space' => $space])
@livewireScripts
</body>
</html>

@props(['title'=>null, 'space'=>'admin'])
<!doctype html>
<html lang="fr">
<head>
@include('partials.head', ['title' => $title, 'robots' => 'noindex, nofollow'])
</head>
<body class="">
<a class="skip-link" href="#contenu">Aller au contenu</a>
@include('partials.sprite')
@include('partials.mode-bar', ['wide' => true])
<header class="site-header header-app">
  <div class="container bar">
    <a class="logo" href="{{ route('home') }}" aria-label="FreeCI, accueil"><svg width="32" height="32" aria-hidden="true" focusable="false"><use href="#logo-mark"/></svg><span class="wm">Free<b>CI</b></span></a>
    <a class="cat-link" href="{{ route('services.index') }}"><x-fc.icon name="search" />Catalogue</a>
    <a class="account-chip" href="{{ route('admin.home') }}" aria-current="page"><span class="avatar" aria-hidden="true">{{ auth()->user()->initials() }}</span><span>{{ auth()->user()->name }}<small>Administration</small></span></a>
    <button class="menu-btn" type="button" data-open="drawer" aria-haspopup="dialog"><x-fc.icon name="menu" :size="22" /><span>Menu</span></button>
  </div>
</header>
<div class="app">
<aside class="sidebar" aria-label="Administration"><div class="side-sticky"><x-fc.sidebar space="admin" /></div></aside>
<main id="contenu" class="main"><div class="main-inner">
@if(session('status'))<div class="notice tone-success" role="status"><x-fc.icon name="check-circle" /><p>{{ session('status') }}</p></div>@endif
@if(session('error'))<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>{{ session('error') }}</p></div>@endif
{{ $slot }}
</div></main>
</div>
<footer class="site-footer slim"><div class="container">
  <p>FreeCI @unless(\App\Integrations\Payments\PaymentMode::isLive())· mode test : aucun argent réel @endunless</p>
  <ul><li><a href="{{ route('info', 'aide') }}">Aide</a></li><li><a href="{{ route('info', 'contact') }}">Contact</a></li><li><a href="{{ route('info', 'conditions') }}">Conditions</a></li><li><a href="{{ route('info', 'confidentialite') }}">Confidentialité</a></li><li><a href="{{ route('info', 'mentions-legales') }}">Mentions légales</a></li></ul>
</div></footer>
@include('partials.drawer', ['space' => 'admin'])
@livewireScripts
</body>
</html>

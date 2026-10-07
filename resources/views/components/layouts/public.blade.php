@props(['title'=>null,'description'=>null,'robots'=>null,'mainClass'=>null])
<!doctype html>
<html lang="fr">
<head>
@include('partials.head', ['title' => $title, 'description' => $description, 'robots' => $robots])
</head>
<body class="">
<a class="skip-link" href="#contenu">Aller au contenu</a>
@include('partials.sprite')
<header class="site-header">
  <div class="container bar">
    <a class="logo" href="{{ route('home') }}" aria-label="FreeCI, accueil"><svg width="32" height="32" aria-hidden="true" focusable="false"><use href="#logo-mark"/></svg><span class="wm">Free<b>CI</b></span></a>
    <nav class="main-nav" aria-label="Navigation principale"><x-fc.menu-links /></nav>
    @guest
      <a class="btn btn-link only-wide" href="{{ route('login') }}">Connexion</a>
      <a class="btn btn-secondary header-cta" href="{{ route('register') }}">Créer un compte</a>
    @endguest
    @auth
      <a class="account-chip" href="{{ route('account.dashboard') }}"><span class="avatar" aria-hidden="true">{{ auth()->user()->initials() }}</span><span>{{ auth()->user()->name }}<small>Espace client</small></span></a>
    @endauth
    <button class="menu-btn" type="button" data-open="drawer" aria-haspopup="dialog"><x-fc.icon name="menu" :size="22" /><span>Menu</span></button>
  </div>
</header>
<main id="contenu" @if($mainClass) class="{{ $mainClass }}" @endif>
@if(session('status'))<div class="container" style="padding-top:16px"><div class="notice tone-success" role="status"><x-fc.icon name="check-circle" /><p>{{ session('status') }}</p></div></div>@endif
@if(session('error'))<div class="container" style="padding-top:16px"><div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>{{ session('error') }}</p></div></div>@endif
{{ $slot }}
</main>
<footer class="site-footer">
  <div class="container">
    <div class="cols">
      <div class="stack-sm">
        <a class="logo" href="{{ route('home') }}" aria-label="FreeCI, accueil"><svg width="32" height="32" aria-hidden="true" focusable="false"><use href="#logo-mark"/></svg><span class="wm">Free<b>CI</b></span></a>
        <p class="muted" style="max-width:32em">{{ config('freeci.home.tagline') ?: \App\Modules\Admin\Settings\AppSettings::default('home.tagline') }}</p>
      </div>
      @foreach(['footer_discover' => 'Découvrir', 'footer_help' => 'Aide', 'footer_info' => 'Informations'] as $area => $heading)
      <div><h2>{{ $heading }}</h2><ul>
        @foreach(\App\Modules\Admin\Navigation\MenuItems::links($area) as [$label, $url, $dest])<li><a href="{{ $url }}">{{ $label }}</a></li>@endforeach</ul></div>
      @endforeach
    </div>
  </div>
</footer>
@include('partials.drawer')
@livewireScripts
@stack('scripts')
</body>
</html>

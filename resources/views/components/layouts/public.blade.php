@props(['title'=>null,'description'=>null,'robots'=>null,'mainClass'=>null])
<!doctype html>
<html lang="fr">
<head>
@include('partials.head', ['title' => $title, 'description' => $description, 'robots' => $robots])
</head>
<body class="">
<a class="skip-link" href="#contenu">Aller au contenu</a>
@include('partials.sprite')
@include('partials.demo-bar')
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
{{ $slot }}
</main>
<footer class="site-footer">
  <div class="container">
    <div class="cols">
      <div class="stack-sm">
        <a class="logo" href="{{ route('home') }}" aria-label="FreeCI, accueil"><svg width="32" height="32" aria-hidden="true" focusable="false"><use href="#logo-mark"/></svg><span class="wm">Free<b>CI</b></span></a>
        <p class="muted" style="max-width:32em">Trouver une compétence, conclure un accord clair, suivre la prestation et comprendre sa situation financière.</p>
      </div>
      <div><h2>FreeCI</h2><ul>
        <li><a href="{{ route('home') }}#comment">Comment ça marche</a></li>
        <li><a href="{{ route('coming-soon', 'aide') }}">Centre d’aide</a></li></ul></div>
      <div><h2>Informations</h2><ul>
        <li><a href="{{ route('coming-soon', 'conditions') }}">Conditions d’utilisation</a></li>
        <li><a href="{{ route('coming-soon', 'confidentialite') }}">Confidentialité</a></li>
        <li><a href="{{ route('coming-soon', 'mentions-legales') }}">Mentions légales</a></li></ul></div>
    </div>
    <p class="legal">Version de démonstration. Noms, prix et services fictifs ; textes juridiques et mentions de l’opérateur à rédiger.</p>
  </div>
</footer>
@include('partials.drawer')
@livewireScripts
@stack('scripts')
</body>
</html>

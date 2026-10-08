@props(['title'=>null,'description'=>null,'robots'=>null,'mainClass'=>null])
<!doctype html>
<html lang="fr">
<head>
@include('partials.head', ['title' => $title, 'description' => $description, 'robots' => $robots])
</head>
<body class="" id="top">
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
<footer class="ft">
  @if(auth()->guest() && ! request()->routeIs('home'))
  <section class="ft-cta" aria-label="Commencer"><div class="in">
    <div><h2>Un projet à confier, un talent à proposer ?</h2><p>Trouvez un service ou publiez le vôtre.</p></div>
    <div class="acts"><a class="b1" href="{{ route('services.index') }}">Trouver un service</a><a class="b2" href="{{ route('register') }}">Proposer mes services</a></div>
  </div></section>
  @endif
  <div class="ft-main">
    <div class="ft-brand">
      <a class="logo" href="{{ route('home') }}" aria-label="FreeCI, accueil"><svg width="36" height="36" aria-hidden="true" focusable="false"><use href="#logo-mark"/></svg><span class="wm">Free<b>CI</b></span></a>
      <p>{{ config('freeci.home.tagline') ?: \App\Modules\Admin\Settings\AppSettings::default('home.tagline') }}</p>
      <div class="ft-facts"><span><x-fc.icon name="globe" :size="16" />Côte d’Ivoire</span><span><x-fc.icon name="card" :size="16" />Prix en FCFA</span><span><x-fc.icon name="shield" :size="16" />Fichiers contrôlés</span></div>
    </div>
    @foreach(['footer_discover' => 'Découvrir', 'footer_help' => 'Aide', 'footer_info' => 'Informations'] as $area => $heading)
    <nav aria-label="{{ $heading }}"><h2>{{ $heading }}</h2><ul>
      @foreach(\App\Modules\Admin\Navigation\MenuItems::links($area) as [$label, $url, $dest])<li><a href="{{ $url }}">{{ $label }}</a></li>@endforeach</ul></nav>
    @endforeach
  </div>
  <div class="ft-bar"><div class="in">
    <span>© {{ now()->year }} FreeCI. Tous droits réservés.</span>
    <nav aria-label="Liens légaux"><a href="{{ route('info', 'conditions') }}">Conditions</a><a href="{{ route('info', 'confidentialite') }}">Confidentialité</a><a href="{{ route('info', 'mentions-legales') }}">Mentions légales</a></nav>
    <a class="ft-top" href="#top"><x-fc.icon name="arrow-right" :size="14" class="up" />Haut de page</a>
  </div></div>
</footer>
@include('partials.drawer')
@livewireScripts
@stack('scripts')
</body>
</html>

<dialog class="drawer" id="drawer" aria-label="Menu">
  <div class="drawer-inner"><div class="drawer-head"><span class="logo" style="min-height:0"><svg width="28" height="28" aria-hidden="true" focusable="false"><use href="#logo-mark"/></svg><span class="wm" style="font-size:1.125rem;font-weight:750;letter-spacing:-.03em">Free<b style="color:var(--accent-700)">CI</b></span></span>
      <button class="icon-btn" type="button" data-close aria-label="Fermer le menu" style="border:0"><x-fc.icon name="close" :size="24" /></button></div>
    <div class="drawer-body">
      @guest
        <a class="btn btn-primary btn-lg btn-block" href="{{ route('register') }}">Créer un compte</a>
        <a class="btn btn-secondary btn-lg btn-block" href="{{ route('login') }}">Se connecter</a>
      @endguest
      <nav aria-label="Navigation principale"><div class="nav-list"><x-fc.menu-links :drawer="true" /></div></nav>
      @auth
        <div><p class="nav-title">Mon espace</p>
          <nav aria-label="Espace client"><div class="nav-list">
            <a href="{{ route('account.dashboard') }}"><x-fc.icon name="grid" />Vue d’ensemble</a>
            <a href="{{ route('coming-soon', 'commandes') }}"><x-fc.icon name="clipboard" />Commandes <x-fc.soon /></a>
            <a href="{{ route('coming-soon', 'messages') }}"><x-fc.icon name="message" />Messages <x-fc.soon /></a>
            <a href="{{ route('coming-soon', 'compte') }}"><x-fc.icon name="user" />Compte <x-fc.soon /></a>
            <a href="{{ route('coming-soon', 'aide') }}"><x-fc.icon name="info" />Aide <x-fc.soon /></a>
          </div></nav>
          <form method="post" action="{{ route('logout') }}" style="margin-top:12px">@csrf<button class="btn btn-secondary btn-block" type="submit">Se déconnecter</button></form>
        </div>
      @endauth
    </div>
  </div>
</dialog>

<x-layouts.account title="Administration">
  <header class="page-head">
    <div class="row-top"><div><p class="eyebrow">Administration</p><h1 class="t-h1">Espace d’administration</h1></div></div>
  </header>
  <div class="card empty"><span class="ico-lg"><x-fc.icon name="shield" :size="26" /></span>
    <p style="font-weight:600">Bientôt disponible.</p>
    <p class="muted" style="max-width:40em">Votre habilitation d’administrateur est active, mais aucune fonction d’administration n’existe encore dans cette version (modération, support, paramètres). Vos espaces client et freelance seront accessibles ici dès leur ouverture.</p>
    <p class="muted small">Connecté : {{ $user->email }}</p>
    <a class="btn btn-secondary" href="{{ route('account.dashboard') }}">Retour à l’espace client</a></div>
</x-layouts.account>

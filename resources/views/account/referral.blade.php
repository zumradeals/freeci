<x-layouts.account title="Parrainage" :space="$space">
  <div class="page-body">
    <nav class="sd-crumbs" aria-label="Fil d’Ariane"><a href="{{ route('account.settings') }}">Mon compte</a> › <span aria-current="page">Parrainage</span></nav>
    <div class="sx-head"><div><p class="sx-kicker">Mon compte</p><h1>Parrainage</h1><p class="muted">Invitez des personnes de confiance : vous êtes récompensé quand leur première commande réelle est validée.</p></div></div>
    <div class="ac-grid"><div class="ac-main">
      <section class="ed-card" aria-labelledby="h-code"><h2 id="h-code" style="margin:0">Votre code</h2>
        <div class="rf-code"><b id="rf-code-v">{{ $r['code'] }}</b><button class="btn btn-secondary" type="button" data-copy="#rf-code-v">Copier le code</button></div>
        <div class="field"><label for="rf-link">Votre lien d’inscription</label><input class="input" id="rf-link" readonly value="{{ $r['link'] }}" onfocus="this.select()"></div>
        <div class="row" style="gap:10px"><button class="btn btn-primary" type="button" data-copy="#rf-link">Copier le lien</button>
          <a class="btn btn-secondary" href="https://wa.me/?text={{ rawurlencode('Rejoins-moi sur FreeCI : '.$r['link']) }}" target="_blank" rel="noopener">Partager sur WhatsApp</a></div></section>
      <section class="ed-card" aria-labelledby="h-rew"><h2 id="h-rew" style="margin:0">Vos récompenses</h2>
        <div class="rf-k"><div class="rf-c"><small>Commandes à commission offerte</small><b>{{ $r['available'] }}</b><span class="muted small">à utiliser sur vos prochaines commandes réelles</span></div>
          <div class="rf-c"><small>Filleuls qualifiés</small><b>{{ $r['qualified'] }} / {{ $r['max'] }}</b><span class="muted small">plafond par parrain</span></div>
          <div class="rf-c"><small>Filleuls inscrits</small><b>{{ $r['registered'] }}</b><span class="muted small">dont {{ collect($r['items'])->where('state', 'qualified')->count() }} qualifié{{ collect($r['items'])->where('state', 'qualified')->count() > 1 ? 's' : '' }}</span></div></div>
        @unless($r['isFreelance'])<div class="notice tone-info" role="note"><x-fc.icon name="info" /><p>Votre récompense s’applique quand vous êtes <strong>freelance</strong> : si votre espace freelance n’est pas encore activé, elle vous attend. Elle ne change jamais le prix payé par un client.</p></div>@endunless
      </section>
      <section class="ed-card" aria-labelledby="h-fil"><h2 id="h-fil" style="margin:0">Vos filleuls</h2>
        @forelse($r['items'] as $i)
          <div class="rf-r"><div><b>{{ $i['name'] }}</b><small>Inscrit le {{ $i['since'] }}</small></div><span><span class="badge tone-{{ $i['tone'] }}">{{ $i['label'] }}</span>@if(! $i['freelance'] && $i['state'] !== 'qualified') <span class="muted small">· espace freelance non activé</span>@endif</span>
            <span class="muted small">@if($i['rewarded'])Récompense accordée le {{ $i['rewarded'] }}@if($i['capped']) (plafond atteint : sans récompense pour vous)@endif @endif</span></div>
        @empty
          <p class="muted" style="margin:0">Personne ne s’est encore inscrit avec votre code. Partagez votre lien pour commencer.</p>
        @endforelse
        <p class="muted small" style="margin:0">Prénom et initiale seulement : jamais d’e-mail ni de détail de commande.</p></section>
    </div>
    <aside class="ac-side"><section class="ed-ck"><h3>Comment ça marche</h3><ol class="rf-tl">
      <li><span class="n">1</span><span>Votre filleul s’inscrit avec votre code (lien ou saisie).</span></li>
      <li><span class="n">2</span><span>Il réussit sa <b>première commande réelle</b> : payée, livrée et validée par le client.</span></li>
      <li><span class="n">3</span><span><b>Vous et lui</b> obtenez chacun {{ $r['freeOrders'] }} commandes à {{ rtrim(rtrim(number_format($r['rate'], 2, ',', ''), '0'), ',') }} % de commission.</span></li></ol></section>
      <section class="ed-ck"><h3>Règles</h3><ul class="av-tl">
        <li><x-fc.icon name="check" :size="18" /><span>Les commandes de test ne comptent jamais.</span></li>
        <li><x-fc.icon name="check" :size="18" /><span>Pas d’auto-parrainage ; un compte n’est parrainé qu’une fois, à l’inscription.</span></li>
        <li><x-fc.icon name="check" :size="18" /><span>{{ $r['max'] }} filleuls qualifiés au plus par parrain.</span></li>
        <li><x-fc.icon name="check" :size="18" /><span>Non cumulable avec un code promotionnel : le plus favorable s’applique à chaque commande.</span></li>
        <li><x-fc.icon name="check" :size="18" /><span>Récompense retirée si le compte est suspendu ou en cas d’abus.</span></li></ul></section></aside></div>
  </div>
</x-layouts.account>

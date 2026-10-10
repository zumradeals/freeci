<x-layouts.account title="Inviter à une mission" space="client">
  <div class="page-body">
    <nav class="sd-crumbs" aria-label="Fil d’Ariane"><a href="{{ route('freelances.show', $slug) }}">Profil de {{ $p['name'] }}</a> › <span aria-current="page">Inviter à une mission</span></nav>
    <div class="sx-head"><div><p class="sx-kicker">Client</p><h1>Inviter à une mission</h1><p class="muted">{{ $p['name'] }} décidera librement de proposer ou non.</p></div></div>
    @if(session('error'))<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>{{ session('error') }}</p></div>@endif
    @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>{{ $errors->first() }}</p></div>@endif
    <div class="ac-grid"><div class="ac-main">
      <div class="iv-fp"><x-fc.avatar :name="$p['name']" :user="$p['userId']" size="lg" :alt="'Photo de '.$p['name']" /><div><b>{{ $p['name'] }}</b><br><span class="muted small">{{ $p['headline'] }}@if($p['city']) · {{ $p['city'] }}@endif</span><div style="margin-top:6px"><x-fc.seller-pill :user="$p['userId']" rate /></div></div></div>
      @if(! $p['seller']['available'])
        <div class="notice tone-warning" role="status"><x-fc.icon name="warn" /><p>Ce freelance est indisponible pour le moment : il ne peut pas être invité.</p></div>
      @elseif(! count($choices))
        <section class="ed-card"><h2 style="margin:0">Vous n’avez aucune mission ouverte</h2><p class="muted" style="margin:0">Seule une mission publiée, dont la date limite n’est pas passée, peut donner lieu à une invitation.</p><div><a class="btn btn-primary" href="{{ route('client.missions.new') }}">Publier une mission</a></div></section>
      @else
        <form class="ed-card" method="post" action="{{ route('invitations.store', $slug) }}" data-once>@csrf
          <h2 style="margin:0">Choisissez une de vos missions ouvertes</h2>
          @foreach($choices as $c)
            <label class="iv-sel {{ $c['invited'] ? 'dis' : '' }}"><input type="radio" name="mission" value="{{ $c['id'] }}" @checked(old('mission', $loop->first && ! $c['invited'] ? $c['id'] : null) === $c['id']) @disabled($c['invited']) required><span><b>{{ $c['title'] }}</b><small>{{ $c['budget'] }} · candidature avant le {{ $c['deadline'] }}</small></span>@if($c['invited'])<span class="badge">Déjà invité</span>@else<span class="badge tone-success">Ouverte</span>@endif</label>
          @endforeach
          <div class="field"><label for="iv-msg">Message à {{ $p['name'] }} (facultatif, {{ $max }} caractères)</label><textarea class="textarea" id="iv-msg" name="message" rows="4" maxlength="{{ $max }}">{{ old('message') }}</textarea><p class="muted small" style="margin:0">Pas de téléphone, e-mail ni lien : les échanges restent dans FreeCI.</p></div>
          <div><button class="btn btn-primary btn-lg" type="submit">Envoyer l’invitation</button></div>
        </form>
      @endif
    </div>
    <aside class="ac-side"><section class="ed-ck"><h3>À savoir</h3><ul class="av-tl">
      <li><x-fc.icon name="check" :size="18" /><span>Une invitation n’est <b>pas une commande</b> et ne vous engage à rien. Le freelance peut proposer ou décliner.</span></li>
      <li><x-fc.icon name="check" :size="18" /><span>Il voit le titre, le budget, la date limite et votre message ; <b>votre identité et vos coordonnées restent privées</b>.</span></li>
      <li><x-fc.icon name="check" :size="18" /><span><b>Une invitation par mission et par freelance</b> ; {{ config('freeci.missions.invitations.per_mission') }} au plus par mission, {{ config('freeci.missions.invitations.per_client_day') }} par jour.</span></li>
      <li><x-fc.icon name="check" :size="18" /><span>La proposition suit les règles habituelles : vous comparez et choisissez comme d’habitude. L’invité n’a aucune priorité.</span></li></ul></section></aside></div>
  </div>
</x-layouts.account>

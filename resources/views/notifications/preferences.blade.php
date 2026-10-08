<x-layouts.account title="Préférences de notification" :space="$space">
  <div class="page-body">
    <nav class="crumbs" aria-label="Fil d’Ariane"><a class="back-m" href="{{ route('notifications.index') }}"><x-fc.icon name="arrow-right" :size="16" class="flip" />Notifications</a><a class="hide-m" href="{{ route('notifications.index') }}">Notifications</a><span class="sep hide-m" aria-hidden="true">›</span><span class="hide-m" aria-current="page">Préférences</span></nav>
    <div class="sx-head"><div><p class="sx-kicker">Notifications</p><h1>Préférences de notification</h1><p class="muted">Choisissez ce que vous recevez dans l’application et par courriel.</p></div></div>
    @unless($mailAvailable)<div class="rq-info" role="note"><x-fc.icon name="warn" :size="18" /><span><b>L’envoi de courriels n’est pas configuré sur cette installation.</b> Seules les notifications dans l’application sont actives ; vos choix de courriel sont enregistrés mais sans effet pour l’instant.</span></div>@endunless
    <div class="ac-grid"><form method="post" action="{{ route('notifications.preferences.save') }}" data-once>@csrf
      <section class="ed-card" aria-labelledby="h-opt"><h2 id="h-opt">Notifications facultatives</h2>
        <div class="ac-pf"><div class="ac-pr head" aria-hidden="true"><span>Événement</span><span>Dans l’application</span><span>Par courriel</span></div>
        @foreach($optional as $o)<div class="ac-pr" role="group" aria-label="{{ $o['label'] }}"><div><b>{{ $o['label'] }}</b><small>{{ $o['description'] }}</small></div>
          <label class="ac-sw"><input type="checkbox" name="pref[{{ $o['key'] }}][in_app]" value="1" @checked($o['in_app'])><i aria-hidden="true"></i><span>Application</span></label>
          <label class="ac-sw"><input type="checkbox" name="pref[{{ $o['key'] }}][email]" value="1" @checked($o['email'])><i aria-hidden="true"></i><span>Courriel</span></label></div>@endforeach</div>
        <p class="muted small">Un courriel est une simple invitation à consulter l’application, sans contenu.</p>
        <div><button class="btn btn-primary" type="submit" data-once-label="Enregistrement…">Enregistrer</button></div></section>
    </form>
    <aside class="ac-side"><section class="ed-ck" aria-labelledby="h-ess"><h3 id="h-ess">Notifications indispensables</h3>
      <p class="muted small">Liées à votre compte et à vos commandes : toujours actives dans l’application, et par courriel quand l’envoi est configuré. Elles ne se désactivent pas.</p>
      <ul class="ac-ess">@foreach($essential as $e)<li><x-fc.icon name="check" :size="18" /><div><b>{{ $e['label'] }}</b><small>{{ $e['description'] }}</small></div></li>@endforeach</ul></section></aside>
    </div>
  </div>
</x-layouts.account>

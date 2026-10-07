<x-layouts.account title="Préférences de notification" :space="$space">
  <nav class="crumbs" aria-label="Fil d’Ariane"><a class="back-m" href="{{ route('notifications.index') }}"><x-fc.icon name="arrow-right" :size="16" class="flip" />Notifications</a><a class="hide-m" href="{{ route('notifications.index') }}">Notifications</a><span class="sep hide-m" aria-hidden="true">›</span><span class="hide-m" aria-current="page">Préférences</span></nav>
  <header class="page-head"><div class="row-top"><div><p class="eyebrow">Notifications</p><h1 class="t-h1">Préférences de notification</h1><p class="lead">Choisissez ce que vous recevez dans l’application et par courriel.</p></div></div></header>
  @unless($mailAvailable)<div class="notice tone-warning" role="note"><x-fc.icon name="warn" /><p><strong>L’envoi de courriels n’est pas configuré sur cette installation.</strong> Seules les notifications dans l’application sont actives ; vos choix de courriel sont enregistrés mais sans effet pour l’instant.</p></div>@endunless
  <div class="split"><form method="post" action="{{ route('notifications.preferences.save') }}" data-once>@csrf
    <section class="card panel" aria-labelledby="h-opt"><h2 class="t-h2 card-title" id="h-opt">Notifications facultatives</h2>
      @foreach($optional as $o)<fieldset style="border:0;padding:0;margin:0 0 12px;min-width:0"><legend style="font-weight:650">{{ $o['label'] }}</legend><p class="muted small">{{ $o['description'] }}</p>
        <label class="check"><input type="checkbox" name="pref[{{ $o['key'] }}][in_app]" value="1" @checked($o['in_app'])> <span>Dans l’application</span></label>
        <label class="check"><input type="checkbox" name="pref[{{ $o['key'] }}][email]" value="1" @checked($o['email'])> <span>Par courriel (invitation à consulter l’application, sans contenu)</span></label></fieldset>@endforeach
      <button class="btn btn-primary" type="submit" data-once-label="Enregistrement…">Enregistrer</button></section>
  </form>
  <section class="card panel" aria-labelledby="h-ess"><h2 class="t-h2 card-title" id="h-ess">Notifications indispensables</h2>
    <p class="muted">Liées à votre compte et à vos commandes : toujours actives dans l’application, et par courriel quand l’envoi est configuré. Elles ne se désactivent pas.</p>
    <ul class="stack-sm">@foreach($essential as $e)<li><strong>{{ $e['label'] }}</strong><br><span class="muted small">{{ $e['description'] }}</span></li>@endforeach</ul></section>
  </div>
</x-layouts.account>

<x-layouts.account title="Alertes de missions" space="freelancer">
  <div class="page-body">
    <nav class="sd-crumbs" aria-label="Fil d’Ariane"><a href="{{ route('freelance.dashboard') }}">Espace freelance</a> › <span aria-current="page">Alertes de missions</span></nav>
    <div class="sx-head"><div><p class="sx-kicker">Espace freelance</p><h1>Alertes de missions</h1><p class="muted">Soyez prévenu quand une mission correspond à votre métier et à votre budget.</p></div><div class="sx-acts"><a class="btn btn-secondary" href="{{ route('freelance.recommended') }}">Missions pour vous</a></div></div>
    @if(session('error'))<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>{{ session('error') }}</p></div>@endif
    @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>{{ $errors->first() }}</p></div>@endif
    <div class="ac-grid"><div class="ac-main">
      <section class="ed-card" aria-labelledby="h-al"><h2 id="h-al" style="margin:0">Mes alertes <span class="muted small">({{ $max }} au plus)</span></h2>
        @forelse($alerts as $a)
          <div class="al-row {{ $a['active'] ? '' : 'off' }}"><div><b>{{ $a['category'] }}</b><small>{{ $a['active'] ? ($a['recent'] ? $a['recent'].' mission'.($a['recent'] > 1 ? 's' : '').' publiée'.($a['recent'] > 1 ? 's' : '').' ces 14 derniers jours' : 'Aucune mission récente') : 'Alerte en pause : aucune notification' }}</small></div><div>{{ $a['minLabel'] }}</div>
            <form method="post" action="{{ route('freelance.alerts.toggle', $a['id']) }}">@csrf<input type="hidden" name="active" value="{{ $a['active'] ? 0 : 1 }}"><button class="al-sw" type="submit" aria-label="{{ $a['active'] ? 'Mettre l’alerte en pause' : 'Activer l’alerte' }} : {{ $a['category'] }}"><span class="{{ $a['active'] ? 'on' : '' }}">Active</span><span class="{{ $a['active'] ? '' : 'on' }}">En pause</span></button></form>
            <form method="post" action="{{ route('freelance.alerts.destroy', $a['id']) }}" data-once>@csrf<button class="btn btn-link" type="submit">Supprimer<span class="sr-only"> l’alerte {{ $a['category'] }}</span></button></form></div>
        @empty
          <p class="muted">Vous n’avez aucune alerte. En attendant, « Missions pour vous » utilise les catégories de vos services publiés.</p>
        @endforelse
      </section>
      <section class="ed-card" aria-labelledby="h-new"><h2 id="h-new" style="margin:0">Nouvelle alerte</h2>
        <form method="post" action="{{ route('freelance.alerts.store') }}" class="al-f" data-once>@csrf
          <div class="field"><label for="al-cat">Catégorie</label><select class="select" id="al-cat" name="category" required>@foreach($categories as $c)<option value="{{ $c['slug'] }}" @selected(old('category') === $c['slug'])>{{ $c['name'] }}</option>@endforeach</select></div>
          <div class="field"><label for="al-min">Budget minimum (FCFA, facultatif)</label><input class="input" id="al-min" name="min_budget" type="text" inputmode="numeric" maxlength="15" value="{{ old('min_budget') }}"></div>
          <div><button class="btn btn-primary" type="submit">Créer l’alerte</button></div>
        </form>
        <p class="muted small" style="margin:0">Une alerte porte sur <b>une catégorie</b> et un <b>budget minimum</b>. Il n’y a pas de mots-clés : FreeCI ne lit pas vos messages ni votre historique.</p></section>
    </div>
    <aside class="ac-side"><section class="ed-ck"><h3>Comment ça marche</h3><ul class="av-tl">
      <li><x-fc.icon name="check" :size="18" /><span>Quand une mission est <b>publiée pour la première fois</b> (après contrôle), vous recevez une notification dans l’application si elle correspond à une alerte active.</span></li>
      <li><x-fc.icon name="check" :size="18" /><span><b>Une seule notification par mission</b>, même si plusieurs alertes correspondent.</span></li>
      <li><x-fc.icon name="check" :size="18" /><span>Au plus <b>{{ config('freeci.missions.alerts.daily_notifications') }} notifications d’alerte par jour</b> ; les autres restent visibles dans « Missions pour vous ».</span></li>
      <li><x-fc.icon name="check" :size="18" /><span>Aucune notification pendant que vous êtes <b>indisponible</b> ; vos alertes sont conservées.</span></li>
      <li><x-fc.icon name="check" :size="18" /><span>Le courriel est facultatif : réglable dans vos <a href="{{ route('notifications.preferences') }}">préférences de notification</a>.</span></li></ul></section></aside></div>
  </div>
</x-layouts.account>

<x-layouts.account title="Livraison" space="freelancer">
  <nav class="crumbs" aria-label="Fil d’Ariane"><a class="back-m" href="{{ route('orders.show', $d->reference) }}"><x-fc.icon name="arrow-right" :size="16" class="flip" />Retour à la commande</a><a class="hide-m" href="{{ route('orders.show', $d->reference) }}">Commande {{ $d->reference }}</a><span class="sep hide-m" aria-hidden="true">›</span><span class="hide-m" aria-current="page">Livraison</span></nav>
  <div class="sx-head"><div><p class="sx-kicker">Espace freelance</p><h1>Préparer la livraison v{{ ($d->delivery['latestVersion'] ?? 0) + 1 }}</h1></div></div>
  <p class="dl-ctx"><span><b>{{ $d->title }}</b></span><span>{{ $d->reference }}</span><span>Client : {{ $d->otherPartyName }}</span></p>
  @if($d->stateValue === 'revision_requested')
    @php($last = collect($d->delivery['deliveries'])->firstWhere('isLatest', true))
    @if($last && $last['correction'])<div class="dl-q" style="margin-top:12px"><strong>Correction n° {{ $last['correction']['number'] }} sur {{ $d->delivery['corrections']['included'] }} demandée le {{ $last['correction']['when'] }}</strong><span>« {{ $last['correction']['reason'] }} »</span></div>@endif
  @endif
  <div class="dl-notice" role="note" style="margin-top:12px"><x-fc.icon name="lock" :size="18" /><span><strong>Brouillon privé.</strong> Le client ne voit rien tant que vous n’avez pas soumis la livraison. Une fois soumise, elle est conservée telle quelle.</span></div>
  @if(session('reasons'))<div class="rq-info" role="alert" style="margin-top:12px"><x-fc.icon name="warn" :size="18" /><ul style="padding-left:18px;list-style:disc">@foreach(session('reasons') as $r)<li>{{ $r }}</li>@endforeach</ul></div>@endif

  <div class="ac-grid" style="margin-top:16px"><div class="ac-main">
    <section class="ed-card" aria-labelledby="h-msg"><h2 id="h-msg">Message de livraison</h2>
      <form method="post" action="{{ route('orders.delivery.message', $d->reference) }}" data-once style="display:grid;gap:12px">@csrf
        <div class="field"><label for="message">Message au client <span class="req">(obligatoire)</span></label>
          <textarea class="textarea" id="message" name="message" rows="6" maxlength="{{ \App\Modules\Orders\Actions\DeliveryDraft::MESSAGE_MAX }}">{{ old('message', $draft['message']) }}</textarea>
          <p class="hint">Décrivez ce qui est livré, les formats et ce que le client doit vérifier. Enregistrez le brouillon avant de soumettre.</p>@error('message')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
        <div><button class="btn btn-secondary" type="submit" data-once-label="Enregistrement…">Enregistrer le brouillon</button></div></form></section>

    <section class="ed-card" aria-labelledby="h-files"><div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;align-items:center"><h2 id="h-files">Fichiers livrés</h2>@if($draft['requiresFiles'])<span class="badge tone-warning"><x-fc.icon name="warn" :size="16" />Au moins un fichier contrôlé requis</span>@endif</div>
      <div class="dl-notice"><x-fc.icon name="shield" :size="18" /><span><strong>Contrôle de sécurité avant examen.</strong> Un fichier n’est livrable qu’une fois contrôlé. Ce contrôle ne dit rien de la qualité de votre travail.</span></div>
      @if(count($draft['files']))
        @include('orders._files', ['files' => $draft['files'], 'remove' => 'orders._file-remove'])
        @if(collect($draft['files'])->contains(fn ($f) => $f['tone'] === 'warning'))<p class="muted small">Le contrôle est en cours. <a href="{{ route('orders.delivery', $d->reference) }}">Actualiser la page</a> pour voir le résultat.</p>@endif
      @else<p class="muted">Aucun fichier pour l’instant.</p>@endif
      @if($draft['scannerOperational'])
        <form method="post" action="{{ route('orders.delivery.upload', $d->reference) }}" enctype="multipart/form-data" data-once class="dl-drop" style="display:grid;gap:12px;text-align:left">@csrf
          <div class="field"><label for="d-file">Ajouter un fichier</label><p class="hint" id="d-file-h">PDF, images (JPG, PNG, WebP) ou plan DWG — {{ $limitMb }} Mo maximum par fichier.</p><input class="input" id="d-file" type="file" name="file" required aria-describedby="d-file-h"></div>
          <div><button class="btn btn-secondary" type="submit" data-once-label="Envoi…">Envoyer le fichier</button></div></form>
      @else
        <div class="dl-notice"><x-fc.icon name="info" :size="18" /><span>Le dépôt de fichiers est <strong>désactivé</strong> sur cette installation : aucun service de contrôle de sécurité n’est disponible.@if($draft['requiresFiles']) <strong>L’accord prévoit des fichiers livrables : la livraison est bloquée tant que ce service n’est pas installé.</strong>@endif</span></div>
      @endif
    </section>
  </div>
  <aside class="ac-side"><section class="ed-ck" aria-labelledby="h-sub"><h3 id="h-sub">{{ $draft['canSubmit'] ? 'Prêt à soumettre' : 'Pas encore prêt' }}</h3>
    @if($draft['canSubmit'])
      <ul class="dl-ck"><li class="ok"><span class="m" aria-hidden="true">✓</span><div><b>Message rédigé</b></div></li>@if(count($draft['files']))<li class="ok"><span class="m" aria-hidden="true">✓</span><div><b>Fichiers contrôlés</b><small>{{ count($draft['files']) }} fichier{{ count($draft['files']) > 1 ? 's' : '' }}</small></div></li>@endif</ul>
      <a class="btn btn-primary btn-lg" href="{{ route('orders.delivery.confirm', $d->reference) }}">Soumettre la livraison</a>
    @else
      <p><strong>La livraison ne peut pas encore être soumise :</strong></p>
      <ul class="dl-ck">@foreach($draft['blockers'] as $b)<li><span class="m" aria-hidden="true">·</span><div>{{ $b }}</div></li>@endforeach</ul>
    @endif</section>
    <section class="ed-ck"><h3>Après l’envoi</h3><p class="muted small">Le client examine la livraison, demande une correction ou la valide. Chaque version est conservée.</p></section></aside>
  </div>
</x-layouts.account>

<x-layouts.account title="Livraison" space="freelancer">
  <nav class="crumbs" aria-label="Fil d’Ariane"><a class="back-m" href="{{ route('orders.show', $d->reference) }}"><x-fc.icon name="arrow-right" :size="16" class="flip" />Retour à la commande</a><a class="hide-m" href="{{ route('orders.show', $d->reference) }}">Commande {{ $d->reference }}</a><span class="sep hide-m" aria-hidden="true">›</span><span class="hide-m" aria-current="page">Livraison</span></nav>
  <h1 class="t-h1">Préparer la livraison v{{ ($d->delivery['latestVersion'] ?? 0) + 1 }}</h1>
  <p class="muted">{{ $d->title }} · {{ $d->reference }} · Client : {{ $d->otherPartyName }}</p>
  @if($d->stateValue === 'revision_requested')
    @php($last = collect($d->delivery['deliveries'])->firstWhere('isLatest', true))
    @if($last && $last['correction'])<div class="quote" style="max-width:680px"><strong>Correction n° {{ $last['correction']['number'] }} sur {{ $d->delivery['corrections']['included'] }} demandée le {{ $last['correction']['when'] }}</strong><br>« {{ $last['correction']['reason'] }} »</div>@endif
  @endif
  <div class="notice tone-info" role="note"><x-fc.icon name="lock" /><p><strong>Brouillon privé.</strong> Le client ne voit rien tant que vous n’avez pas soumis la livraison. Une fois soumise, elle est conservée telle quelle.</p></div>
  @if(session('reasons'))<div class="notice tone-warning" role="alert"><x-fc.icon name="warn" /><ul style="padding-left:18px;list-style:disc">@foreach(session('reasons') as $r)<li>{{ $r }}</li>@endforeach</ul></div>@endif

  <div class="cols"><div class="stack-lg">
    <section class="card" aria-labelledby="h-msg" style="max-width:680px"><h2 class="t-h2 card-title" id="h-msg">Message de livraison</h2>
      <form method="post" action="{{ route('orders.delivery.message', $d->reference) }}" data-once style="display:grid;gap:12px">@csrf
        <div class="field"><label for="message">Message au client <span class="req">(obligatoire)</span></label>
          <textarea class="textarea" id="message" name="message" rows="6" maxlength="{{ \App\Modules\Orders\Actions\DeliveryDraft::MESSAGE_MAX }}">{{ old('message', $draft['message']) }}</textarea>
          <p class="hint">Décrivez ce qui est livré, les formats et ce que le client doit vérifier. Enregistrez le brouillon avant de soumettre.</p>@error('message')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
        <div><button class="btn btn-secondary" type="submit" data-once-label="Enregistrement…">Enregistrer le brouillon</button></div></form></section>

    <section class="card" aria-labelledby="h-files" style="max-width:680px"><div class="row" style="justify-content:space-between;margin-bottom:8px"><h2 class="t-h2" id="h-files">Fichiers livrés</h2>@if($draft['requiresFiles'])<span class="badge tone-warning"><x-fc.icon name="warn" :size="16" />Au moins un fichier contrôlé requis</span>@endif</div>
      <p class="note-line"><x-fc.icon name="shield" :size="16" /><span><strong>Contrôle de sécurité avant examen.</strong> Un fichier n’est livrable qu’une fois contrôlé. Ce contrôle ne dit rien de la qualité de votre travail.</span></p>
      @if(count($draft['files']))
        <div class="files" style="margin-top:12px">@foreach($draft['files'] as $f)
          <div class="file-line"><x-fc.icon name="file" :size="22" class="fi" /><span class="fn">{{ $f['name'] }}<span class="meta-f">{{ $f['ext'] }} · {{ $f['size'] }}</span><span class="sec"><x-fc.icon :name="$f['icon']" :size="16" />{{ $f['label'] }}</span>@if($f['note'])<small class="muted">{{ $f['note'] }}</small>@endif</span>
            <span class="acts">@if($f['url'])<a class="btn btn-secondary" href="{{ $f['url'] }}"><x-fc.icon name="download" :size="18" />Télécharger<span class="sr-only"> {{ $f['name'] }}</span></a>@endif
              <form method="post" action="{{ route('orders.delivery.remove', [$d->reference, $f['id']]) }}">@csrf<button class="btn btn-link" type="submit">Retirer<span class="sr-only"> {{ $f['name'] }}</span></button></form></span></div>
        @endforeach</div>
        @if(collect($draft['files'])->contains(fn ($f) => $f['tone'] === 'warning'))<p class="muted small" style="margin-top:8px">Le contrôle est en cours. <a href="{{ route('orders.delivery', $d->reference) }}">Actualiser la page</a> pour voir le résultat.</p>@endif
      @else<p class="muted" style="margin-top:12px">Aucun fichier pour l’instant.</p>@endif
      @if($draft['scannerOperational'])
        <form method="post" action="{{ route('orders.delivery.upload', $d->reference) }}" enctype="multipart/form-data" data-once style="display:grid;gap:12px;margin-top:16px">@csrf
          <div class="field"><label for="d-file">Ajouter un fichier</label><p class="hint" id="d-file-h">PDF, images (JPG, PNG, WebP) ou plan DWG — {{ $limitMb }} Mo maximum par fichier.</p><input class="input" id="d-file" type="file" name="file" required aria-describedby="d-file-h"></div>
          <div><button class="btn btn-secondary" type="submit" data-once-label="Envoi…">Envoyer le fichier</button></div></form>
      @else
        <p class="note-line" style="margin-top:12px"><x-fc.icon name="info" :size="16" /><span>Le dépôt de fichiers est <strong>désactivé</strong> sur cette installation : aucun service de contrôle de sécurité n’est disponible.@if($draft['requiresFiles']) <strong>L’accord prévoit des fichiers livrables : la livraison est bloquée tant que ce service n’est pas installé.</strong>@endif</span></p>
      @endif
    </section>

    <section class="card" aria-labelledby="h-sub" style="max-width:680px"><h2 class="t-h2 card-title" id="h-sub">Soumettre au client</h2>
      @if($draft['canSubmit'])
        <p>La livraison est prête : message rédigé{{ count($draft['files']) ? ' et fichiers contrôlés' : '' }}.</p>
        <div style="margin-top:12px"><a class="btn btn-primary btn-lg" href="{{ route('orders.delivery.confirm', $d->reference) }}">Soumettre la livraison</a></div>
      @else
        <div class="notice tone-warning" role="status"><x-fc.icon name="warn" /><div><p><strong>La livraison ne peut pas encore être soumise :</strong></p><ul style="padding-left:18px;list-style:disc;margin-top:6px">@foreach($draft['blockers'] as $b)<li>{{ $b }}</li>@endforeach</ul></div></div>
      @endif
    </section>
  </div></div>
</x-layouts.account>

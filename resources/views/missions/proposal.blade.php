@php
  $L = $limits; $err = fn ($k) => $errors->first($k); $val = fn ($k, $d = '') => old($k, $p[$k] ?? $d);
@endphp
<x-layouts.account :title="$p ? 'Réviser ma proposition' : 'Faire une proposition'" space="freelancer">
  <div class="page-body">
    <nav class="ed-crumbs" aria-label="Fil d’Ariane"><a href="{{ route('missions.index') }}">Missions</a><span aria-hidden="true">/</span><a href="{{ route('missions.show', $m['slug']) }}">{{ $m['title'] }}</a><span aria-hidden="true">/</span><span aria-current="page">Ma proposition</span></nav>
    <header class="sx-head"><div><p class="sx-kicker">{{ $p ? 'Version '.$p['number'].' · à réviser' : 'Nouvelle proposition' }}</p><h1>{{ $p ? 'Réviser ma proposition' : 'Faire une proposition' }}</h1>@if($p)<p class="muted">Enregistrer crée la <strong>version {{ $p['number'] + 1 }}</strong> ; la version {{ $p['number'] }} est conservée. Le client retient une version précise.</p>@else<p class="muted">Un prix ferme, un délai et ce que vous livrez : le client compare puis en retient une.</p>@endif</div></header>
    @if($m['mine']['stale'] ?? false)<div class="notice tone-warning ed-banner" role="note"><x-fc.icon name="warn" /><p><strong>Besoin modifié depuis votre proposition.</strong> Relisez la mission puis reconfirmez (ou ajustez) votre offre : elle ne peut pas être retenue avant.</p></div>@endif
    @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>{{ $errors->has('profile') ? $errors->first('profile') : 'Vérifiez les champs signalés ci-dessous. Vos saisies sont conservées.' }}</p></div>@endif
    <form method="post" action="{{ route('missions.proposal.store', $m['slug']) }}" data-once class="ed-grid pr-form" novalidate>@csrf
      <div class="ed-main">
      <input type="hidden" name="expected_number" value="{{ $p['number'] ?? 0 }}">
      <section class="ed-card"><h2>Votre offre</h2><div class="pr-row2">
        <x-fc.field name="price_xof" label="Prix ferme (FCFA)" type="text" :value="$val('price_xof')" :hint="'De '.number_format($L['price_xof'][0], 0, ',', ' ').' à '.number_format($L['price_xof'][1], 0, ',', ' ').' FCFA (valeurs provisoires). Ce prix ne change plus une fois la proposition retenue.'" />
        <x-fc.field name="delivery_days" label="Délai de réalisation (jours)" type="text" :value="$val('delivery_days')" :hint="$L['delivery_days'][0].' à '.$L['delivery_days'][1].' jours, à partir du départ du travail.'" />
        <x-fc.field name="revisions_included" label="Corrections incluses" type="text" :value="$val('revisions_included', '1')" :hint="$L['revisions'][0].' à '.$L['revisions'][1].'.'" />
        <x-fc.field name="validity_days" label="Durée de validité (jours)" type="text" :value="$val('validity_days', '7')" :hint="'La proposition ne peut plus être retenue après ce délai (de '.$L['validity_days'][0].' à '.$L['validity_days'][1].' jours).'" />
      </div></section>
      @include('missions._milestones-form')
      <section class="ed-card"><h2>Contenu</h2>
        <div class="field"><label for="f-scope">Périmètre proposé</label><p class="hint" id="h-scope">{{ $L['scope'][0] }} à {{ $L['scope'][1] }} caractères. Aucune adresse e-mail ni numéro de téléphone.</p><textarea class="textarea" id="f-scope" name="scope" rows="6" aria-describedby="h-scope @if($err('scope')) e-scope @endif" @if($err('scope')) aria-invalid="true" @endif>{{ $val('scope') }}</textarea>@if($err('scope'))<p class="field-error" id="e-scope"><x-fc.icon name="error" :size="16" />{{ $err('scope') }}</p>@endif</div>
        <div class="field"><label for="f-deliverables">Livrables</label><p class="hint" id="h-del">Une ligne par livrable (1 à {{ $L['deliverables_max'] }}).</p><textarea class="textarea" id="f-deliverables" name="deliverables" rows="4" aria-describedby="h-del @if($err('deliverables')) e-deliverables @endif" @if($err('deliverables')) aria-invalid="true" @endif>{{ $val('deliverables') }}</textarea>@if($err('deliverables'))<p class="field-error" id="e-deliverables"><x-fc.icon name="error" :size="16" />{{ $err('deliverables') }}</p>@endif</div>
        <fieldset class="field" style="border:0;padding:0;min-width:0"><legend class="label" style="font-weight:600">Mode de livraison</legend>
          <label class="check"><input type="radio" name="delivery_mode" value="files" @checked($val('delivery_mode', 'files') === 'files')> <span><strong>Au moins un fichier</strong> contrôlé par livraison (recommandé).</span></label>
          <label class="check"><input type="radio" name="delivery_mode" value="message" @checked($val('delivery_mode', 'files') === 'message')> <span><strong>Par message seul</strong> : aucun fichier exigé.</span></label><p class="hint">Figé dans l’accord s’il est retenu.</p></fieldset>
        <div class="field"><label for="f-message">Message au client (facultatif)</label><textarea class="textarea" id="f-message" name="message" rows="3" maxlength="1000">{{ $val('message') }}</textarea>@if($err('message'))<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $err('message') }}</p>@endif</div>
      </section>
      <div class="ed-bar editor-pagination"><p class="hint"><x-fc.icon name="lock" :size="16" /> Seuls vous et le client voyez cette proposition.</p><div class="r"><a class="btn btn-secondary" href="{{ route('missions.show', $m['slug']) }}">Annuler</a><button class="btn btn-primary" type="submit" data-once-label="Envoi…">{{ $p ? 'Enregistrer la nouvelle version' : 'Envoyer ma proposition' }}</button></div></div>
      </div>
      <aside class="ed-side" aria-label="La mission et votre offre">
        <div class="ed-prev"><p class="k">La mission</p><h2 style="font-size:1.0625rem;font-weight:700;overflow-wrap:anywhere">{{ $m['title'] }}</h2><p class="muted small">{{ $m['category'] }}</p>
          <div class="pr-line"><span class="small muted">Budget du client</span><b class="pr-amt">{{ $m['budget']->formatted() }} FCFA</b></div>
          <div class="pr-line"><span class="small muted">Candidatures jusqu’au</span><b>{{ $m['deadline'] }}</b></div>
          <a class="small" href="{{ route('missions.show', $m['slug']) }}" target="_blank" rel="noopener">Relire la mission</a></div>
        <div class="ed-ck"><h3>Votre proposition en résumé</h3>
          <ul class="rq-facts" style="border-top:0"><li><x-fc.icon name="card" :size="20" /><span><b data-pr-price>—</b> prix ferme</span></li><li><x-fc.icon name="clock" :size="20" /><span><b data-pr-days>—</b> après le départ</span></li><li><x-fc.icon name="pencil" :size="20" /><span><b data-pr-rev>—</b> incluses</span></li><li><x-fc.icon name="calendar" :size="20" /><span>Valable <b data-pr-valid>—</b></span></li></ul></div>
        <p class="rq-info"><x-fc.icon name="info" :size="18" /><span>Les conditions saisies ici deviendront l’<strong>accord figé</strong> de la commande si elle est retenue.</span></p>
      </aside>
    </form>
  </div>
</x-layouts.account>

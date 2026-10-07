@php
  $L = $limits; $err = fn ($k) => $errors->first($k); $val = fn ($k, $d = '') => old($k, $p[$k] ?? $d);
@endphp
<x-layouts.account :title="$p ? 'Réviser ma proposition' : 'Faire une proposition'" space="freelancer">
  <div class="page-body">
    <nav class="crumbs" aria-label="Fil d’Ariane"><a class="back-m" href="{{ route('missions.show', $m['slug']) }}"><x-fc.icon name="arrow-right" :size="16" class="flip" />La mission</a><a class="hide-m" href="{{ route('missions.index') }}">Missions</a><span class="sep hide-m" aria-hidden="true">›</span><span class="hide-m" aria-current="page">Ma proposition</span></nav>
    <h1 class="t-h1">{{ $p ? 'Réviser ma proposition' : 'Faire une proposition' }}</h1>
    <section class="card form-card"><p style="font-weight:650">{{ $m['title'] }}</p><p class="muted small">{{ $m['category'] }} · budget du client {{ $m['budget']->formatted() }} FCFA · candidatures jusqu’au {{ $m['deadline'] }}</p></section>
    @if($m['mine']['stale'] ?? false)<div class="notice tone-warning" role="note"><x-fc.icon name="warn" /><p><strong>Besoin modifié depuis votre proposition.</strong> Relisez la mission puis reconfirmez (ou ajustez) votre offre : elle ne peut pas être retenue avant.</p></div>
    @elseif($p)<div class="notice tone-info" role="note"><x-fc.icon name="info" /><p>Enregistrer crée la <strong>version {{ $p['number'] + 1 }}</strong> ; la version {{ $p['number'] }} est conservée. Le client retient une version précise.</p></div>@endif
    <div class="notice tone-info" role="note"><x-fc.icon name="lock" /><p>Seuls vous et le client voyez cette proposition. Les conditions saisies ici deviendront l’<strong>accord figé</strong> de la commande si elle est retenue.</p></div>
    @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>Vérifiez les champs signalés ci-dessous. Vos saisies sont conservées.</p></div>@endif
    <form method="post" action="{{ route('missions.proposal.store', $m['slug']) }}" data-once style="display:grid;gap:16px;max-width:720px" novalidate>@csrf
      <input type="hidden" name="expected_number" value="{{ $p['number'] ?? 0 }}">
      <section class="card" style="display:grid;gap:16px"><h2 class="t-h2 card-title">Offre</h2>
        <x-fc.field name="price_xof" label="Prix ferme (FCFA)" type="text" :value="$val('price_xof')" :hint="'De '.number_format($L['price_xof'][0], 0, ',', ' ').' à '.number_format($L['price_xof'][1], 0, ',', ' ').' FCFA (valeurs provisoires). Ce prix ne change plus une fois la proposition retenue.'" />
        <x-fc.field name="delivery_days" label="Délai de réalisation (jours)" type="text" :value="$val('delivery_days')" :hint="$L['delivery_days'][0].' à '.$L['delivery_days'][1].' jours, à partir du départ du travail.'" />
        <x-fc.field name="revisions_included" label="Corrections incluses" type="text" :value="$val('revisions_included', '1')" :hint="$L['revisions'][0].' à '.$L['revisions'][1].'.'" />
        <x-fc.field name="validity_days" label="Durée de validité (jours)" type="text" :value="$val('validity_days', '7')" :hint="'La proposition ne peut plus être retenue après ce délai (de '.$L['validity_days'][0].' à '.$L['validity_days'][1].' jours).'" />
      </section>
      <section class="card" style="display:grid;gap:16px"><h2 class="t-h2 card-title">Contenu</h2>
        <div class="field"><label for="f-scope">Périmètre proposé</label><p class="hint" id="h-scope">{{ $L['scope'][0] }} à {{ $L['scope'][1] }} caractères. Aucune adresse e-mail ni numéro de téléphone.</p><textarea class="textarea" id="f-scope" name="scope" rows="6" aria-describedby="h-scope @if($err('scope')) e-scope @endif" @if($err('scope')) aria-invalid="true" @endif>{{ $val('scope') }}</textarea>@if($err('scope'))<p class="field-error" id="e-scope"><x-fc.icon name="error" :size="16" />{{ $err('scope') }}</p>@endif</div>
        <div class="field"><label for="f-deliverables">Livrables</label><p class="hint" id="h-del">Une ligne par livrable (1 à {{ $L['deliverables_max'] }}).</p><textarea class="textarea" id="f-deliverables" name="deliverables" rows="4" aria-describedby="h-del @if($err('deliverables')) e-deliverables @endif" @if($err('deliverables')) aria-invalid="true" @endif>{{ $val('deliverables') }}</textarea>@if($err('deliverables'))<p class="field-error" id="e-deliverables"><x-fc.icon name="error" :size="16" />{{ $err('deliverables') }}</p>@endif</div>
        <fieldset class="field" style="border:0;padding:0;min-width:0"><legend class="label" style="font-weight:600">Mode de livraison</legend>
          <label class="check"><input type="radio" name="delivery_mode" value="files" @checked($val('delivery_mode', 'files') === 'files')> <span><strong>Au moins un fichier</strong> contrôlé par livraison (recommandé).</span></label>
          <label class="check"><input type="radio" name="delivery_mode" value="message" @checked($val('delivery_mode', 'files') === 'message')> <span><strong>Par message seul</strong> : aucun fichier exigé.</span></label><p class="hint">Figé dans l’accord s’il est retenu.</p></fieldset>
        <div class="field"><label for="f-message">Message au client (facultatif)</label><textarea class="textarea" id="f-message" name="message" rows="3" maxlength="1000">{{ $val('message') }}</textarea>@if($err('message'))<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $err('message') }}</p>@endif</div>
      </section>
      <div class="row"><button class="btn btn-primary btn-lg" type="submit" data-once-label="Envoi…">{{ $p ? 'Enregistrer la nouvelle version' : 'Envoyer ma proposition' }}</button><a class="btn btn-link" href="{{ route('missions.show', $m['slug']) }}">Annuler</a></div>
    </form>
  </div>
</x-layouts.account>

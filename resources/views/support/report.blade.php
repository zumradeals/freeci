<x-layouts.account title="Signaler">
  <div class="page-body">
    <nav class="crumbs" aria-label="Fil d’Ariane"><a class="back-m" href="{{ route('support.index') }}"><x-fc.icon name="arrow-right" :size="16" class="flip" />Assistance</a><a class="hide-m" href="{{ route('support.index') }}">Assistance</a><span class="sep hide-m" aria-hidden="true">›</span><span class="hide-m" aria-current="page">Signaler</span></nav>
    <div class="sx-head"><div><p class="sx-kicker">Assistance</p><h1>Signaler : {{ strtolower($typeLabel) }}</h1><p class="muted">Votre signalement est transmis à l’équipe habilitée.</p></div></div>
    <div class="ac-grid"><form method="post" action="{{ route('support.report', [$type, $id]) }}">@csrf
      <input type="hidden" name="operation_key" value="{{ $key }}">
      <section class="ed-card" aria-labelledby="h-rep"><h2 id="h-rep">Votre signalement</h2>
        <div class="field"><label for="f-reason">Motif</label><select class="select" id="f-reason" name="reason" required>@foreach($reasons as $k => $l)<option value="{{ $k }}" @selected(old('reason') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="field"><label for="f-body">Que s’est-il passé ?</label><textarea class="textarea" id="f-body" name="body" required minlength="10" maxlength="4000">{{ old('body') }}</textarea>@error('body')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror<p class="hint">10 caractères au moins.</p></div>
        <div class="ac-acts"><button class="btn btn-primary btn-lg" type="submit" data-once>Envoyer le signalement</button><a class="btn btn-link" href="{{ url()->previous() }}">Annuler</a></div></section>
    </form>
    <aside class="ac-side"><section class="ed-ck" aria-labelledby="h-conf"><h3 id="h-conf">Confidentialité</h3><ul class="ac-ess">
      <li><x-fc.icon name="lock" :size="18" /><div><b>La personne signalée n’est pas informée.</b></div></li>
      <li><x-fc.icon name="message" :size="18" /><div><b>Pour un message</b><small>seule une copie de ce message est transmise à l’équipe ; le reste de la conversation reste privé.</small></div></li></ul></section></aside></div>
  </div>
</x-layouts.account>

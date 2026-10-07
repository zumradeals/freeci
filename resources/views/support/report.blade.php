<x-layouts.account title="Signaler">
  <div class="page-body">
    <header class="page-head"><div class="row-top"><div><p class="eyebrow"><a href="{{ route('support.index') }}">← Assistance</a></p><h1 class="t-h1">Signaler : {{ strtolower($typeLabel) }}</h1></div></div></header>
    <form method="post" action="{{ route('support.report', [$type, $id]) }}" class="card stack" style="max-width:46em">@csrf
      <input type="hidden" name="operation_key" value="{{ $key }}">
      <div class="notice tone-info"><x-fc.icon name="info" /><p>La personne signalée <strong>n’est pas informée</strong>. Pour un message, seule une copie de ce message est transmise à l’équipe : le reste de la conversation reste privé.</p></div>
      <div class="field"><label for="f-reason">Motif</label><select class="select" id="f-reason" name="reason" required>@foreach($reasons as $k => $l)<option value="{{ $k }}" @selected(old('reason') === $k)>{{ $l }}</option>@endforeach</select></div>
      <div class="field"><label for="f-body">Que s’est-il passé ?</label><textarea class="textarea" id="f-body" name="body" required minlength="10" maxlength="4000">{{ old('body') }}</textarea>@error('body')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
      <div class="row"><button class="btn btn-primary btn-lg" type="submit" data-once>Envoyer le signalement</button><a class="btn btn-link" href="{{ url()->previous() }}">Annuler</a></div>
    </form>
  </div>
</x-layouts.account>

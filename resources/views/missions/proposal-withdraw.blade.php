<x-layouts.account title="Retirer ma proposition" space="freelancer">
  <section class="card form-card"><h1 class="t-h1">Retirer ma proposition</h1><p style="margin-top:8px"><strong>{{ $p['title'] }}</strong></p>
    <div class="notice tone-info" style="margin-top:16px"><x-fc.icon name="info" /><p>Votre proposition ne pourra plus être retenue. Ses versions sont <strong>conservées</strong> ; vous pourrez en déposer une nouvelle tant que la mission accepte des candidatures.</p></div>
    <form method="post" action="{{ route('freelance.proposals.withdraw.store', $p['id']) }}" data-once style="margin-top:16px">@csrf<div class="row"><button class="btn btn-secondary btn-lg" type="submit" data-once-label="Retrait…">Retirer ma proposition</button><a class="btn btn-link" href="{{ route('freelance.proposals') }}">Annuler</a></div></form></section>
</x-layouts.account>

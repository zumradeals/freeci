@php
  $titles = ['submit' => 'Soumettre à modération', 'unsubmit' => 'Retirer la soumission', 'revise' => 'Modifier la mission publiée', 'close' => 'Fermer la mission', 'cancel' => 'Annuler la mission', 'reopen' => 'Rouvrir la mission'];
  $slugs = ['submit' => 'soumettre', 'unsubmit' => 'retirer-soumission', 'revise' => 'nouvelle-version', 'close' => 'fermer', 'cancel' => 'annuler', 'reopen' => 'rouvrir'];
  $name = $working?->title ?: $live?->title ?: 'Mission';
@endphp
<x-layouts.account :title="$titles[$kind]" space="client">
  <nav class="crumbs" aria-label="Fil d’Ariane"><a class="back-m" href="{{ route('client.missions.show', $mission->getKey()) }}"><x-fc.icon name="arrow-right" :size="16" class="flip" />Retour à la mission</a><a class="hide-m" href="{{ route('client.missions') }}">Mes missions</a><span class="sep hide-m" aria-hidden="true">›</span><span class="hide-m" aria-current="page">{{ $titles[$kind] }}</span></nav>
  <section class="card form-card" aria-labelledby="c-title"><h1 class="t-h1" id="c-title">{{ $titles[$kind] }}</h1><p style="margin-top:8px"><strong>{{ $name }}</strong></p>
    <div class="notice tone-info" style="margin-top:16px"><x-fc.icon name="info" /><div><p><strong>Ce qui va se passer</strong></p><ul class="stack-sm" style="padding-left:18px;list-style:disc;margin-top:6px">
      @switch($kind)
        @case('submit')<li>La version {{ $working->number }} est transmise à la modération et <strong>n’est plus modifiable</strong> pendant le contrôle (vous pouvez retirer la soumission).</li><li>@if($live)La version publiée (v{{ $live->number }}) reste en ligne ; <strong>les propositions déjà reçues devront être reconfirmées</strong> par leurs auteurs avant de pouvoir être retenues.@else La mission reste <strong>invisible du public</strong> jusqu’à l’approbation.@endif</li>@break
        @case('unsubmit')<li>La version redevient un brouillon modifiable. La version publiée éventuelle n’est pas touchée.</li>@break
        @case('revise')<li>Une <strong>nouvelle version</strong> est créée à partir de la version publiée ; la version publiée reste en ligne tant qu’elle n’est pas approuvée.</li><li>Après approbation, les {{ $proposals }} proposition(s) reçue(s) ne seront plus retenables tant que leurs auteurs ne les auront pas <strong>reconfirmées</strong> pour la nouvelle version.</li>@break
        @case('close')<li>La mission n’est plus proposée et ses propositions actives sont closes. L’historique est conservé ; la fermeture est définitive.</li>@break
        @case('cancel')<li>La mission, jamais publiée, est annulée. Aucune proposition n’existe.</li>@break
        @case('reopen')<li>La mission redevient ouverte pour la sélection. La proposition libérée n’est pas retenable telle quelle : son auteur doit la reconfirmer. Les autres propositions encore valides le restent.</li><li>Cette réouverture est <strong>votre décision</strong> : elle n’a jamais lieu automatiquement.</li>@break
      @endswitch</ul></div></div>
    @if($kind === 'submit' && count($problems))<div class="notice tone-warning" role="alert" style="margin-top:12px"><x-fc.icon name="warn" /><div><p><strong>La mission n’est pas encore prête :</strong></p><ul style="padding-left:18px;list-style:disc;margin-top:6px">@foreach($problems as $p)<li>{{ $p }}</li>@endforeach</ul></div></div>
      <div class="row" style="margin-top:12px"><a class="btn btn-primary" href="{{ route('client.missions.edit', $mission->getKey()) }}">Revenir au brouillon</a></div>
    @else
    <form method="post" action="{{ route('client.missions.act', [$mission->getKey(), $slugs[$kind]]) }}" data-once style="display:grid;gap:16px;margin-top:16px">@csrf
      @if($kind === 'submit')<input type="hidden" name="revision_no" value="{{ $working->revision_no }}">@endif
      @if($kind === 'close')<div class="field"><label for="note">Raison (facultatif, conservée dans l’historique)</label><input class="input" id="note" name="note" maxlength="500"></div>@endif
      <div class="row"><button class="btn {{ in_array($kind, ['submit', 'reopen', 'revise']) ? 'btn-primary' : 'btn-secondary' }} btn-lg" type="submit" data-once-label="Enregistrement…">{{ $titles[$kind] }}</button><a class="btn btn-link" href="{{ route('client.missions.show', $mission->getKey()) }}">Annuler</a></div></form>
    @endif
  </section>
</x-layouts.account>

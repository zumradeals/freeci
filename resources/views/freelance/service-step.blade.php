@php
  $titles = ['submit' => 'Soumettre à modération', 'unsubmit' => 'Retirer la soumission', 'revise' => 'Modifier le service publié', 'withdraw' => 'Retirer du catalogue', 'restore' => 'Remettre en ligne'];
  $slugs = ['submit' => 'soumettre', 'unsubmit' => 'retirer-soumission', 'revise' => 'nouvelle-version', 'withdraw' => 'retirer-du-catalogue', 'restore' => 'remettre-en-ligne'];
  $name = $version?->title ?: $live?->title ?: $service->title;
@endphp
<x-layouts.account :title="$titles[$kind]" space="freelancer">
  <nav class="crumbs" aria-label="Fil d’Ariane"><a class="back-m" href="{{ route('freelance.services') }}"><x-fc.icon name="arrow-right" :size="16" class="flip" />Mes services</a><a class="hide-m" href="{{ route('freelance.services') }}">Mes services</a><span class="sep hide-m" aria-hidden="true">›</span><span class="hide-m" aria-current="page">{{ $titles[$kind] }}</span></nav>
  <section class="card" style="max-width:680px" aria-labelledby="c-title"><h1 class="t-h1" id="c-title">{{ $titles[$kind] }}</h1><p style="margin-top:8px"><strong>{{ $name }}</strong></p>
    @if($kind === 'submit' && $problems)
      <div class="notice tone-warning" role="alert" style="margin-top:16px"><x-fc.icon name="warn" /><div><p><strong>Le service n’est pas encore prêt :</strong></p><ul style="padding-left:18px;list-style:disc;margin-top:6px">@foreach($problems as $p)<li>{{ $p }}</li>@endforeach</ul></div></div>
      <div style="margin-top:16px" class="row"><a class="btn btn-primary" href="{{ route('freelance.services.edit', $service->getKey()) }}">Revenir au brouillon</a></div>
    @else
      <div class="notice tone-info" style="margin-top:16px"><x-fc.icon name="info" /><div><p><strong>Ce qui va se passer</strong></p><ul class="stack-sm" style="padding-left:18px;list-style:disc;margin-top:6px">
        @switch($kind)
          @case('submit')<li>La version {{ $version->number }} est transmise à la modération et <strong>n’est plus modifiable</strong> pendant le contrôle (vous pouvez retirer la soumission).</li><li>@if($live)La version publiée (v{{ $live->number }}) <strong>reste en ligne</strong> jusqu’à l’approbation.@else Le service reste <strong>invisible du public</strong> jusqu’à l’approbation.@endif</li><li>Vous verrez la décision, et son motif en cas de refus, dans « Mes services ».</li>@break
          @case('unsubmit')<li>La version redevient un brouillon modifiable. La version publiée, s’il y en a une, n’est pas touchée.</li>@break
          @case('revise')<li>Une <strong>nouvelle version</strong> est créée à partir de la version publiée. Elle n’est visible de personne tant qu’elle n’est pas approuvée.</li><li>Les commandes déjà passées gardent leur accord d’origine, quel que soit le contenu de la nouvelle version.</li>@break
          @case('withdraw')<li>Le service <strong>n’est plus proposé</strong> : il disparaît du catalogue et de votre profil public, et personne ne peut le commander.</li><li>Les commandes et demandes <strong>déjà en cours</strong> ne sont ni supprimées ni modifiées : vos obligations envers ces clients restent les mêmes.</li><li>Vous pourrez le remettre en ligne.</li>@break
          @case('restore')<li>Le service redevient visible et commandable, avec sa version publiée.</li>@break
        @endswitch</ul></div></div>
      <form method="post" action="{{ route('freelance.services.act', [$service->getKey(), $slugs[$kind]]) }}" data-once style="display:grid;gap:16px;margin-top:16px">@csrf
        @if($kind === 'submit')<input type="hidden" name="revision_no" value="{{ $version->revision_no }}">@endif
        @if($kind === 'withdraw')<div class="field"><label for="note">Raison (facultatif, conservée dans l’historique)</label><input class="input" id="note" name="note" maxlength="500"></div>@endif
        <div class="row"><button class="btn {{ in_array($kind, ['submit', 'restore', 'revise']) ? 'btn-primary' : 'btn-secondary' }} btn-lg" type="submit" data-once-label="Enregistrement…">{{ $titles[$kind] }}</button><a class="btn btn-link" href="{{ route('freelance.services') }}">Annuler</a></div></form>
    @endif
  </section>
</x-layouts.account>

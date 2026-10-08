@php
  $stepIcons = ['search', 'clipboard', 'card', 'package', 'check-circle'];
  $cardIcon = fn (string $t) => match (true) { (bool) preg_match('/paiement/iu', $t) => 'card', (bool) preg_match('/avis/iu', $t) => 'shield', (bool) preg_match('/messagerie|assistance|litige/iu', $t) => 'message', default => 'info' };
  $freelanceUrl = \App\Modules\Admin\Navigation\Destinations::url((string) config('freeci.home.freelance_btn_dest')) ?? route('freelance.activate');
@endphp
<x-layouts.info :title="$title" :slug="$slug" :approved="$approved" :lead="$structure['lead'] ?? null">
  @if($structure)
    <section class="hw-sec" aria-labelledby="hw-steps"><h2 class="hw-h2" id="hw-steps">{{ $structure['stepsTitle'] }}</h2>
      <ol class="hw-steps">@foreach($structure['steps'] as $i => $st)<li><span class="hw-num">{{ $i + 1 }}</span><span class="hw-ico"><x-fc.icon :name="$stepIcons[$i] ?? 'check-circle'" :size="26" /></span><h3>{{ $st['title'] }}</h3><div>{!! $st['text'] !!}</div></li>@endforeach</ol></section>
    @if(count($structure['sections']))<div class="hw-cards">@foreach($structure['sections'] as $sec)<section class="card hw-card {{ preg_match('/paiement/iu', $sec['title']) ? 'hw-note' : '' }}"><span class="hw-ico"><x-fc.icon :name="$cardIcon($sec['title'])" :size="24" /></span><h2>{{ $sec['title'] }}</h2><div class="hw-card-body">{!! $sec['html'] !!}</div></section>@endforeach</div>@endif
    <section class="hw-ctas" aria-label="Pour continuer">
      <div class="hw-cta-box"><h2>Je cherche un service</h2><p>Comparez des prestations à prix et délai annoncés.</p><a class="btn btn-primary" href="{{ route('services.index') }}">Explorer les services</a></div>
      <div class="hw-cta-box"><h2>Je propose mes services</h2><p>Créez votre espace freelance et publiez vos services.</p><a class="btn btn-primary" href="{{ $freelanceUrl }}">Devenir freelance</a></div>
      <div class="hw-cta-box"><h2>Une question ?</h2><p>Retrouvez les réponses aux questions courantes.</p><a class="btn btn-secondary" href="{{ route('info', 'aide') }}">Centre d’aide</a></div>
    </section>
  @else
    <div class="prose">{!! $html !!}</div>
  @endif
  @if($slug === 'contact')
    <div class="card" style="max-width:46rem">@auth<p>Depuis votre espace connecté, ouvrez une demande : elle est suivie avec une référence.</p><a class="btn btn-primary" href="{{ route('support.index') }}">Ouvrir l’assistance</a>@else<p>Connectez-vous pour ouvrir une demande d’assistance suivie.</p><a class="btn btn-primary" href="{{ route('login') }}">Se connecter</a>@endauth</div>
  @endif
</x-layouts.info>

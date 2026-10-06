@php($m = fn (int $n) => \App\Shared\Money::xof($n)->formatted().' FCFA')
@php($cats = ['upcoming' => 'À venir (prestation non validée)', 'blocked' => 'Bloqué', 'available' => 'Disponible (reversement non encore demandé)', 'processing' => 'Reversement en cours', 'paid' => 'Reversé'])
<x-layouts.account title="Revenus" space="freelancer">
  <header class="page-head"><div class="row-top"><div><p class="eyebrow">Espace freelance</p><h1 class="t-h1">Revenus</h1></div></div></header>
  <div class="notice tone-warning"><x-fc.icon name="warn" /><p><strong>« Disponible » ne veut pas dire « versé ».</strong> Un reversement est demandé puis approuvé par l’équipe et enregistré manuellement : l’exécution automatique via Genius Pay n’est <strong>pas disponible</strong> (aucune API de reversement documentée). Le silence d’un client ne déclenche jamais de reversement. Les montants sont calculés au taux de commission figé dans chaque accord.</p></div>

  <section class="card" style="margin-top:16px"><h2 class="t-h2">Montants réels</h2>
    <dl class="defs">@foreach($cats as $k => $label)<div><dt>{{ $label }}</dt><dd>{{ $m($d['totals']['real'][$k]) }}</dd></div>@endforeach</dl></section>
  @if(array_sum($d['totals']['test']) > 0)
  <section class="card" style="margin-top:16px"><h2 class="t-h2">Montants de test <span class="tag-demo">aucun argent réel</span></h2>
    <dl class="defs">@foreach($cats as $k => $label)<div><dt>{{ $label }}</dt><dd>{{ $m($d['totals']['test'][$k]) }}</dd></div>@endforeach</dl>
    <p class="muted small">Ces montants viennent de commandes de test : ils ne sont jamais un revenu.</p></section>
  @endif

  <section class="card" style="margin-top:16px"><h2 class="t-h2">Destination de reversement</h2>
    @if($d['beneficiary'])<p>{{ ['mobile_money' => 'Mobile money', 'bank_transfer' => 'Virement bancaire', 'other' => 'Autre'][$d['beneficiary']['method']] }} · titulaire « {{ $d['beneficiary']['holder'] }} » — <span class="badge tone-{{ $d['beneficiary']['status'] === 'verified' ? 'success' : 'warning' }}">{{ $d['beneficiary']['status'] === 'verified' ? 'Vérifiée' : 'En attente de vérification' }}</span> <span class="muted small">(la destination n’est jamais réaffichée)</span></p>@else<p class="muted">Aucune destination déclarée : sans destination vérifiée, aucun reversement n’est possible.</p>@endif
    @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>{{ $errors->first() }}</p></div>@endif
    <form method="post" action="{{ route('freelance.earnings.beneficiary') }}" class="stack-sm">@csrf
      <div class="field"><label for="f15">Moyen</label><select id="f15" name="method"><option value="mobile_money">Mobile money</option><option value="bank_transfer">Virement bancaire</option><option value="other">Autre</option></select></div>
      <div class="field"><label for="f16">Titulaire</label><input id="f16" name="holder" minlength="2" maxlength="120" required></div>
      <div class="field"><label for="f17">Numéro ou coordonnées</label><input id="f17" name="destination" minlength="6" maxlength="120" autocomplete="off" required></div>
      <p class="muted small">Une nouvelle déclaration remplace la précédente et doit être vérifiée par l’équipe avant tout reversement.</p>
      <button class="btn btn-secondary" type="submit">Enregistrer la destination</button></form></section>

  <section class="card" style="margin-top:16px"><h2 class="t-h2">Par commande</h2>
    @forelse($d['rows'] as $r)
      <div style="margin-top:12px"><p><a href="{{ route('orders.show', $r['reference']) }}">{{ $r['title'] }}</a> <span class="muted small">· {{ $r['reference'] }}</span> @if($r['bucket'] === 'test')<span class="tag-demo">Test</span>@endif — <strong>{{ $r['cat'] === 'unknown' ? 'conditions financières non figées' : $cats[$r['cat']] }}</strong></p>
        <p class="small">Payé {{ $m($r['paid']) }}@if($r['refunded'] > 0) · remboursé {{ $m($r['refunded']) }}@endif @if($r['due'] !== null)· commission {{ $m((int) $r['commission']) }} ({{ $r['bp'] / 100 }} %) · <strong>part du freelance {{ $m((int) $r['due']) }}</strong>@endif @if($r['state_label'])· {{ $r['state_label'] }}@endif</p>
        @if($r['cat'] === 'unknown')<p class="muted small">Commande antérieure à la fixation du taux dans l’accord : le montant n’est pas calculé et aucun taux n’est inventé. Contactez l’assistance.</p>
        @elseif($r['cat'] !== 'paid' && $r['cat'] !== 'processing' && count($r['reasons']))<ul class="muted small">@foreach($r['reasons'] as $x)<li>{{ $x }}</li>@endforeach</ul>@endif</div>
    @empty<p class="muted">Aucune commande payée pour l’instant.</p>@endforelse</section>
</x-layouts.account>

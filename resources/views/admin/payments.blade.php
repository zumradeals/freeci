<x-layouts.admin title="Paiements à vérifier">
  <header class="page-head"><div class="row-top"><div><p class="eyebrow">Administration</p><h1 class="t-h1">Paiements à vérifier</h1></div></div></header>
  <div class="notice tone-warning"><x-fc.icon name="warn" /><p><strong>Rapprochement, pas d’exécution.</strong> Ces dossiers signalent un paiement incohérent, tardif ou à traiter. Marquer un dossier « examiné » le trace seulement : rien n’est remboursé, versé, relancé ni démarré. Les paiements de bac à sable et du simulateur ne représentent aucun argent réel.</p></div>
  <nav class="tabs" aria-label="Filtre" style="margin:16px 0"><a href="{{ route('admin.payments') }}" @if($status === 'open') aria-current="page" @endif>À examiner ({{ $open }})</a><a href="{{ route('admin.payments', ['statut' => 'resolved']) }}" @if($status === 'resolved') aria-current="page" @endif>Examinés</a></nav>
  @if($page->count())
    <div class="table-wrap"><table class="list"><caption class="sr-only">Dossiers de rapprochement</caption>
      <thead><tr><th scope="col">Motif</th><th scope="col">Commande</th><th scope="col">Prestataire</th><th scope="col">Montant</th><th scope="col">Signalé</th><th scope="col">Examen</th></tr></thead><tbody>
      @foreach($page as $r)<tr><td class="c-title"><span class="ttl" style="min-height:0">{{ $r['reason'] }}</span><span class="ref">{{ $r['reference'] }}</span></td>
        <td data-label="Commande">{{ $r['order'] }} <span class="muted small">({{ $r['orderState'] }})</span></td><td data-label="Prestataire">{{ $r['provider'] }} · <strong>{{ $r['environment'] }}</strong></td><td data-label="Montant">{{ $r['amount'] }}<br><span class="muted small">{{ $r['paymentState'] }}</span></td><td data-label="Signalé">{{ $r['when'] }}</td>
        <td data-label="Examen">@if($r['resolved'])<span class="badge tone-success">Examiné</span><br><span class="muted small">{{ $r['note'] }}</span>@else<form method="post" action="{{ route('admin.payments.review', $r['id']) }}" class="stack-sm">@csrf<label class="sr-only" for="n-{{ $r['id'] }}">Conclusion</label><textarea class="textarea" style="min-height:64px" id="n-{{ $r['id'] }}" name="note" required minlength="10" maxlength="1000" placeholder="Conclusion de l’examen"></textarea><button class="btn btn-secondary" type="submit" data-once>Marquer examiné</button></form>@endif</td></tr>@endforeach
    </tbody></table></div>
    <x-admin.pager :p="$page" />
  @else
    <div class="card empty"><span class="ico-lg"><x-fc.icon name="check-circle" :size="26" /></span><p style="font-weight:600">Aucun dossier {{ $status === 'resolved' ? 'examiné' : 'à examiner' }}.</p></div>
  @endif
</x-layouts.admin>

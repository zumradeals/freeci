@props(['service', 'id' => null, 'aside' => false])
@php($own = auth()->check() && auth()->id() === $service->sellerUserId)
@if(isset($preview))
  <p class="effect"><strong>Aperçu.</strong> Le bouton de demande apparaîtra ici une fois le service publié.</p>
@elseif($own)
  <p class="effect"><strong>C’est votre service.</strong> Vous ne pouvez pas le commander. Les demandes reçues apparaissent dans votre <a href="{{ route('freelance.dashboard') }}">espace freelance</a>.</p>
@elseif(! $service->acceptsRequests)
  <p class="effect"><strong>Demandes fermées.</strong> Ce service n’accepte pas de demandes pour le moment.</p>
@else
  <a class="btn btn-primary btn-lg btn-block" @if($id) id="{{ $id }}" @endif href="{{ route('services.request', $service->slug) }}">Demander cette prestation</a>
  @if($aside)
  <ul class="sp-assure">
    <li><x-fc.icon name="check-circle" :size="18" />Prix, délai et périmètre figés dès l’accord</li>
    <li><x-fc.icon name="check-circle" :size="18" />Aucun paiement à cette étape</li>
    <li><x-fc.icon name="check-circle" :size="18" />Réponse de {{ explode(' ', $service->sellerName)[0] }} sous {{ config('freeci.orders.response_hours') }} h</li>
    <li><x-fc.icon name="check-circle" :size="18" />Vous validez la livraison</li>
  </ul>
  @else
  <p class="effect"><strong>Vous décrivez votre besoin.</strong> {{ explode(' ', $service->sellerName)[0] }} a {{ config('freeci.orders.response_hours') }} h pour accepter ou refuser. <strong>Aucun paiement à cette étape.</strong></p>
  @endif
  @guest<p class="muted small">Il vous faudra vous connecter ou créer un compte : vous reviendrez ici ensuite.</p>@endguest
@endif
@if(! isset($preview) && ! $own)<p style="margin-top:8px"><a href="{{ route('messages.start.service', $service->slug) }}"><x-fc.icon name="message" :size="16" /> Poser une question à {{ explode(' ', $service->sellerName)[0] }}</a></p>@endif

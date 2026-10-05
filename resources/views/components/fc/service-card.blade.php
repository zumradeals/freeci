@props(['service', 'level' => 3])
<article class="svc" {{ $attributes }}>
  <div class="thumb">
    @if($service->imageSrc)
      <img src="{{ $service->imageSrc }}" alt="{{ $service->imageAlt }}" width="640" height="480" loading="lazy">
    @endif
  </div>
  <div class="body">
    <p class="cat-l">{{ $service->categoryName }}@if($service->isDemo) · <span class="tag-demo">Exemple fictif</span>@endif</p>
    <h{{ $level }} class="ttl"><a class="stretch" href="{{ route('services.show', $service->slug) }}">{{ $service->title }}</a></h{{ $level }}>
    <p class="seller"><span class="avatar" aria-hidden="true">{{ $service->sellerInitials }}</span>{{ $service->sellerName }} · {{ $service->sellerHeadline }}</p>
    <div class="foot"><span class="dl"><x-fc.icon name="clock" :size="18" />{{ $service->deliveryDays }} {{ $service->deliveryDays > 1 ? 'jours' : 'jour' }}</span><x-fc.money :amount="$service->price" /></div>
  </div>
</article>

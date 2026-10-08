@props(['service', 'level' => 3])
<article class="svc" {{ $attributes }}>
  <div class="thumb">
    @if($service->id !== '')<x-fc.fav-button kind="service" :slug="$service->slug" :on="$service->favorited" />@endif
    @if($service->imageSrc)
      <img src="{{ $service->imageSrc }}" alt="{{ $service->imageAlt }}" width="640" height="480" loading="lazy">
    @endif
  </div>
  <div class="body">
    <p class="cat-l">{{ $service->categoryName }}</p>
    <h{{ $level }} class="ttl"><a class="stretch" href="{{ route('services.show', $service->slug) }}">{{ $service->title }}</a></h{{ $level }}>
    <p class="seller"><x-fc.avatar :name="$service->sellerName" :user="$service->sellerUserId" />{{ $service->sellerName }} · {{ $service->sellerHeadline }}</p>
    @if($service->ratingCount > 0)<p class="rate-l"><x-fc.rating :avg="$service->ratingAvg" :count="$service->ratingCount" /></p>@endif
    <div class="foot"><span class="dl"><x-fc.icon name="clock" :size="18" />{{ $service->deliveryDays }} {{ $service->deliveryDays > 1 ? 'jours' : 'jour' }}</span><x-fc.money :amount="$service->price" /></div>
  </div>
</article>

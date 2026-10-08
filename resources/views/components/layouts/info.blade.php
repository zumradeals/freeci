@props(['title', 'slug', 'approved' => false, 'lead' => null])
<x-layouts.public :title="$title" :robots="$approved ? null : 'noindex, nofollow'" main-class="catalog-page">
<div class="service-directory info-page">
  <section class="sd-band" aria-labelledby="info-title">
    <div class="container">
      <nav class="sd-crumbs" aria-label="Fil d’Ariane"><a href="{{ url('/') }}">Accueil</a><span aria-hidden="true">/</span><span aria-current="page">{{ $title }}</span></nav>
      <h1 class="hw-h1" id="info-title">{{ $title }}</h1>
      @if($lead)<p class="hw-lead">{!! $lead !!}</p>@endif
    </div>
  </section>
  <div class="sd-cats"><div class="container">
    <nav class="hw-tabs" aria-label="Informations">
      @foreach(\App\Http\Controllers\InfoPageController::PAGES as $k => $label)<a class="hw-tab {{ $k === $slug ? 'on' : '' }}" href="{{ route('info', $k) }}" @if($k === $slug) aria-current="page" @endif>{{ $label }}</a>@endforeach
    </nav>
  </div></div>
  <div class="container hw-wrap info-body">
    <x-fc.draft-banner :approved="$approved" />
    {{ $slot }}
  </div>
</div>
</x-layouts.public>

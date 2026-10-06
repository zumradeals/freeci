@props(['title', 'slug', 'approved' => false])
<x-layouts.public :title="$title" :robots="$approved ? null : 'noindex, nofollow'">
<div class="container info-layout">
  <nav class="info-nav" aria-label="Informations">
    @foreach(\App\Http\Controllers\InfoPageController::PAGES as $k => $label)<a href="{{ route('info', $k) }}" @if($k === $slug) aria-current="page" @endif>{{ $label }}</a>@endforeach
  </nav>
  <div class="info-body">
    <h1 class="t-h1">{{ $title }}</h1>
    <x-fc.draft-banner :approved="$approved" />
    {{ $slot }}
  </div>
</div>
</x-layouts.public>

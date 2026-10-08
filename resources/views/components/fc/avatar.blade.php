@props(['name' => '', 'photo' => null, 'user' => null, 'size' => 'md', 'alt' => ''])
@php
  $photo = $photo ?? ($user ? app(\App\Modules\Accounts\Queries\ProfilePhotoIds::class)->for((string) $user) : null);
  $initials = collect(preg_split('/\s+/', trim($name)) ?: [])->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') ?: '?';
  $variant = in_array($size, ['lg', 'xl'], true) ? 'large' : 'small';
@endphp
<span {{ $attributes->class(['avatar', 'avatar-'.$size => $size !== 'md', 'has-photo' => $photo]) }} @if(! $photo || $alt === '') aria-hidden="true" @endif>{{ $initials }}@if($photo)<img src="{{ route('photo.show', [$photo, $variant]) }}" alt="{{ $alt }}" width="{{ $variant === 'large' ? 160 : 56 }}" height="{{ $variant === 'large' ? 160 : 56 }}" loading="lazy" decoding="async">@endif</span>

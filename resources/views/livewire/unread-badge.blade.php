<span wire:poll.30s>@if($n > 0)<span class="count-badge" aria-label="{{ $n }} non lu{{ $n > 1 ? 's' : '' }}">{{ $n > 99 ? '99+' : $n }}</span>@endif</span>

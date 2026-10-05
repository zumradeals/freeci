@props(['name', 'size' => 20, 'class' => null])
<svg width="{{ $size }}" height="{{ $size }}" aria-hidden="true" focusable="false" @if($class) class="{{ $class }}" @endif><use href="#i-{{ $name }}"/></svg>

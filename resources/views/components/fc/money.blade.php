@props(['amount', 'size' => 'md'])
<span class="price price-{{ $size }}">{{ $amount->formatted() }}<small> FCFA</small></span>

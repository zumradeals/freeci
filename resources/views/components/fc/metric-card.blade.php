@props(['label', 'n', 'ico' => 'clipboard', 'hot' => false, 'href' => '#'])
<a class="sx-metric {{ $hot ? 'hot' : '' }}" href="{{ $href }}"><span class="sx-mi"><x-fc.icon :name="$ico" :size="22" /></span><span class="sx-mv">{{ $n }}</span><span class="sx-ml">{{ $label }}</span></a>

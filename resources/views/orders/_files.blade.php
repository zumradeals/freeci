<div class="dl-files">@foreach($files as $f)
  <div class="dl-file"><span class="dl-fi" aria-hidden="true"><x-fc.icon name="file" :size="22" /></span>
    <div><b>{{ $f['name'] }}</b><small>{{ $f['ext'] }} · {{ $f['size'] }}</small><span class="dl-ok t-{{ $f['tone'] ?? 'success' }}"><x-fc.icon :name="$f['icon']" :size="16" />{{ $f['label'] }}</span>@if($f['note'])<small>{{ $f['note'] }}</small>@endif</div>
    <div class="dl-fa">@if($f['url'])<a class="btn btn-secondary" href="{{ $f['url'] }}"><x-fc.icon name="download" :size="18" />Télécharger<span class="sr-only"> {{ $f['name'] }}</span></a>@endif @isset($remove)@include($remove, ['f' => $f])@endisset</div></div>
@endforeach</div>

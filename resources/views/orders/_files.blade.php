<div class="files">@foreach($files as $f)
  <div class="file-line"><x-fc.icon name="file" :size="22" class="fi" /><span class="fn">{{ $f['name'] }}<span class="meta-f">{{ $f['ext'] }} · {{ $f['size'] }}</span><span class="sec"><x-fc.icon :name="$f['icon']" :size="16" />{{ $f['label'] }}</span>@if($f['note'])<small class="muted">{{ $f['note'] }}</small>@endif</span>
    <span class="acts">@if($f['url'])<a class="btn btn-secondary" href="{{ $f['url'] }}"><x-fc.icon name="download" :size="18" />Télécharger<span class="sr-only"> {{ $f['name'] }}</span></a>@endif</span></div>
@endforeach</div>

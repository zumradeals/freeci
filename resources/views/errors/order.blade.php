<x-layouts.account :title="$title" space="client">
  <div class="card empty form-card"><span class="ico-lg"><x-fc.icon name="info" :size="26" /></span>
    <h1 class="t-h2">{{ $title }}</h1><p class="muted" style="max-width:36em">{{ $message }}</p>
    <a class="btn btn-primary" href="{{ $back }}">{{ $backLabel }}</a></div>
</x-layouts.account>

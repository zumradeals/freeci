<x-layouts.account :title="$title" space="client">
  <div class="er-main">
    <section class="er" aria-labelledby="er-title"><div class="er-i" aria-hidden="true"><x-fc.icon name="info" :size="32" /></div>
      <h1 id="er-title">{{ $title }}</h1><p>{{ $message }}</p>
      <div class="er-acts"><a class="btn btn-primary btn-lg" href="{{ $back }}">{{ $backLabel }}</a></div></section>
  </div>
</x-layouts.account>

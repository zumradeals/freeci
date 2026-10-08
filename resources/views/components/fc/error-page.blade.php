@props(['code', 'icon' => 'minus-circle', 'tone' => '', 'title'])
<div class="er-main">
  <section class="er" aria-labelledby="er-title"><div class="er-i {{ $tone }}" aria-hidden="true"><x-fc.icon :name="$icon" :size="32" /></div>
    <span class="er-code">Erreur {{ $code }}</span>
    <h1 id="er-title">{{ $title }}</h1>
    <p>{{ $slot }}</p>
    @isset($actions)<div class="er-acts">{{ $actions }}</div>@endisset
    @isset($extra){{ $extra }}@endisset
  </section>
</div>

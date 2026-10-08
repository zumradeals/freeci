@props(['title', 'heading', 'lead' => null])
<x-layouts.public :title="$title" robots="noindex" main-class="catalog-page">
<div class="au">
  <section class="au-side" aria-label="FreeCI">
    <div><p class="au-h" role="presentation">{{ $heading }}</p>@if($lead)<p class="au-lead">{{ $lead }}</p>@endif</div>
    <ul>
      <li><span class="ic"><x-fc.icon name="clipboard" :size="22" /></span><div><b>Un accord clair</b><span>Prix, délai et périmètre figés dès l’accord.</span></div></li>
      <li><span class="ic"><x-fc.icon name="message" :size="22" /></span><div><b>Un suivi au même endroit</b><span>Vos échanges et livraisons dans un seul dossier.</span></div></li>
      <li><span class="ic"><x-fc.icon name="check-circle" :size="22" /></span><div><b>Vous validez la livraison</b><span>Vous examinez le travail avant de décider.</span></div></li>
    </ul>
    <img src="{{ asset('images/home/creative-work-640.webp') }}" alt="" width="640" height="480" loading="lazy">
  </section>
  <section class="au-main"><div class="au-card">{{ $slot }}</div></section>
</div>
</x-layouts.public>

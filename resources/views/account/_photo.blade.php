@php($photoId = app(\App\Modules\Accounts\Queries\ProfilePhotoIds::class)->for(auth()->id()))
<div class="ph-row">
  <x-fc.avatar :name="auth()->user()->name" :photo="$photoId" size="ph" :alt="$photoId ? 'Votre photo de profil' : ''" />
  <div class="ph-acts">
    <form method="post" action="{{ route('account.photo.store') }}" enctype="multipart/form-data" data-once class="ph-form">@csrf
      <div class="field"><label for="ph-file">{{ $photoId ? 'Changer la photo' : 'Choisir une photo' }}</label>
        <input class="input" id="ph-file" type="file" name="photo" accept="image/jpeg,image/png,image/webp" required aria-describedby="ph-h">
        <p class="hint" id="ph-h">JPG, PNG ou WebP · {{ config('freeci.catalog.image_max_mb') }} Mo maximum · au moins 200 px de côté · recadrée en carré. Les informations cachées de l’image (dont la position) sont retirées à l’enregistrement.</p>
        @error('photo')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
      <div><button class="btn btn-secondary" type="submit" data-once-label="Envoi…"><x-fc.icon name="camera" :size="18" />{{ $photoId ? 'Remplacer la photo' : 'Enregistrer la photo' }}</button></div></form>
    @if($photoId)<form method="post" action="{{ route('account.photo.destroy') }}" data-once>@csrf<button class="btn btn-link" type="submit">Supprimer la photo</button></form>@endif
  </div>
</div>

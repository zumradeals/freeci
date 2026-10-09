<x-layouts.account :title="$activation ? 'Activer l’espace freelance' : 'Profil freelance'" :space="$space">
  <div class="page-body">
    <header class="page-head"><div class="row-top"><div><p class="eyebrow">{{ $activation ? 'Espace client' : 'Espace freelance' }}</p><h1 class="t-h1">{{ $activation ? 'Activer l’espace freelance' : 'Votre profil' }}</h1></div></div></header>
    <div class="cols"><div class="stack-lg">
    @unless($activation)
    @php($items = app(\App\Modules\Accounts\Queries\PortfolioQueries::class)->active(auth()->id()))
    @php($poMax = (int) config('freeci.catalog.portfolio_max'))
    <section class="card form-card" aria-labelledby="h-po" id="h-po"><h2 class="t-h2 card-title">Réalisations</h2>
      <p class="muted">Montrez 1 à {{ $poMax }} exemples de votre travail. Chaque réalisation est <strong>publique</strong> dès son enregistrement (une fois votre profil publié) ; elle peut être signalée avec votre profil et retirée par l’équipe, avec un motif.</p>
      <p class="po-cnt"><span>{{ count($items) }} sur {{ $poMax }} réalisations</span></p>
      <div style="display:grid;gap:10px">@forelse($items as $it)
        <div class="po-ed"><img src="{{ route('portfolio.show', [$it['id'], 'card']) }}" alt="" width="96" height="64" loading="lazy"><div><b>{{ $it['title'] }}</b><small>{{ $it['description'] }}@if($it['year']) · {{ $it['year'] }}@endif</small></div>
          <div class="acts"><form method="post" action="{{ route('freelance.portfolio.destroy', $it['id']) }}" data-once>@csrf<button class="btn btn-link" type="submit">Supprimer<span class="sr-only"> {{ $it['title'] }}</span></button></form></div>
          <details class="po-edit"><summary class="btn btn-secondary">Modifier<span class="sr-only"> {{ $it['title'] }}</span></summary>
            <form method="post" action="{{ route('freelance.portfolio.update', $it['id']) }}" class="po-form" data-once>@csrf
              <div class="field"><label for="pt-{{ $it['id'] }}">Titre (3 à 80 caractères)</label><input class="input" id="pt-{{ $it['id'] }}" name="title" value="{{ $it['title'] }}" minlength="3" maxlength="80" required></div>
              <div class="field"><label for="pd-{{ $it['id'] }}">Description (jusqu’à 300 caractères)</label><textarea class="textarea" id="pd-{{ $it['id'] }}" name="description" rows="3" maxlength="300">{{ $it['description'] }}</textarea></div>
              <div class="field"><label for="py-{{ $it['id'] }}">Année (facultative)</label><input class="input" id="py-{{ $it['id'] }}" name="year" value="{{ $it['year'] }}" inputmode="numeric" maxlength="4" style="max-width:140px"></div>
              <div><button class="btn btn-primary" type="submit">Enregistrer</button></div></form></details></div>
      @empty<p class="muted">Aucune réalisation pour l’instant.</p>@endforelse</div>
      @if(count($items) < $poMax)
      <form method="post" action="{{ route('freelance.portfolio.store') }}" enctype="multipart/form-data" class="po-add" data-once>@csrf
        <b>Ajouter une réalisation</b>
        <div class="field"><label for="po-img">Image</label><input class="input" id="po-img" type="file" name="image" accept="image/jpeg,image/png,image/webp" required aria-describedby="po-img-h"><p class="hint" id="po-img-h">JPG, PNG ou WebP · {{ config('freeci.catalog.image_max_mb') }} Mo maximum · au moins {{ config('freeci.catalog.image_min_width') }} px de large · réencodée, informations cachées retirées.</p></div>
        <div class="field"><label for="po-title">Titre (3 à 80 caractères)</label><input class="input" id="po-title" name="title" value="{{ old('title') }}" minlength="3" maxlength="80" required>@error('title')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
        <div class="field"><label for="po-desc">Description (jusqu’à 300 caractères)</label><textarea class="textarea" id="po-desc" name="description" rows="3" maxlength="300">{{ old('description') }}</textarea><p class="hint">Pas d’adresse e-mail, de numéro de téléphone ni de lien : ces textes sont publics.</p>@error('description')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
        <div class="field"><label for="po-year">Année (facultative)</label><input class="input" id="po-year" name="year" value="{{ old('year') }}" inputmode="numeric" maxlength="4" placeholder="{{ now()->year }}" style="max-width:140px">@error('year')<p class="field-error"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
        <div><button class="btn btn-primary" type="submit" data-once-label="Envoi…">Ajouter la réalisation</button></div></form>
      @else<p class="muted">Vous avez atteint {{ $poMax }} réalisations : supprimez-en une pour en ajouter.</p>@endif</section>
    <section class="card form-card" aria-labelledby="h-photo"><h2 class="t-h2 card-title" id="h-photo">Photo de profil</h2>
      <p class="muted">Votre photo est <strong>publique</strong> dès son enregistrement (une fois votre profil publié) : elle apparaît sur votre profil, vos services et vos messages. Elle peut être signalée et retirée par l’équipe, avec un motif.</p>
      @include('account._photo')
      <ul class="ph-tips" style="margin-top:12px"><li><x-fc.icon name="check" :size="16" />Votre visage, bien cadré, sur fond simple</li><li><x-fc.icon name="check" :size="16" />Une photo récente et nette</li><li class="no"><x-fc.icon name="close" :size="16" />Pas de coordonnées, de logo trompeur ni de photo d’une autre personne</li></ul></section>
    @endunless
    <div class="card form-card">
      <p class="muted">{{ $activation ? 'Activez l’espace freelance pour recevoir des demandes de prestation. Trois informations suffisent ; vous complèterez le reste ensuite.' : 'Les informations de cette section sont publiques une fois votre profil publié.' }}</p>
      @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>Vérifiez les champs signalés ci-dessous. Vos saisies sont conservées.</p></div>@endif
      <form method="post" action="{{ route('freelance.profile.save') }}" class="stack" style="display:grid;gap:16px;margin-top:16px" data-once>
        @csrf
        <h2 class="t-h2 card-title">Informations publiques</h2>
        <x-fc.field name="display_name" label="Nom affiché" :value="$profile['display_name'] ?? $user->name" autocomplete="name" />
        <x-fc.field name="headline" label="Votre activité" :value="$profile['headline'] ?? ''" hint="Ex. « Dessinateur DAO », « Traductrice »." />
        <x-fc.field name="city" label="Ville" :value="$profile['city'] ?? ''" :required="false" hint="Requise pour publier votre profil." />
        @unless($activation)
          <div class="field"><label for="f-bio">Présentation</label><p class="hint" id="h-bio">Votre parcours et ce que vous savez faire ({{ config('freeci.catalog.bio')[0] }} à {{ config('freeci.catalog.bio')[1] }} caractères). Pas d’adresse e-mail ni de numéro de téléphone : ce texte est public.</p>
            <textarea class="textarea" id="f-bio" name="bio" rows="6" maxlength="3000" aria-describedby="h-bio @error('bio') e-bio @enderror" @error('bio') aria-invalid="true" @enderror>{{ old('bio', $profile['bio'] ?? '') }}</textarea>
            @error('bio')<p class="field-error" id="e-bio"><x-fc.icon name="error" :size="16" />{{ $message }}</p>@enderror</div>
          <x-fc.field name="skills" label="Compétences" :value="implode(', ', $profile['skills'] ?? [])" :required="false" hint="De 1 à {{ config('freeci.catalog.skills_max') }}, séparées par des virgules (ex. AutoCAD, Mise en plan, Revit)." />
        @endunless
        <button class="btn btn-primary btn-lg" type="submit" data-once-label="Enregistrement…">{{ $activation ? 'Activer l’espace freelance' : 'Enregistrer' }}</button>
      </form>
    </div>

    @unless($activation)
    <section class="card form-card" aria-labelledby="h-priv"><h2 class="t-h2 card-title" id="h-priv">Données privées</h2>
      <dl class="defs"><div><dt>Adresse e-mail du compte</dt><dd>{{ $user->email }}<small>Jamais affichée sur votre profil ni sur vos services. Elle ne sert qu’à votre connexion et aux messages de FreeCI.</small></dd></div></dl>
      <p class="note-line"><x-fc.icon name="lock" :size="16" /><span>Aucun badge de vérification n’existe encore : ni vous ni personne ne peut s’en attribuer un depuis cette page.</span></p></section>

    <section class="card form-card" aria-labelledby="h-pub"><div class="row" style="justify-content:space-between"><h2 class="t-h2" id="h-pub">Publication du profil</h2>
        @if($profile['published'] ?? false)<span class="badge tone-success"><x-fc.icon name="check-circle" :size="16" />Profil public</span>@else<span class="badge tone-neutral"><x-fc.icon name="minus-circle" :size="16" />Non publié</span>@endif</div>
      @if($errors->has('profile'))<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>{{ $errors->first('profile') }}</p></div>@endif
      @if($profile['published'] ?? false)
        <p style="margin-top:8px">Votre profil est visible sur <a href="{{ route('freelances.show', $profile['slug']) }}">votre page publique</a>, avec vos services publiés uniquement.</p>
      @elseif(count($profile['missing'] ?? ['x']))
        <p style="margin-top:8px">Pour publier votre profil, il manque :</p><ul class="checklist" style="margin-top:8px">@foreach($profile['missing'] ?? [] as $m)<li><x-fc.icon name="minus-circle" /><span>{{ $m }}</span></li>@endforeach</ul>
      @else
        <p style="margin-top:8px">Votre profil est complet. Une fois publié, il s’affiche sur une page publique et accompagne vos services.</p>
        <form method="post" action="{{ route('freelance.profile.publish') }}" data-once style="margin-top:12px">@csrf<button class="btn btn-primary" type="submit" data-once-label="Publication…">Publier mon profil</button></form>
      @endif
    </section>
    @endunless
    </div></div>
  </div>
</x-layouts.account>

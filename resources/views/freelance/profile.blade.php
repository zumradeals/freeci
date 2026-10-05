<x-layouts.account :title="$activation ? 'Activer l’espace freelance' : 'Profil freelance'" :space="$space">
  <header class="page-head"><div class="row-top"><div><p class="eyebrow">{{ $activation ? 'Espace client' : 'Espace freelance' }}</p><h1 class="t-h1">{{ $activation ? 'Activer l’espace freelance' : 'Votre profil' }}</h1></div></div></header>
  <div class="card" style="max-width:640px">
    <p class="muted">{{ $activation ? 'Activez l’espace freelance pour recevoir des demandes de prestation. Trois informations suffisent ; vous les modifierez à tout moment.' : 'Ces informations s’affichent sur vos services publiés.' }}</p>
    @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>Vérifiez les champs signalés ci-dessous.</p></div>@endif
    <form method="post" action="{{ route('freelance.profile.save') }}" class="stack" style="display:grid;gap:16px;margin-top:16px">
      @csrf
      <x-fc.field name="display_name" label="Nom affiché" :value="$profile['display_name'] ?? $user->name" autocomplete="name" />
      <x-fc.field name="headline" label="Votre activité" :value="$profile['headline'] ?? ''" hint="Ex. « Dessinateur DAO », « Traductrice »." />
      <x-fc.field name="city" label="Ville (facultatif)" :value="$profile['city'] ?? ''" :required="false" />
      <button class="btn btn-primary btn-lg" type="submit">{{ $activation ? 'Activer l’espace freelance' : 'Enregistrer' }}</button>
    </form>
  </div>
</x-layouts.account>

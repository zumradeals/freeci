@php
  $mc = config('freeci.missions.milestones');
  $oldRows = old('milestones');
  $rows = is_array($oldRows) ? $oldRows : collect($p['milestones'] ?? [])->all();
  $use = old('use_milestones', ! empty($p['milestones'] ?? []) ? '1' : null);
  $slots = $mc['count'][1];
  $filled = max(count($rows), 3);
@endphp
<section class="ed-card jl-form" aria-labelledby="h-ms" data-ms-form>
  <div class="row" style="justify-content:space-between;gap:8px"><h2 id="h-ms" style="margin:0">Paiement par jalons <span class="muted small">(facultatif)</span></h2><span class="badge tone-info">{{ $mc['count'][0] }} à {{ $mc['count'][1] }} jalons</span></div>
  <label class="check"><input type="checkbox" name="use_milestones" value="1" data-ms-toggle @checked($use)> <span><strong>Découper cette proposition en jalons payés séparément.</strong> Le client ne paie qu’un jalon à la fois ; chaque jalon est une commande ordinaire.</span></label>
  @if($errors->has('milestones'))<p class="field-error" role="alert"><x-fc.icon name="error" :size="16" />{{ $errors->first('milestones') }}</p>@endif
  <div class="jl-rows" data-ms-rows>
    @for($i = 0; $i < $slots; $i++)
      @php($r = $rows[$i] ?? [])
      <div class="jl-row" data-ms-row @if($i >= $filled) data-ms-extra @endif>
        <div class="row" style="justify-content:space-between;gap:8px"><h3 class="t-h3" style="margin:0">Jalon {{ $i + 1 }}</h3>@if($i >= 2)<button class="btn btn-link" type="button" data-ms-remove>Retirer</button>@endif</div>
        <div class="jl-g">
          <div class="field"><label for="ms-t-{{ $i }}">Titre ({{ $mc['title'][0] }} à {{ $mc['title'][1] }} caractères)</label><input class="input" id="ms-t-{{ $i }}" name="milestones[{{ $i }}][title]" maxlength="{{ $mc['title'][1] }}" value="{{ $r['title'] ?? '' }}" @if($errors->has("milestones.$i.title")) aria-invalid="true" @endif></div>
          <div class="field"><label for="ms-p-{{ $i }}">Prix (FCFA)</label><input class="input" id="ms-p-{{ $i }}" name="milestones[{{ $i }}][price]" inputmode="numeric" value="{{ $r['price'] ?? '' }}" data-ms-price @if($errors->has("milestones.$i.price")) aria-invalid="true" @endif></div>
          <div class="field"><label for="ms-d-{{ $i }}">Délai (jours)</label><input class="input" id="ms-d-{{ $i }}" name="milestones[{{ $i }}][days]" inputmode="numeric" value="{{ $r['days'] ?? '' }}" data-ms-days @if($errors->has("milestones.$i.days")) aria-invalid="true" @endif></div>
        </div>
        <div class="field"><label for="ms-s-{{ $i }}">Ce que vous livrez à ce jalon ({{ $mc['scope'][0] }} caractères au moins)</label><textarea class="textarea" id="ms-s-{{ $i }}" name="milestones[{{ $i }}][scope]" rows="2" @if($errors->has("milestones.$i.scope")) aria-invalid="true" @endif>{{ $r['scope'] ?? '' }}</textarea></div>
        @foreach(['title', 'price', 'days', 'scope'] as $f)@if($errors->has("milestones.$i.$f"))<p class="field-error" role="alert"><x-fc.icon name="error" :size="16" />{{ $errors->first("milestones.$i.$f") }}</p>@endif @endforeach
      </div>
    @endfor
  </div>
  <div><button class="btn btn-secondary" type="button" data-ms-add>Ajouter un jalon</button></div>
  <div class="jl-sum" aria-live="polite"><div><span>Somme des jalons</span><span data-ms-sum>0 FCFA</span></div><div><span>Délai total (somme des délais)</span><span data-ms-total-days>0 jour</span></div><div class="tot"><span>Contrôle</span><span data-ms-check class="muted">—</span></div></div>
  <ul class="jl-rules"><li>Le plan est <b>figé</b> quand le client vous retient ; avant, vous pouvez réviser votre proposition.</li><li>La somme des jalons doit être égale au prix ferme ; chaque jalon est de {{ number_format($mc['price_min'], 0, ',', ' ') }} FCFA au moins. Le délai total est la somme des délais des jalons.</li><li>La commission est calculée jalon par jalon, au taux de l’accord. Les corrections incluses valent pour chaque jalon.</li></ul>
</section>

<x-layouts.admin title="Paramètres">
  <header class="page-head"><div class="row-top"><div><p class="eyebrow">Administration</p><h1 class="t-h1">Paramètres</h1><p class="lead">Les règles de la plateforme, modifiables ici sans intervention technique. Chaque changement est motivé, daté et conservé.</p></div></div></header>
  @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>{{ $errors->first() }}</p></div>@endif
  <div class="page-body">
    <div class="notice tone-info"><x-fc.icon name="info" /><p><strong>Provisoire ou approuvé ?</strong> Une valeur modifiée reste « provisoire » tant que vous ne cochez pas « Je valide ces valeurs ». Les commandes, accords et avis existants ne changent jamais : seuls les nouveaux en profitent. Les secrets (courrier, clés Genius Pay) ne se règlent pas ici. <a href="{{ route('admin.legal') }}">Textes légaux</a></p></div>
    @foreach($groups as $g)
    <section class="card panel" aria-labelledby="g-{{ $g['id'] }}">
      <div class="card-head"><h2 class="t-h2" id="g-{{ $g['id'] }}">{{ $g['title'] }}</h2>@if($g['approvable'])<span class="badge {{ $g['allApproved'] ? 'tone-success' : 'tone-warning' }}">{{ $g['allApproved'] ? 'Approuvé' : 'Provisoire' }}</span>@endif</div>
      <p class="muted">{{ $g['intro'] }}</p>
      <form method="post" action="{{ route('admin.settings.save', $g['id']) }}" class="stack" data-once>@csrf
        <div class="form-grid">
          @foreach($g['fields'] as $f)
            @php($name = 'v['.str_replace('.', '_', $f['key']).']')
            @if($f['type'] === 'bool')
              <div class="field span-all"><label class="check" for="f-{{ $loop->parent->index }}-{{ $loop->index }}"><input type="checkbox" id="f-{{ $loop->parent->index }}-{{ $loop->index }}" name="{{ $name }}" value="1" @checked($f['value'])> <span><strong>{{ $f['label'] }}</strong><br><span class="muted small">{{ $f['help'] }}</span></span></label></div>
            @else
              <div class="field {{ in_array($f['type'], ['text', 'email'], true) && ($f['length'] ?? 0) > 150 ? 'span-all' : '' }}"><label for="f-{{ $loop->parent->index }}-{{ $loop->index }}">{{ $f['label'] }}@if(isset($f['unit'])) ({{ $f['unit'] }})@endif</label>
                <input class="input" id="f-{{ $loop->parent->index }}-{{ $loop->index }}" name="{{ $name }}" value="{{ $f['input'] }}" @if($f['type'] === 'email') type="email" @elseif(in_array($f['type'], ['percent', 'int', 'xof'], true)) inputmode="decimal" @endif maxlength="{{ $f['length'] ?? 20 }}" autocomplete="off">
                <p class="muted small">{{ $f['help'] }} Valeur par défaut : {{ $f['default'] }}@if($f['status']) · <span class="badge {{ $f['status'] === 'approved' ? 'tone-success' : 'tone-warning' }}">{{ $f['status'] === 'approved' ? 'approuvé' : 'provisoire' }}</span>@if($f['approvedBy']) par {{ $f['approvedBy'] }} le {{ $f['approvedAt'] }}@endif @endif</p></div>
            @endif
          @endforeach
        </div>
        <div class="field span-all"><label for="r-{{ $g['id'] }}">Motif du changement (10 caractères minimum)</label><textarea class="input" id="r-{{ $g['id'] }}" name="reason" rows="2" minlength="10" maxlength="1000" required></textarea></div>
        @if($g['financial'] ?? false)<label class="check"><input type="checkbox" name="confirm" value="1"> <span>Je comprends que ce changement ne s’applique qu’aux <strong>nouvelles</strong> commandes et engage les montants prélevés.</span></label>@endif
        @if($g['approvable'])<label class="check"><input type="checkbox" name="approve" value="1"> <span>Je valide ces valeurs : elles passent au statut <strong>approuvé</strong>.</span></label>@endif
        <div><button class="btn btn-primary" type="submit">Enregistrer {{ mb_strtolower($g['title']) === 'site' ? 'le site' : 'les paramètres' }}</button></div>
      </form>
    </section>
    @endforeach
    <section class="card panel" aria-labelledby="g-hist"><div class="card-head"><h2 class="t-h2" id="g-hist">Derniers changements</h2><span class="meta-r">Historique conservé, jamais modifiable</span></div>
      @forelse($changes as $c)
        <article class="record"><div class="record-head"><div><strong>{{ $c['label'] }}</strong><p class="muted small">{{ $c['when'] }} · {{ $c['actor'] }}</p></div><span class="badge {{ $c['status'] === 'approved' ? 'tone-success' : 'tone-warning' }}">{{ $c['status'] === 'approved' ? 'approuvé' : 'provisoire' }}</span></div>
          <p class="small">{{ $c['old'] }} → <strong>{{ $c['new'] }}</strong> · {{ $c['reason'] }}</p></article>
      @empty<p class="muted empty-note">Aucun changement enregistré : les valeurs par défaut du système s’appliquent.</p>@endforelse
    </section>
  </div>
</x-layouts.admin>

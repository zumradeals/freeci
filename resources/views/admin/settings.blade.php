<x-layouts.admin title="Paramètres">
  <header class="page-head"><div class="row-top"><div><p class="eyebrow">Administration</p><h1 class="t-h1">Paramètres</h1><p class="lead">Toutes les règles et réglages de la plateforme, modifiables ici sans intervention technique. Chaque changement est motivé, daté et conservé.</p></div></div></header>
  @if($errors->any())<div class="notice tone-error" role="alert"><x-fc.icon name="error" /><p>{{ $errors->first() }}</p></div>@endif
  <div class="page-body">
    <div class="notice tone-info"><x-fc.icon name="info" /><p><strong>Comment ça marche.</strong> Une valeur modifiée reste « provisoire » tant que vous ne cochez pas « Je valide ces valeurs ». Les commandes, accords et avis existants ne changent jamais : seuls les nouveaux en profitent. Les clés et mots de passe sont <strong>chiffrés et jamais réaffichés</strong> : saisissez-en un nouveau pour remplacer l’ancien. Ne se règlent pas ici (nécessaires avant l’accès à la base) : clé de chiffrement, identifiants de la base, adresse du site. <a href="{{ route('admin.legal') }}">Textes légaux</a></p></div>
    <nav class="chips" aria-label="Rubriques">@foreach($groups as $g)<a class="chip" href="#g-{{ $g['id'] }}">{{ $g['title'] }}</a>@endforeach</nav>
    @foreach($groups as $g)
    <section class="card panel" aria-labelledby="g-{{ $g['id'] }}" id="g-{{ $g['id'] }}">
      <div class="card-head"><h2 class="t-h2" id="g-{{ $g['id'] }}-t">{{ $g['title'] }}</h2>@if($g['approvable'])<span class="badge {{ $g['allApproved'] ? 'tone-success' : 'tone-warning' }}">{{ $g['allApproved'] ? 'Approuvé' : 'Provisoire' }}</span>@endif</div>
      <p class="muted">{{ $g['intro'] }}</p>
      @if($g['id'] === 'courrier')
        <div class="row" style="gap:12px;align-items:center"><form method="post" action="{{ route('admin.settings.test-mail') }}" data-once>@csrf<button class="btn btn-secondary" type="submit">Envoyer un courriel de test à mon adresse</button></form><span class="muted small">Enregistrez d’abord vos changements, puis testez.</span></div>
      @elseif($g['id'] === 'paiement')
        <div class="row" style="gap:12px;align-items:center">@foreach(['sandbox' => 'bac à sable', 'live' => 'réel'] as $e => $name)<form method="post" action="{{ route('admin.settings.test-genius', $e) }}" data-once>@csrf<button class="btn btn-secondary" type="submit">Tester la connexion ({{ $name }})</button></form>@endforeach<span class="muted small">Appel réel de lecture : aucun paiement n’est créé.</span></div>
      @endif
      <form method="post" action="{{ route('admin.settings.save', $g['id']) }}" class="stack" data-once autocomplete="off">@csrf
        <div class="form-grid">
          @foreach($g['fields'] as $f)
            @php($name = str_replace('.', '_', $f['key']))
            @php($fid = 'f-'.$g['id'].'-'.$loop->index)
            @if($f['type'] === 'bool')
              <div class="field span-all"><label class="check" for="{{ $fid }}"><input type="checkbox" id="{{ $fid }}" name="v[{{ $name }}]" value="1" @checked($f['value'])> <span><strong>{{ $f['label'] }}</strong><br><span class="muted small">{{ $f['help'] }}</span></span></label></div>
            @elseif($f['type'] === 'select')
              <div class="field"><label for="{{ $fid }}">{{ $f['label'] }}</label><select class="select" id="{{ $fid }}" name="v[{{ $name }}]">@foreach($f['options'] as $ov => $ol)<option value="{{ $ov }}" @selected((string) $f['value'] === (string) $ov)>{{ $ol }}</option>@endforeach</select>
                @if($f['help'])<p class="muted small">{{ $f['help'] }}</p>@endif</div>
            @elseif($f['type'] === 'secret')
              <div class="field"><label for="{{ $fid }}">{{ $f['label'] }}</label>
                <input class="input" type="password" id="{{ $fid }}" name="v[{{ $name }}]" value="" maxlength="{{ $f['length'] }}" autocomplete="new-password" placeholder="{{ $f['value'] ? '•••••• défini — laisser vide pour conserver' : 'Non défini' }}">
                <p class="muted small">{{ $f['help'] }} <strong>{{ $f['shown'] }}</strong>@if($f['custom']) · <label class="check" style="display:inline-flex"><input type="checkbox" name="clear[{{ $name }}]" value="1"> <span>retirer (revenir à la valeur du serveur)</span></label>@endif</p></div>
            @else
              <div class="field {{ in_array($f['type'], ['text', 'email'], true) && ($f['length'] ?? 0) > 150 ? 'span-all' : '' }}"><label for="{{ $fid }}">{{ $f['label'] }}@if(! empty($f['unit'])) ({{ $f['unit'] }})@endif</label>
                <input class="input" id="{{ $fid }}" name="v[{{ $name }}]" value="{{ $f['input'] }}" @if($f['type'] === 'email') type="email" @elseif(in_array($f['type'], ['percent', 'int', 'xof'], true)) inputmode="decimal" @endif maxlength="{{ $f['length'] ?? 20 }}" autocomplete="off">
                <p class="muted small">{{ $f['help'] }}@if(isset($f['min'])) De {{ $f['type'] === 'percent' ? $f['min'] / 100 : $f['min'] }} à {{ $f['type'] === 'percent' ? $f['max'] / 100 : $f['max'] }}.@endif Défaut : {{ $f['default'] }}@if($f['status']) · <span class="badge {{ $f['status'] === 'approved' ? 'tone-success' : 'tone-warning' }}">{{ $f['status'] === 'approved' ? 'approuvé' : 'provisoire' }}</span>@if($f['approvedBy']) par {{ $f['approvedBy'] }} le {{ $f['approvedAt'] }}@endif @endif</p></div>
            @endif
          @endforeach
        </div>
        <div class="field span-all"><label for="r-{{ $g['id'] }}">Motif du changement (10 caractères minimum)</label><textarea class="input" id="r-{{ $g['id'] }}" name="reason" rows="2" minlength="10" maxlength="1000" required></textarea></div>
        @if($g['danger'] ?? false)<div class="field"><label for="lp-{{ $g['id'] }}">Pour passer en paiement réel : saisissez PAIEMENT REEL</label><input class="input" id="lp-{{ $g['id'] }}" name="live_phrase" autocomplete="off" maxlength="40" placeholder="Laisser vide pour tout autre changement"></div>@endif
        @if($g['financial'] ?? false)<label class="check"><input type="checkbox" name="confirm" value="1"> <span>Je comprends que ce changement engage des montants ou des paiements, et ne s’applique qu’aux <strong>nouvelles</strong> commandes.</span></label>@endif
        @if($g['approvable'])<label class="check"><input type="checkbox" name="approve" value="1"> <span>Je valide ces valeurs : elles passent au statut <strong>approuvé</strong>.</span></label>@endif
        <div><button class="btn btn-primary" type="submit">Enregistrer « {{ $g['title'] }} »</button></div>
      </form>
    </section>
    @endforeach
    <section class="card panel" aria-labelledby="g-hist"><div class="card-head"><h2 class="t-h2" id="g-hist">Derniers changements</h2><span class="meta-r">Historique conservé, jamais modifiable ; les secrets n’y figurent pas</span></div>
      @forelse($changes as $c)
        <article class="record"><div class="record-head"><div><strong>{{ $c['label'] }}</strong><p class="muted small">{{ $c['when'] }} · {{ $c['actor'] }}</p></div><span class="badge {{ $c['status'] === 'approved' ? 'tone-success' : 'tone-warning' }}">{{ $c['status'] === 'approved' ? 'approuvé' : 'provisoire' }}</span></div>
          <p class="small">{{ $c['old'] }} → <strong>{{ $c['new'] }}</strong> · {{ $c['reason'] }}</p></article>
      @empty<p class="muted empty-note">Aucun changement enregistré : les valeurs par défaut du système s’appliquent.</p>@endforelse
    </section>
  </div>
</x-layouts.admin>

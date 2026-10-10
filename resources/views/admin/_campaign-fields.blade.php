<div class="rf-g3">
  <div class="field"><label for="{{ $p }}code">Code (4 à 20 caractères)</label><input class="input" id="{{ $p }}code" name="code" value="{{ $v['code'] }}" maxlength="20" required autocapitalize="characters"></div>
  <div class="field"><label for="{{ $p }}rate">Commission appliquée (%)</label><input class="input" id="{{ $p }}rate" name="rate" value="{{ $v['rate'] }}" inputmode="decimal" required></div>
  <div class="field"><label for="{{ $p }}free">Nombre de commandes</label><input class="input" id="{{ $p }}free" name="free_orders" value="{{ $v['free_orders'] }}" inputmode="numeric" required></div>
  <div class="field"><label for="{{ $p }}from">Début</label><input class="input" id="{{ $p }}from" type="date" name="starts_on" value="{{ $v['starts_on'] }}" required></div>
  <div class="field"><label for="{{ $p }}to">Fin</label><input class="input" id="{{ $p }}to" type="date" name="ends_on" value="{{ $v['ends_on'] }}" required></div>
  <div class="field"><label for="{{ $p }}max">Utilisations maximum</label><input class="input" id="{{ $p }}max" name="max_uses" value="{{ $v['max_uses'] }}" inputmode="numeric" required></div>
</div>
<div class="field"><label for="{{ $p }}note">Note interne (facultatif)</label><input class="input" id="{{ $p }}note" name="note" value="{{ $v['note'] }}" maxlength="200"></div>

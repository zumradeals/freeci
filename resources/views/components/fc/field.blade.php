@props(['name', 'label', 'type' => 'text', 'value' => null, 'hint' => null, 'autocomplete' => null, 'required' => true, 'reveal' => false, 'strength' => false])
@php($err = $errors->first($name))
<div class="field">
  <label for="f-{{ $name }}">{{ $label }}</label>
  @if($hint)<p class="hint" id="h-{{ $name }}">{{ $hint }}</p>@endif
  @if($reveal)<div class="au-pw">@endif
  <input class="input" id="f-{{ $name }}" name="{{ $name }}" type="{{ $type }}" @if($type !== 'password') value="{{ old($name, $value) }}" @endif
    @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif @if($required) required @endif
    @if($err || $hint) aria-describedby="{{ $err ? 'e-'.$name : '' }} {{ $hint ? 'h-'.$name : '' }}" @endif
    @if($err) aria-invalid="true" @endif>
  @if($reveal)<button type="button" class="au-reveal" data-reveal="f-{{ $name }}" aria-pressed="false" hidden>Afficher<span class="sr-only"> le mot de passe</span></button></div>@endif
  @if($strength)<div class="au-meter" data-strength-for="f-{{ $name }}" aria-hidden="true" hidden><i></i><i></i><i></i><i></i></div>@endif
  @if($err)<p class="field-error" id="e-{{ $name }}"><x-fc.icon name="error" :size="16" />{{ $err }}</p>@endif
</div>
